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

	/**
	 * The duplicate phrase warning and the accessibility findings.
	 *
	 * The keys read here are the keys the score route sends, and there is a
	 * test on the PHP side asserting that it keeps sending them, because there
	 * is no JavaScript test runner in this plugin and a panel reading a key
	 * nobody sends reports an all clear on a page with nine things wrong with
	 * it. That happened once already: D-80.8.
	 *
	 * @param {Object} result What the score route answered.
	 */
	function paintNotes( result ) {
		var target = box.querySelector( '[data-solseo-notes]' );

		if ( ! target ) {
			return;
		}

		target.textContent = '';

		var dupes = result.duplicates || {};
		var competing = dupes.competing || [];
		var alongside = dupes.alongside || [];
		var names = [];
		var line;

		if ( competing.length || alongside.length ) {
			( competing.length ? competing : alongside ).forEach( function ( one ) {
				names.push( one.title );
			} );

			line = document.createElement( 'p' );
			line.className = competing.length ? 'solseo-note solseo-note-warn' : 'solseo-note';
			line.textContent = ( competing.length ? strings.duplicateWarn : strings.duplicateFine )
				.replace( '%1$s', dupes.phrase || '' )
				.replace( '%2$s', names.join( ', ' ) );
			target.appendChild( line );
		}

		var findings = result.a11y || [];

		if ( ! findings.length ) {
			return;
		}

		var wrap = document.createElement( 'div' );
		var heading = document.createElement( 'h4' );
		var list = document.createElement( 'ul' );

		wrap.className = 'solseo-check-group';
		heading.textContent = strings.a11yTitle;
		wrap.appendChild( heading );

		findings.forEach( function ( finding ) {
			var item = document.createElement( 'li' );

			item.className = 'solseo-check solseo-status-poor';
			item.appendChild( document.createTextNode( finding.says ) );
			list.appendChild( item );
		} );

		wrap.appendChild( list );

		var note = document.createElement( 'p' );

		note.className = 'description';
		note.textContent = strings.a11yCovers;
		wrap.appendChild( note );

		target.appendChild( wrap );
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
		paintNotes( result );

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

	/**
	 * Fill the Search Console panel, once, the first time it is opened.
	 *
	 * The same route the block editor's panel reads. Asking on the editor
	 * load instead would spend a call on every page somebody opens, for a
	 * panel most of them never look at.
	 */
	var searchConsoleAsked = false;

	function searchConsole() {
		var holder = box.querySelector( '[data-solseo-search-console]' );

		if ( ! holder || searchConsoleAsked ) {
			return;
		}

		searchConsoleAsked = true;

		window.wp.apiFetch( { path: '/solseo/v1/search-console?post_id=' + holder.getAttribute( 'data-post' ) } )
			.then( function ( answer ) {
				holder.textContent = '';

				if ( ! answer || answer.trouble ) {
					holder.appendChild( paragraph( ( answer && answer.trouble ) || strings.gscFailed, 'solseo-note solseo-note-warn' ) );

					return;
				}

				if ( ! answer.has ) {
					holder.appendChild( paragraph( strings.gscNone, 'description' ) );

					return;
				}

				var totals = document.createElement( 'ul' );

				totals.className = 'solseo-plain-list';
				totals.appendChild( figure( answer.clicks, strings.gscClicks ) );
				totals.appendChild( figure( answer.impressions, strings.gscImpressions ) );
				totals.appendChild( figure( answer.position.toFixed( 1 ), strings.gscPosition ) );
				holder.appendChild( totals );

				var heading = document.createElement( 'h4' );

				heading.textContent = strings.gscQueries;
				holder.appendChild( heading );

				if ( ! answer.queries || ! answer.queries.length ) {
					holder.appendChild( paragraph( strings.gscNoQueries, 'description' ) );
				} else {
					var list = document.createElement( 'ul' );

					list.className = 'solseo-plain-list';

					answer.queries.forEach( function ( row ) {
						list.appendChild(
							figure(
								row.query,
								row.clicks + ' / ' + row.impressions + ' / ' + row.position.toFixed( 1 )
							)
						);
					} );

					holder.appendChild( list );
				}

				holder.appendChild(
					paragraph(
						strings.gscRange.replace( '%1$s', answer.from ).replace( '%2$s', answer.to ),
						'description'
					)
				);
			} )
			.catch( function () {
				holder.textContent = '';
				holder.appendChild( paragraph( strings.gscFailed, 'solseo-note solseo-note-warn' ) );
			} );
	}

	function paragraph( text, className ) {
		var node = document.createElement( 'p' );

		node.className = className;
		node.textContent = text;

		return node;
	}

	function figure( strong, rest ) {
		var item = document.createElement( 'li' );
		var bold = document.createElement( 'strong' );

		bold.textContent = String( strong );
		item.appendChild( bold );
		item.appendChild( document.createTextNode( ' ' + rest ) );

		return item;
	}

	box.querySelectorAll( '[data-solseo-tab]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var name = button.getAttribute( 'data-solseo-tab' );

			if ( 'search' === name ) {
				searchConsole();
			}

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
