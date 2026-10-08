/* Talkwyn Pro widget extensions: streaming, product cards, proactive messages, away status. */
( function () {
	'use strict';
	var api = window.Talkwyn;
	var cfg = window.TalkwynConfig || {};
	var pro = cfg.pro || {};
	if ( ! api ) {
		return;
	}

	/* ---------- Streaming transport ---------- */
	function streamOnce( payload, hooks, retried ) {
		return api.auth( retried ).then( function ( a ) {
			return fetch( cfg.rest + 'stream', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
				body: JSON.stringify( Object.assign( {}, payload, { token: a.token, session: a.session } ) )
			} );
		} ).then( function ( r ) {
			var type = r.headers.get( 'Content-Type' ) || '';
			if ( ! r.ok || type.indexOf( 'text/event-stream' ) === -1 ) {
				return r.json().then( function ( j ) {
					if ( j && j.code === 'talkwyn_token' && ! retried ) {
						return streamOnce( payload, hooks, true );
					}
					var err = new Error( ( j && j.message ) || 'error' );
					err.data = j;
					err.status = r.status;
					throw err;
				} );
			}
			if ( ! r.body || ! r.body.getReader ) {
				throw new Error( 'no-stream' );
			}
			var reader = r.body.getReader();
			var decoder = new TextDecoder();
			var buffer = '';
			var done = null;
			function handle( block ) {
				var event = 'message';
				var data = '';
				block.split( '\n' ).forEach( function ( line ) {
					if ( line.indexOf( 'event:' ) === 0 ) {
						event = line.slice( 6 ).trim();
					} else if ( line.indexOf( 'data:' ) === 0 ) {
						data += line.slice( 5 ).trim();
					}
				} );
				if ( ! data ) {
					return;
				}
				var j;
				try {
					j = JSON.parse( data );
				} catch ( e ) {
					return;
				}
				if ( event === 'delta' && hooks && hooks.onDelta ) {
					hooks.onDelta( j.html );
				} else if ( event === 'done' ) {
					done = j;
				}
			}
			function pump() {
				return reader.read().then( function ( res ) {
					if ( res.value ) {
						buffer += decoder.decode( res.value, { stream: true } );
						var parts = buffer.split( '\n\n' );
						buffer = parts.pop();
						parts.forEach( handle );
					}
					if ( res.done ) {
						if ( buffer.trim() ) {
							handle( buffer );
						}
						if ( ! done ) {
							throw new Error( 'stream-ended' );
						}
						return done;
					}
					return pump();
				} );
			}
			return pump();
		} );
	}

	if ( pro.stream && window.fetch && window.TextDecoder ) {
		api.setTransport( function ( payload, hooks ) {
			var started = false;
			var wrapped = {
				onDelta: function ( html ) {
					started = true;
					hooks.onDelta( html );
				}
			};
			return streamOnce( payload, wrapped, false ).catch( function ( err ) {
				// Server errors are final; a broken stream before any text falls back to the normal endpoint.
				if ( err && err.status ) {
					throw err;
				}
				if ( started ) {
					throw err;
				}
				return api.request( 'chat', payload );
			} );
		} );
	}

	/* ---------- Product cards ---------- */
	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text ) {
			n.textContent = text;
		}
		return n;
	}
	api.on( 'message', function ( d ) {
		var cards = d.data && d.data.extra && d.data.extra.cards;
		if ( ! cards || ! cards.length || ! d.el ) {
			return;
		}
		var row = el( 'div', 'twc-cards' );
		cards.forEach( function ( c ) {
			var card = el( 'div', 'twc-card' );
			if ( c.image ) {
				var img = el( 'img' );
				img.src = c.image;
				img.alt = '';
				img.loading = 'lazy';
				card.appendChild( img );
			}
			var body = el( 'div', 'twc-card__body' );
			var title = el( 'a', 'twc-card__title', c.title );
			title.href = c.url;
			title.dir = 'auto';
			body.appendChild( title );
			if ( c.price ) {
				body.appendChild( el( 'span', 'twc-card__price', c.price ) );
			}
			var actions = el( 'div', 'twc-card__actions' );
			var view = el( 'a', 'twc-btn twc-btn--ghost', c.labels.view );
			view.href = c.url;
			actions.appendChild( view );
			if ( c.cart_url ) {
				var add = el( 'a', 'twc-btn twc-btn--brand', c.labels.cart );
				add.href = c.cart_url;
				add.rel = 'nofollow';
				actions.appendChild( add );
			} else if ( ! c.stock ) {
				actions.appendChild( el( 'span', 'twc-card__out', c.labels.out ) );
			}
			body.appendChild( actions );
			card.appendChild( body );
			row.appendChild( card );
		} );
		var meta = d.el.querySelector( '.twc-meta' );
		d.el.insertBefore( row, meta || null );
	} );

	/* ---------- Away status ---------- */
	function applyAway() {
		if ( ! pro.away ) {
			return;
		}
		document.querySelectorAll( '[data-talkwyn-chat]' ).forEach( function ( root ) {
			root.classList.add( 'is-away' );
			var status = root.querySelector( '.twc-status' );
			if ( status && pro.awayLabel ) {
				status.textContent = pro.awayLabel;
			}
		} );
	}

	/* ---------- Proactive messages ---------- */
	var seenKey = 'talkwyn_pro_seen';
	function seen() {
		try {
			return JSON.parse( window.sessionStorage.getItem( seenKey ) || '[]' );
		} catch ( e ) {
			return [];
		}
	}
	function markSeen( id ) {
		var list = seen();
		list.push( id );
		try {
			window.sessionStorage.setItem( seenKey, JSON.stringify( list ) );
		} catch ( e ) {}
	}
	function ruleId( r, i ) {
		return i + ':' + r.trigger + ':' + r.message.length;
	}
	function showTeaser( rule ) {
		var root = document.querySelector( '[data-talkwyn-chat="floating"]' );
		if ( ! root || api.isOpen() ) {
			return;
		}
		if ( rule.open ) {
			api.open();
			api.addMessage( 'assistant', null, { text: rule.message, tools: false } );
			return;
		}
		var old = root.querySelector( '.twc-teaser' );
		if ( old ) {
			old.remove();
		}
		var teaser = el( 'div', 'twc-teaser' );
		teaser.setAttribute( 'role', 'status' );
		var text = el( 'button', 'twc-teaser__text', rule.message );
		text.type = 'button';
		text.dir = 'auto';
		var close = el( 'button', 'twc-teaser__close' );
		close.type = 'button';
		close.setAttribute( 'aria-label', ( cfg.text && cfg.text.close ) || 'Close' );
		close.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12"/><path d="M18 6 6 18"/></svg>';
		text.addEventListener( 'click', function () {
			teaser.remove();
			api.open();
			api.addMessage( 'assistant', null, { text: rule.message, tools: false } );
		} );
		close.addEventListener( 'click', function () {
			teaser.remove();
		} );
		teaser.appendChild( text );
		teaser.appendChild( close );
		root.appendChild( teaser );
	}
	function setupRules() {
		var rules = pro.rules || [];
		var path = window.location.pathname + window.location.search;
		var done = seen();
		rules.forEach( function ( rule, i ) {
			var id = ruleId( rule, i );
			if ( done.indexOf( id ) !== -1 ) {
				return;
			}
			if ( rule.url && path.indexOf( rule.url ) === -1 ) {
				return;
			}
			var fired = false;
			function fire() {
				if ( fired || api.isOpen() ) {
					return;
				}
				fired = true;
				markSeen( id );
				showTeaser( rule );
			}
			if ( rule.trigger === 'scroll' ) {
				var onScroll = function () {
					var h = document.documentElement.scrollHeight - window.innerHeight;
					var pct = h > 0 ? ( window.scrollY / h ) * 100 : 100;
					if ( pct >= ( rule.value || 50 ) ) {
						window.removeEventListener( 'scroll', onScroll );
						fire();
					}
				};
				window.addEventListener( 'scroll', onScroll, { passive: true } );
			} else if ( rule.trigger === 'exit' ) {
				if ( window.matchMedia( '(pointer: fine)' ).matches ) {
					document.addEventListener( 'mouseout', function onOut( e ) {
						if ( ! e.relatedTarget && e.clientY <= 0 ) {
							document.removeEventListener( 'mouseout', onOut );
							fire();
						}
					} );
				}
			} else {
				setTimeout( fire, Math.max( 0, rule.value || 0 ) * 1000 );
			}
		} );
	}

	function start() {
		applyAway();
		setupRules();
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
