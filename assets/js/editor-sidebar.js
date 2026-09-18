/**
 * The SolSEO panel in the block editor, and the score in the toolbar.
 *
 * No build step. registerPlugin and PluginSidebar are called through
 * wp.element.createElement against the script handles WordPress already ships,
 * so there is no bundler, no node_modules and no compiled file in the zip.
 *
 * PluginSidebar moved from the edit-post package to the editor package in
 * WordPress 6.6. Both handles are loaded and the export is looked for in the
 * new home first, so 6.4 works and 6.6 onwards does not complain.
 */
( function () {
	'use strict';

	if ( ! window.solseo || ! window.solseo.ui || ! window.wp || ! wp.plugins || ! wp.apiFetch ) {
		return;
	}

	var el = wp.element.createElement;
	var ui = window.solseo.ui;
	var data = window.solseo.data || {};
	var strings = data.strings || {};

	var Sidebar = ( wp.editor && wp.editor.PluginSidebar ) || ( wp.editPost && wp.editPost.PluginSidebar );
	var MenuItem = ( wp.editor && wp.editor.PluginSidebarMoreMenuItem ) || ( wp.editPost && wp.editPost.PluginSidebarMoreMenuItem );

	if ( ! Sidebar ) {
		return;
	}

	var waiting = null;
	var pending = false;
	var lastSent = '';

	/**
	 * Everything the analyser needs to look at, as one string.
	 *
	 * Cheap to build and cheap to compare, so the expensive call that renders
	 * the whole document is only made when something it would read has moved.
	 *
	 * @return {string} A fingerprint of the editable state.
	 */
	function fingerprint() {
		var editor = wp.data.select( 'core/editor' );
		var blocks = wp.data.select( 'core/block-editor' ).getBlocks();

		return [
			blocks.length,
			editor.getEditedPostAttribute( 'title' ),
			editor.getEditedPostAttribute( 'slug' ),
			window.solseo.fields.get( 'title' ),
			window.solseo.fields.get( 'description' ),
			window.solseo.fields.get( 'focus_keyword' ),
			wp.data.select( 'core/block-editor' ).getBlockCount()
		].join( '' );
	}

	/**
	 * Ask the server what this page scores.
	 */
	function score() {
		var editor = wp.data.select( 'core/editor' );
		var sent;

		if ( pending ) {
			return;
		}

		sent = {
			post_id: editor.getCurrentPostId(),
			title: window.solseo.fields.get( 'title' ),
			description: window.solseo.fields.get( 'description' ),
			keyword: window.solseo.fields.get( 'focus_keyword' ),
			slug: editor.getEditedPostAttribute( 'slug' ) || '',
			post_title: editor.getEditedPostAttribute( 'title' ) || '',
			content: editor.getEditedPostContent()
		};

		pending = true;
		wp.data.dispatch( 'solseo/editor' ).setScoring( true );

		wp.apiFetch( { path: '/solseo/v1/score', method: 'POST', data: sent } )
			.then( function ( result ) {
				pending = false;
				wp.data.dispatch( 'solseo/editor' ).setAnalysis( result, sent );
			} )
			.catch( function () {
				pending = false;
				wp.data.dispatch( 'solseo/editor' ).setScoring( false );
			} );
	}

	/**
	 * Score again when something worth scoring has changed.
	 */
	function watch() {
		var now = fingerprint();

		if ( now === lastSent ) {
			return;
		}

		lastSent = now;

		window.clearTimeout( waiting );
		waiting = window.setTimeout( score, 600 );
	}

	/**
	 * The mark and the score, for the button in the editor toolbar.
	 *
	 * The mark is there because a bare number in a row of other plugins'
	 * numbers says nothing about whose it is.
	 *
	 * @param {Object} props Keys: analysis.
	 * @return {Object} An element.
	 */
	function Pill( props ) {
		var analysis = props.analysis;
		var band = analysis ? analysis.band : 'none';
		var value = analysis ? String( analysis.score ) : '..';

		return el(
			'span',
			{ className: 'solseo-pin solseo-band-' + band },
			el( ui.Mark, { size: 13 } ),
			el( 'span', { className: 'solseo-pin-score' }, value )
		);
	}

	/**
	 * The top of the panel: where this page stands, and what it will look like.
	 *
	 * @param {Object} props Keys: analysis.
	 * @return {Object} An element.
	 */
	function Standing( props ) {
		var analysis = props.analysis;
		var band = analysis ? analysis.band : 'none';
		var bands = data.bands || {};

		return el(
			'div',
			{ className: 'solseo-standing solseo-band-' + band },
			el(
				'div',
				{ className: 'solseo-standing-score' },
				el( 'span', { className: 'solseo-standing-number' }, analysis ? String( analysis.score ) : '..' ),
				el(
					'span',
					{ className: 'solseo-standing-band' },
					analysis ? ( bands[ band ] || '' ) : strings.analysing
				)
			),
			el( ui.Snippet, {} )
		);
	}

	/**
	 * The General panel: the snippet, the fields and the checks.
	 *
	 * @param {Object} props Keys: analysis.
	 * @return {Object} An element.
	 */
	function General( props ) {
		var analysis = props.analysis;
		var groups = [];
		var raw = analysis && analysis.groups ? analysis.groups : {};
		var name;

		// The groups arrive keyed by name, so the key is the id.
		for ( name in raw ) {
			if ( Object.prototype.hasOwnProperty.call( raw, name ) ) {
				groups.push( {
					id: name,
					label: raw[ name ].label,
					checks: raw[ name ].checks || [],
					counts: raw[ name ].counts || {}
				} );
			}
		}

		return el(
			'div',
			null,
			el( ui.Text, {
				field: 'focus_keyword',
				label: strings.keyword,
				help: strings.keywordHelp
			} ),
			el( ui.Text, {
				field: 'keywords',
				label: strings.phrases,
				help: strings.phrasesHelp
			} ),
			el( ui.Text, {
				field: 'title',
				label: strings.seoTitle,
				placeholder: data.titleTemplate
			} ),
			el( ui.Gauge, { measure: analysis ? analysis.title : null } ),
			el( ui.Area, {
				field: 'description',
				label: strings.description,
				rows: 3
			} ),
			el( ui.Gauge, { measure: analysis ? analysis.description : null } ),
			analysis
				? el(
					'div',
					{ className: 'solseo-check-groups' },
					groups.map( function ( group ) {
						return el( ui.Checks, { key: group.id, group: group } );
					} )
				)
				: el( 'p', { className: 'description' }, strings.analysing )
		);
	}

	/**
	 * The Social panel.
	 *
	 * @return {Object} An element.
	 */
	function Social() {
		return el(
			'div',
			null,
			el( ui.Text, { field: 'og_title', label: strings.ogTitle } ),
			el( ui.Area, { field: 'og_description', label: strings.ogDesc, rows: 2 } ),
			el( ui.Image, { field: 'og_image', label: strings.ogImage, action: strings.chooseSocial, help: strings.ogImageHelp } ),
			el( ui.Text, { field: 'twitter_title', label: strings.cardTitle } ),
			el( ui.Area, { field: 'twitter_description', label: strings.cardDesc, rows: 2 } )
		);
	}

	/**
	 * The Advanced panel.
	 *
	 * @return {Object} An element.
	 */
	function Advanced() {
		var options = [ { label: strings.schemaAuto, value: '' } ];
		var types = data.schemaTypes || {};
		var key;

		for ( key in types ) {
			if ( Object.prototype.hasOwnProperty.call( types, key ) ) {
				options.push( { label: types[ key ], value: key } );
			}
		}

		return el(
			'div',
			null,
			el( ui.Text, { field: 'canonical', label: strings.canonical, help: strings.canonHelp } ),
			el(
				'fieldset',
				{ className: 'solseo-fieldset' },
				el( 'legend', null, strings.engines ),
				el( ui.Tick, { field: 'robots_noindex', label: strings.noindex } ),
				el( ui.Tick, { field: 'robots_nofollow', label: strings.nofollow } ),
				el( ui.TickInList, { field: 'robots_advanced', value: 'noimageindex', label: strings.noimage } ),
				el( ui.TickInList, { field: 'robots_advanced', value: 'noarchive', label: strings.noarchive } )
			),
			el( ui.Choice, { field: 'schema_type', label: strings.schema, options: options } )
		);
	}

	/*
	 * The three that ship go through the same registry an add-on uses, so
	 * there is one code path rather than ours and theirs.
	 */
	window.solseo.panels.register( { id: 'general', title: strings.general, order: 10, render: General, open: true } );
	window.solseo.panels.register( { id: 'social', title: strings.social, order: 20, render: Social } );
	window.solseo.panels.register( { id: 'advanced', title: strings.advanced, order: 30, render: Advanced } );

	/**
	 * The whole panel, and the button that opens it.
	 *
	 * @return {Object} An element.
	 */
	function Panel() {
		var picked = wp.data.useSelect( function ( select ) {
			return {
				analysis: select( 'solseo/editor' ).getAnalysis(),
				sent: select( 'solseo/editor' ).getSent(),
				panels: select( 'solseo/editor' ).getPanels()
			};
		}, [] );

		wp.element.useEffect( function () {
			var stop = wp.data.subscribe( watch );

			watch();

			return stop;
		}, [] );

		return el(
			wp.element.Fragment,
			null,
			MenuItem
				? el( MenuItem, { target: 'solseo', icon: el( Pill, { analysis: picked.analysis } ) }, strings.panel )
				: null,
			el(
				Sidebar,
				{
					name: 'solseo',
					title: strings.panel,
					icon: el( Pill, { analysis: picked.analysis } )
				},
				el(
					'div',
					{ className: 'solseo-sidebar' },
					el( Standing, { analysis: picked.analysis } ),
					picked.panels.map( function ( panel ) {
						var body = panel.render( {
							analysis: picked.analysis,
							sent: picked.sent,
							fields: window.solseo.fields,
							postId: wp.data.select( 'core/editor' ).getCurrentPostId()
						} );

						if ( panel.raw ) {
							return el( wp.element.Fragment, { key: panel.id }, body );
						}

						return el(
							ui.Collapsible,
							{
								key: panel.id,
								id: 'panel-' + panel.id,
								title: panel.title,
								open: !! panel.open
							},
							body
						);
					} )
				)
			)
		);
	}

	wp.plugins.registerPlugin( 'solseo', { render: Panel } );
}() );
