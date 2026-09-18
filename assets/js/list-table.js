/**
 * Editing SEO titles and descriptions straight from the posts list.
 *
 * The fields are already on the page, rendered and escaped by PHP and hidden
 * by CSS. This shows them, collects what changed, and sends only that.
 *
 * The toggle is put into the header cell here rather than printed by PHP,
 * because WordPress wraps a sortable column's label in the sort link, and a
 * button inside that link sorts the table when you press it.
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.apiFetch || ! window.solseoList ) {
		return;
	}

	var strings = window.solseoList.strings || {};
	var editing = false;
	var dirty = false;

	/**
	 * Say something above the table, where WordPress puts its own messages.
	 *
	 * Built here rather than hooked onto admin_notices, because this plugin
	 * prints exactly one admin notice and this is not it.
	 *
	 * @param {string}  text What happened.
	 * @param {boolean} bad  Whether it went wrong.
	 */
	function say( text, bad ) {
		var top = document.querySelector( '.tablenav.top' );
		var box = document.getElementById( 'solseo-bulk-message' );

		if ( ! top ) {
			return;
		}

		if ( ! box ) {
			box = document.createElement( 'div' );
			box.id = 'solseo-bulk-message';
			box.className = 'solseo-bulk-message';
			box.setAttribute( 'aria-live', 'polite' );
			top.parentNode.insertBefore( box, top.nextSibling );
		}

		box.textContent = text;
		box.classList.toggle( 'is-error', !! bad );
	}

	/**
	 * Every row that has fields in it.
	 *
	 * @return {Array} The edit blocks.
	 */
	function rows() {
		return Array.prototype.slice.call( document.querySelectorAll( '.solseo-cell-edit' ) );
	}

	/**
	 * Show or hide the fields.
	 *
	 * @param {boolean} on Whether to show them.
	 */
	function show( on ) {
		var button = document.querySelector( '.solseo-bulk-toggle' );
		var bar = document.getElementById( 'solseo-bulk-bar' );
		var quick = document.querySelectorAll( '.row-actions .inline' );
		var i;

		editing = on;

		rows().forEach( function ( row ) {
			row.hidden = ! on;
		} );

		if ( button ) {
			button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			button.textContent = on ? strings.done : strings.edit;
		}

		if ( bar ) {
			bar.hidden = ! on;
		}

		/*
		 * Quick Edit replaces the whole row, which would throw away anything
		 * typed into our fields in it. It comes back when we are done.
		 */
		for ( i = 0; i < quick.length; i++ ) {
			quick[ i ].style.display = on ? 'none' : '';
		}
	}

	/**
	 * What has been typed into the rows, as the endpoint wants it.
	 *
	 * @return {Array} One entry per editable row.
	 */
	function collect() {
		var out = [];

		rows().forEach( function ( row ) {
			var title = row.querySelector( '[data-solseo-row="title"]' );
			var description = row.querySelector( '[data-solseo-row="description"]' );

			if ( ! title || ! description ) {
				return;
			}

			out.push( {
				id: parseInt( row.getAttribute( 'data-post' ), 10 ),
				title: title.value,
				description: description.value
			} );
		} );

		return out;
	}

	/**
	 * Repaint one row's pill from what came back.
	 *
	 * @param {Object} result One entry from the saved list.
	 */
	function repaint( result ) {
		var row = document.querySelector( '.solseo-cell-edit[data-post="' + result.id + '"]' );
		var cell;
		var pill;
		var band;

		if ( ! row || ! row.parentNode ) {
			return;
		}

		cell = row.parentNode.querySelector( '.solseo-cell' );

		if ( ! cell ) {
			return;
		}

		pill = cell.querySelector( '.solseo-pill' );
		band = cell.querySelector( '.solseo-cell-band' );

		if ( pill ) {
			pill.textContent = String( result.score );
			pill.className = 'solseo-pill solseo-band-' + result.band;
		}

		if ( band ) {
			band.textContent = result.label;
		}

		row.classList.remove( 'is-error' );
		row.classList.add( 'is-saved' );
	}

	/**
	 * Mark a row that could not be saved, keeping what was typed in it.
	 *
	 * @param {Object} result One entry from the failed list.
	 */
	function mark( result ) {
		var row = document.querySelector( '.solseo-cell-edit[data-post="' + result.id + '"]' );

		if ( ! row ) {
			return;
		}

		row.classList.add( 'is-error' );
		row.classList.remove( 'is-saved' );
	}

	/**
	 * Send what changed.
	 */
	function save() {
		var payload = collect();
		var button = document.querySelector( '.solseo-bulk-save' );

		if ( ! payload.length ) {
			say( strings.nothing, false );

			return;
		}

		if ( button ) {
			button.disabled = true;
			button.textContent = strings.saving;
		}

		wp.apiFetch( { path: '/solseo/v1/bulk-meta', method: 'POST', data: { rows: payload } } )
			.then( function ( answer ) {
				var message = ( strings.saved || '%d saved.' ).replace( '%d', answer.saved.length );

				answer.saved.forEach( repaint );
				answer.failed.forEach( mark );

				if ( answer.failed.length ) {
					message += ' ' + ( strings.failed || '%d could not be saved.' ).replace( '%d', answer.failed.length );
				}

				say( message, answer.failed.length > 0 );

				dirty = answer.failed.length > 0;

				if ( button ) {
					button.disabled = false;
					button.textContent = strings.save;
				}
			} )
			.catch( function () {
				say( strings.wrong, true );

				if ( button ) {
					button.disabled = false;
					button.textContent = strings.save;
				}
			} );
	}

	/**
	 * Put the toggle in the header cell and the buttons under the table.
	 */
	function build() {
		var head = document.querySelector( 'thead th.column-solseo_score, thead td.column-solseo_score' );
		var table = document.querySelector( '.wp-list-table' );
		var toggle;
		var bar;

		if ( ! head || ! table || ! rows().length ) {
			return;
		}

		toggle = document.createElement( 'button' );
		toggle.type = 'button';
		toggle.className = 'solseo-bulk-toggle';
		toggle.setAttribute( 'aria-pressed', 'false' );
		toggle.textContent = strings.edit;
		toggle.addEventListener( 'click', function () {
			show( ! editing );
		} );

		head.appendChild( toggle );

		bar = document.createElement( 'div' );
		bar.id = 'solseo-bulk-bar';
		bar.className = 'solseo-bulk-bar';
		bar.hidden = true;

		bar.innerHTML = '<button type="button" class="button button-primary solseo-bulk-save"></button> '
			+ '<button type="button" class="button solseo-bulk-cancel"></button>';

		bar.querySelector( '.solseo-bulk-save' ).textContent = strings.save;
		bar.querySelector( '.solseo-bulk-cancel' ).textContent = strings.cancel;

		bar.querySelector( '.solseo-bulk-save' ).addEventListener( 'click', save );
		bar.querySelector( '.solseo-bulk-cancel' ).addEventListener( 'click', function () {
			dirty = false;
			show( false );
		} );

		table.parentNode.insertBefore( bar, table.nextSibling );

		rows().forEach( function ( row ) {
			row.addEventListener( 'input', function () {
				dirty = true;
			} );
		} );
	}

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( ! editing || ! dirty ) {
			return undefined;
		}

		event.preventDefault();
		event.returnValue = strings.unsaved;

		return strings.unsaved;
	} );

	document.addEventListener( 'DOMContentLoaded', build );
}() );
