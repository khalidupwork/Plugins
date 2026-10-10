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
					// Accordion: opening one group closes the others.
					mnav.querySelectorAll( '.tw-mnav__toggle[aria-expanded="true"]' ).forEach( function ( t ) {
						if ( t !== toggle ) {
							t.setAttribute( 'aria-expanded', 'false' );
							var s = document.getElementById( t.getAttribute( 'aria-controls' ) );
							if ( s ) {
								s.hidden = true;
							}
						}
					} );
					toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
					if ( sub ) {
						sub.hidden = open;
					}
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

	/* ---------- FAQ: smooth open and close ---------- */
	document.querySelectorAll( '.tw-faq details' ).forEach( function ( d ) {
		var summary = d.querySelector( 'summary' );
		if ( ! summary || reduceMotion || ! d.animate ) {
			return;
		}
		var anim = null;
		summary.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( anim ) {
				return;
			}
			var start = d.offsetHeight;
			var end;
			if ( d.open ) {
				d.classList.add( 'is-closing' );
				end = summary.offsetHeight;
			} else {
				d.open = true;
				end = d.offsetHeight;
			}
			anim = d.animate( { height: [ start + 'px', end + 'px' ] }, { duration: 320, easing: 'cubic-bezier(.2,.8,.2,1)' } );
			anim.onfinish = function () {
				if ( d.classList.contains( 'is-closing' ) ) {
					d.open = false;
					d.classList.remove( 'is-closing' );
				}
				anim = null;
			};
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
			return 'ar';
		}
		if ( /[ऀ-ॿ]/.test( text ) ) {
			return 'hi';
		}
		if ( /\b(hola|cuánto|cuanto|precio|blanqueamiento|sábado|seguro|cita|mañana)\b/i.test( text ) ) {
			return 'es';
		}
		if ( /\b(bonjour|combien|samedi|blanchiment|rendez-vous|ouvert|vous|est-ce)\b/i.test( text ) ) {
			return 'fr';
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

/* Posts: move the contents box into the sticky sidebar on wide screens and mark the section in view. */
( function () {
	var target = document.querySelector( '[data-tw-toc-target]' );
	var toc = document.querySelector( '.tw-post-body .tw-toc-box' );
	if ( ! target || ! toc ) {
		return;
	}
	var home = document.createComment( 'toc' );
	toc.parentNode.insertBefore( home, toc );
	var mq = window.matchMedia( '(min-width: 1100px)' );
	function place() {
		if ( mq.matches && toc.parentNode !== target ) {
			target.appendChild( toc );
		} else if ( ! mq.matches && toc.parentNode === target ) {
			home.parentNode.insertBefore( toc, home.nextSibling );
		}
	}
	place();
	if ( mq.addEventListener ) {
		mq.addEventListener( 'change', place );
	}
	var links = Array.prototype.slice.call( toc.querySelectorAll( 'a[href^="#"]' ) );
	if ( ! ( 'IntersectionObserver' in window ) || ! links.length ) {
		return;
	}
	var byId = {};
	links.forEach( function ( a ) {
		byId[ decodeURIComponent( a.getAttribute( 'href' ).slice( 1 ) ) ] = a;
	} );
	var io = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( e ) {
			if ( e.isIntersecting && byId[ e.target.id ] ) {
				links.forEach( function ( a ) {
					a.removeAttribute( 'aria-current' );
				} );
				byId[ e.target.id ].setAttribute( 'aria-current', 'true' );
			}
		} );
	}, { rootMargin: '-20% 0px -70% 0px' } );
	Object.keys( byId ).forEach( function ( id ) {
		var h = document.getElementById( id );
		if ( h ) {
			io.observe( h );
		}
	} );
}() );

