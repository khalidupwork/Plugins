/* Credit Market Free Audit – front-end form */
( function () {
	'use strict';

	var cfg = window.CMA_AUDIT || {};
	var i18n = cfg.i18n || {};

	// Progress target (%) reached when each step finishes.
	var STEP_TARGET = { start: 25, mobile: 60, desktop: 90, finalize: 100 };
	var REQUEST_TIMEOUT = 150000;

	function post( data ) {
		var body = new FormData();
		body.append( 'action', cfg.action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
		var timer = controller ? setTimeout( function () { controller.abort(); }, REQUEST_TIMEOUT ) : null;

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
			signal: controller ? controller.signal : undefined
		} )
			.then( function ( res ) {
				return res.json().catch( function () {
					throw new Error( i18n.error );
				} );
			} )
			.then( function ( json ) {
				if ( timer ) { clearTimeout( timer ); }
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || i18n.error );
				}
				return json.data;
			} )
			.catch( function ( err ) {
				if ( timer ) { clearTimeout( timer ); }
				if ( err && err.name === 'AbortError' ) {
					throw new Error( i18n.timeout );
				}
				throw err;
			} );
	}

	function Audit( root ) {
		this.root = root;
		this.form = root.querySelector( '.cma-form' );
		this.formWrap = root.querySelector( '.cma-form-wrap' );
		this.errorBox = root.querySelector( '.cma-error' );
		this.progress = root.querySelector( '.cma-progress' );
		this.bar = root.querySelector( '.cma-progress__bar span' );
		this.text = root.querySelector( '.cma-progress__text' );
		this.pct = root.querySelector( '.cma-progress__pct' );
		this.result = root.querySelector( '.cma-result' );
		this.button = root.querySelector( '.cma-btn' );
		this.value = 0;
		this.creep = null;

		this.form.addEventListener( 'submit', this.onSubmit.bind( this ) );
	}

	Audit.prototype.showError = function ( message ) {
		this.errorBox.textContent = message;
		this.errorBox.hidden = false;
	};

	Audit.prototype.setBusy = function ( busy ) {
		this.button.disabled = busy;
		this.root.classList.toggle( 'is-busy', busy );
		Array.prototype.forEach.call( this.form.elements, function ( el ) {
			if ( el.type !== 'hidden' ) { el.readOnly = busy; }
		} );
	};

	Audit.prototype.setProgress = function ( value ) {
		this.value = Math.max( this.value, Math.min( 100, value ) );
		this.bar.style.width = this.value + '%';
		this.pct.textContent = Math.round( this.value ) + '%';
	};

	// Slowly move the bar toward (but never reach) the step's target while waiting.
	Audit.prototype.startStep = function ( step ) {
		var self = this;
		var target = STEP_TARGET[ step ] - 2;
		this.text.textContent = i18n[ step ] || '';

		Array.prototype.forEach.call( this.root.querySelectorAll( '.cma-progress__steps li' ), function ( li ) {
			var s = li.getAttribute( 'data-step' );
			li.classList.toggle( 'is-active', s === step );
		} );

		clearInterval( this.creep );
		this.creep = setInterval( function () {
			var remaining = target - self.value;
			if ( remaining > 0.2 ) {
				self.setProgress( self.value + remaining * 0.04 );
			}
		}, 400 );
	};

	Audit.prototype.finishStep = function ( step ) {
		clearInterval( this.creep );
		this.setProgress( STEP_TARGET[ step ] );
		var li = this.root.querySelector( '.cma-progress__steps li[data-step="' + step + '"]' );
		if ( li ) {
			li.classList.remove( 'is-active' );
			li.classList.add( 'is-done' );
		}
	};

	Audit.prototype.validate = function () {
		var email = this.form.elements.email.value.trim();
		var url = this.form.elements.url.value.trim();
		var consent = this.form.elements.consent;

		if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
			this.form.elements.email.focus();
			return i18n.invalidEmail;
		}
		if ( ! /^(https?:\/\/)?[^\s\/]+\.[^\s]{2,}/i.test( url ) ) {
			this.form.elements.url.focus();
			return i18n.invalidUrl;
		}
		if ( consent && ! consent.checked ) {
			return i18n.consent;
		}
		return '';
	};

	Audit.prototype.onSubmit = function ( e ) {
		e.preventDefault();
		var self = this;
		this.errorBox.hidden = true;

		var invalid = this.validate();
		if ( invalid ) {
			this.showError( invalid );
			return;
		}

		var f = this.form.elements;
		var payload = {
			step: 'start',
			name: f.name ? f.name.value.trim() : '',
			email: f.email.value.trim(),
			url: f.url.value.trim(),
			cma_hp: f.cma_hp ? f.cma_hp.value : ''
		};
		if ( f.consent_shown ) {
			payload.consent_shown = '1';
			payload.consent = f.consent && f.consent.checked ? '1' : '';
		}

		this.value = 0;
		this.setProgress( 0 );
		Array.prototype.forEach.call( this.root.querySelectorAll( '.cma-progress__steps li' ), function ( li ) {
			li.classList.remove( 'is-done', 'is-active' );
		} );
		this.setBusy( true );
		this.progress.hidden = false;
		this.startStep( 'start' );

		var run = function ( step, data ) {
			return post( data ).then( function ( res ) {
				self.finishStep( step );
				if ( res.next ) {
					self.startStep( res.next );
					return run( res.next, { step: res.next, token: res.token } );
				}
				return res;
			} );
		};

		run( 'start', payload )
			.then( function ( res ) {
				self.showResult( res );
			} )
			.catch( function ( err ) {
				clearInterval( self.creep );
				self.progress.hidden = true;
				self.showError( err.message || i18n.error );
			} )
			.then( function () {
				self.setBusy( false );
			} );
	};

	Audit.prototype.showResult = function ( res ) {
		var self = this;
		this.text.textContent = i18n.done || '';

		var msg = this.result.querySelector( '.cma-result__msg' );
		var actions = this.result.querySelector( '.cma-result__actions' );
		var report = this.result.querySelector( '.cma-result__report' );

		msg.innerHTML = '';
		var strong = document.createElement( 'strong' );
		strong.textContent = i18n.done || '';
		msg.appendChild( strong );
		var note = document.createElement( 'span' );
		note.textContent = ' ' + ( res.email_sent ? ( i18n.emailSent || '' ).replace( '%s', res.email ) : i18n.emailFail || '' );
		msg.appendChild( note );

		actions.innerHTML = '';
		actions.appendChild( this.button_( i18n.download, res.download_url, 'primary', false ) );
		actions.appendChild( this.button_( i18n.pdf, res.print_url, 'secondary', true ) );
		actions.appendChild( this.button_( i18n.view, res.view_url, 'secondary', true ) );

		var again = document.createElement( 'button' );
		again.type = 'button';
		again.className = 'cma-action cma-action--link';
		again.textContent = i18n.again || '';
		again.addEventListener( 'click', function () {
			self.reset();
		} );
		actions.appendChild( again );

		// Server-rendered report; every value is escaped in PHP.
		report.innerHTML = res.html;

		this.formWrap.hidden = true;
		this.result.hidden = false;
		if ( this.result.scrollIntoView ) {
			this.result.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	};

	Audit.prototype.button_ = function ( label, href, kind, newTab ) {
		var a = document.createElement( 'a' );
		a.className = 'cma-action cma-action--' + kind;
		a.href = href;
		a.textContent = label;
		if ( newTab ) {
			a.target = '_blank';
			a.rel = 'noopener';
		}
		return a;
	};

	Audit.prototype.reset = function () {
		this.result.hidden = true;
		this.result.querySelector( '.cma-result__report' ).innerHTML = '';
		this.progress.hidden = true;
		this.formWrap.hidden = false;
		this.form.elements.url.value = '';
		this.form.elements.url.focus();
	};

	function boot( scope ) {
		Array.prototype.forEach.call( ( scope || document ).querySelectorAll( '.cma-audit' ), function ( el ) {
			if ( ! el.cmaAudit ) {
				el.cmaAudit = new Audit( el );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { boot(); } );
	} else {
		boot();
	}

	// Elementor editor / popups render widgets after page load.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/cma-free-audit.default', function ( $scope ) {
				boot( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
			} );
		}
	} );

	window.CMAAuditBoot = boot;
}() );
