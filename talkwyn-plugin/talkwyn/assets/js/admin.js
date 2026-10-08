/* Talkwyn admin. */
jQuery( function ( $ ) {
	'use strict';
	var A = window.TalkwynAdmin || {};
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	/* Site scan */
	var running = false;
	var total = 0;
	function status( text ) {
		$( '#twa-scan-status' ).text( text );
	}
	function bar( pct ) {
		$( '.twa-progress span' ).css( 'width', pct + '%' );
	}
	function batch( offset ) {
		$.post( A.ajax, { action: 'talkwyn_index_batch', nonce: A.nonce, offset: offset } ).done( function ( r ) {
			if ( ! r || ! r.success ) {
				running = false;
				status( __( 'Scan failed. Please try again.', 'talkwyn' ) );
				return;
			}
			var d = r.data;
			var pct = total ? Math.min( 100, Math.round( ( d.next_offset / total ) * 100 ) ) : 100;
			bar( d.done ? 100 : pct );
			if ( d.done ) {
				running = false;
				$( '#twa-scan' ).prop( 'disabled', false );
				/* translators: %s: number of chunks */
				status( sprintf( __( 'Scan complete. %s chunks indexed.', 'talkwyn' ), d.chunks ) );
			} else {
				/* translators: 1: chunks, 2: percent */
				status( sprintf( __( '%1$s chunks indexed, %2$s%%', 'talkwyn' ), d.chunks, pct ) );
				batch( d.next_offset );
			}
		} ).fail( function () {
			running = false;
			$( '#twa-scan' ).prop( 'disabled', false );
			status( __( 'Scan failed. Please try again.', 'talkwyn' ) );
		} );
	}
	$( '#twa-scan' ).on( 'click', function () {
		if ( running ) {
			return;
		}
		running = true;
		$( this ).prop( 'disabled', true );
		bar( 2 );
		status( __( 'Preparing the scan', 'talkwyn' ) );
		$.post( A.ajax, { action: 'talkwyn_index_start', nonce: A.nonce } ).done( function ( r ) {
			total = r && r.success ? r.data.total : 0;
			batch( 0 );
		} ).fail( function () {
			running = false;
			$( '#twa-scan' ).prop( 'disabled', false );
			status( __( 'Could not start the scan.', 'talkwyn' ) );
		} );
	} );
	$( '#twa-clear' ).on( 'click', function () {
		if ( ! window.confirm( __( 'Remove all scanned site knowledge?', 'talkwyn' ) ) ) {
			return;
		}
		$.post( A.ajax, { action: 'talkwyn_clear_index', nonce: A.nonce } ).done( function ( r ) {
			bar( 0 );
			/* translators: %s: number of chunks */
			status( sprintf( __( '%s chunks indexed', 'talkwyn' ), r && r.data ? r.data.chunks : 0 ) );
		} );
	} );

	/* Providers */
	function fields( box ) {
		var out = {};
		box.find( '[name^="talkwyn["]' ).each( function () {
			var m = this.name.match( /^talkwyn\[([^\]]+)\]$/ );
			if ( m ) {
				out[ m[ 1 ] ] = $( this ).val();
			}
		} );
		return out;
	}
	$( document ).on( 'click', '.twa-test', function () {
		var btn = $( this );
		var box = btn.closest( '[data-provider]' );
		var out = box.find( '.twa-test-status' );
		btn.prop( 'disabled', true );
		out.removeClass( 'is-good is-bad' ).text( __( 'Testing', 'talkwyn' ) );
		$.post( A.ajax, { action: 'talkwyn_test_provider', nonce: A.nonce, provider: box.data( 'provider' ), fields: fields( box ) } ).done( function ( r ) {
			out.addClass( 'is-good' ).text( r.data.message );
		} ).fail( function ( x ) {
			out.addClass( 'is-bad' ).text( ( x.responseJSON && x.responseJSON.data && x.responseJSON.data.message ) || __( 'Connection failed.', 'talkwyn' ) );
		} ).always( function () {
			btn.prop( 'disabled', false );
		} );
	} );
	$( document ).on( 'click', '.twa-load-models', function () {
		var box = $( this ).closest( '[data-provider]' );
		var id = box.data( 'provider' );
		var out = box.find( '.twa-models-status' );
		out.removeClass( 'is-good is-bad' ).text( __( 'Loading', 'talkwyn' ) );
		$.post( A.ajax, { action: 'talkwyn_models', nonce: A.nonce, provider: id, fields: fields( box ) } ).done( function ( r ) {
			var list = $( '#twa-models-' + id ).empty();
			r.data.models.forEach( function ( m ) {
				list.append( $( '<option>' ).attr( 'value', m ) );
			} );
			/* translators: %s: number of models */
			out.addClass( 'is-good' ).text( sprintf( __( '%s models. Click the model field to pick one, or type your own.', 'talkwyn' ), r.data.models.length ) );
		} ).fail( function ( x ) {
			out.addClass( 'is-bad' ).text( ( x.responseJSON && x.responseJSON.data && x.responseJSON.data.message ) || __( 'Could not load models.', 'talkwyn' ) );
		} );
	} );

	/* Smart Contrast preview (same math as the server) */
	function lum( hex ) {
		hex = hex.replace( '#', '' );
		if ( hex.length === 3 ) {
			hex = hex.replace( /(.)/g, '$1$1' );
		}
		var c = [ 0, 2, 4 ].map( function ( i ) {
			var v = parseInt( hex.substr( i, 2 ), 16 ) / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * c[ 0 ] + 0.7152 * c[ 1 ] + 0.0722 * c[ 2 ];
	}
	function ratio( a, b ) {
		var x = lum( a );
		var y = lum( b );
		return ( Math.max( x, y ) + 0.05 ) / ( Math.min( x, y ) + 0.05 );
	}
	$( '.twa-color' ).on( 'input change', function () {
		var color = $( this ).val();
		var box = $( '.twa-contrast' );
		var ink = box.data( 'ink' ) || '#1A0F12';
		var white = ratio( color, '#FFFFFF' );
		var dark = ratio( color, ink );
		var on = white >= dark ? '#FFFFFF' : ink;
		var best = Math.max( white, dark );
		box.find( '.twa-contrast__sample' ).css( { background: color, color: on } );
		box.find( '.twa-contrast__note' ).text(
			/* translators: 1: text colour, 2: contrast ratio */
			sprintf( __( 'Smart Contrast uses %1$s text on this colour (contrast %2$s:1).', 'talkwyn' ), on === '#FFFFFF' ? __( 'white', 'talkwyn' ) : __( 'dark', 'talkwyn' ), best.toFixed( 1 ) )
		);
		box.find( '.twa-contrast__warn' ).prop( 'hidden', best >= 4.5 );
		$( '.twa-preview__chat' ).each( function () {
			this.style.setProperty( '--twc-brand', color );
			this.style.setProperty( '--twc-on-brand', on );
		} );
	} );

	/* Avatar picker */
	var frame;
	$( '#twa-avatar-media' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( ! frame ) {
			frame = wp.media( { title: __( 'Choose an avatar', 'talkwyn' ), button: { text: __( 'Use this image', 'talkwyn' ) }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				$( '#twa-avatar_url' ).val( a.url );
				$( '#twa-avatar_type' ).val( 'custom' ).trigger( 'change' );
			} );
		}
		frame.open();
	} );

	/* Provider fallback order: drag or use the arrows */
	var order = $( '[data-twa-order]' );
	function syncOrder() {
		var ids = order.children( 'li' ).map( function () {
			return $( this ).data( 'id' );
		} ).get();
		$( '#twa-provider_order' ).val( ids.join( ',' ) );
	}
	if ( order.length ) {
		var dragging = null;
		order.on( 'dragstart', 'li', function ( e ) {
			dragging = this;
			$( this ).addClass( 'is-dragging' );
			e.originalEvent.dataTransfer.effectAllowed = 'move';
		} ).on( 'dragend', 'li', function () {
			$( this ).removeClass( 'is-dragging' );
			dragging = null;
			syncOrder();
		} ).on( 'dragover', 'li', function ( e ) {
			e.preventDefault();
			if ( ! dragging || dragging === this ) {
				return;
			}
			var rect = this.getBoundingClientRect();
			var after = e.originalEvent.clientY > rect.top + rect.height / 2;
			if ( after ) {
				this.after( dragging );
			} else {
				this.before( dragging );
			}
		} );
		order.on( 'click', '[data-move]', function () {
			var li = $( this ).closest( 'li' );
			if ( 'up' === $( this ).data( 'move' ) ) {
				li.prev().before( li );
			} else {
				li.next().after( li );
			}
			syncOrder();
		} );
	}

	/* Live preview on the Appearance tab: redrawn from the form on every change. */
	var preview = $( '.twa-preview__chat' );
	if ( preview.length ) {
		var P = preview.data( 'twa-preview' ) || {};
		var esc = function ( t ) {
			return $( '<div>' ).text( null == t ? '' : String( t ) ).html();
		};
		var get = function ( key ) {
			var f = $( '[name="talkwyn[' + key + ']"]' ).first();
			if ( ! f.length ) {
				return '';
			}
			return f.is( ':checkbox' ) ? f.is( ':checked' ) : String( f.val() || '' );
		};
		var svg = function ( paths ) {
			return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + paths + '</svg>';
		};
		var ICON = {
			menu: '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
			reset: '<path d="M4 4v6h6"/><path d="M5.5 15a7.5 7.5 0 1 0 .8-7.7L4 10"/>',
			sound: '<path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="M15 9.5a4 4 0 0 1 0 5"/><path d="M17.5 7a7.5 7.5 0 0 1 0 10"/>',
			expand: '<path d="M8 3H3v5"/><path d="M16 3h5v5"/><path d="M8 21H3v-5"/><path d="M16 21h5v-5"/>',
			minimize: '<path d="M5 12h14"/>',
			close: '<path d="m6 6 12 12"/><path d="M18 6 6 18"/>',
			name: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
			transcript: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			language: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18"/>',
			popout: '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
			add_chat: '<path d="M12 5v14"/><path d="M5 12h14"/>',
			copy: '<rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
			up: '<path d="M7 10v10H4V10h3ZM7 19h9.5a2 2 0 0 0 1.9-1.4l1.4-5A2 2 0 0 0 17.9 10H14l.6-3.2A2.3 2.3 0 0 0 12.4 4L7 10"/>',
			down: '<path d="M7 14V4H4v10h3ZM7 5h9.5a2 2 0 0 1 1.9 1.4l1.4 5A2 2 0 0 1 17.9 14H14l.6 3.2a2.3 2.3 0 0 1-2.2 2.8L7 14"/>'
		};
		var icons = P.icons || {};
		var now = ( function () {
			try {
				return new Intl.DateTimeFormat( undefined, { hour: 'numeric', minute: '2-digit' } ).format( new Date() );
			} catch ( e ) {
				return '';
			}
		}() );

		var renderPreview = function () {
			var name = get( 'bot_name' ) || 'Talkwyn';
			var menuOn = get( 'widget_menu' );

			// Header: avatar, name, status, buttons.
			var type = get( 'avatar_type' );
			var url = $.trim( get( 'avatar_url' ) );
			var avatar = preview.find( '.twc-avatar' ).empty();
			if ( 'custom' === type && url ) {
				avatar.append( $( '<img alt="" width="36" height="36">' ).attr( 'src', url ) );
			} else if ( 'initials' === type ) {
				avatar.html( '<span class="twc-avatar__text">' + esc( ( $.trim( name ).charAt( 0 ) || 'T' ).toUpperCase() ) + '</span>' );
			} else {
				avatar.html( icons[ 'chat' === type ? 'chat_dots' : ( 'headset' === type ? 'headset' : 'talkwyn' ) ] || '' );
			}
			preview.find( '.twc-name' ).text( name );
			preview.find( '.twc-status' ).text( get( 'online_label' ) );
			var tools = '';
			var btn = function ( cls, label, icon ) {
				return '<span class="twc-head-btn ' + cls + '" title="' + esc( label ) + '">' + svg( icon ) + '</span>';
			};
			if ( menuOn ) {
				tools += '<button type="button" class="twc-head-btn twc-menu-btn" aria-expanded="false" aria-label="' + esc( P.chatMenu ) + '">' + svg( ICON.menu ) + '</button>';
			} else {
				if ( get( 'show_reset_button' ) ) {
					tools += btn( 'twc-reset', get( 'reset_label' ), ICON.reset );
				}
				if ( get( 'show_sound_button' ) ) {
					tools += btn( 'twc-sound', get( 'sound_label' ), ICON.sound );
				}
			}
			if ( get( 'full_screen_enabled' ) ) {
				tools += btn( 'twc-expand', get( 'expand_label' ), ICON.expand );
			}
			if ( get( 'show_minimize_button' ) ) {
				tools += btn( 'twc-minimize', get( 'minimize_label' ), ICON.minimize );
			}
			tools += btn( 'twc-close', get( 'close_label' ), ICON.close );
			preview.find( '.twc-tools' ).html( tools );

			// Menu (opens from the three dots).
			var items = [ [ 'name', P.changeName ] ];
			if ( get( 'transcript_enabled' ) ) {
				items.push( [ 'transcript', P.transcript ] );
			}
			if ( get( 'show_sound_button' ) ) {
				items.push( [ 'sound', get( 'sound_label' ) ] );
			}
			if ( get( 'language_menu' ) ) {
				items.push( [ 'language', P.language ] );
			}
			items.push( [ 'popout', P.popout ] );
			if ( get( 'show_reset_button' ) ) {
				items.push( [ 'reset', get( 'reset_label' ) ] );
			}
			if ( get( 'show_badge' ) ) {
				items.push( [ 'add_chat', P.addChat ] );
			}
			var menu = preview.find( '.twc-menu' );
			menu.html( items.map( function ( it ) {
				return '<span class="twc-menu__item" data-id="' + it[ 0 ] + '">' + svg( ICON[ it[ 0 ] ] ) + '<span>' + esc( it[ 1 ] ) + '</span></span>';
			} ).join( '' ) );
			if ( ! menuOn ) {
				menu.prop( 'hidden', true );
			}

			// Messages.
			var meta = '';
			if ( get( 'show_timestamps' ) && now ) {
				meta += '<time class="twc-time">' + esc( now ) + '</time>';
			}
			if ( get( 'show_message_tools' ) || get( 'feedback_enabled' ) ) {
				meta += '<div class="twc-msg-tools">';
				if ( get( 'show_message_tools' ) ) {
					meta += '<span class="twc-tool" title="' + esc( get( 'copy_label' ) ) + '">' + svg( ICON.copy ) + '</span>';
				}
				if ( get( 'feedback_enabled' ) ) {
					meta += '<span class="twc-tool twc-fb" title="' + esc( get( 'helpful_label' ) ) + '">' + svg( ICON.up ) + '</span><span class="twc-tool twc-fb" title="' + esc( get( 'not_helpful_label' ) ) + '">' + svg( ICON.down ) + '</span>';
				}
				meta += '</div>';
			}
			var answer = '<div class="twc-msg twc-msg--bot"><div class="twc-bubble">' + esc( P.answer ) + '</div>';
			if ( get( 'show_sources' ) ) {
				answer += '<div class="twc-sources"><span class="twc-sources__label">' + esc( get( 'sources_label' ) ) + '</span><a>' + esc( P.source ) + '</a></div>';
			}
			answer += meta ? '<div class="twc-meta">' + meta + '</div>' : '';
			answer += '</div>';
			var typingText = get( 'typing_label' ).split( '{bot}' ).join( name );
			preview.find( '.twc-messages' ).html(
				'<div class="twc-msg twc-msg--bot"><div class="twc-bubble">' + esc( get( 'welcome_message' ) ) + '</div></div>' +
				'<div class="twc-msg twc-msg--user"><div class="twc-bubble">' + esc( P.question ) + '</div></div>' +
				answer +
				'<div class="twc-msg twc-msg--bot twc-typing"><div class="twc-bubble"><span class="twc-dots" aria-hidden="true"><i></i><i></i><i></i></span>' + ( typingText ? '<span class="twc-typing__label">' + esc( typingText ) + '</span>' : '' ) + '</div></div>'
			);
			preview.find( '.twc-suggestions' ).html( get( 'suggested_questions' ).split( /\r?\n/ ).filter( function ( l ) {
				return $.trim( l );
			} ).slice( 0, 4 ).map( function ( l ) {
				return '<span class="twc-chip">' + esc( $.trim( l ) ) + '</span>';
			} ).join( '' ) );
			preview.find( '.twa-preview__ph' ).text( get( 'placeholder' ) );

			// Footer: privacy note and the optional badge.
			var foot = P.privacy ? '<p class="twc-privacy">' + esc( P.privacy ) + '</p>' : '';
			if ( get( 'show_badge' ) ) {
				foot += '<span class="twc-badge">' + '<svg viewBox="0 0 32 32" aria-hidden="true"><path d="M8 4.5h16A5.5 5.5 0 0 1 29.5 10v10A5.5 5.5 0 0 1 24 25.5H13.5L7.5 30v-4.6A5.5 5.5 0 0 1 2.5 20V10A5.5 5.5 0 0 1 8 4.5Z"/></svg><span>' + esc( P.poweredBy ) + '</span></span>';
			}
			preview.find( '.twc-foot' ).html( foot );

			// Launcher, position, header style, pulse.
			var label = $.trim( get( 'launcher_label' ) );
			preview.find( '.twc-launcher-label' ).text( label ).prop( 'hidden', ! label );
			preview.find( '.twc-launcher' ).html( icons[ get( 'launcher_icon' ) ] || icons.talkwyn || '' );
			preview.toggleClass( 'twc--left', 'left' === get( 'position' ) );
			preview.toggleClass( 'twc--gradient', 'gradient' === get( 'header_style' ) );
			var pulse = !! get( 'launcher_pulse' );
			if ( pulse !== preview.hasClass( 'twc--pulse' ) ) {
				preview.toggleClass( 'twc--pulse', pulse );
			}
			var w = get( 'chat_width' );
			var h = get( 'chat_height' );
			$( '.twa-preview__size' ).text( w && h && P.size ? P.size.replace( '%1$s', w ).replace( '%2$s', h ) : '' );
		};

		preview.on( 'click', '.twc-menu-btn', function () {
			var menu = preview.find( '.twc-menu' );
			menu.prop( 'hidden', ! menu.prop( 'hidden' ) );
			$( this ).attr( 'aria-expanded', menu.prop( 'hidden' ) ? 'false' : 'true' );
		} );
		preview.closest( 'form' ).on( 'input change', renderPreview );
		$( document ).on( 'input change', '#twa-avatar_url, #twa-avatar_type', renderPreview );
		renderPreview();
	}

	/* Save tab shows a dot once something on the form changed. */
	$( '.twa-savebar' ).each( function () {
		var bar = $( this );
		bar.closest( 'form' ).one( 'input change', function () {
			bar.addClass( 'is-dirty' );
		} );
	} );

	/* Copy email */
	$( document ).on( 'click', '.twa-copy', function () {
		var btn = $( this );
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( btn.data( 'copy' ) ).then( function () {
				btn.addClass( 'is-good' );
				setTimeout( function () {
					btn.removeClass( 'is-good' );
				}, 1200 );
			} );
		}
	} );

	/* Review: mark as done when the visitor follows the link */
	$( document ).on( 'click', '[data-twa-review]', function () {
		var href = $( this ).data( 'twa-href' );
		if ( href ) {
			fetch( href, { credentials: 'same-origin' } );
		}
		$( this ).closest( '.twa-review' ).slideUp( 150 );
	} );
} );