/* Header popups: Log in and Start free trial open as dialogs. Real links stay as the fallback. */
( function () {
	function dialogFor( name ) {
		var d = document.getElementById( 'tw-modal-' + name );
		return d && typeof d.showModal === 'function' ? d : null;
	}
	function fill( d ) {
		var body = d.querySelector( '[data-tw-modal-body]' );
		var tpl = d.querySelector( 'template[data-tw-modal-tpl]' );
		if ( body && tpl && ! body.childNodes.length ) {
			body.appendChild( tpl.content.cloneNode( true ) );
		}
	}
	function open( name, opener ) {
		var d = dialogFor( name );
		if ( ! d ) {
			return false;
		}
		var mnav = document.getElementById( 'tw-mnav' );
		if ( mnav && ! mnav.hidden ) {
			var c = mnav.querySelector( '[data-tw-mnav-close]' );
			if ( c ) {
				c.click();
			}
		}
		fill( d );
		d.twOpener = opener || null;
		d.showModal();
		var first = d.querySelector( 'input:not([type="hidden"]):not([tabindex="-1"]), button[type="submit"], a.tw-pill' );
		if ( first ) {
			first.focus();
		}
		return true;
	}
	document.addEventListener( 'click', function ( e ) {
		var t = e.target.closest( '[data-tw-modal]' );
		if ( t ) {
			var name = t.getAttribute( 'data-tw-modal' );
			// On the pricing page the trial form is already on the page: scroll to it instead.
			if ( 'trial' === name && document.getElementById( 'trial' ) ) {
				return;
			}
			if ( e.metaKey || e.ctrlKey || e.shiftKey ) {
				return;
			}
			if ( open( name, t ) ) {
				e.preventDefault();
			}
			return;
		}
		if ( e.target.closest( '[data-tw-modal-close]' ) ) {
			e.target.closest( 'dialog' ).close();
		}
	} );
	document.querySelectorAll( 'dialog.tw-modal' ).forEach( function ( d ) {
		// Click on the backdrop closes the dialog.
		d.addEventListener( 'click', function ( e ) {
			if ( e.target === d ) {
				d.close();
			}
		} );
		d.addEventListener( 'close', function () {
			if ( d.twOpener && document.contains( d.twOpener ) ) {
				d.twOpener.focus();
			}
		} );
	} );
	window.twOpenModal = open;
	// After a trial form submit on any page, show the result in the popup.
	if ( /[?&]twh_trial=/.test( window.location.search ) && ! document.getElementById( 'trial' ) ) {
		open( 'trial' );
	}
}() );

/* Site forms (contact, waitlist) send over AJAX and show the result in place.
   Without JavaScript they post and redirect back as before. */
( function () {
	'use strict';
	function notice( form, type, text ) {
		var el = form.previousElementSibling;
		if ( ! el || ! el.classList || ! el.classList.contains( 'tw-notice' ) ) {
			el = document.createElement( 'p' );
			form.parentNode.insertBefore( el, form );
		}
		el.className = 'tw-notice tw-notice--' + type;
		el.setAttribute( 'role', 'status' );
		el.textContent = text;
		return el;
	}
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form.matches || ! form.matches( 'form[data-tw-ajax]' ) || ! window.fetch || ! window.FormData ) {
			return;
		}
		e.preventDefault();
		if ( ! form.reportValidity() ) {
			return;
		}
		var name = form.getAttribute( 'data-tw-ajax' );
		var btn = form.querySelector( 'button[type="submit"]' );
		var label = btn ? btn.innerHTML : '';
		if ( btn ) {
			btn.disabled = true;
			btn.setAttribute( 'aria-busy', 'true' );
		}
		// getAttribute: the form has a field named "action", which hides form.action.
		var post = ( form.getAttribute( 'action' ) || '' ).split( '#' )[ 0 ];
		var ajax = post.replace( 'admin-post.php', 'admin-ajax.php' );
		var reset = function () {
			if ( btn ) {
				btn.disabled = false;
				btn.removeAttribute( 'aria-busy' );
				btn.innerHTML = label;
			}
		};
		fetch( ajax + '?action=talkwyn_form_nonce&form=' + encodeURIComponent( name ), { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( n ) {
				var data = new FormData( form );
				data.set( 'tw_ajax', '1' );
				if ( n && n.success && n.data && n.data.nonce ) {
					data.set( '_tw_nonce', n.data.nonce );
				}
				return fetch( post, { method: 'POST', body: data, credentials: 'same-origin' } );
			} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				var el = notice( form, res.type || 'error', res.message || '' );
				if ( res.success ) {
					form.hidden = true;
					el.setAttribute( 'tabindex', '-1' );
					el.focus();
					return;
				}
				if ( window.twCaptchaReset ) {
					window.twCaptchaReset( form );
				}
				reset();
			} )
			.catch( function () {
				form.removeAttribute( 'data-tw-ajax' );
				HTMLFormElement.prototype.submit.call( form );
			} );
	} );
}() );

/*
 * Spam check (Cloudflare Turnstile). Each form with a check has an empty
 * .tw-captcha box. The Turnstile script loads only once such a box is visible,
 * and boxes inside a popup render when the popup opens.
 */
