/**
 * What the editor panel is built on, and what an add-on extends.
 *
 * Two things live here. A wp.data store holding the current analysis, so the
 * score in the toolbar and the checks in the panel are painted from one place
 * and cannot disagree. And window.solseo, which is the published surface: a
 * panel registry, the field accessors, and a version number an add-on can
 * refuse to attach to.
 *
 * A registry rather than a filter, deliberately. This plugin and the add-on
 * are separate downloads that update on their own, so a version handshake is
 * the realistic case rather than a hypothetical one, and a panel registered
 * after the first render still appears.
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.data || ! wp.element ) {
		return;
	}

	var PREFIX = '_solseo_';

	var EMPTY = {
		analysis: null,
		sent: null,
		scoring: false,
		panels: []
	};

	/**
	 * Fold an action into the state.
	 *
	 * @param {Object} state  What it was.
	 * @param {Object} action What happened.
	 * @return {Object} What it is now.
	 */
	function reducer( state, action ) {
		var panels;

		if ( ! state ) {
			state = EMPTY;
		}

		switch ( action.type ) {
			case 'SET_ANALYSIS':
				return {
					analysis: action.analysis,
					sent: action.sent,
					scoring: false,
					panels: state.panels
				};

			case 'SET_SCORING':
				return {
					analysis: state.analysis,
					sent: state.sent,
					scoring: action.scoring,
					panels: state.panels
				};

			case 'REGISTER_PANEL':
				panels = state.panels.filter( function ( one ) {
					return one.id !== action.panel.id;
				} );

				panels = panels.concat( [ action.panel ] );

				panels.sort( function ( a, b ) {
					return ( a.order || 50 ) - ( b.order || 50 );
				} );

				return {
					analysis: state.analysis,
					sent: state.sent,
					scoring: state.scoring,
					panels: panels
				};
		}

		return state;
	}

	var definition = {
		reducer: reducer,

		actions: {
			setAnalysis: function ( analysis, sent ) {
				return { type: 'SET_ANALYSIS', analysis: analysis, sent: sent };
			},
			setScoring: function ( scoring ) {
				return { type: 'SET_SCORING', scoring: scoring };
			},
			registerPanel: function ( panel ) {
				return { type: 'REGISTER_PANEL', panel: panel };
			}
		},

		selectors: {
			getAnalysis: function ( state ) {
				return state.analysis;
			},
			getSent: function ( state ) {
				return state.sent;
			},
			isScoring: function ( state ) {
				return state.scoring;
			},
			getPanels: function ( state ) {
				return state.panels;
			}
		}
	};

	if ( wp.data.createReduxStore ) {
		wp.data.register( wp.data.createReduxStore( 'solseo/editor', definition ) );
	} else {
		wp.data.registerStore( 'solseo/editor', definition );
	}

	/**
	 * Read one of our fields off the post being edited.
	 *
	 * @param {string} name Field name without the prefix.
	 * @return {*} What is stored, or an empty string.
	 */
	function get( name ) {
		var meta = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		var value = meta[ PREFIX + name ];

		return value === undefined ? '' : value;
	}

	/**
	 * Write one of our fields, without clobbering anybody else's.
	 *
	 * The whole meta object has to be dispatched, so it is copied first: a
	 * second plugin editing its own key in the same tick must not lose it.
	 *
	 * @param {string} name  Field name without the prefix.
	 * @param {*}      value What to store.
	 */
	function set( name, value ) {
		var meta = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		var next = {};
		var key;

		for ( key in meta ) {
			if ( Object.prototype.hasOwnProperty.call( meta, key ) ) {
				next[ key ] = meta[ key ];
			}
		}

		next[ PREFIX + name ] = value;

		wp.data.dispatch( 'core/editor' ).editPost( { meta: next } );
	}

	window.solseo = window.solseo || {};

	/*
	 * The number an add-on checks before it attaches. Bumping it is how a
	 * panel written against an older contract is refused rather than throwing
	 * inside our render and taking the whole sidebar with it.
	 */
	window.solseo.apiVersion = 1;
	window.solseo.store = 'solseo/editor';
	window.solseo.prefix = PREFIX;

	window.solseo.fields = {
		get: get,
		set: set
	};

	window.solseo.panels = {
		/**
		 * Add a panel to the sidebar.
		 *
		 * @param {Object} panel Keys: id, title, order, render, raw.
		 */
		register: function ( panel ) {
			if ( ! panel || ! panel.id || typeof panel.render !== 'function' ) {
				return;
			}

			wp.data.dispatch( 'solseo/editor' ).registerPanel( panel );
		},

		/**
		 * Every panel registered so far.
		 *
		 * @return {Array} The panels, in order.
		 */
		all: function () {
			return wp.data.select( 'solseo/editor' ).getPanels();
		}
	};

	window.solseo.data = window.solseoEditor || {};
}() );
