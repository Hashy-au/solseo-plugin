<?php
/**
 * Runs the checks and turns them into a score.
 *
 * @package SolSEO
 */

namespace SolSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * The SolSEO score.
 */
class Analyser {

	/**
	 * Score a saved post.
	 *
	 * @param int|\WP_Post $post Post or post ID.
	 * @return array
	 */
	public static function post( $post ) {
		return self::run( Paper::from_post( $post ) );
	}

	/**
	 * Run every group against a paper.
	 *
	 * @param array $paper Paper.
	 * @return array Keys: score, band, checks, groups.
	 */
	public static function run( array $paper ) {
		$checks = array();

		foreach ( self::groups() as $class ) {
			$checks = array_merge( $checks, $class::run( $paper ) );
		}

		/**
		 * Filter the check results before they are scored.
		 *
		 * @param array $checks Check results.
		 * @param array $paper  The analysed paper.
		 */
		$checks = apply_filters( 'solseo_checks', $checks, $paper );

		$score = self::tally( $checks );

		return array(
			'score'  => $score,
			'band'   => self::band( $score ),
			'checks' => $checks,
			'groups' => self::by_group( $checks ),
			'source' => isset( $paper['source'] ) ? $paper['source'] : array(),
		);
	}

	/**
	 * Score a post and write the result to its meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array The analysis.
	 */
	public static function store( $post_id ) {
		$analysis = self::post( $post_id );

		update_post_meta( $post_id, '_solseo_score', $analysis['score'] );
		update_post_meta( $post_id, '_solseo_score_summary', self::summary( $analysis ) );

		return $analysis;
	}

	/**
	 * Turn check results into a number out of a hundred.
	 *
	 * @param array $checks Check results.
	 * @return int
	 */
	public static function tally( array $checks ) {
		$earned = 0.0;
		$total  = 0;

		foreach ( $checks as $check ) {
			if ( Checks::SKIPPED === $check['status'] ) {
				continue;
			}

			$total  += $check['weight'];
			$earned += Checks::points( $check['status'] ) * $check['weight'];
		}

		if ( ! $total ) {
			return 0;
		}

		return (int) round( ( $earned / $total ) * 100 );
	}

	/**
	 * Which band a score falls in.
	 *
	 * @param int $score Score out of a hundred.
	 * @return string
	 */
	public static function band( $score ) {
		if ( $score >= 85 ) {
			return 'excellent';
		}

		if ( $score >= 70 ) {
			return 'good';
		}

		return $score >= 45 ? 'fair' : 'poor';
	}

	/**
	 * The words shown next to a band.
	 *
	 * @param string $band Band name.
	 * @return string
	 */
	public static function band_label( $band ) {
		$labels = array(
			'excellent' => __( 'Excellent', 'solseo' ),
			'good'      => __( 'Good', 'solseo' ),
			'fair'      => __( 'Fair', 'solseo' ),
			'poor'      => __( 'Needs work', 'solseo' ),
		);

		return isset( $labels[ $band ] ) ? $labels[ $band ] : $labels['poor'];
	}

	/**
	 * The heading each group of checks is listed under.
	 *
	 * @param string $group Group name.
	 * @return string
	 */
	public static function group_label( $group ) {
		$labels = array(
			'keyword'     => __( 'Keyword', 'solseo' ),
			'basics'      => __( 'Basics', 'solseo' ),
			'readability' => __( 'Readability', 'solseo' ),
			'writing'     => __( 'Writing', 'solseo' ),
			'product'     => __( 'Product', 'solseo' ),
		);

		return isset( $labels[ $group ] ) ? $labels[ $group ] : ucfirst( $group );
	}

	/**
	 * Check classes, in the order they are shown.
	 *
	 * @return array
	 */
	protected static function groups() {
		return array(
			__NAMESPACE__ . '\\Keyword_Checks',
			__NAMESPACE__ . '\\Basic_Checks',
			__NAMESPACE__ . '\\Readability_Checks',
			__NAMESPACE__ . '\\Writing_Checks',
			__NAMESPACE__ . '\\Product_Checks',
		);
	}

	/**
	 * Group the results, with a count of each status.
	 *
	 * @param array $checks Check results.
	 * @return array
	 */
	protected static function by_group( array $checks ) {
		$groups = array();

		foreach ( $checks as $check ) {
			$name = $check['group'];

			if ( ! isset( $groups[ $name ] ) ) {
				$groups[ $name ] = array(
					'label'  => self::group_label( $name ),
					'checks' => array(),
					'counts' => array(
						Checks::GOOD => 0,
						Checks::FAIR => 0,
						Checks::POOR => 0,
					),
				);
			}

			$groups[ $name ]['checks'][] = $check;

			if ( isset( $groups[ $name ]['counts'][ $check['status'] ] ) ) {
				++$groups[ $name ]['counts'][ $check['status'] ];
			}
		}

		return $groups;
	}

	/**
	 * A small record of the analysis, kept for the admin screens.
	 *
	 * @param array $analysis Full analysis.
	 * @return array
	 */
	protected static function summary( array $analysis ) {
		$failed = array();

		foreach ( $analysis['checks'] as $check ) {
			if ( Checks::POOR === $check['status'] ) {
				$failed[] = $check['id'];
			}
		}

		return array(
			'score'     => $analysis['score'],
			'band'      => $analysis['band'],
			'failed'    => $failed,
			'source'    => isset( $analysis['source']['slug'] ) ? $analysis['source']['slug'] : 'stored',
			'scored_at' => current_time( 'mysql', true ),
		);
	}
}