( function () {
	var cfg = window.twCaptcha;
	if ( ! cfg || ! cfg.key ) {
		return;
	}
	var loading = false;
	function render() {
		document.querySelectorAll( '.tw-captcha:not([data-tw-id])' ).forEach( function ( el ) {
			if ( ! el.offsetParent ) {
				return;
			}
			if ( ! window.turnstile ) {
				if ( ! loading ) {
					loading = true;
					var s = document.createElement( 'script' );
					s.src = cfg.src;
					s.async = true;
					document.head.appendChild( s );
				}
				return;
			}
			el.setAttribute( 'data-tw-id', window.turnstile.render( el, {
				sitekey: cfg.key,
				action: el.getAttribute( 'data-action' ) || 'form',
				size: 'flexible',
				theme: 'light'
			} ) );
		} );
	}
	window.twCaptchaReady = render;
	window.twCaptchaReset = function ( form ) {
		var el = form && form.querySelector( '.tw-captcha[data-tw-id]' );
		if ( el && window.turnstile ) {
			window.turnstile.reset( el.getAttribute( 'data-tw-id' ) );
		}
	};
	// The trial form from Talkwyn Hub reports a failed request with this event.
	document.addEventListener( 'twh:trial_error', function ( e ) {
		window.twCaptchaReset( e.detail && e.detail.form );
	} );
	var queued = false;
	new MutationObserver( function () {
		if ( queued ) {
			return;
		}
		queued = true;
		window.requestAnimationFrame( function () {
			queued = false;
			render();
		} );
	} ).observe( document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'open', 'hidden' ] } );
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', render );
	} else {
		render();
	}
}() );

/*
 * Docs: filter the sidebar, close the sidebar on phones, and mark the
 * "On this page" link for the section in view.
 */
( function () {
	var menu = document.querySelector( '[data-tw-docs-menu]' );
	if ( ! menu ) {
		return;
	}
	if ( window.matchMedia( '(max-width: 999px)' ).matches ) {
		menu.removeAttribute( 'open' );
	}
	var input = menu.querySelector( '[data-tw-docs-filter]' );
	var none = menu.querySelector( '[data-tw-docs-none]' );
	if ( input ) {
		input.addEventListener( 'input', function () {
			var q = input.value.trim().toLowerCase();
			var shown = 0;
			menu.querySelectorAll( '.tw-docs__group' ).forEach( function ( group ) {
				var any = false;
				group.querySelectorAll( 'li' ).forEach( function ( li ) {
					var hit = ! q || li.textContent.toLowerCase().indexOf( q ) !== -1;
					li.hidden = ! hit;
					any = any || hit;
					shown += hit ? 1 : 0;
				} );
				group.hidden = ! any;
			} );
			if ( none ) {
				none.hidden = shown > 0;
			}
		} );
	}
	var links = Array.prototype.slice.call( document.querySelectorAll( '.tw-docs-app .tw-toc a[href^="#"]' ) );
	if ( ! links.length || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	var byId = {};
	links.forEach( function ( a ) {
		byId[ decodeURIComponent( a.getAttribute( 'href' ).slice( 1 ) ) ] = a;
	} );
	var heads = Object.keys( byId ).map( function ( id ) {
		return document.getElementById( id );
	} ).filter( Boolean );
	var visible = {};
	var mark = function ( id ) {
		links.forEach( function ( a ) {
			a.removeAttribute( 'aria-current' );
		} );
		if ( byId[ id ] ) {
			byId[ id ].setAttribute( 'aria-current', 'true' );
		}
	};
	var spy = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( e ) {
			visible[ e.target.id ] = e.isIntersecting;
		} );
		for ( var i = 0; i < heads.length; i++ ) {
			if ( visible[ heads[ i ].id ] ) {
				mark( heads[ i ].id );
				return;
			}
		}
	}, { rootMargin: '-80px 0px -65% 0px' } );
	heads.forEach( function ( h ) {
		spy.observe( h );
	} );
}() );

/* Cookie banner. The choice lives in the essential tw_consent cookie (180 days).
   Google Analytics loads only after "Accept all"; the WP Consent API hears about it too. */
