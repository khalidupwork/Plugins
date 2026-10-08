/**
 * Talkwyn chat widget.
 *
 * Public API (for add-ons): window.Talkwyn
 *   on(event, fn)       events: ready, open, close, message, reply, lead, reset
 *   open(), close(), send(text)
 *   addMessage(role, html, options)
 *   setTransport(fn)    fn(payload, {onDelta}) => Promise<replyData>
 *   request(path, body) call a Talkwyn REST route with the session token
 *   auth(force)         Promise<{token, session}> for custom routes
 *   config, session()
 */
( function () {
	'use strict';

	var cfg = window.TalkwynConfig;
	if ( ! cfg || window.Talkwyn ) {
		return;
	}

	var T = cfg.text || {};
	var F = cfg.flags || {};
	var listeners = {};
	var instances = [];
	var store = safeStorage();
	var state = {
		session: store.get( 'talkwyn_session' ) || '',
		token: '',
		tokenAt: 0,
		loaded: false,
		loading: null,
		history: [],
		flags: {}
	};
	var transport = defaultTransport;

	window.Talkwyn = {
		config: cfg,
		on: function ( event, fn ) {
			( listeners[ event ] = listeners[ event ] || [] ).push( fn );
		},
		open: function () {
			if ( instances[ 0 ] ) {
				instances[ 0 ].open();
			}
		},
		close: function () {
			instances.forEach( function ( i ) {
				i.close();
			} );
		},
		send: function ( text ) {
			if ( instances[ 0 ] ) {
				instances[ 0 ].open();
				instances[ 0 ].send( text );
			}
		},
		addMessage: function ( role, html, options ) {
			return instances[ 0 ] ? instances[ 0 ].addMessage( role, html, options || {} ) : null;
		},
		setTransport: function ( fn ) {
			if ( typeof fn === 'function' ) {
				transport = fn;
			}
		},
		request: request,
		auth: function ( force ) {
			return loadSession( force ).then( function () {
				return { token: state.token, session: state.session };
			} );
		},
		session: function () {
			return state.session;
		},
		isOpen: function () {
			return instances.some( function ( i ) {
				return i.isOpen();
			} );
		}
	};

	function emit( event, detail ) {
		( listeners[ event ] || [] ).forEach( function ( fn ) {
			try {
				fn( detail || {} );
			} catch ( e ) {
				if ( window.console ) {
					window.console.error( e );
				}
			}
		} );
		document.dispatchEvent( new CustomEvent( 'talkwyn:' + event, { detail: detail || {} } ) );
	}

	function safeStorage() {
		var ls = null;
		try {
			ls = window.localStorage;
			ls.setItem( 'talkwyn_t', '1' );
			ls.removeItem( 'talkwyn_t' );
		} catch ( e ) {
			ls = null;
		}
		var mem = {};
		return {
			get: function ( k ) {
				return ls ? ls.getItem( k ) : ( k in mem ? mem[ k ] : null );
			},
			set: function ( k, v ) {
				if ( ls ) {
					try {
						ls.setItem( k, v );
					} catch ( e ) {}
				} else {
					mem[ k ] = v;
				}
			},
			remove: function ( k ) {
				if ( ls ) {
					ls.removeItem( k );
				} else {
					delete mem[ k ];
				}
			}
		};
	}

	/* ---------- Server ---------- */

	function loadSession( force ) {
		var fresh = state.token && Date.now() - state.tokenAt < 6 * 3600 * 1000;
		if ( fresh && ! force ) {
			return Promise.resolve( state );
		}
		if ( state.loading && ! force ) {
			return state.loading;
		}
		var url = cfg.rest + 'session' + ( state.session ? '?session=' + encodeURIComponent( state.session ) : '' );
		state.loading = fetch( url, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( j ) {
				state.token = j.token || '';
				state.tokenAt = Date.now();
				if ( j.session && j.session !== state.session ) {
					state.session = j.session;
					store.set( 'talkwyn_session', state.session );
				}
				state.history = j.history || [];
				state.flags = j.flags || {};
				state.loaded = true;
				state.loading = null;
				return state;
			} )
			.catch( function ( e ) {
				state.loading = null;
				throw e;
			} );
		return state.loading;
	}

	function request( path, body, retried ) {
		return loadSession().then( function () {
			var payload = Object.assign( {}, body || {}, { token: state.token, session: state.session } );
			return fetch( cfg.rest + path, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( payload )
			} ).then( function ( r ) {
				return r.json().then( function ( j ) {
					if ( ! r.ok && j && j.code === 'talkwyn_token' && ! retried ) {
						return loadSession( true ).then( function () {
							return request( path, body, true );
						} );
					}
					if ( ! r.ok ) {
						var err = new Error( ( j && j.message ) || T.error );
						err.data = j;
						err.status = r.status;
						throw err;
					}
					return j;
				} );
			} );
		} );
	}

	function defaultTransport( payload ) {
		return request( 'chat', payload );
	}

	/* ---------- Widget ---------- */

	function init() {
		var roots = document.querySelectorAll( '[data-talkwyn-chat]' );
		Array.prototype.forEach.call( roots, function ( root ) {
			if ( ! root.dataset.ready ) {
				root.dataset.ready = '1';
				instances.push( createChat( root ) );
			}
		} );
		if ( ! instances.length ) {
			return;
		}
		emit( 'ready', { api: window.Talkwyn } );
		// Continue an active conversation on this page.
		if ( state.session && store.get( 'talkwyn_active' ) === '1' ) {
			loadSession().then( function () {
				instances.forEach( function ( i ) {
					i.restore();
				} );
				if ( store.get( 'talkwyn_open' ) === '1' && instances[ 0 ] && instances[ 0 ].mode === 'floating' ) {
					instances[ 0 ].open( true );
				}
			} ).catch( function () {} );
		}
	}

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text !== undefined && text !== null ) {
			n.textContent = text;
		}
		return n;
	}

	function createChat( root ) {
		var mode = root.getAttribute( 'data-talkwyn-chat' );
		var panel = root.querySelector( '.twc-panel' );
		var launcher = root.querySelector( '.twc-launcher' );
		var messages = root.querySelector( '.twc-messages' );
		var suggestions = root.querySelector( '.twc-suggestions' );
		var form = root.querySelector( '.twc-compose' );
		var input = form.querySelector( 'textarea' );
		var foot = root.querySelector( '.twc-foot' );
		var btn = function ( c ) {
			return root.querySelector( '.' + c );
		};
		var expandBtn = btn( 'twc-expand' );
		var soundBtn = btn( 'twc-sound' );
		var busy = false;
		var opened = mode === 'inline';
		var full = false;
		var rendered = false;
		var soundOn = store.get( 'talkwyn_sound' ) === null ? !! F.soundDefault : store.get( 'talkwyn_sound' ) === '1';
		var audio = null;

		root.style.setProperty( '--twc-brand', cfg.brand );
		root.style.setProperty( '--twc-on-brand', cfg.onBrand );
		root.style.setProperty( '--twc-width', cfg.width + 'px' );
		root.style.setProperty( '--twc-height', cfg.height + 'px' );
		root.classList.toggle( 'twc--left', cfg.position === 'left' );
		if ( /^(ar|ur|fa|he|ps|sd|ug|yi|dv)\b/i.test( pageLang() ) ) {
			root.setAttribute( 'dir', 'rtl' );
		}

		renderFoot();
		renderSound();

		if ( launcher ) {
			launcher.addEventListener( 'click', function () {
				if ( opened ) {
					close();
				} else {
					open();
				}
			} );
		}
		[ 'twc-close', 'twc-minimize' ].forEach( function ( c ) {
			var b = btn( c );
			if ( b ) {
				b.addEventListener( 'click', close );
			}
		} );
		if ( expandBtn ) {
			expandBtn.addEventListener( 'click', function () {
				setFull( ! full );
			} );
		}
		if ( soundBtn ) {
			soundBtn.addEventListener( 'click', function () {
				soundOn = ! soundOn;
				store.set( 'talkwyn_sound', soundOn ? '1' : '0' );
				renderSound();
				if ( soundOn ) {
					beep();
				}
			} );
		}
		var resetBtn = btn( 'twc-reset' );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', reset );
		}
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			send( input.value );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && ! e.shiftKey && ! e.isComposing ) {
				e.preventDefault();
				send( input.value );
			}
		} );
		input.addEventListener( 'input', grow );
		root.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && mode === 'floating' && opened ) {
				close();
				if ( launcher ) {
					launcher.focus();
				}
			}
		} );

		if ( mode === 'inline' ) {
			firstRender();
		}

		function pageLang() {
			return ( document.documentElement.getAttribute( 'lang' ) || cfg.pageLanguage || navigator.language || '' ).trim();
		}

		function grow() {
			input.style.height = 'auto';
			input.style.height = Math.min( input.scrollHeight, 140 ) + 'px';
		}

		function firstRender() {
			if ( rendered ) {
				return;
			}
			rendered = true;
			if ( state.loaded && state.history.length ) {
				restore();
			} else {
				welcome();
			}
		}

		function welcome() {
			messages.innerHTML = '';
			addMessage( 'assistant', null, { text: T.welcome, tools: false } );
			renderSuggestions();
		}

		function restore() {
			if ( ! state.history.length ) {
				return;
			}
			rendered = true;
			messages.innerHTML = '';
			addMessage( 'assistant', null, { text: T.welcome, tools: false, time: false } );
			var last = state.history.length - 1;
			state.history.forEach( function ( m, i ) {
				if ( m.kind === 'lead_offer' ) {
					if ( i === last && ! state.flags.lead_declined && ! state.flags.lead_submitted && ! state.flags.lead_form ) {
						renderLeadOffer();
					} else {
						addMessage( 'assistant', null, { text: m.text, tools: false, time: m.time } );
					}
					return;
				}
				if ( m.kind === 'lead_form' ) {
					if ( m.text ) {
						addMessage( 'assistant', null, { text: m.text, tools: false, time: m.time } );
					}
					if ( ! state.flags.lead_submitted ) {
						renderLeadForm();
					}
					return;
				}
				if ( m.kind === 'lead_saved' ) {
					renderLeadSaved( m.extra && m.extra.lead_id );
					return;
				}
				if ( m.role === 'user' ) {
					addMessage( 'user', null, { text: m.text, time: m.time } );
				} else {
					addMessage( 'assistant', m.html, {
						text: m.text,
						sources: m.sources,
						id: m.id,
						extra: m.extra,
						time: m.time,
						tools: m.kind === 'message'
					} );
				}
			} );
			suggestions.innerHTML = '';
		}

		function open( silent ) {
			if ( opened && mode === 'floating' && ! panel.hidden ) {
				return;
			}
			opened = true;
			panel.hidden = false;
			root.classList.add( 'is-open' );
			if ( launcher ) {
				launcher.setAttribute( 'aria-expanded', 'true' );
			}
			store.set( 'talkwyn_open', '1' );
			firstRender();
			if ( F.mobileFull && window.matchMedia( '(max-width: 640px)' ).matches ) {
				setFull( true );
			}
			// Fetch a fresh token as soon as the visitor opens the chat.
			loadSession().then( function () {
				if ( state.history.length && messages.querySelectorAll( '.twc-msg' ).length <= 1 ) {
					restore();
				}
			} ).catch( function () {} );
			if ( ! silent ) {
				setTimeout( function () {
					input.focus();
				}, 60 );
			}
			scroll();
			emit( 'open', { mode: mode } );
		}

		function close() {
			if ( mode !== 'floating' ) {
				return;
			}
			opened = false;
			panel.hidden = true;
			root.classList.remove( 'is-open' );
			if ( launcher ) {
				launcher.setAttribute( 'aria-expanded', 'false' );
			}
			store.set( 'talkwyn_open', '0' );
			setFull( false );
			emit( 'close', {} );
		}

		function setFull( on ) {
			var mobile = F.mobileFull && window.matchMedia( '(max-width: 640px)' ).matches;
			if ( on && ! F.fullScreen && ! mobile ) {
				return;
			}
			full = !! on;
			root.classList.toggle( 'is-full', full );
			document.documentElement.classList.toggle( 'twc-lock', full && opened );
			if ( expandBtn ) {
				expandBtn.setAttribute( 'aria-pressed', full ? 'true' : 'false' );
			}
			scroll();
		}

		function renderSound() {
			if ( soundBtn ) {
				soundBtn.classList.toggle( 'is-muted', ! soundOn );
				soundBtn.setAttribute( 'aria-pressed', soundOn ? 'true' : 'false' );
			}
		}

		function beep() {
			if ( ! soundOn ) {
				return;
			}
			try {
				var AC = window.AudioContext || window.webkitAudioContext;
				if ( ! AC ) {
					return;
				}
				audio = audio || new AC();
				var o = audio.createOscillator();
				var g = audio.createGain();
				o.type = 'sine';
				o.frequency.value = 720;
				g.gain.setValueAtTime( 0.0001, audio.currentTime );
				g.gain.exponentialRampToValueAtTime( 0.045, audio.currentTime + 0.01 );
				g.gain.exponentialRampToValueAtTime( 0.0001, audio.currentTime + 0.13 );
				o.connect( g );
				g.connect( audio.destination );
				o.start();
				o.stop( audio.currentTime + 0.14 );
			} catch ( e ) {}
		}

		function renderFoot() {
			foot.innerHTML = '';
			if ( F.handoff && cfg.handoffUrl ) {
				var a = el( 'a', 'twc-handoff', T.handoff );
				a.href = cfg.handoffUrl;
				a.target = '_blank';
				a.rel = 'noopener';
				foot.appendChild( a );
			}
			if ( T.privacy ) {
				foot.appendChild( el( 'p', 'twc-privacy', T.privacy ) );
			}
			if ( cfg.poweredBy ) {
				var p = el( 'a', 'twc-powered', T.poweredBy );
				p.href = cfg.poweredUrl;
				p.target = '_blank';
				p.rel = 'noopener';
				foot.appendChild( p );
			}
		}

		function renderSuggestions() {
			suggestions.innerHTML = '';
			( cfg.suggestions || [] ).forEach( function ( q ) {
				var b = el( 'button', 'twc-chip', q );
				b.type = 'button';
				b.dir = 'auto';
				b.addEventListener( 'click', function () {
					send( q );
				} );
				suggestions.appendChild( b );
			} );
		}

		function scroll() {
			window.requestAnimationFrame( function () {
				messages.scrollTop = messages.scrollHeight;
			} );
		}

		function stamp( wrap, time ) {
			if ( ! F.timestamps || time === false ) {
				return;
			}
			var d = time ? new Date( time * 1000 ) : new Date();
			var t = el( 'time', 'twc-time' );
			t.dateTime = d.toISOString();
			try {
				t.textContent = new Intl.DateTimeFormat( undefined, { hour: 'numeric', minute: '2-digit' } ).format( d );
			} catch ( e ) {
				t.textContent = d.toLocaleTimeString();
			}
			wrap.appendChild( t );
		}

		function addMessage( role, html, o ) {
			o = o || {};
			var wrap = el( 'div', 'twc-msg twc-msg--' + ( role === 'user' ? 'user' : 'bot' ) );
			var bubble = el( 'div', 'twc-bubble' );
			bubble.dir = 'auto';
			if ( html ) {
				bubble.innerHTML = html;
			} else {
				bubble.textContent = o.text || '';
			}
			wrap.appendChild( bubble );
			if ( o.sources && o.sources.length ) {
				var src = el( 'div', 'twc-sources' );
				src.appendChild( el( 'span', 'twc-sources__label', T.sources ) );
				o.sources.forEach( function ( s ) {
					var a = el( 'a', '', s.title );
					a.href = s.url;
					a.dir = 'auto';
					src.appendChild( a );
				} );
				wrap.appendChild( src );
			}
			var meta = el( 'div', 'twc-meta' );
			stamp( meta, o.time );
			if ( role !== 'user' && o.tools !== false && ( F.copy || F.feedback ) ) {
				meta.appendChild( tools( o.text || bubble.textContent, o.id ) );
			}
			if ( meta.childNodes.length ) {
				wrap.appendChild( meta );
			}
			messages.appendChild( wrap );
			scroll();
			emit( 'message', { role: role, el: wrap, bubble: bubble, data: o } );
			return wrap;
		}

		function tools( text, id ) {
			var box = el( 'div', 'twc-msg-tools' );
			if ( F.copy ) {
				var copy = el( 'button', 'twc-tool' );
				copy.type = 'button';
				copy.title = T.copy;
				copy.setAttribute( 'aria-label', T.copy );
				copy.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>';
				copy.addEventListener( 'click', function () {
					copyText( text ).then( function ( ok ) {
						if ( ok ) {
							copy.classList.add( 'is-on' );
							copy.title = T.copied;
							copy.setAttribute( 'aria-label', T.copied );
							setTimeout( function () {
								copy.classList.remove( 'is-on' );
								copy.title = T.copy;
								copy.setAttribute( 'aria-label', T.copy );
							}, 1400 );
						}
					} );
				} );
				box.appendChild( copy );
			}
			if ( F.feedback && id ) {
				[ [ 'helpful', T.helpful, 'M7 10v10H4V10h3ZM7 19h9.5a2 2 0 0 0 1.9-1.4l1.4-5A2 2 0 0 0 17.9 10H14l.6-3.2A2.3 2.3 0 0 0 12.4 4L7 10' ], [ 'not_helpful', T.notHelpful, 'M7 14V4H4v10h3ZM7 5h9.5a2 2 0 0 1 1.9 1.4l1.4 5A2 2 0 0 1 17.9 14H14l.6 3.2a2.3 2.3 0 0 1-2.2 2.8L7 14' ] ].forEach( function ( f ) {
					var b = el( 'button', 'twc-tool twc-fb' );
					b.type = 'button';
					b.title = f[ 1 ];
					b.setAttribute( 'aria-label', f[ 1 ] );
					b.setAttribute( 'aria-pressed', 'false' );
					b.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + f[ 2 ] + '"/></svg>';
					b.addEventListener( 'click', function () {
						box.querySelectorAll( '.twc-fb' ).forEach( function ( x ) {
							x.classList.remove( 'is-on' );
							x.setAttribute( 'aria-pressed', 'false' );
						} );
						b.classList.add( 'is-on' );
						b.setAttribute( 'aria-pressed', 'true' );
						request( 'feedback', { message_id: id, feedback: f[ 0 ] } ).catch( function () {} );
					} );
					box.appendChild( b );
				} );
			}
			return box;
		}

		function typing() {
			var wrap = el( 'div', 'twc-msg twc-msg--bot twc-typing' );
			var bubble = el( 'div', 'twc-bubble' );
			var dots = el( 'span', 'twc-dots' );
			dots.setAttribute( 'aria-hidden', 'true' );
			dots.innerHTML = '<i></i><i></i><i></i>';
			var label = el( 'span', 'twc-typing__label', T.typing );
			label.dir = 'auto';
			label.setAttribute( 'role', 'status' );
			bubble.appendChild( dots );
			bubble.appendChild( label );
			wrap.appendChild( bubble );
			messages.appendChild( wrap );
			scroll();
			return wrap;
		}

		function send( text ) {
			text = ( text || '' ).trim();
			if ( ! text || busy ) {
				return;
			}
			if ( ! opened ) {
				open( true );
			}
			busy = true;
			root.classList.add( 'is-busy' );
			input.value = '';
			grow();
			suggestions.innerHTML = '';
			addMessage( 'user', null, { text: text } );
			store.set( 'talkwyn_active', '1' );
			var wait = typing();
			var live = null;
			var payload = { message: text, page_url: location.href, page_lang: pageLang() };
			transport( payload, {
				onDelta: function ( html ) {
					if ( wait ) {
						wait.remove();
						wait = null;
					}
					if ( ! live ) {
						live = addMessage( 'assistant', html, { tools: false, time: false } );
					} else {
						live.querySelector( '.twc-bubble' ).innerHTML = html;
					}
					scroll();
				}
			} ).then( function ( j ) {
				if ( wait ) {
					wait.remove();
				}
				if ( live ) {
					live.remove();
				}
				if ( j.session && j.session !== state.session ) {
					state.session = j.session;
					store.set( 'talkwyn_session', j.session );
				}
				var node = addMessage( 'assistant', j.reply, {
					text: j.text,
					sources: j.sources,
					id: j.message_id,
					extra: j.extra
				} );
				beep();
				emit( 'reply', { data: j, el: node } );
				if ( j.lead_offer && F.lead ) {
					if ( F.leadAskFirst && ! j.lead_direct ) {
						renderLeadOffer();
					} else {
						state.flags.lead_form = 1;
						if ( ! j.lead_direct ) {
							addMessage( 'assistant', null, { text: T.leadIntro, tools: false } );
						}
						renderLeadForm();
					}
				}
			} ).catch( function ( e ) {
				if ( wait ) {
					wait.remove();
				}
				if ( live ) {
					live.remove();
				}
				addMessage( 'assistant', null, { text: ( e && e.data && e.data.message ) || ( e && e.status ? T.error : T.connection ), tools: false } );
			} ).then( function () {
				busy = false;
				root.classList.remove( 'is-busy' );
			} );
		}

		function renderLeadOffer() {
			var wrap = el( 'div', 'twc-msg twc-msg--bot twc-offer' );
			var card = el( 'div', 'twc-bubble' );
			card.appendChild( el( 'p', '', T.leadPrompt ) );
			var row = el( 'div', 'twc-offer__actions' );
			var yes = el( 'button', 'twc-btn twc-btn--brand', T.leadYes );
			var no = el( 'button', 'twc-btn twc-btn--ghost', T.leadNo );
			yes.type = no.type = 'button';
			yes.addEventListener( 'click', function () {
				wrap.remove();
				addMessage( 'user', null, { text: T.leadYes } );
				addMessage( 'assistant', null, { text: T.leadIntro, tools: false } );
				state.flags.lead_form = 1;
				request( 'event', { type: 'lead_yes' } ).catch( function () {} );
				renderLeadForm();
			} );
			no.addEventListener( 'click', function () {
				wrap.remove();
				addMessage( 'user', null, { text: T.leadNo } );
				addMessage( 'assistant', null, { text: T.leadDecline, tools: false } );
				state.flags.lead_declined = 1;
				request( 'event', { type: 'lead_no' } ).catch( function () {} );
			} );
			row.appendChild( yes );
			row.appendChild( no );
			card.appendChild( row );
			wrap.appendChild( card );
			messages.appendChild( wrap );
			scroll();
		}

		function field( name, label, required, type ) {
			var w = el( 'label', 'twc-field' );
			w.appendChild( el( 'span', '', label + ( required ? ' *' : '' ) ) );
			var i = el( 'input' );
			i.name = name;
			i.type = type || 'text';
			i.required = !! required;
			i.dir = 'auto';
			if ( type === 'email' ) {
				i.autocomplete = 'email';
			} else if ( type === 'tel' ) {
				i.autocomplete = 'tel';
			} else {
				i.autocomplete = 'name';
			}
			w.appendChild( i );
			return w;
		}

		function renderLeadForm() {
			var existing = messages.querySelector( '.twc-lead' );
			if ( existing ) {
				existing.remove();
			}
			var wrap = el( 'div', 'twc-msg twc-msg--bot twc-lead' );
			var f = el( 'form', 'twc-lead__form' );
			f.noValidate = true;
			f.appendChild( el( 'strong', 'twc-lead__title', T.leadTitle ) );
			f.appendChild( field( 'name', T.name, F.requireName, 'text' ) );
			f.appendChild( field( 'email', T.email, F.requireEmail, 'email' ) );
			f.appendChild( field( 'phone', T.phone, F.requirePhone, 'tel' ) );
			var hp = el( 'label', 'twc-hp' );
			hp.setAttribute( 'aria-hidden', 'true' );
			hp.appendChild( el( 'span', '', T.websiteField ) );
			var hpi = el( 'input' );
			hpi.name = 'website';
			hpi.tabIndex = -1;
			hpi.autocomplete = 'off';
			hp.appendChild( hpi );
			f.appendChild( hp );
			if ( F.consent ) {
				var c = el( 'label', 'twc-consent' );
				var cb = el( 'input' );
				cb.type = 'checkbox';
				cb.name = 'consent';
				cb.value = '1';
				cb.required = true;
				c.appendChild( cb );
				c.appendChild( el( 'span', '', T.consent ) );
				f.appendChild( c );
			}
			var ts = null;
			if ( cfg.turnstile ) {
				ts = el( 'div', 'twc-turnstile' );
				f.appendChild( ts );
				renderTurnstile( ts );
			}
			var submit = el( 'button', 'twc-btn twc-btn--brand twc-btn--block', T.leadButton );
			submit.type = 'submit';
			f.appendChild( submit );
			var note = el( 'p', 'twc-lead__note' );
			note.setAttribute( 'role', 'alert' );
			f.appendChild( note );
			f.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				note.textContent = '';
				var fe = f.elements;
				var data = { name: fe.namedItem( 'name' ).value, email: fe.namedItem( 'email' ).value, phone: fe.namedItem( 'phone' ).value, website: hpi.value, consent: F.consent ? fe.namedItem( 'consent' ).checked : true, page_url: location.href };
				if ( ts ) {
					var tsi = f.querySelector( '[name="cf-turnstile-response"]' );
					data.turnstile = tsi ? tsi.value : '';
				}
				var label = submit.textContent;
				submit.disabled = true;
				submit.textContent = T.sending;
				request( 'lead', data ).then( function ( j ) {
					wrap.remove();
					state.flags.lead_submitted = j.lead_id || 1;
					renderLeadSaved( j.lead_id );
					emit( 'lead', { id: j.lead_id } );
				} ).catch( function ( err ) {
					note.textContent = ( err && err.data && err.data.message ) || T.leadError;
					submit.disabled = false;
					submit.textContent = label;
				} );
			} );
			wrap.appendChild( f );
			messages.appendChild( wrap );
			scroll();
			var first = f.querySelector( 'input[name="name"]' );
			if ( first && opened ) {
				setTimeout( function () {
					first.focus();
				}, 80 );
			}
		}

		function renderTurnstile( box ) {
			var tries = 0;
			( function wait() {
				if ( window.turnstile && window.turnstile.render ) {
					window.turnstile.render( box, { sitekey: cfg.turnstile, size: 'flexible' } );
				} else if ( tries++ < 40 ) {
					setTimeout( wait, 250 );
				}
			}() );
		}

		function renderLeadSaved( id ) {
			var wrap = el( 'div', 'twc-msg twc-msg--bot twc-saved' );
			var chip = el( 'div', 'twc-chip-saved' );
			chip.setAttribute( 'role', 'status' );
			chip.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
			chip.appendChild( el( 'span', '', T.leadSaved + ( id ? ' · ' + T.reference + id : '' ) ) );
			wrap.appendChild( chip );
			var p = el( 'div', 'twc-bubble' );
			p.dir = 'auto';
			p.textContent = T.leadSuccess + ' ' + T.continue;
			wrap.appendChild( p );
			messages.appendChild( wrap );
			scroll();
		}

		function reset() {
			if ( T.resetConfirm && ! window.confirm( T.resetConfirm ) ) {
				return;
			}
			var done = function ( session ) {
				state.session = session || '';
				state.history = [];
				state.flags = {};
				state.token = '';
				store.remove( 'talkwyn_active' );
				if ( session ) {
					store.set( 'talkwyn_session', session );
				} else {
					store.remove( 'talkwyn_session' );
				}
				instances.forEach( function ( i ) {
					i.welcome();
				} );
				input.focus();
				emit( 'reset', {} );
			};
			request( 'reset', {} ).then( function ( j ) {
				done( j.session );
			} ).catch( function () {
				done( '' );
			} );
		}

		function copyText( text ) {
			if ( navigator.clipboard && window.isSecureContext ) {
				return navigator.clipboard.writeText( text ).then( function () {
					return true;
				}, function () {
					return false;
				} );
			}
			var ta = el( 'textarea' );
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.select();
			var ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch ( e ) {}
			ta.remove();
			return Promise.resolve( ok );
		}

		return {
			mode: mode,
			open: open,
			close: close,
			send: send,
			restore: restore,
			welcome: welcome,
			addMessage: addMessage,
			isOpen: function () {
				return opened;
			}
		};
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
