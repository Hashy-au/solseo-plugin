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
			el( ui.Snippet, {} ),
			readBy( analysis )
		);
	}

	/**
	 * One line saying where the text that was scored came from.
	 *
	 * Silent on an ordinary post, because "read with WordPress" is noise. It
	 * speaks when a page builder drew the page, since a score that reads the
	 * builder and a score that reads an empty post_content are different
	 * numbers and the writer deserves to know which one this is.
	 *
	 * @param {Object} analysis The analysis, or null.
	 * @return {Object|null} An element.
	 */
	function readBy( analysis ) {
		var source = analysis && analysis.source ? analysis.source : null;

		if ( ! source || ! source.slug || 'stored' === source.slug ) {
			return null;
		}

		return el(
			'p',
			{ className: 'solseo-standing-source' },
			source.note ? source.note : strings.readWith.replace( '%s', source.label )
		);
	}

	/**
	 * The internal links on this page that point at no page.
	 *
	 * The server works these out while it is scoring, because "does this
	 * address resolve to something" is a question only the server can answer,
	 * and it answers it without making a request, so the list keeps up with
	 * typing. Nothing here is checked against anybody else's site.
	 *
	 * @param {Object} analysis The analysis, or null.
	 * @return {Object|null} An element.
	 */
	function deadLinks( analysis ) {
		var links = ( analysis && analysis.links ) || [];

		if ( ! links.length ) {
			return null;
		}

		return el(
			'div',
			{ className: 'solseo-dead-links' },
			el( 'h3', null, strings.deadLinks ),
			el(
				'ul',
				null,
				links.map( function ( link ) {
					return el(
						'li',
						{ key: link.path },
						el( 'strong', null, link.text || strings.deadLinkNoText ),
						' ',
						el( 'code', null, link.href )
					);
				} )
			),
			el( 'p', { className: 'description' }, strings.deadLinksHelp )
		);
	}

	/**
	 * Anything else of theirs going for the same phrase.
	 *
	 * Two sentences, and they are different on purpose. Two pages on the same
	 * side of the site are competing. A listing and an article are not, and
	 * saying they are is how somebody learns to ignore this in a week.
	 *
	 * The keys read here are the keys the score route sends. There is no
	 * JavaScript test runner in this plugin, so the PHP side pins them: a panel
	 * reading a key nobody sends says nothing at all and looks like good news.
	 *
	 * @param {Object} analysis The analysis, or null.
	 * @return {Object|null} An element.
	 */
	function duplicates( analysis ) {
		var found = ( analysis && analysis.duplicates ) || {};
		var competing = found.competing || [];
		var alongside = found.alongside || [];
		var names = [];

		if ( ! competing.length && ! alongside.length ) {
			return null;
		}

		( competing.length ? competing : alongside ).forEach( function ( one ) {
			names.push( one.title );
		} );

		return el(
			'p',
			{ className: competing.length ? 'solseo-note solseo-note-warn' : 'solseo-note' },
			( competing.length ? strings.duplicateWarn : strings.duplicateFine )
				.replace( '%1$s', found.phrase || '' )
				.replace( '%2$s', names.join( ', ' ) )
		);
	}

	/**
	 * The accessibility failures this writing causes.
	 *
	 * Content only, and the sentence underneath says so. A writer told the page
	 * is fine who then finds out otherwise stops reading anything this says.
	 *
	 * @param {Object} props Keys: analysis.
	 * @return {Object} An element.
	 */
	function Access( props ) {
		var findings = ( props.analysis && props.analysis.a11y ) || [];

		if ( ! props.analysis ) {
			return el( 'p', { className: 'description' }, strings.analysing );
		}

		if ( ! findings.length ) {
			return el( 'p', { className: 'description' }, strings.a11yClear );
		}

		return el(
			'div',
			{ className: 'solseo-a11y' },
			el(
				'ul',
				null,
				findings.map( function ( finding, index ) {
					return el(
						'li',
						{ key: finding.rule + index, className: 'solseo-check solseo-status-poor' },
						el( 'strong', null, finding.says ),
						' ',
						el( 'code', null, finding.element )
					);
				} )
			),
			el( 'p', { className: 'description' }, strings.a11yCovers )
		);
	}

	/**
	 * The pages that could link to this one.
	 *
	 * Its own request, made when this panel is opened rather than on the score
	 * route, because finding these means reading every page that mentions the
	 * phrase and the score route runs six hundred milliseconds after every
	 * keystroke.
	 *
	 * @param {Object} props Keys: postId.
	 * @return {Object} An element.
	 */
	function Suggest( props ) {
		var state = wp.element.useState( { loading: true, rows: [], trouble: '' } );
		var held = state[0];
		var set = state[1];
		var postId = props.postId;

		var read = function () {
			wp.apiFetch( { path: '/solseo/v1/suggestions?post_id=' + postId } )
				.then( function ( answer ) {
					set( { loading: false, rows: answer.suggestions || [], trouble: '' } );
				} )
				.catch( function () {
					set( { loading: false, rows: [], trouble: strings.suggestFailed } );
				} );
		};

		wp.element.useEffect( read, [ postId ] );

		var accept = function ( sourceId ) {
			set( { loading: true, rows: held.rows, trouble: '' } );

			wp.apiFetch( {
				path: '/solseo/v1/suggestions',
				method: 'POST',
				data: { post_id: postId, source_id: sourceId }
			} )
				.then( function ( answer ) {
					set( { loading: false, rows: answer.suggestions || [], trouble: '' } );
				} )
				.catch( function ( error ) {
					set( {
						loading: false,
						rows: held.rows,
						trouble: ( error && error.message ) || strings.suggestFailed
					} );
				} );
		};

		if ( held.loading ) {
			return el( 'p', { className: 'description' }, strings.analysing );
		}

		if ( ! held.rows.length ) {
			return el(
				'div',
				null,
				el( 'p', { className: 'description' }, strings.suggestNone ),
				held.trouble ? el( 'p', { className: 'solseo-note solseo-note-warn' }, held.trouble ) : null
			);
		}

		return el(
			'div',
			{ className: 'solseo-suggestions' },
			el( 'p', { className: 'description' }, strings.suggestIntro ),
			el(
				'ul',
				null,
				held.rows.map( function ( row ) {
					return el(
						'li',
						{ key: row.source_id },
						el( 'strong', null, row.title ),
						el( 'p', { className: 'description' }, row.sentence ),
						el(
							wp.components.Button,
							{
								variant: 'secondary',
								isSecondary: true,
								onClick: function () {
									accept( row.source_id );
								}
							},
							strings.suggestAdd.replace( '%s', row.anchor )
						)
					);
				} )
			),
			el( 'p', { className: 'description' }, strings.suggestUndo ),
			held.trouble ? el( 'p', { className: 'solseo-note solseo-note-warn' }, held.trouble ) : null
		);
	}

	/**
	 * What this page actually did in Google over the last twenty eight days.
	 *
	 * Its own request, made when the panel is opened, because it leaves this
	 * server and the score route does not. The answer is kept for six hours
	 * on the site's own server, so opening the same page twice in an
	 * afternoon asks Google once.
	 *
	 * @param {Object} props Keys: postId.
	 * @return {Object} An element.
	 */
	function SearchConsole( props ) {
		var state = wp.element.useState( { loading: true, figures: null, trouble: '' } );
		var held = state[0];
		var set = state[1];
		var postId = props.postId;

		wp.element.useEffect( function () {
			set( { loading: true, figures: null, trouble: '' } );

			wp.apiFetch( { path: '/solseo/v1/search-console?post_id=' + postId } )
				.then( function ( answer ) {
					set( {
						loading: false,
						figures: answer && ! answer.trouble ? answer : null,
						trouble: ( answer && answer.trouble ) || ''
					} );
				} )
				.catch( function () {
					set( { loading: false, figures: null, trouble: strings.gscFailed } );
				} );
		}, [ postId ] );

		if ( held.loading ) {
			return el( 'p', { className: 'description' }, strings.gscLoading );
		}

		if ( held.trouble ) {
			return el( 'p', { className: 'solseo-note solseo-note-warn' }, held.trouble );
		}

		if ( ! held.figures || ! held.figures.has ) {
			return el( 'p', { className: 'description' }, strings.gscNone );
		}

		var figures = held.figures;

		return el(
			'div',
			{ className: 'solseo-gsc' },
			el(
				'ul',
				{ className: 'solseo-plain-list' },
				el( 'li', null, el( 'strong', null, String( figures.clicks ) ), ' ', strings.gscClicks ),
				el( 'li', null, el( 'strong', null, String( figures.impressions ) ), ' ', strings.gscImpressions ),
				el(
					'li',
					null,
					el( 'strong', null, figures.position ? figures.position.toFixed( 1 ) : '0.0' ),
					' ',
					strings.gscPosition
				)
			),
			el( 'h3', null, strings.gscQueries ),
			figures.queries && figures.queries.length
				? el(
					'ul',
					{ className: 'solseo-plain-list' },
					figures.queries.map( function ( row ) {
						return el(
							'li',
							{ key: row.query },
							el( 'strong', null, row.query ),
							' ',
							el(
								'span',
								{ className: 'description' },
								String( row.clicks ) + ' / ' + String( row.impressions ) + ' / ' + row.position.toFixed( 1 )
							)
						);
					} )
				)
				: el( 'p', { className: 'description' }, strings.gscNoQueries ),
			el(
				'p',
				{ className: 'description' },
				strings.gscRange.replace( '%1$s', figures.from ).replace( '%2$s', figures.to )
			)
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
			duplicates( analysis ),
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
				: el( 'p', { className: 'description' }, strings.analysing ),
			deadLinks( analysis )
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
	window.solseo.panels.register( { id: 'access', title: strings.a11yTitle, order: 15, render: Access } );
	window.solseo.panels.register( { id: 'suggest', title: strings.suggestTitle, order: 18, render: Suggest } );

	/*
	 * Only when a Google account is connected. A panel that exists to say
	 * "set this up somewhere else" is a panel everybody learns to skip, and
	 * this one would sit above the two that do work.
	 */
	if ( data.searchConsole ) {
		window.solseo.panels.register( { id: 'gsc', title: strings.gscTitle, order: 19, render: SearchConsole } );
	}

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
