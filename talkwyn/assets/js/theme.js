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
	document.addEventListener( 'click', function ( e ) {
		var el = e.target.closest( '[data-tw-event]' );
		if ( el ) {
			track( el.getAttribute( 'data-tw-event' ), { location: el.getAttribute( 'data-tw-location' ) || '' } );
		}
	} );

	/* ---------- Theme toggle (light / dark / system) ---------- */
	var labels = { system: 'Theme: system', light: 'Theme: light', dark: 'Theme: dark' };
	function currentMode() {
		try {
			return localStorage.getItem( 'tw-theme' ) || 'system';
		} catch ( e ) {
			return 'system';
		}
	}
	function applyMode( mode ) {
		if ( mode === 'light' || mode === 'dark' ) {
			doc.setAttribute( 'data-theme', mode );
		} else {
			doc.removeAttribute( 'data-theme' );
		}
		document.querySelectorAll( '[data-tw-theme-toggle]' ).forEach( function ( btn ) {
			btn.setAttribute( 'data-mode', mode );
			btn.setAttribute( 'title', labels[ mode ] );
			var label = btn.querySelector( '[data-tw-theme-label]' );
			if ( label ) {
				label.textContent = labels[ mode ];
			}
		} );
	}
	applyMode( currentMode() );
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-tw-theme-toggle]' );
		if ( ! btn ) {
			return;
		}
		var order = [ 'system', 'light', 'dark' ];
		var next = order[ ( order.indexOf( currentMode() ) + 1 ) % order.length ];
		try {
			if ( next === 'system' ) {
				localStorage.removeItem( 'tw-theme' );
			} else {
				localStorage.setItem( 'tw-theme', next );
			}
		} catch ( err ) {}
		applyMode( next );
	} );

	/* ---------- Hero word rotator ---------- */
	document.querySelectorAll( '[data-tw-rotator]' ).forEach( function ( rotator ) {
		var words = rotator.querySelectorAll( '.tw-rotator__word' );
		if ( words.length < 2 || reduceMotion || window.innerWidth < 782 ) {
			return;
		}
		var i = 0;
		var timer = setInterval( function () {
			words[ i ].classList.remove( 'is-active' );
			i = ( i + 1 ) % words.length;
			words[ i ].classList.add( 'is-active' );
		}, 3000 );
		// Pause when the hero is not visible.
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				if ( ! entries[ 0 ].isIntersecting ) {
					clearInterval( timer );
				}
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

	/* ---------- Reveal on scroll (one gentle moment per section) ---------- */
	var reveals = document.querySelectorAll( '.tw-reveal' );
	if ( reveals.length ) {
		if ( reduceMotion || ! ( 'IntersectionObserver' in window ) ) {
			reveals.forEach( function ( el ) {
				el.classList.add( 'is-visible' );
			} );
		} else {
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'is-visible' );
						io.unobserve( entry.target );
					}
				} );
			}, { rootMargin: '0px 0px -10% 0px' } );
			reveals.forEach( function ( el ) {
				io.observe( el );
			} );
		}
	}

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
