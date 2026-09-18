/**
 * The pieces the panels are made of.
 *
 * Published on window.solseo.ui so an add-on's panel looks like the ones that
 * shipped rather than approximating them.
 */
( function () {
	'use strict';

	if ( ! window.solseo || ! window.wp || ! wp.element || ! wp.components ) {
		return;
	}

	var el = wp.element.createElement;
	var data = window.solseo.data || {};
	var strings = data.strings || {};

	/* The same mark the plugin uses in the admin menu, so the two agree. */
	var MARK = 'M10 3.2a1 1 0 0 1 1 1v1.3a1 1 0 0 1-2 0V4.2a1 1 0 0 1 1-1Zm0 11a1 1 0 0 1 1 1v1.3a1 1 0 0 1-2 0v-1.3a1 1 0 0 1 1-1Zm6.8-4.2a1 1 0 0 1-1 1h-1.3a1 1 0 0 1 0-2h1.3a1 1 0 0 1 1 1Zm-11 0a1 1 0 0 1-1 1H3.5a1 1 0 0 1 0-2h1.3a1 1 0 0 1 1 1Zm8.7-4.9a1 1 0 0 1 0 1.4l-.9.9a1 1 0 1 1-1.4-1.4l.9-.9a1 1 0 0 1 1.4 0Zm-7.8 7.8a1 1 0 0 1 0 1.4l-.9.9a1 1 0 0 1-1.4-1.4l.9-.9a1 1 0 0 1 1.4 0Zm7.8 2.3a1 1 0 0 1-1.4 0l-.9-.9a1 1 0 0 1 1.4-1.4l.9.9a1 1 0 0 1 0 1.4ZM6.6 6.6a1 1 0 0 1-1.4 0l-.9-.9a1 1 0 0 1 1.4-1.4l.9.9a1 1 0 0 1 0 1.4ZM10 6.7a3.3 3.3 0 1 1 0 6.6 3.3 3.3 0 0 1 0-6.6Z';

	/**
	 * The plugin's mark.
	 *
	 * @param {Object} props Keys: size.
	 * @return {Object} An element.
	 */
	function Mark( props ) {
		var size = ( props && props.size ) || 14;

		return el(
			'svg',
			{
				className: 'solseo-mark',
				viewBox: '0 0 20 20',
				width: size,
				height: size,
				'aria-hidden': 'true',
				focusable: 'false',
			},
			el( 'path', { fill: 'currentColor', d: MARK } )
		);
	}

	/*
	 * Passed to every control. Unknown props are ignored on 6.4 and these two
	 * keep the console clean from 6.7 on, so one set of calls covers the range.
	 */
	var MODERN = {
		__nextHasNoMarginBottom: true,
		__next40pxDefaultSize: true
	};

	/**
	 * Merge two plain objects, left to right.
	 *
	 * @param {Object} base  Defaults.
	 * @param {Object} extra What overrides them.
	 * @return {Object} A new object.
	 */
	function merge( base, extra ) {
		var out = {};
		var key;

		for ( key in base ) {
			if ( Object.prototype.hasOwnProperty.call( base, key ) ) {
				out[ key ] = base[ key ];
			}
		}

		for ( key in extra ) {
			if ( Object.prototype.hasOwnProperty.call( extra, key ) ) {
				out[ key ] = extra[ key ];
			}
		}

		return out;
	}

	/**
	 * A text field bound to one of our meta keys.
	 *
	 * @param {Object} props Keys: field, label, help, placeholder.
	 * @return {Object} An element.
	 */
	function Text( props ) {
		return el(
			wp.components.TextControl,
			merge( MODERN, {
				label: props.label,
				help: props.help,
				placeholder: props.placeholder,
				value: String( window.solseo.fields.get( props.field ) || '' ),
				onChange: function ( value ) {
					window.solseo.fields.set( props.field, value );
				}
			} )
		);
	}

	/**
	 * A multi-line field bound to one of our meta keys.
	 *
	 * @param {Object} props Keys: field, label, help, rows.
	 * @return {Object} An element.
	 */
	function Area( props ) {
		return el(
			wp.components.TextareaControl,
			merge( MODERN, {
				label: props.label,
				help: props.help,
				rows: props.rows || 3,
				value: String( window.solseo.fields.get( props.field ) || '' ),
				onChange: function ( value ) {
					window.solseo.fields.set( props.field, value );
				}
			} )
		);
	}

	/**
	 * A tick box bound to one of our meta keys.
	 *
	 * @param {Object} props Keys: field, label.
	 * @return {Object} An element.
	 */
	function Tick( props ) {
		return el(
			wp.components.CheckboxControl,
			merge( MODERN, {
				label: props.label,
				checked: !! window.solseo.fields.get( props.field ),
				onChange: function ( value ) {
					window.solseo.fields.set( props.field, value );
				}
			} )
		);
	}

	/**
	 * A tick box that adds or removes one value from a list field.
	 *
	 * @param {Object} props Keys: field, value, label.
	 * @return {Object} An element.
	 */
	function TickInList( props ) {
		var list = window.solseo.fields.get( props.field );

		if ( ! Array.isArray( list ) ) {
			list = [];
		}

		return el(
			wp.components.CheckboxControl,
			merge( MODERN, {
				label: props.label,
				checked: list.indexOf( props.value ) !== -1,
				onChange: function ( on ) {
					var next = list.filter( function ( one ) {
						return one !== props.value;
					} );

					if ( on ) {
						next = next.concat( [ props.value ] );
					}

					window.solseo.fields.set( props.field, next );
				}
			} )
		);
	}

	/**
	 * A dropdown bound to one of our meta keys.
	 *
	 * @param {Object} props Keys: field, label, options.
	 * @return {Object} An element.
	 */
	function Choice( props ) {
		return el(
			wp.components.SelectControl,
			merge( MODERN, {
				label: props.label,
				options: props.options,
				value: String( window.solseo.fields.get( props.field ) || '' ),
				onChange: function ( value ) {
					window.solseo.fields.set( props.field, value );
				}
			} )
		);
	}

	/**
	 * The pixel width bar under a title or a description.
	 *
	 * @param {Object} props Keys: measure, an object with width and limit.
	 * @return {Object} An element.
	 */
	function Gauge( props ) {
		var measure = props.measure;
		var share;
		var over;

		if ( ! measure || ! measure.limit ) {
			return null;
		}

		share = Math.min( 100, Math.round( ( measure.width / measure.limit ) * 100 ) );
		over = measure.width > measure.limit;

		return el(
			'span',
			{
				className: 'solseo-gauge' + ( over ? ' is-over' : '' ),
				title: ( strings.pixels || '%1$d of %2$d pixels' )
					.replace( '%1$d', measure.width )
					.replace( '%2$d', measure.limit )
			},
			el( 'span', { style: { width: share + '%' } } )
		);
	}

	/**
	 * The search result preview.
	 *
	 * @param {Object} props Keys: analysis, permalink.
	 * @return {Object} An element.
	 */
	function Snippet( props ) {
		var meta = wp.data.select( 'core/editor' );
		var title = window.solseo.fields.get( 'title' ) || meta.getEditedPostAttribute( 'title' ) || '';
		var description = window.solseo.fields.get( 'description' ) || '';
		var link = meta.getPermalink ? meta.getPermalink() : '';

		return el(
			'div',
			{ className: 'solseo-snippet' },
			el( 'span', { className: 'solseo-snippet-url' }, String( link || '' ).replace( /^https?:\/\//, '' ) ),
			el( 'span', { className: 'solseo-snippet-title' }, title ),
			el( 'span', { className: 'solseo-snippet-description' }, description )
		);
	}

	/**
	 * A heading that opens and shuts a region.
	 *
	 * Hand written rather than PanelBody, whose title takes a string only and
	 * so cannot carry the coloured count of what needs attention.
	 *
	 * @param {Object} props Keys: id, title, badge, open, children.
	 * @return {Object} An element.
	 */
	function Collapsible( props ) {
		var state = wp.element.useState( !! props.open );
		var open = state[ 0 ];
		var setOpen = state[ 1 ];

		return el(
			'div',
			{ className: 'solseo-collapsible' },
			el(
				'button',
				{
					type: 'button',
					className: 'solseo-collapsible-head',
					'aria-expanded': open ? 'true' : 'false',
					'aria-controls': 'solseo-region-' + props.id,
					onClick: function () {
						setOpen( ! open );
					}
				},
				el( 'span', { className: 'solseo-collapsible-title' }, props.title ),
				props.badge ? props.badge : null
			),
			el(
				'div',
				{
					id: 'solseo-region-' + props.id,
					className: 'solseo-collapsible-body',
					hidden: ! open
				},
				open ? props.children : null
			)
		);
	}

	/**
	 * One group of checks, with a count of what needs attention.
	 *
	 * @param {Object} props Keys: group, an entry from the analysis.
	 * @return {Object} An element.
	 */
	function Checks( props ) {
		var group = props.group;
		var counts = group.counts || {};
		var wrong = ( counts.fair || 0 ) + ( counts.poor || 0 );

		return el(
			Collapsible,
			{
				id: group.id,
				title: group.label,
				open: wrong > 0,
				badge: el(
					'span',
					{ className: 'solseo-group-count solseo-band-' + ( wrong ? 'fair' : 'excellent' ) },
					wrong
						? ( strings.errors || '%d to look at' ).replace( '%d', wrong )
						: ( strings.allGood || 'All good' )
				)
			},
			el(
				'ul',
				{ className: 'solseo-checks' },
				group.checks.map( function ( check ) {
					return el(
						'li',
						{
							key: check.id,
							className: 'solseo-check solseo-status-' + check.status
						},
						el( 'span', { className: 'solseo-dot' } ),
						check.note
					);
				} )
			)
		);
	}

	/**
	 * The image picker, using the block editor's own media frame.
	 *
	 * @param {Object} props Keys: field, label, help.
	 * @return {Object} An element.
	 */
	function Image( props ) {
		var id = parseInt( window.solseo.fields.get( props.field ), 10 ) || 0;

		/*
		 * The picture itself, so the box shows what is set rather than a
		 * number. useSelect is called whether or not there is an id, because a
		 * hook cannot be called conditionally.
		 */
		var media = wp.data.useSelect(
			function ( select ) {
				return id ? select( 'core' ).getMedia( id ) : null;
			},
			[ id ]
		);

		var url = media && media.source_url ? media.source_url : '';

		if ( ! wp.blockEditor || ! wp.blockEditor.MediaUpload ) {
			return null;
		}

		return el(
			'div',
			{ className: 'solseo-sidebar-image' },
			el( 'span', { className: 'solseo-sidebar-label' }, props.label ),
			el(
				wp.blockEditor.MediaUploadCheck,
				null,
				el( wp.blockEditor.MediaUpload, {
					allowedTypes: [ 'image' ],
					value: id,
					onSelect: function ( picked ) {
						window.solseo.fields.set( props.field, picked.id );
					},
					render: function ( open ) {
						return el(
							'button',
							{
								type: 'button',
								className: 'solseo-image-drop' + ( url ? ' has-image' : '' ),
								onClick: open.open,
							},
							url
								? el( 'img', { src: url, alt: '' } )
								: el( 'span', { className: 'solseo-image-drop-label' }, props.action || strings.choose )
						);
					},
				} )
			),
			id
				? el(
					wp.components.Button,
					{
						variant: 'link',
						isDestructive: true,
						className: 'solseo-image-clear',
						onClick: function () {
							window.solseo.fields.set( props.field, 0 );
						},
					},
					strings.remove
				)
				: null,
			props.help ? el( 'p', { className: 'description' }, props.help ) : null
		);
	}

	window.solseo.ui = {
		el: el,
		Mark: Mark,
		Text: Text,
		Area: Area,
		Tick: Tick,
		TickInList: TickInList,
		Choice: Choice,
		Gauge: Gauge,
		Snippet: Snippet,
		Collapsible: Collapsible,
		Checks: Checks,
		Image: Image
	};
}() );
