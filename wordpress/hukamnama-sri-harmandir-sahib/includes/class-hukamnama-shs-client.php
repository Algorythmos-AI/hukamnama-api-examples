<?php
/**
 * Reading and proving a day's Hukamnama. No WordPress functions here, so it can be tested alone.
 *
 * @package Hukamnama_SHS
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'HUKAMNAMA_SHS_TESTING' ) ) {
	exit;
}

/**
 * The rules every display must keep: the text is shown only after it is proved against its hash,
 * and a day is never shown under another day's date.
 */
class Hukamnama_SHS_Client {

	const API       = 'https://api.gurbanisoul.com';
	const SITE      = 'https://gurbanisoul.com/';
	const MAX_LINES = 400;

	/**
	 * True when the record is a published day whose lines hash to its range_sha256.
	 *
	 * @param mixed $day A decoded API answer.
	 */
	public static function is_proved( $day ) {
		if ( ! is_array( $day ) || ! isset( $day['status'], $day['amritsar_date'], $day['range_sha256'], $day['lines'], $day['citation'] ) ) {
			return false;
		}
		if ( 'published' !== $day['status'] || ! is_string( $day['citation'] ) ) {
			return false;
		}
		if ( ! is_string( $day['amritsar_date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day['amritsar_date'] ) ) {
			return false;
		}
		if ( ! is_string( $day['range_sha256'] ) || ! preg_match( '/^[0-9a-f]{64}$/', $day['range_sha256'] ) ) {
			return false;
		}
		if ( ! is_array( $day['lines'] ) || 0 === count( $day['lines'] ) || count( $day['lines'] ) > self::MAX_LINES ) {
			return false;
		}
		$text = array();
		foreach ( $day['lines'] as $line ) {
			if ( ! is_array( $line ) || ! isset( $line['gurmukhi'] ) || ! is_string( $line['gurmukhi'] ) ) {
				return false;
			}
			$text[] = $line['gurmukhi'];
		}
		// The hash is over every line joined by a newline, as UTF-8, with no newline at the end.
		return hash_equals( $day['range_sha256'], hash( 'sha256', implode( "\n", $text ) ) );
	}

	/**
	 * Today's Hukamnama, or the most recent one when today's is not published yet.
	 *
	 * @param callable $get Takes an API path ("/v1/hukamnama/today") and returns the decoded answer, or null on failure.
	 * @return array { 'day' => array } on success, or { 'error' => 'unavailable'|'none'|'not_proved' }.
	 */
	public static function latest_published( $get ) {
		$day = call_user_func( $get, '/v1/hukamnama/today' );
		if ( ! is_array( $day ) || ! isset( $day['status'] ) ) {
			return array( 'error' => 'unavailable' );
		}
		if ( 'published' !== $day['status'] ) {
			$latest = isset( $day['latest'] ) ? $day['latest'] : null;
			if ( ! is_string( $latest ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $latest ) ) {
				return array( 'error' => 'none' );
			}
			$day = call_user_func( $get, '/v1/hukamnama/' . $latest );
			if ( ! is_array( $day ) || ! isset( $day['status'] ) ) {
				return array( 'error' => 'unavailable' );
			}
			if ( 'published' !== $day['status'] ) {
				return array( 'error' => 'none' );
			}
			// Asked for one date: any other date in the answer is refused.
			if ( ! isset( $day['amritsar_date'] ) || $day['amritsar_date'] !== $latest ) {
				return array( 'error' => 'not_proved' );
			}
		}
		if ( ! self::is_proved( $day ) ) {
			return array( 'error' => 'not_proved' );
		}
		return array( 'day' => $day );
	}

	/**
	 * The date in Amritsar at a moment (a fixed UTC+05:30; India has no summer time).
	 *
	 * @param int $now Unix time.
	 */
	public static function amritsar_date( $now ) {
		return gmdate( 'Y-m-d', $now + 19800 );
	}

	/**
	 * Call it today's only while its date is still the date in Amritsar.
	 *
	 * @param array $day A proved day.
	 * @param int   $now Unix time.
	 */
	public static function is_today( $day, $now ) {
		return $day['amritsar_date'] === self::amritsar_date( $now );
	}

	/**
	 * The link the credit carries, only when it points at gurbanisoul.com.
	 *
	 * @param array $day A proved day.
	 */
	public static function credit_url( $day ) {
		$url = isset( $day['attribution']['url'] ) && is_string( $day['attribution']['url'] ) ? $day['attribution']['url'] : '';
		return 0 === strpos( $url, self::SITE ) ? $url : self::SITE . 'en/hukamnama';
	}
}
