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
		if ( store.get( 'talkwyn_seen' ) === '1' ) {
			Array.prototype.forEach.call( roots, function ( r ) {
				r.classList.add( 'twc--seen' );
			} );
		}
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

		if ( mode === 'floating' && cfg.devices && cfg.devices !== 'all' ) {
			var small = window.matchMedia( '(max-width: 782px)' ).matches;
			if ( ( cfg.devices === 'desktop' && small ) || ( cfg.devices === 'mobile' && ! small ) ) {
				root.hidden = true;
			}
		}
		var savedLang = store.get( 'talkwyn_lang' ) || '';
		if ( savedLang && cfg.languages && cfg.languages[ savedLang ] && cfg.languages[ savedLang ].rtl ) {
			root.setAttribute( 'dir', 'rtl' );
		}

		renderFoot();
		renderSound();
		setupMenu();

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
			store.set( 'talkwyn_seen', '1' );
			root.classList.add( 'twc--seen' );
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
			var item = root.querySelector( '.twc-menu [data-id="sound"]' );
			if ( item ) {
				item.querySelector( 'span' ).textContent = soundOn ? T.soundOn : T.soundOff;
				item.setAttribute( 'aria-checked', soundOn ? 'true' : 'false' );
			}
		}

		/* ---------- Menu ---------- */

		function menuIcon( id ) {
			var icons = {
				name: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
				transcript: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
				sound: '<path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="M15 9.5a4 4 0 0 1 0 5"/>',
				language: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18"/>',
				popout: '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
				reset: '<path d="M4 4v6h6"/><path d="M5.5 15a7.5 7.5 0 1 0 .8-7.7L4 10"/>',
				add_chat: '<path d="M12 5v14"/><path d="M5 12h14"/>'
			};
			return '<svg viewBox="0 0 24 24" aria-hidden="true">' + ( icons[ id ] || '<circle cx="12" cy="12" r="2"/>' ) + '</svg>';
		}

		function setupMenu() {
			var btn = root.querySelector( '.twc-menu-btn' );
			var box = root.querySelector( '.twc-menu' );
			if ( ! btn || ! box ) {
				return;
			}
			( cfg.menu || [] ).forEach( function ( item ) {
				if ( item.id === 'popout' && root.classList.contains( 'twc--popout' ) ) {
					return;
				}
				var b = el( item.url ? 'a' : 'button', 'twc-menu__item' );
				if ( item.url ) {
					b.href = item.url;
					b.target = '_blank';
					b.rel = 'nofollow noopener';
				} else {
					b.type = 'button';
				}
				b.setAttribute( 'role', item.id === 'sound' ? 'menuitemcheckbox' : 'menuitem' );
				b.setAttribute( 'data-id', item.id );
				b.innerHTML = menuIcon( item.id );
				b.appendChild( el( 'span', '', item.label ) );
				b.addEventListener( 'click', function () {
					closeMenu();
					runMenu( item );
				} );
				box.appendChild( b );
			} );
			renderSound();
			btn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				if ( box.hidden ) {
					box.hidden = false;
					btn.setAttribute( 'aria-expanded', 'true' );
					var first = box.querySelector( '.twc-menu__item' );
					if ( first ) {
						first.focus();
					}
				} else {
					closeMenu();
				}
			} );
			document.addEventListener( 'click', function ( e ) {
				if ( ! box.hidden && ! box.contains( e.target ) ) {
					closeMenu();
				}
			} );
			box.addEventListener( 'keydown', function ( e ) {
				var items = Array.prototype.slice.call( box.querySelectorAll( '.twc-menu__item' ) );
				var i = items.indexOf( document.activeElement );
				if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
					e.preventDefault();
					items[ ( i + ( e.key === 'ArrowDown' ? 1 : -1 ) + items.length ) % items.length ].focus();
				} else if ( e.key === 'Escape' ) {
					e.stopPropagation();
					closeMenu();
					btn.focus();
				}
			} );
			function closeMenu() {
				box.hidden = true;
				btn.setAttribute( 'aria-expanded', 'false' );
			}
		}

		function runMenu( item ) {
			if ( item.url ) {
				return;
			}
			if ( ! opened ) {
				open( true );
			}
			switch ( item.id ) {
				case 'name':
					nameForm();
					break;
				case 'transcript':
					transcriptForm();
					break;
				case 'sound':
					soundOn = ! soundOn;
					store.set( 'talkwyn_sound', soundOn ? '1' : '0' );
					renderSound();
					if ( soundOn ) {
						beep();
					}
					break;
				case 'language':
					languagePicker();
					break;
				case 'popout':
					window.open( cfg.popoutUrl, 'talkwyn_chat', 'width=460,height=760,noopener=no' );
					close();
					break;
				case 'reset':
					reset();
					break;
				default:
					emit( 'menu', { id: item.id, item: item } );
			}
		}

		function card( cls, title ) {
			var old = messages.querySelector( '.twc-sheet' );
			if ( old ) {
				old.remove();
			}
			var wrap = el( 'div', 'twc-msg twc-msg--bot twc-sheet ' + cls );
			var f = el( 'form', 'twc-lead__form' );
			f.noValidate = true;
			f.appendChild( el( 'strong', 'twc-lead__title', title ) );
			wrap.appendChild( f );
			messages.appendChild( wrap );
			scroll();
			return { wrap: wrap, form: f, shown: Date.now() };
		}

		function actions( f, label ) {
			var row = el( 'div', 'twc-offer__actions' );
			var ok = el( 'button', 'twc-btn twc-btn--brand', label );
			ok.type = 'submit';
			var no = el( 'button', 'twc-btn twc-btn--ghost', T.cancel );
			no.type = 'button';
			row.appendChild( ok );
			row.appendChild( no );
			f.appendChild( row );
			var note = el( 'p', 'twc-lead__note' );
			note.setAttribute( 'role', 'alert' );
			f.appendChild( note );
			return { ok: ok, no: no, note: note };
		}

		function nameForm() {
			var c = card( 'twc-sheet--name', T.yourName );
			var input = field( 'name', T.yourName, true, 'text' );
			input.querySelector( 'input' ).value = store.get( 'talkwyn_name' ) || '';
			c.form.appendChild( input );
			var a = actions( c.form, T.save );
			a.no.addEventListener( 'click', function () {
				c.wrap.remove();
			} );
			c.form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var name = input.querySelector( 'input' ).value.trim().slice( 0, 60 );
				if ( ! name ) {
					return;
				}
				store.set( 'talkwyn_name', name );
				request( 'event', { type: 'name', value: name } ).catch( function () {} );
				c.wrap.remove();
				addMessage( 'assistant', null, { text: T.nameSaved.replace( '%s', name ), tools: false } );
			} );
			setTimeout( function () {
				input.querySelector( 'input' ).focus();
			}, 60 );
		}

		function transcriptForm() {
			var c = card( 'twc-sheet--transcript', T.transcriptTitle );
			var email = field( 'email', T.email, true, 'email' );
			c.form.appendChild( email );
			var hp = el( 'label', 'twc-hp' );
			hp.setAttribute( 'aria-hidden', 'true' );
			var hpi = el( 'input' );
			hpi.name = 'website';
			hpi.tabIndex = -1;
			hpi.autocomplete = 'off';
			hp.appendChild( hpi );
			c.form.appendChild( hp );
			var consent = el( 'label', 'twc-consent' );
			var cb = el( 'input' );
			cb.type = 'checkbox';
			cb.required = true;
			consent.appendChild( cb );
			consent.appendChild( el( 'span', '', T.transcriptConsent ) );
			c.form.appendChild( consent );
			var a = actions( c.form, T.transcriptSend );
			a.no.addEventListener( 'click', function () {
				c.wrap.remove();
			} );
			c.form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				a.note.textContent = '';
				a.ok.disabled = true;
				request( 'transcript', { email: email.querySelector( 'input' ).value, consent: cb.checked, website: hpi.value, elapsed: Date.now() - c.shown, page_url: location.href } ).then( function ( j ) {
					c.wrap.remove();
					addMessage( 'assistant', null, { text: j.message, tools: false } );
				} ).catch( function ( err ) {
					a.note.textContent = ( err && err.data && err.data.message ) || T.leadError;
					a.ok.disabled = false;
				} );
			} );
			setTimeout( function () {
				email.querySelector( 'input' ).focus();
			}, 60 );
		}

		function languagePicker() {
			var c = card( 'twc-sheet--lang', T.language || '' );
			c.form.querySelector( '.twc-lead__title' ).textContent = ( cfg.menu || [] ).filter( function ( i ) {
				return i.id === 'language';
			} ).map( function ( i ) {
				return i.label;
			} )[ 0 ] || '';
			var grid = el( 'div', 'twc-langs' );
			var current = store.get( 'talkwyn_lang' ) || '';
			var pick = function ( code ) {
				store.set( 'talkwyn_lang', code );
				var info = code && cfg.languages[ code ];
				root.setAttribute( 'dir', info && info.rtl ? 'rtl' : ( /^(ar|ur|fa|he)\b/i.test( pageLang() ) ? 'rtl' : 'ltr' ) );
				request( 'event', { type: 'lang', value: code } ).catch( function () {} );
				c.wrap.remove();
				if ( info ) {
					addMessage( 'assistant', null, { text: T.languageSet.replace( '%s', info.native ), tools: false } );
				}
			};
			var auto = el( 'button', 'twc-chip' + ( current ? '' : ' is-on' ), T.autoLanguage );
			auto.type = 'button';
			auto.addEventListener( 'click', function () {
				pick( '' );
			} );
			grid.appendChild( auto );
			Object.keys( cfg.languages || {} ).forEach( function ( code ) {
				var b = el( 'button', 'twc-chip' + ( current === code ? ' is-on' : '' ), cfg.languages[ code ].native );
				b.type = 'button';
				b.lang = code;
				b.addEventListener( 'click', function () {
					pick( code );
				} );
				grid.appendChild( b );
			} );
			c.form.appendChild( grid );
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
			if ( cfg.badge ) {
				var p = el( 'a', 'twc-badge' );
				p.href = cfg.badgeUrl;
				p.target = '_blank';
				p.rel = 'nofollow noopener';
				p.innerHTML = '<svg viewBox="0 0 32 32" aria-hidden="true"><path d="M8 4.5h16A5.5 5.5 0 0 1 29.5 10v10A5.5 5.5 0 0 1 24 25.5H13.5L7.5 30v-4.6A5.5 5.5 0 0 1 2.5 20V10A5.5 5.5 0 0 1 8 4.5Z"/></svg>';
				p.appendChild( el( 'span', '', T.poweredBy ) );
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
			var meta = el( 'div', 'twc-meta' );
			stamp( meta, o.time );
			var src = null;
			if ( o.sources && o.sources.length ) {
				// Sources stay folded behind one small button so answers look clean.
				src = el( 'ul', 'twc-sources' );
				src.hidden = true;
				src.id = 'twc-src-' + Math.random().toString( 36 ).slice( 2, 9 );
				o.sources.forEach( function ( s ) {
					var li = el( 'li' );
					var a = el( 'a', '', s.title );
					a.href = s.url;
					a.dir = 'auto';
					li.appendChild( a );
					src.appendChild( li );
				} );
				var toggle = el( 'button', 'twc-src-btn' );
				toggle.type = 'button';
				toggle.setAttribute( 'aria-expanded', 'false' );
				toggle.setAttribute( 'aria-controls', src.id );
				toggle.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/></svg>';
				toggle.appendChild( el( 'span', '', T.sources + ' (' + o.sources.length + ')' ) );
				toggle.addEventListener( 'click', function () {
					src.hidden = ! src.hidden;
					toggle.setAttribute( 'aria-expanded', src.hidden ? 'false' : 'true' );
					if ( ! src.hidden ) {
						scroll();
					}
				} );
				meta.appendChild( toggle );
			}
			if ( role !== 'user' && o.tools !== false && ( F.copy || F.feedback ) ) {
				meta.appendChild( tools( o.text || bubble.textContent, o.id ) );
			}
			if ( meta.childNodes.length ) {
				wrap.appendChild( meta );
			}
			if ( src ) {
				wrap.appendChild( src );
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
			var shownAt = Date.now();
			if ( store.get( 'talkwyn_name' ) ) {
				f.querySelector( 'input[name="name"]' ).value = store.get( 'talkwyn_name' );
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
				var data = { name: fe.namedItem( 'name' ).value, email: fe.namedItem( 'email' ).value, phone: fe.namedItem( 'phone' ).value, website: hpi.value, consent: F.consent ? fe.namedItem( 'consent' ).checked : true, elapsed: Date.now() - shownAt, page_url: location.href };
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
