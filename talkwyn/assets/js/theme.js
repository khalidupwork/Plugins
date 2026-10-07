/* Talkwyn theme script. Vanilla JS, no dependencies, deferred. */
( function () {
	'use strict';

	var doc = document.documentElement;
	doc.classList.remove( 'no-js' );
	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/* ---------- Analytics events ---------- */
	function track( name, props ) {
		try {
			if ( typeof window.gtag === 'function' ) {
				window.gtag( 'event', name, props || {} );
			}
			if ( typeof window.plausible === 'function' ) {
				window.plausible( name, { props: props || {} } );
			}
		} catch ( e ) {}
	}
	window.twTrack = track;
	if ( /^\/pricing\/?$/.test( window.location.pathname ) ) {
		window.addEventListener( 'load', function () {
			track( 'pricing_view' );
		} );
	}
	document.addEventListener( 'click', function ( e ) {
		var el = e.target.closest( '[data-tw-event]' );
		if ( el ) {
			track( el.getAttribute( 'data-tw-event' ), { location: el.getAttribute( 'data-tw-location' ) || '' } );
		}
	} );

	/* ---------- Header: mega menu (desktop) ---------- */
	var header = document.querySelector( '[data-tw-header]' );
	if ( header ) {
		var triggers = Array.prototype.slice.call( header.querySelectorAll( '.tw-mega__trigger[aria-controls]' ) );
		var openItem = null;
		var hoverTimer = null;
		var canHover = window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;

		var closeMega = function ( focusTrigger ) {
			if ( ! openItem ) {
				return;
			}
			var t = openItem.querySelector( '.tw-mega__trigger' );
			t.setAttribute( 'aria-expanded', 'false' );
			openItem.querySelector( '.tw-mega__panel' ).hidden = true;
			openItem.classList.remove( 'is-open' );
			header.classList.remove( 'has-open-panel' );
			openItem = null;
			if ( focusTrigger ) {
				t.focus();
			}
		};
		var openMega = function ( item ) {
			if ( openItem === item ) {
				return;
			}
			closeMega( false );
			item.querySelector( '.tw-mega__trigger' ).setAttribute( 'aria-expanded', 'true' );
			item.querySelector( '.tw-mega__panel' ).hidden = false;
			item.classList.add( 'is-open' );
			header.classList.add( 'has-open-panel' );
			openItem = item;
		};

		triggers.forEach( function ( t ) {
			var item = t.parentNode;
			t.addEventListener( 'click', function () {
				if ( openItem === item ) {
					closeMega( false );
				} else {
					openMega( item );
				}
			} );
			t.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'ArrowDown' ) {
					e.preventDefault();
					openMega( item );
					var first = item.querySelector( '.tw-mega__panel a' );
					if ( first ) {
						first.focus();
					}
				} else if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) {
					var all = Array.prototype.slice.call( header.querySelectorAll( '.tw-mega__trigger' ) );
					var dir = ( e.key === 'ArrowRight' ) === ( document.dir !== 'rtl' ) ? 1 : -1;
					var next = all[ ( all.indexOf( t ) + dir + all.length ) % all.length ];
					e.preventDefault();
					next.focus();
				}
			} );
			if ( canHover ) {
				item.addEventListener( 'mouseenter', function () {
					clearTimeout( hoverTimer );
					hoverTimer = setTimeout( function () {
						openMega( item );
					}, openItem ? 0 : 90 );
				} );
				item.addEventListener( 'mouseleave', function () {
					clearTimeout( hoverTimer );
					hoverTimer = setTimeout( function () {
						if ( openItem === item && ! item.contains( document.activeElement ) ) {
							closeMega( false );
						}
					}, 220 );
				} );
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && openItem ) {
				closeMega( openItem.contains( document.activeElement ) );
			}
		} );
		// Close when focus or a click leaves the open panel.
		header.addEventListener( 'focusout', function ( e ) {
			if ( openItem && e.relatedTarget && ! openItem.contains( e.relatedTarget ) ) {
				closeMega( false );
			}
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( openItem && ! openItem.contains( e.target ) ) {
				closeMega( false );
			}
		} );

		/* ---------- Header: full-screen menu (mobile) ---------- */
		var burger = header.querySelector( '.tw-hdr__burger' );
		var mnav = document.getElementById( 'tw-mnav' );
		var lastFocus = null;
		var focusables = function () {
			return Array.prototype.filter.call( mnav.querySelectorAll( 'a[href], button:not([disabled])' ), function ( el ) {
				return el.offsetParent !== null;
			} );
		};
		var closeNav = function () {
			mnav.hidden = true;
			burger.setAttribute( 'aria-expanded', 'false' );
			doc.classList.remove( 'tw-nav-open' );
			if ( lastFocus ) {
				lastFocus.focus();
			}
		};
		if ( burger && mnav ) {
			burger.addEventListener( 'click', function () {
				lastFocus = burger;
				mnav.hidden = false;
				burger.setAttribute( 'aria-expanded', 'true' );
				doc.classList.add( 'tw-nav-open' );
				var close = mnav.querySelector( '[data-tw-mnav-close]' );
				if ( close ) {
					close.focus();
				}
			} );
			mnav.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '[data-tw-mnav-close]' ) ) {
					closeNav();
					return;
				}
				var toggle = e.target.closest( '.tw-mnav__toggle' );
				if ( toggle ) {
					var sub = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
					var open = toggle.getAttribute( 'aria-expanded' ) === 'true';
					toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
					sub.hidden = open;
					return;
				}
				// Same-page anchors (like /#live-demo) close the menu.
				if ( e.target.closest( 'a[href*="#"]' ) ) {
					closeNav();
				}
			} );
			mnav.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					closeNav();
				} else if ( e.key === 'Tab' ) {
					var f = focusables();
					if ( ! f.length ) {
						return;
					}
					if ( e.shiftKey && document.activeElement === f[ 0 ] ) {
						e.preventDefault();
						f[ f.length - 1 ].focus();
					} else if ( ! e.shiftKey && document.activeElement === f[ f.length - 1 ] ) {
						e.preventDefault();
						f[ 0 ].focus();
					}
				}
			} );
			window.addEventListener( 'resize', function () {
				if ( ! mnav.hidden && window.innerWidth >= 1024 ) {
					closeNav();
				}
			} );
		}

		// A thin shadow once the page scrolls under the sticky header.
		var onScroll = function () {
			header.classList.toggle( 'is-scrolled', window.scrollY > 8 );
		};
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		onScroll();
	}

	/* ---------- Hero word rotator ---------- */
	document.querySelectorAll( '[data-tw-rotator]' ).forEach( function ( rotator ) {
		var words = rotator.querySelectorAll( '.tw-rotator__word' );
		if ( words.length < 2 || reduceMotion ) {
			return;
		}
		var i = 0;
		var timer = null;
		var tick = function () {
			words[ i ].classList.remove( 'is-active' );
			i = ( i + 1 ) % words.length;
			words[ i ].classList.add( 'is-active' );
		};
		// Rotate only while the hero is on screen.
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				clearInterval( timer );
				timer = entries[ 0 ].isIntersecting ? setInterval( tick, 2600 ) : null;
			} ).observe( rotator );
		}
	} );

	/* ---------- Copy buttons ---------- */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-tw-copy]' );
		if ( ! btn ) {
			return;
		}
		var target = document.getElementById( btn.getAttribute( 'data-tw-copy' ) );
		var text = target ? target.textContent.trim() : '';
		var done = function () {
			var old = btn.getAttribute( 'data-label' ) || btn.textContent;
			btn.setAttribute( 'data-label', old );
			btn.textContent = btn.getAttribute( 'data-copied' ) || 'Copied';
			setTimeout( function () {
				btn.textContent = old;
			}, 1600 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
		} else {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.select();
			document.execCommand( 'copy' );
			document.body.removeChild( ta );
			done();
		}
	} );

	/* ---------- Motion ----------
	 * Every effect starts from the finished, static page: without JS or with reduced
	 * motion nothing is hidden. Hidden states keep their space, so nothing shifts.
	 */
	var canMove = ! reduceMotion && 'IntersectionObserver' in window;
	window.twReady = true;
	doc.classList.toggle( 'tw-motion', canMove );
	function onVisible( els, fn, options ) {
		if ( ! els.length ) {
			return;
		}
		if ( ! canMove ) {
			els.forEach( fn );
			return;
		}
		var obs = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					obs.unobserve( entry.target );
					fn( entry.target );
				}
			} );
		}, options || { rootMargin: '0px 0px -12% 0px' } );
		els.forEach( function ( el ) {
			obs.observe( el );
		} );
	}

	// Stagger: children of [data-tw-stagger] get an index for transition delays.
	document.querySelectorAll( '[data-tw-stagger]' ).forEach( function ( parent ) {
		Array.prototype.forEach.call( parent.children, function ( child, i ) {
			child.style.setProperty( '--tw-i', i );
		} );
	} );

	// Reveal on scroll.
	onVisible( Array.prototype.slice.call( document.querySelectorAll( '.tw-reveal, [data-tw-reveal]' ) ), function ( el ) {
		el.classList.add( 'is-visible' );
	} );

	// Looping decoration only runs while on screen.
	var live = document.querySelectorAll( '[data-tw-live]' );
	if ( canMove && live.length ) {
		var liveObs = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				entry.target.classList.toggle( 'is-live', entry.isIntersecting );
			} );
		} );
		live.forEach( function ( el ) {
			liveObs.observe( el );
		} );
	}

	// Counters: the real number is in the HTML; it counts up once when seen.
	onVisible( Array.prototype.slice.call( document.querySelectorAll( '[data-tw-count]' ) ), function ( el ) {
		var target = parseInt( el.getAttribute( 'data-tw-count' ), 10 );
		if ( ! canMove || ! target ) {
			return;
		}
		var start = null;
		var dur = 1200;
		function frame( t ) {
			start = start || t;
			var k = Math.min( 1, ( t - start ) / dur );
			var eased = 1 - Math.pow( 1 - k, 3 );
			el.textContent = Math.round( target * eased ).toLocaleString();
			if ( k < 1 ) {
				requestAnimationFrame( frame );
			}
		}
		requestAnimationFrame( frame );
	} );

	// Chat playback: messages appear one by one, bot replies "type" first.
	function playChat( chat ) {
		var body = chat.querySelector( '.tw-chat__body' );
		if ( ! body ) {
			return;
		}
		var items = Array.prototype.filter.call( body.children, function ( el ) {
			return el.matches( '.tw-msg, .tw-chip--saved, .tw-lead-card' );
		} );
		if ( ! items.length ) {
			return;
		}
		var loop = chat.getAttribute( 'data-tw-play' ) === 'loop';
		var timers = [];
		items.forEach( function ( el ) {
			if ( el.classList.contains( 'tw-msg--bot' ) && ! el.querySelector( '.tw-msg__dots' ) ) {
				el.insertAdjacentHTML( 'afterbegin', '<span class="tw-msg__dots" aria-hidden="true"><i></i><i></i><i></i></span>' );
			}
		} );
		function reset() {
			items.forEach( function ( el ) {
				el.classList.remove( 'is-shown', 'is-typing' );
				el.classList.add( 'is-pending' );
			} );
		}
		function run() {
			reset();
			var t = 200;
			items.forEach( function ( el ) {
				if ( el.classList.contains( 'tw-msg--bot' ) ) {
					timers.push( setTimeout( function () {
						el.classList.remove( 'is-pending' );
						el.classList.add( 'is-typing' );
					}, t ) );
					t += 850;
					timers.push( setTimeout( function () {
						el.classList.remove( 'is-typing' );
						el.classList.add( 'is-shown' );
					}, t ) );
					t += 550;
				} else {
					timers.push( setTimeout( function () {
						el.classList.remove( 'is-pending' );
						el.classList.add( 'is-shown' );
					}, t ) );
					t += 550;
				}
			} );
			if ( loop ) {
				timers.push( setTimeout( function () {
					chat.classList.add( 'is-resetting' );
					timers.push( setTimeout( function () {
						chat.classList.remove( 'is-resetting' );
						run();
					}, 500 ) );
				}, t + 4500 ) );
			}
		}
		reset();
		run();
		if ( loop ) {
			// Stop the loop off screen; restart from the top when it comes back.
			new IntersectionObserver( function ( entries ) {
				if ( entries[ 0 ].isIntersecting ) {
					if ( ! timers.length ) {
						run();
					}
				} else {
					timers.forEach( clearTimeout );
					timers = [];
					items.forEach( function ( el ) {
						el.classList.remove( 'is-pending', 'is-typing' );
						el.classList.add( 'is-shown' );
					} );
				}
			} ).observe( chat );
		}
	}
	if ( canMove ) {
		var chats = Array.prototype.slice.call( document.querySelectorAll( '.tw-chat[data-tw-play]' ) );
		chats.forEach( function ( chat ) {
			chat.classList.add( 'is-armed' );
		} );
		onVisible( chats, playChat, { rootMargin: '0px 0px -20% 0px' } );
	}

	// Spotlight: cards light up under the pointer.
	if ( canMove && window.matchMedia( '(pointer: fine)' ).matches ) {
		var spotEl = null;
		var spotEvt = null;
		document.addEventListener( 'pointermove', function ( e ) {
			spotEvt = e;
			if ( spotEl === null ) {
				spotEl = requestAnimationFrame( function () {
					spotEl = null;
					var card = spotEvt.target.closest && spotEvt.target.closest( '.tw-spot' );
					if ( card ) {
						var r = card.getBoundingClientRect();
						card.style.setProperty( '--mx', ( spotEvt.clientX - r.left ) + 'px' );
						card.style.setProperty( '--my', ( spotEvt.clientY - r.top ) + 'px' );
					}
				} );
			}
		}, { passive: true } );

		// Gentle tilt on the hero chat.
		document.querySelectorAll( '[data-tw-tilt]' ).forEach( function ( el ) {
			var area = el.closest( 'section' ) || el;
			area.addEventListener( 'pointermove', function ( e ) {
				var r = area.getBoundingClientRect();
				var x = ( e.clientX - r.left ) / r.width - 0.5;
				var y = ( e.clientY - r.top ) / r.height - 0.5;
				el.style.setProperty( '--tw-rx', ( -y * 5 ).toFixed( 2 ) + 'deg' );
				el.style.setProperty( '--tw-ry', ( x * 7 ).toFixed( 2 ) + 'deg' );
			}, { passive: true } );
			area.addEventListener( 'pointerleave', function () {
				el.style.setProperty( '--tw-rx', '0deg' );
				el.style.setProperty( '--tw-ry', '0deg' );
			} );
		} );
	}

	// Night timeline: the step in the middle of the screen drives the chat on the right.
	document.querySelectorAll( '[data-tw-night]' ).forEach( function ( night ) {
		var steps = night.querySelectorAll( '.tw-night__item' );
		function activate( i ) {
			steps.forEach( function ( s, j ) {
				s.classList.toggle( 'is-active', j === i );
				s.classList.toggle( 'is-past', j < i );
			} );
			night.style.setProperty( '--tw-progress', ( ( i + 1 ) / steps.length ).toFixed( 3 ) );
		}
		activate( 0 );
		if ( ! canMove ) {
			return;
		}
		night.classList.add( 'is-scrolly' );
		// Observe the text blocks: on wide screens the list items are display: contents and have no box.
		var texts = Array.prototype.map.call( steps, function ( s ) {
			return s.querySelector( '.tw-night__text' ) || s;
		} );
		var obs = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					activate( texts.indexOf( entry.target ) );
				}
			} );
		}, { rootMargin: '-40% 0px -40% 0px' } );
		texts.forEach( function ( t ) {
			obs.observe( t );
		} );
	} );

	/* ---------- Docs: "Was this helpful?" ---------- */
	document.querySelectorAll( '[data-tw-helpful]' ).forEach( function ( box ) {
		box.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( 'button[data-value]' );
			if ( ! btn || ! window.twRest ) {
				return;
			}
			box.querySelectorAll( 'button' ).forEach( function ( b ) {
				b.disabled = true;
			} );
			var status = box.querySelector( '[data-tw-helpful-status]' );
			fetch( window.twRest.url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { post_id: parseInt( box.getAttribute( 'data-tw-helpful' ), 10 ), value: btn.getAttribute( 'data-value' ) } )
			} ).then( function () {
				status.textContent = 'Thanks for the feedback.';
				track( 'doc_feedback', { value: btn.getAttribute( 'data-value' ) } );
			} ).catch( function () {
				status.textContent = 'Could not send feedback right now.';
			} );
		} );
	} );

	/* ---------- Scripted demo ---------- */
	function escapeHtml( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function detectDir( text ) {
		return /[֐-ࣿ]/.test( text ) ? 'rtl' : 'ltr';
	}
	function detectLang( text ) {
		if ( /[؀-ۿ]/.test( text ) ) {
			return /[ٹڈڑںےگکھپچژ]/.test( text ) ? 'ur' : 'ar';
		}
		if ( /[ऀ-ॿ]/.test( text ) ) {
			return 'hi';
		}
		if ( /\b(hola|cuánto|cuanto|precio|blanqueamiento|sábado|seguro|cita|mañana)\b/i.test( text ) ) {
			return 'es';
		}
		if ( /\b(kya|hai|kab|kitna|kitne|mein|aap|chahiye|karna)\b/i.test( text ) ) {
			return 'ur-Latn';
		}
		return 'en';
	}

	document.querySelectorAll( '[data-tw-demo]' ).forEach( function ( demo ) {
		var dataEl = demo.querySelector( 'script[type="application/json"]' );
		if ( ! dataEl ) {
			return;
		}
		var data = JSON.parse( dataEl.textContent );
		var body = demo.querySelector( '.tw-chat__body' );
		var form = demo.querySelector( '.tw-chat__form' );
		var input = form ? form.querySelector( 'input' ) : null;
		var busy = false;
		var asked = 0;

		function scrollDown() {
			body.scrollTop = body.scrollHeight;
		}
		function addUser( text ) {
			var el = document.createElement( 'div' );
			el.className = 'tw-msg tw-msg--user';
			el.setAttribute( 'dir', detectDir( text ) );
			el.setAttribute( 'lang', detectLang( text ).replace( '-Latn', '' ) );
			el.innerHTML = '<p>' + escapeHtml( text ) + '</p>';
			body.appendChild( el );
			scrollDown();
		}
		function addBot( answer ) {
			var el = document.createElement( 'div' );
			el.className = 'tw-msg tw-msg--bot';
			el.setAttribute( 'dir', answer.dir || 'ltr' );
			if ( answer.lang ) {
				el.setAttribute( 'lang', answer.lang );
			}
			var html = answer.html;
			if ( answer.sources && answer.sources.length ) {
				html += '<div class="tw-msg__sources"><span class="tw-msg__sources-label">' + escapeHtml( answer.sourcesLabel || data.sourcesLabel ) + '</span>';
				answer.sources.forEach( function ( s ) {
					html += '<span class="tw-chip tw-chip--source">' + escapeHtml( s ) + '</span>';
				} );
				html += '</div>';
			}
			el.innerHTML = html;
			body.appendChild( el );
			if ( answer.lead ) {
				addLeadOffer( answer.lead );
			}
			scrollDown();
		}
		function addLeadOffer( lead ) {
			var card = document.createElement( 'div' );
			card.className = 'tw-lead-card';
			card.setAttribute( 'dir', lead.dir || 'ltr' );
			card.innerHTML = '<p style="margin:0">' + escapeHtml( lead.text ) + '</p><div class="tw-lead-card__row"><button type="button" class="tw-lead-card__btn tw-chip" data-yes>' + escapeHtml( lead.yes ) + '</button><button type="button" class="tw-lead-card__btn tw-lead-card__btn--ghost tw-chip" data-no>' + escapeHtml( lead.no ) + '</button></div>';
			body.appendChild( card );
			card.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '[data-yes]' ) ) {
					card.outerHTML = '<span class="tw-chip tw-chip--saved" role="status">✓ ' + escapeHtml( lead.saved ) + '</span>';
					track( 'demo_lead_saved' );
				} else if ( e.target.closest( '[data-no]' ) ) {
					card.remove();
				}
				scrollDown();
			} );
		}
		function typing() {
			var t = document.createElement( 'div' );
			t.className = 'tw-typing';
			t.setAttribute( 'aria-label', data.typingLabel );
			t.setAttribute( 'role', 'status' );
			t.innerHTML = '<span></span><span></span><span></span>';
			body.appendChild( t );
			scrollDown();
			return t;
		}
		function match( text ) {
			var lower = text.toLowerCase();
			var lang = detectLang( text );
			var best = null;
			var bestScore = 0;
			data.intents.forEach( function ( intent ) {
				var score = 0;
				intent.keywords.forEach( function ( k ) {
					if ( lower.indexOf( k.toLowerCase() ) !== -1 ) {
						score += k.length > 3 ? 2 : 1;
					}
				} );
				if ( score > bestScore ) {
					bestScore = score;
					best = intent;
				}
			} );
			var answers = best ? best.answers : data.unknown;
			return answers[ lang ] || answers[ lang.split( '-' )[ 0 ] ] || answers.en;
		}
		function ask( text ) {
			text = String( text || '' ).trim().slice( 0, 300 );
			if ( ! text || busy ) {
				return;
			}
			busy = true;
			asked++;
			track( 'demo_question', { count: asked } );
			addUser( text );
			var t = typing();
			setTimeout( function () {
				t.remove();
				addBot( match( text ) );
				busy = false;
			}, reduceMotion ? 100 : 900 + Math.min( 900, text.length * 12 ) );
		}

		demo.addEventListener( 'click', function ( e ) {
			var chip = e.target.closest( '[data-tw-ask]' );
			if ( chip ) {
				ask( chip.getAttribute( 'data-tw-ask' ) );
			}
		} );
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				ask( input.value );
				input.value = '';
			} );
		}
	} );
}() );