( function () {
	var box = document.querySelector( '[data-tw-cookie]' );
	function read() {
		var m = document.cookie.match( /(?:^|;\s*)tw_consent=(all|essential)/ );
		return m ? m[ 1 ] : '';
	}
	function loadGa() {
		if ( ! window.twGa4 || window.gtag || 'all' !== read() ) {
			return;
		}
		var s = document.createElement( 'script' );
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent( window.twGa4 );
		document.head.appendChild( s );
		window.dataLayer = window.dataLayer || [];
		window.gtag = function () {
			window.dataLayer.push( arguments );
		};
		window.gtag( 'js', new Date() );
		window.gtag( 'config', window.twGa4, { anonymize_ip: true } );
	}
	function choose( value ) {
		document.cookie = 'tw_consent=' + value + ';path=/;max-age=' + ( 180 * 86400 ) + ';SameSite=Lax' + ( 'https:' === location.protocol ? ';Secure' : '' );
		if ( typeof window.wp_set_consent === 'function' ) {
			window.wp_set_consent( 'statistics', 'all' === value ? 'allow' : 'deny' );
			window.wp_set_consent( 'marketing', 'all' === value ? 'allow' : 'deny' );
		}
		if ( box ) {
			box.hidden = true;
		}
		loadGa();
		document.dispatchEvent( new CustomEvent( 'tw:consent', { detail: value } ) );
	}
	loadGa();
	if ( ! box ) {
		return;
	}
	if ( ! read() ) {
		box.hidden = false;
	}
	box.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-tw-consent]' );
		if ( b ) {
			choose( b.getAttribute( 'data-tw-consent' ) );
		}
	} );
	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest( 'a[href$="#cookie-settings"]' );
		if ( a ) {
			e.preventDefault();
			box.hidden = false;
			var first = box.querySelector( 'button' );
			if ( first ) {
				first.focus();
			}
		}
	} );
}() );

/* Homepage: the free trial popup after the visitor scrolls part of the page, once per
   window.twNudge.days. It waits for the cookie banner and never opens over another dialog. */
( function () {
	var cfg = window.twNudge;
	if ( ! cfg || ! window.twOpenModal ) {
		return;
	}
	var key = 'tw_nudge_seen';
	try {
		var seen = parseInt( window.localStorage.getItem( key ) || '0', 10 );
		if ( seen && Date.now() - seen < cfg.days * 86400000 ) {
			return;
		}
	} catch ( e ) {}
	var done = false;
	function check() {
		if ( done ) {
			return;
		}
		var h = document.documentElement.scrollHeight - window.innerHeight;
		if ( h <= 0 || ( window.scrollY / h ) * 100 < cfg.scroll ) {
			return;
		}
		var banner = document.querySelector( '[data-tw-cookie]' );
		if ( ( banner && ! banner.hidden ) || document.querySelector( 'dialog[open]' ) ) {
			return;
		}
		done = true;
		window.removeEventListener( 'scroll', onScroll );
		try {
			window.localStorage.setItem( key, String( Date.now() ) );
		} catch ( e ) {}
		var d = document.getElementById( 'tw-modal-trial' );
		if ( d ) {
			d.classList.add( 'is-nudge' );
		}
		window.twOpenModal( 'trial' );
		// Not opened by the visitor: focus the close button, so phones do not pop the keyboard.
		var x = d && d.querySelector( '[data-tw-modal-close]' );
		if ( x ) {
			x.focus();
		}
		if ( window.twTrack ) {
			window.twTrack( 'trial_popup_shown' );
		}
	}
	var ticking = false;
	function onScroll() {
		if ( ! ticking ) {
			ticking = true;
			window.requestAnimationFrame( function () {
				ticking = false;
				check();
			} );
		}
	}
	window.addEventListener( 'scroll', onScroll, { passive: true } );
	document.addEventListener( 'tw:consent', check );
}() );

/* Checkout: put the "Buy with confidence" card under the order summary once WooCommerce
   has drawn the checkout. If the summary column never appears, the card stays below. */
( function () {
	var card = document.querySelector( '[data-tw-checkout-trust]' );
	if ( ! card ) {
		return;
	}
	var tries = 0;
	function place() {
		// Phones: WooCommerce puts the summary above the form, so the card goes after the form.
		if ( window.innerWidth < 782 ) {
			var main = document.querySelector( '.wc-block-checkout__main' );
			if ( main && main.querySelector( '.wc-block-checkout__actions, .wc-block-components-checkout-place-order-button' ) ) {
				main.appendChild( card );
				card.classList.add( 'is-placed' );
				return true;
			}
			return false;
		}
		var summary = document.querySelector( '.wc-block-checkout__sidebar .wp-block-woocommerce-checkout-order-summary-block' );
		if ( summary && summary.querySelector( '.wc-block-components-totals-footer-item, .wc-block-components-order-summary' ) ) {
			summary.parentNode.insertBefore( card, summary.nextSibling );
			card.classList.add( 'is-placed' );
			return true;
		}
		return false;
	}
	if ( place() ) {
		return;
	}
	var mo = new MutationObserver( function () {
		if ( place() || ++tries > 200 ) {
			mo.disconnect();
		}
	} );
	mo.observe( document.body, { childList: true, subtree: true } );
	setTimeout( function () {
		mo.disconnect();
	}, 15000 );
}() );
