/**
 * The media picker used by the image fields.
 */
( function () {
	'use strict';

	function attach( field ) {
		var preview = field.querySelector( '.solseo-image-preview' );
		var input = field.querySelector( '.solseo-image-id' );
		var choose = field.querySelector( '.solseo-image-choose' );
		var clear = field.querySelector( '.solseo-image-clear' );
		var frame = null;

		choose.addEventListener( 'click', function () {
			if ( ! frame ) {
				frame = window.wp.media( { multiple: false, library: { type: 'image' } } );

				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					var thumbnail = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;

					input.value = image.id;
					preview.src = thumbnail;
					preview.classList.remove( 'hidden' );
					clear.classList.remove( 'hidden' );
				} );
			}

			frame.open();
		} );

		clear.addEventListener( 'click', function () {
			input.value = 0;
			preview.src = '';
			preview.classList.add( 'hidden' );
			clear.classList.add( 'hidden' );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		document.querySelectorAll( '.solseo-image-field' ).forEach( attach );
	} );
} )();
