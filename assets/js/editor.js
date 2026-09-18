/**
 * Scores the post while it is being written.
 */
( function () {
	'use strict';

	var box = document.querySelector( '.solseo-box' );

	if ( ! box || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	var strings = ( window.solseoEditor || {} ).strings || {};
	var timer = null;
	var pending = false;

	function fields() {
		var out = {};

		box.querySelectorAll( '[data-solseo-field]' ).forEach( function ( input ) {
			out[ input.getAttribute( 'data-solseo-field' ) ] = input.value;
		} );

		return out;
	}

	/**
	 * The content of the post, whichever editor is in use.
	 */
	function content() {
		if ( window.wp.data && window.wp.data.select( 'core/editor' ) ) {
			var post = window.wp.data.select( 'core/editor' ).getEditedPostContent();

			if ( typeof post === 'string' ) {
				return post;
			}
		}

		if ( window.tinymce ) {
			var editor = window.tinymce.get( 'content' );

			if ( editor && ! editor.isHidden() ) {
				return editor.getContent();
			}
		}

		var textarea = document.getElementById( 'content' );

		return textarea ? textarea.value : '';
	}

	function slug() {
		var field = document.getElementById( 'post_name' )
			|| document.querySelector( '#editable-post-name-full' );

		if ( field ) {
			return field.value !== undefined ? field.value : field.textContent;
		}

		if ( window.wp.data && window.wp.data.select( 'core/editor' ) ) {
			var edited = window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'slug' );

			if ( edited ) {
				return edited;
			}
		}

		return box.getAttribute( 'data-slug' ) || '';
	}

	function postTitle() {
		if ( window.wp.data && window.wp.data.select( 'core/editor' ) ) {
			return window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';
		}

		var field = document.getElementById( 'title' );

		return field ? field.value : '';
	}

	function paintPreview( values ) {
		var title = box.querySelector( '[data-solseo-preview="title"]' );
		var description = box.querySelector( '[data-solseo-preview="description"]' );

		var heading = values.title || postTitle();

		if ( title && heading ) {
			title.textContent = heading;
		}

		if ( description && values.description ) {
			description.textContent = values.description;
		}
	}

	function paintGauge( name, width, limit ) {
		var gauge = box.querySelector( '[data-solseo-gauge="' + name + '"]' );

		if ( ! gauge ) {
			return;
		}

		var share = Math.min( 100, Math.round( ( width / limit ) * 100 ) );

		gauge.classList.toggle( 'is-over', width > limit );
		gauge.firstElementChild.style.width = share + '%';

		if ( strings.pixels ) {
			gauge.setAttribute( 'title', strings.pixels.replace( '%1$d', width ).replace( '%2$d', limit ) );
		}
	}

	function paintChecks( groups ) {
		var target = box.querySelector( '[data-solseo-checks]' );

		if ( ! target ) {
			return;
		}

		target.textContent = '';

		Object.keys( groups ).forEach( function ( key ) {
			var group = groups[ key ];
			var wrap = document.createElement( 'div' );
			var heading = document.createElement( 'h4' );
			var list = document.createElement( 'ul' );

			wrap.className = 'solseo-check-group';
			heading.textContent = group.label;
			wrap.appendChild( heading );

			group.checks.forEach( function ( check ) {
				var item = document.createElement( 'li' );
				var dot = document.createElement( 'span' );

				item.className = 'solseo-check solseo-status-' + check.status;
				dot.className = 'solseo-dot';

				item.appendChild( dot );
				item.appendChild( document.createTextNode( check.note ) );
				list.appendChild( item );
			} );

			wrap.appendChild( list );
			target.appendChild( wrap );
		} );
	}

	function paint( result, sent ) {
		var dial = box.querySelector( '[data-solseo-dial]' );

		if ( dial ) {
			dial.className = 'solseo-dial solseo-band-' + result.band;
			dial.style.setProperty( '--solseo-dial', result.score );
			dial.querySelector( '.solseo-dial-value' ).textContent = result.score;

			var label = dial.querySelector( '.solseo-dial-label' );

			if ( label ) {
				label.textContent = result.label;
			}
		}

		paintGauge( 'title', result.title.width, result.title.limit );
		paintGauge( 'description', result.description.width, result.description.limit );
		paintChecks( result.groups );

		// Anything else bolted onto the box listens for this rather than
		// reading the editor a second time. `sent` is what was scored.
		box.dispatchEvent( new CustomEvent( 'solseo:scored', { detail: { result: result, sent: sent } } ) );
	}

	function score() {
		if ( pending ) {
			return;
		}

		var values = fields();

		paintPreview( values );
		pending = true;

		var sent = {
			post_id: parseInt( box.getAttribute( 'data-post' ), 10 ),
			title: values.title || postTitle(),
			description: values.description || '',
			keyword: values.keyword || '',
			slug: slug(),
			content: content()
		};

		window.wp.apiFetch( {
			path: '/solseo/v1/score',
			method: 'POST',
			data: sent
		} ).then( function ( result ) {
			pending = false;
			paint( result, sent );
		} ).catch( function () {
			pending = false;
		} );
	}

	function queue() {
		window.clearTimeout( timer );
		timer = window.setTimeout( score, 600 );
	}

	box.querySelectorAll( '[data-solseo-field]' ).forEach( function ( input ) {
		input.addEventListener( 'input', queue );
	} );

	box.querySelectorAll( '[data-solseo-tab]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var name = button.getAttribute( 'data-solseo-tab' );

			box.querySelectorAll( '[data-solseo-tab]' ).forEach( function ( other ) {
				other.classList.toggle( 'is-active', other === button );
			} );

			box.querySelectorAll( '[data-solseo-panel]' ).forEach( function ( panel ) {
				panel.classList.toggle( 'is-active', panel.getAttribute( 'data-solseo-panel' ) === name );
			} );
		} );
	} );

	score();
} )();
