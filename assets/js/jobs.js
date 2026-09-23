/**
 * Drives a long running job from the page it was started on.
 *
 * This tab is the runner. It asks for one chunk, waits, and asks for the next,
 * so the bar moves because work happened rather than because time passed.
 * Closing the tab stops it with its place saved, which the screen says out
 * loud before anybody presses anything.
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.apiFetch || ! window.solseoJobs ) {
		return;
	}

	var strings = window.solseoJobs.strings || {};

	/**
	 * Read the arguments a region wants to send.
	 *
	 * @param {Element} region The job region.
	 * @return {Object} Arguments for the job.
	 */
	function args( region ) {
		var out = {};
		var fixed = region.getAttribute( 'data-solseo-args' );
		var inputs = region.querySelectorAll( '[data-solseo-arg]' );
		var i;
		var input;
		var name;

		if ( fixed ) {
			try {
				out = JSON.parse( fixed );
			} catch ( e ) {
				out = {};
			}
		}

		for ( i = 0; i < inputs.length; i++ ) {
			input = inputs[ i ];
			name = input.getAttribute( 'data-solseo-arg' );

			if ( name.slice( -2 ) === '[]' ) {
				name = name.slice( 0, -2 );

				if ( ! out[ name ] ) {
					out[ name ] = [];
				}

				if ( input.checked ) {
					out[ name ].push( input.value );
				}

				continue;
			}

			if ( input.type === 'checkbox' ) {
				out[ name ] = input.checked;

				continue;
			}

			out[ name ] = input.value;
		}

		return out;
	}

	/**
	 * Put a state on the screen.
	 *
	 * @param {Element} region The job region.
	 * @param {Object}  state  The job state.
	 */
	function paint( region, state ) {
		var bar = region.querySelector( '[data-solseo-job-bar]' );
		var message = region.querySelector( '[data-solseo-job-message]' );
		var counts = region.querySelector( '[data-solseo-job-counts]' );
		var start = region.querySelector( '[data-solseo-job-start]' );
		var stop = region.querySelector( '[data-solseo-job-stop]' );
		var done = region.querySelector( '[data-solseo-job-done]' );
		var running = state.status === 'running' || state.status === 'verifying';
		var share = state.total ? Math.min( 100, Math.round( ( state.done / state.total ) * 100 ) ) : 0;

		region.setAttribute( 'data-solseo-job-status', state.status );

		if ( bar ) {
			bar.style.width = share + '%';
			bar.parentNode.setAttribute( 'aria-valuenow', String( share ) );
		}

		if ( message ) {
			message.textContent = state.message || '';
		}

		if ( counts ) {
			counts.textContent = state.total
				? strings.progress.replace( '%1$s', state.done ).replace( '%2$s', state.total )
				: '';
		}

		if ( start ) {
			start.hidden = running;
			start.disabled = running;

			if ( ! running && state.done && state.status !== 'done' ) {
				start.textContent = strings.carryOn;
			}
		}

		if ( stop ) {
			stop.hidden = ! running;
		}

		if ( done ) {
			done.hidden = state.status !== 'done';
		}

		if ( state.status === 'done' ) {
			region.dispatchEvent(
				new CustomEvent( 'solseo:job-done', { bubbles: true, detail: state } )
			);

			/*
			 * What a finished run unlocks is decided on the server, because
			 * that is where it has to be decided. Reloading is how this page
			 * asks.
			 *
			 * Only a run this page watched finish is worth reloading for. The
			 * state read on load also says 'done', so reloading on that turns
			 * a finished job into a page that reloads itself forever.
			 */
			if ( region.solseoRan && region.hasAttribute( 'data-solseo-reload' ) ) {
				window.setTimeout( function () {
					window.location.reload();
				}, 1200 );
			}
		}
	}

	/**
	 * Say what went wrong, in words somebody can act on.
	 *
	 * @param {Element} region The job region.
	 * @param {Object}  error  What came back.
	 */
	function fail( region, error ) {
		var message = region.querySelector( '[data-solseo-job-message]' );
		var start = region.querySelector( '[data-solseo-job-start]' );
		var stop = region.querySelector( '[data-solseo-job-stop]' );
		var status = error && error.data ? error.data.status : 0;
		var text = strings.failed;

		if ( status === 409 ) {
			text = error.message;
		} else if ( status === 401 || status === 403 ) {
			text = strings.expired;
		} else if ( error && error.message ) {
			text = error.message;
		}

		if ( message ) {
			message.textContent = text;
		}

		region.setAttribute( 'data-solseo-job-status', 'failed' );

		if ( start ) {
			start.hidden = false;
			start.disabled = false;
		}

		if ( stop ) {
			stop.hidden = true;
		}
	}

	/**
	 * Ask for one chunk, then the next, until there are none left.
	 *
	 * @param {Element} region The job region.
	 * @param {Object}  state  The job state we last saw.
	 */
	function step( region, state ) {
		if ( state.status !== 'running' && state.status !== 'verifying' ) {
			paint( region, state );

			return;
		}

		if ( region.solseoStopping ) {
			return;
		}

		wp.apiFetch( {
			path: '/solseo/v1/job',
			method: 'POST',
			data: {
				job: region.getAttribute( 'data-solseo-job' ),
				action: 'step',
				token: state.token
			}
		} )
			.then( function ( next ) {
				paint( region, next );
				step( region, next );
			} )
			.catch( function ( error ) {
				fail( region, error );
			} );
	}

	/**
	 * Start a job from the beginning, or carry one on.
	 *
	 * @param {Element} region The job region.
	 */
	function start( region ) {
		var id = region.getAttribute( 'data-solseo-job' );

		region.solseoStopping = false;
		region.solseoRan = true;

		wp.apiFetch( {
			path: '/solseo/v1/job',
			method: 'POST',
			data: {
				job: id,
				action: 'start',
				args: args( region )
			}
		} )
			.then( function ( state ) {
				paint( region, state );
				step( region, state );
			} )
			.catch( function ( error ) {
				fail( region, error );
			} );
	}

	/**
	 * Stop a job cleanly, keeping its place.
	 *
	 * @param {Element} region The job region.
	 * @param {Object}  state  The job state we last saw.
	 */
	function stop( region, state ) {
		region.solseoStopping = true;

		wp.apiFetch( {
			path: '/solseo/v1/job',
			method: 'POST',
			data: {
				job: region.getAttribute( 'data-solseo-job' ),
				action: 'stop',
				token: state.token
			}
		} )
			.then( function ( next ) {
				paint( region, next );
			} )
			.catch( function ( error ) {
				fail( region, error );
			} );
	}

	/**
	 * Wire one region up and show where its job is.
	 *
	 * @param {Element} region The job region.
	 */
	function attach( region ) {
		var id = region.getAttribute( 'data-solseo-job' );
		var startButton = region.querySelector( '[data-solseo-job-start]' );
		var stopButton = region.querySelector( '[data-solseo-job-stop]' );
		var latest = null;

		wp.apiFetch( { path: '/solseo/v1/job?job=' + encodeURIComponent( id ) } )
			.then( function ( state ) {
				latest = state;

				if ( state.status === 'running' || state.status === 'verifying' ) {
					state.status = 'stopped';
					state.message = strings.interrupted;
				}

				/*
				 * A job that finished before this page was drawn has already
				 * been written up by the screen, which knows more about what it
				 * unlocked than this does. Repeating it here says the same
				 * sentence twice.
				 */
				if ( state.status === 'done' ) {
					state.message = '';
				}

				paint( region, state );
			} )
			.catch( function () {} );

		if ( startButton ) {
			startButton.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				start( region );
			} );
		}

		if ( stopButton ) {
			stopButton.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				if ( latest ) {
					stop( region, latest );
				}
			} );
		}

		region.addEventListener( 'solseo:job-done', function ( event ) {
			latest = event.detail;
		} );
	}

	/**
	 * Ask before doing something to somebody else's plugin.
	 *
	 * @param {Event} event The submit event.
	 */
	function confirmed( event ) {
		var question = this.getAttribute( 'data-solseo-confirm' );

		if ( question && ! window.confirm( question ) ) {
			event.preventDefault();
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var regions = document.querySelectorAll( '[data-solseo-job]' );
		var forms = document.querySelectorAll( '[data-solseo-confirm]' );
		var i;

		for ( i = 0; i < regions.length; i++ ) {
			attach( regions[ i ] );
		}

		for ( i = 0; i < forms.length; i++ ) {
			forms[ i ].addEventListener( 'submit', confirmed );
		}
	} );
}() );
