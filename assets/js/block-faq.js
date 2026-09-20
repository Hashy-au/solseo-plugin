/**
 * The questions and answers block.
 *
 * No build step. registerBlockType is called through wp.element.createElement
 * against the handles WordPress already ships, so there is no bundler and no
 * compiled file in the zip.
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.blocks || ! wp.element || ! wp.blockEditor || ! wp.components ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var RichText = wp.blockEditor.RichText;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;

	/**
	 * Which items repeat a question that came before them.
	 *
	 * @param {Array} items The questions and answers.
	 * @return {Object} Index to true, for the repeats.
	 */
	function duplicates( items ) {
		var seen = {};
		var found = {};

		items.forEach( function ( item, index ) {
			var question = ( item.question || '' ).replace( /<[^>]*>/g, '' ).trim().toLowerCase();

			if ( ! question ) {
				return;
			}

			if ( seen[ question ] ) {
				found[ index ] = true;
			}

			seen[ question ] = true;
		} );

		return found;
	}

	/**
	 * One question and its answer.
	 *
	 * @param {Object} props Keys: item, index, repeated, onChange, onRemove.
	 * @return {Object} An element.
	 */
	function Item( props ) {
		var item = props.item;

		return el(
			'div',
			{ className: 'solseo-faq-edit__item' },
			props.repeated
				? el(
					Notice,
					{ status: 'warning', isDismissible: false },
					__( 'This question is already on the page. Two answers to one question is not valid, so only the first is published.', 'solseo' )
				)
				: null,
			el( RichText, {
				tagName: 'p',
				className: 'solseo-faq-edit__question',
				value: item.question || '',
				allowedFormats: [],
				placeholder: __( 'Ask the question the way a customer would', 'solseo' ),
				onChange: function ( value ) {
					props.onChange( { question: value } );
				}
			} ),
			el( RichText, {
				tagName: 'div',
				className: 'solseo-faq-edit__answer',
				value: item.answer || '',
				placeholder: __( 'Answer it in a sentence or two', 'solseo' ),
				onChange: function ( value ) {
					props.onChange( { answer: value } );
				}
			} ),
			el(
				Button,
				{
					variant: 'link',
					isDestructive: true,
					onClick: props.onRemove
				},
				__( 'Remove this question', 'solseo' )
			)
		);
	}

	wp.blocks.registerBlockType( 'solseo/faq', {
		edit: function ( props ) {
			var items = props.attributes.items || [];
			var repeated = duplicates( items );
			var blockProps = useBlockProps( { className: 'solseo-faq-edit' } );

			/**
			 * Replace one item.
			 *
			 * @param {number} index Which one.
			 * @param {Object} patch What changed.
			 */
			function change( index, patch ) {
				var next = items.map( function ( item, at ) {
					return at === index ? Object.assign( {}, item, patch ) : item;
				} );

				props.setAttributes( { items: next } );
			}

			/**
			 * Drop one item.
			 *
			 * @param {number} index Which one.
			 */
			function remove( index ) {
				props.setAttributes( {
					items: items.filter( function ( item, at ) {
						return at !== index;
					} )
				} );
			}

			return el(
				'div',
				blockProps,
				el( RichText, {
					tagName: 'h2',
					className: 'solseo-faq-edit__heading',
					value: props.attributes.heading || '',
					allowedFormats: [],
					placeholder: __( 'Heading, if you want one', 'solseo' ),
					onChange: function ( value ) {
						props.setAttributes( { heading: value } );
					}
				} ),
				items.map( function ( item, index ) {
					return el( Item, {
						key: index,
						item: item,
						index: index,
						repeated: !! repeated[ index ],
						onChange: function ( patch ) {
							change( index, patch );
						},
						onRemove: function () {
							remove( index );
						}
					} );
				} ),
				el(
					Button,
					{
						variant: 'secondary',
						onClick: function () {
							props.setAttributes( {
								items: items.concat( [ { question: '', answer: '' } ] )
							} );
						}
					},
					__( 'Add a question', 'solseo' )
				),
				el(
					'p',
					{ className: 'solseo-faq-edit__note' },
					__( 'These are published as structured data. Google shows questions in search results for some sites and not others, so this is worth doing and is not a promise of anything.', 'solseo' )
				)
			);
		},

		// Rendered in PHP, so nothing is saved into the post but the attributes.
		save: function () {
			return null;
		}
	} );
}() );
