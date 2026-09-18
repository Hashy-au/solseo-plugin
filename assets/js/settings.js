/**
 * The small live bits on the settings screens.
 *
 * Two previews and a copy button. Neither preview asks the server for
 * anything: the page is handed the parts either side of what you type, and
 * joins them back together as you type. Text nodes only, never HTML.
 */
( function () {
	'use strict';

	var strings = ( window.solseoSettings && window.solseoSettings.strings ) || {};

	/**
	 * Whether a set of rules asks every crawler off the whole site.
	 *
	 * Read from what is in the box rather than from which button was pressed,
	 * so typing it by hand warns too.
	 *
	 * @param {string} text The composed file.
	 * @return {boolean} True when the site is being hidden.
	 */
	function hidesEverything( text ) {
		var groups = text.split( /^user-agent\s*:/im );
		var i;
		var group;

		for ( i = 1; i < groups.length; i++ ) {
			group = groups[ i ];

			if ( ! /^\s*\*/.test( group ) ) {
				continue;
			}

			if ( /^\s*disallow\s*:\s*\/\s*$/im.test( group ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Wire the robots.txt screen up.
	 */
	function robots() {
		var box = document.getElementById( 'solseo-robots-rules' );
		var preview = document.getElementById( 'solseo-robots-preview' );
		var danger = document.getElementById( 'solseo-robots-danger' );
		var presets = document.querySelectorAll( '.solseo-preset-apply' );
		var waiting = null;
		var i;

		if ( ! box ) {
			return;
		}

		/**
		 * Show the danger band from what is in the box.
		 *
		 * Read from the typed rules rather than from which button was pressed,
		 * so typing a disallow-all by hand warns as well.
		 */
		function warn() {
			if ( danger ) {
				danger.hidden = ! hidesEverything( box.value );
			}
		}

		/**
		 * Ask the server what this would actually serve.
		 *
		 * The composing is not the joining: rules meant for every crawler are
		 * folded into the group WordPress already wrote. Doing that here as
		 * well would be a second copy of it, and the two would part company.
		 * So the one that serves the file answers, and this waits.
		 */
		function paint() {
			if ( ! preview || ! window.wp || ! wp.apiFetch ) {
				return;
			}

			window.clearTimeout( waiting );

			waiting = window.setTimeout( function () {
				wp.apiFetch( {
					path: '/solseo/v1/robots-preview',
					method: 'POST',
					data: { rules: box.value }
				} )
					.then( function ( answer ) {
						preview.textContent = answer.robots;
						preview.classList.remove( 'is-stale' );
					} )
					.catch( function () {
						// Keep the last good answer and say it is behind.
						preview.classList.add( 'is-stale' );
					} );
			}, 400 );

			preview.classList.add( 'is-stale' );
		}

		box.addEventListener( 'input', function () {
			warn();
			paint();
		} );

		for ( i = 0; i < presets.length; i++ ) {
			presets[ i ].addEventListener( 'click', function ( event ) {
				event.preventDefault();

				box.value = this.getAttribute( 'data-solseo-preset' );
				box.focus();
				warn();
				paint();
			} );
		}

		warn();
	}

	/**
	 * Wire the breadcrumb preview up.
	 */
	function breadcrumbs() {
		var trail = document.querySelector( '.solseo-preview .solseo-breadcrumbs' );
		var panel = document.querySelector( '.solseo-preview' );
		var prefix = document.getElementById( 'solseo-crumb-prefix' );
		var home = document.getElementById( 'solseo-crumb-home' );
		var sep = document.getElementById( 'solseo-crumb-sep' );
		var enabled = document.querySelector( '[name="solseo[breadcrumbs_enabled]"]' );

		if ( ! trail ) {
			return;
		}

		/**
		 * Redraw the trail from the three fields.
		 */
		function paint() {
			var prefixNode = trail.querySelector( '.solseo-breadcrumb-prefix' );
			var first = trail.querySelector( 'a' );
			var seps = trail.querySelectorAll( '.solseo-breadcrumb-separator' );
			var i;

			if ( prefixNode && prefix ) {
				prefixNode.textContent = prefix.value;
				prefixNode.hidden = prefix.value === '';
			}

			if ( first && home ) {
				first.textContent = home.value || strings.home || 'Home';
			}

			if ( sep ) {
				for ( i = 0; i < seps.length; i++ ) {
					seps[ i ].textContent = ' ' + ( sep.value || '/' ) + ' ';
				}
			}

			if ( panel && enabled ) {
				panel.classList.toggle( 'solseo-preview-off', ! enabled.checked );
			}
		}

		if ( prefix ) {
			prefix.addEventListener( 'input', paint );
		}

		if ( home ) {
			home.addEventListener( 'input', paint );
		}

		if ( sep ) {
			sep.addEventListener( 'input', paint );
		}

		if ( enabled ) {
			enabled.addEventListener( 'change', paint );
		}

		paint();
	}

	/**
	 * Copy a snippet to the clipboard, with a fallback for plain http.
	 */
	function copiers() {
		var buttons = document.querySelectorAll( '.solseo-copy' );
		var i;

		/**
		 * Say it worked, then go back to saying what it does.
		 *
		 * @param {Element} button The button pressed.
		 */
		function said( button ) {
			var was = button.textContent;

			button.textContent = strings.copied || 'Copied';

			window.setTimeout( function () {
				button.textContent = was;
			}, 2000 );
		}

		/**
		 * Put text on the clipboard the old way.
		 *
		 * @param {string} text What to copy.
		 */
		function fallback( text ) {
			var field = document.createElement( 'textarea' );

			field.value = text;
			field.setAttribute( 'readonly', 'readonly' );
			field.style.position = 'absolute';
			field.style.left = '-9999px';

			document.body.appendChild( field );
			field.select();

			try {
				document.execCommand( 'copy' );
			} catch ( e ) {
				// Nothing useful to do: the text is selected either way.
			}

			document.body.removeChild( field );
		}

		for ( i = 0; i < buttons.length; i++ ) {
			buttons[ i ].addEventListener( 'click', function ( event ) {
				var text = this.getAttribute( 'data-copy' );
				var button = this;

				event.preventDefault();

				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( text ).then( function () {
						said( button );
					} );

					return;
				}

				fallback( text );
				said( button );
			} );
		}
	}

	/*
	 * The confirm on a form carrying data-solseo-confirm is bound once, in
	 * jobs.js, which loads on every screen that has one. Binding it here as
	 * well would ask the same question twice.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		robots();
		breadcrumbs();
		copiers();
	} );
}() );
