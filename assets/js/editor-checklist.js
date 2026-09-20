/**
 * The five things worth fixing, shown where somebody is about to publish.
 *
 * No build step, the same way the sidebar does it: registerPlugin and
 * PluginPrePublishPanel through wp.element.createElement against the handles
 * WordPress already ships.
 *
 * This blocks nothing. It is the failed checks the sidebar already worked out,
 * cut to the five that carry the most weight, put where they are read rather
 * than where they are filed. The free plugin never stops anybody publishing.
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.plugins || ! wp.element || ! wp.data ) {
		return;
	}

	var el = wp.element.createElement;
	var data = window.solseoEditor || {};
	var strings = data.strings || {};

	var PrePublish = ( wp.editor && wp.editor.PluginPrePublishPanel ) ||
		( wp.editPost && wp.editPost.PluginPrePublishPanel );

	if ( ! PrePublish ) {
		return;
	}

	/** How many are shown. More than this is a list nobody reads. */
	var SHOWN = 5;

	/**
	 * The failed checks, heaviest first.
	 *
	 * The analysis arrives with its checks inside their groups and with no flat
	 * list beside them, which is the shape the sidebar reads too. Reading a
	 * top level analysis.checks finds undefined and reports an all clear on a
	 * page with nine things wrong with it, which is exactly what this did until
	 * a real editor said "Nothing is missing" over a score of 24.
	 *
	 * @param {Object} analysis The current analysis.
	 * @return {Array} Failed checks.
	 */
	function failures( analysis ) {
		var found = [];
		var groups = ( analysis && analysis.groups ) || {};
		var name;

		for ( name in groups ) {
			if ( ! Object.prototype.hasOwnProperty.call( groups, name ) ) {
				continue;
			}

			( groups[ name ].checks || [] ).forEach( function ( check ) {
				if ( 'poor' === check.status ) {
					found.push( check );
				}
			} );
		}

		found.sort( function ( a, b ) {
			return ( b.weight || 0 ) - ( a.weight || 0 );
		} );

		return found;
	}

	/**
	 * The panel body.
	 *
	 * @return {Object} An element.
	 */
	function Checklist() {
		var analysis = wp.data.useSelect( function ( select ) {
			var store = select( 'solseo/editor' );

			return store ? store.getAnalysis() : null;
		}, [] );

		if ( ! analysis ) {
			return el( 'p', { className: 'solseo-prepublish-note' }, strings.analysing || '' );
		}

		var failed = failures( analysis );

		if ( ! failed.length ) {
			return el(
				'p',
				{ className: 'solseo-prepublish-note' },
				strings.checklistClear || ''
			);
		}

		return el(
			'div',
			{ className: 'solseo-prepublish' },
			el( 'p', { className: 'solseo-prepublish-note' }, strings.checklistIntro || '' ),
			el(
				'ul',
				{ className: 'solseo-prepublish-list' },
				failed.slice( 0, SHOWN ).map( function ( check ) {
					return el( 'li', { key: check.id }, check.note );
				} )
			),
			failed.length > SHOWN
				? el(
					'p',
					{ className: 'solseo-prepublish-more' },
					( strings.checklistMore || '' ).replace( '%d', failed.length - SHOWN )
				)
				: null
		);
	}

	/**
	 * The panel, and its title.
	 *
	 * @return {Object} An element.
	 */
	function Panel() {
		var score = wp.data.useSelect( function ( select ) {
			var store = select( 'solseo/editor' );
			var analysis = store ? store.getAnalysis() : null;

			return analysis ? analysis.score : null;
		}, [] );

		var title = null === score
			? strings.panel
			: ( strings.checklistTitle || '' ).replace( '%d', score );

		return el(
			PrePublish,
			{
				title: title,
				initialOpen: true
			},
			el( Checklist, null )
		);
	}

	wp.plugins.registerPlugin( 'solseo-checklist', { render: Panel } );
}() );
