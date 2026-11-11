<?php
/**
 * Plugin Name:       Hukamnama · Sri Harmandir Sahib
 * Plugin URI:        https://gurbanisoul.com/en/developers
 * Description:       Shows the daily Hukamnama from Sri Harmandir Sahib, Amritsar, with the shortcode [hukamnama]. Every line is checked against its hash before it is shown.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Algorythmos
 * Author URI:        https://gurbanisoul.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       hukamnama-sri-harmandir-sahib
 *
 * @package Hukamnama_SHS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-hukamnama-shs-client.php';

define( 'HUKAMNAMA_SHS_VERSION', '1.0.0' );
define( 'HUKAMNAMA_SHS_FRESH', 'hukamnama_shs_fresh' );     // a proved day, kept five minutes.
define( 'HUKAMNAMA_SHS_LAST', 'hukamnama_shs_last' );       // the last proved day, kept two days.
define( 'HUKAMNAMA_SHS_FAILED', 'hukamnama_shs_failed' );   // set for a minute after a failure.

/**
 * The present moment, as Unix time. Filterable so tests can set the clock.
 */
function hukamnama_shs_now() {
	return (int) apply_filters( 'hukamnama_shs_now', time() );
}

/**
 * One request to the API. Null on any failure.
 *
 * @param string $path An API path.
 */
function hukamnama_shs_get( $path ) {
	$response = wp_remote_get(
		Hukamnama_SHS_Client::API . $path,
		array(
			'timeout'    => 8,
			'user-agent' => 'hukamnama-sri-harmandir-sahib/' . HUKAMNAMA_SHS_VERSION . ' (WordPress)',
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	return is_array( $body ) ? $body : null;
}

/**
 * The day to show, and whether it is fresh.
 *
 * Asks the API at most once every five minutes (once a minute after a failure). When the API
 * cannot be reached, the last proved day is shown under its own date.
 *
 * @return array|null A proved day, or null when there is nothing that may be shown.
 */
function hukamnama_shs_day() {
	$day = get_transient( HUKAMNAMA_SHS_FRESH );
	if ( false === $day && false === get_transient( HUKAMNAMA_SHS_FAILED ) ) {
		$result = Hukamnama_SHS_Client::latest_published( 'hukamnama_shs_get' );
		if ( isset( $result['day'] ) ) {
			$day = $result['day'];
			set_transient( HUKAMNAMA_SHS_FRESH, $day, 5 * MINUTE_IN_SECONDS );
			set_transient( HUKAMNAMA_SHS_LAST, $day, 2 * DAY_IN_SECONDS );
		} else {
			set_transient( HUKAMNAMA_SHS_FAILED, $result['error'], MINUTE_IN_SECONDS );
		}
	}
	if ( false === $day ) {
		$day = get_transient( HUKAMNAMA_SHS_LAST );
	}
	// Whatever the cache holds is proved again before every display.
	return Hukamnama_SHS_Client::is_proved( $day ) ? $day : null;
}

/**
 * The embed: gurbanisoul.com's own small page in a frame. No script, no cookie.
 *
 * @param array $atts Shortcode attributes, already limited to known values.
 */
function hukamnama_shs_embed( $atts ) {
	$src = add_query_arg(
		array(
			'lang'  => $atts['lang'],
			'theme' => $atts['theme'],
		),
		Hukamnama_SHS_Client::SITE . 'embed/hukamnama'
	);
	return sprintf(
		'<iframe class="hukamnama-shs-embed" src="%s" title="%s" loading="lazy" style="width:100%%;max-width:640px;height:520px;border:0"></iframe>',
		esc_url( $src ),
		esc_attr__( 'Hukamnama · Sri Harmandir Sahib', 'hukamnama-sri-harmandir-sahib' )
	);
}

/**
 * [hukamnama] — the whole shabad, its Amritsar date, the citation and the credit.
 *
 * Attributes: translit="yes" adds the transliteration under each line, under its own label.
 *             mode="embed" shows gurbanisoul.com's embed instead (with lang="en|pa|fr" and
 *             theme="auto|light|dark").
 *
 * @param array|string $atts Shortcode attributes.
 */
function hukamnama_shs_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'translit' => 'no',
			'mode'     => 'text',
			'lang'     => 'en',
			'theme'    => 'auto',
		),
		$atts,
		'hukamnama'
	);
	$atts['lang']  = in_array( $atts['lang'], array( 'en', 'pa', 'fr' ), true ) ? $atts['lang'] : 'en';
	$atts['theme'] = in_array( $atts['theme'], array( 'auto', 'light', 'dark' ), true ) ? $atts['theme'] : 'auto';
	if ( 'embed' === $atts['mode'] ) {
		return hukamnama_shs_embed( $atts );
	}

	wp_enqueue_style( 'hukamnama-shs' );
	$name = esc_html__( 'Hukamnama · Sri Harmandir Sahib', 'hukamnama-sri-harmandir-sahib' );
	$day  = hukamnama_shs_day();
	if ( null === $day ) {
		// Nothing proved to show: say so, and show no text at all.
		return sprintf(
			'<div class="hukamnama-shs"><p class="hukamnama-shs-name">%s</p><p>%s <a href="%s">gurbanisoul.com</a></p></div>',
			$name,
			esc_html__( 'The Hukamnama cannot be shown right now. Please try again in a few minutes.', 'hukamnama-sri-harmandir-sahib' ),
			esc_url( Hukamnama_SHS_Client::SITE . $atts['lang'] . '/hukamnama' )
		);
	}

	$date = wp_date( get_option( 'date_format' ), strtotime( $day['amritsar_date'] . ' 00:00:00 UTC' ), new DateTimeZone( 'UTC' ) );
	/* translators: %s: the Hukamnama's date in Amritsar. */
	$today = __( "Today's Hukamnama, %s (Amritsar)", 'hukamnama-sri-harmandir-sahib' );
	/* translators: %s: the Hukamnama's date in Amritsar. */
	$dated = __( 'Hukamnama, %s (Amritsar)', 'hukamnama-sri-harmandir-sahib' );
	$label = sprintf( Hukamnama_SHS_Client::is_today( $day, hukamnama_shs_now() ) ? $today : $dated, $date );

	$out  = '<div class="hukamnama-shs">';
	$out .= '<p class="hukamnama-shs-name">' . $name . '</p>';
	$out .= '<p class="hukamnama-shs-date">' . esc_html( $label ) . '</p>';
	$out .= '<div class="hukamnama-shs-lines" lang="pa">';
	foreach ( $day['lines'] as $line ) {
		// Every line, exactly as it arrived: escaped for HTML, never shortened or re-spaced.
		$out .= '<p>' . esc_html( $line['gurmukhi'] ) . '</p>';
	}
	$out .= '</div>';
	if ( 'yes' === $atts['translit'] ) {
		$out .= '<p class="hukamnama-shs-label">' . esc_html__( 'Transliteration', 'hukamnama-sri-harmandir-sahib' ) . '</p>';
		$out .= '<div class="hukamnama-shs-translit" lang="pa-Latn">';
		foreach ( $day['lines'] as $line ) {
			if ( isset( $line['translit'] ) && is_string( $line['translit'] ) ) {
				$out .= '<p>' . esc_html( $line['translit'] ) . '</p>';
			}
		}
		$out .= '</div>';
	}
	$credit = isset( $day['attribution']['text'] ) && is_string( $day['attribution']['text'] )
		? $day['attribution']['text']
		: 'Hukamnama · Sri Harmandir Sahib, via Gurbani Soul';
	$out   .= '<p class="hukamnama-shs-foot">' . esc_html( $day['citation'] ) . '<br>';
	$out   .= '<a href="' . esc_url( Hukamnama_SHS_Client::credit_url( $day ) ) . '">' . esc_html( $credit ) . '</a></p>';
	$out   .= '</div>';
	return $out;
}

/**
 * The shortcode and its small stylesheet.
 */
function hukamnama_shs_init() {
	add_shortcode( 'hukamnama', 'hukamnama_shs_shortcode' );
	wp_register_style( 'hukamnama-shs', false, array(), HUKAMNAMA_SHS_VERSION );
	wp_add_inline_style(
		'hukamnama-shs',
		'.hukamnama-shs{max-width:40rem}.hukamnama-shs-name{font-weight:600;margin:0}.hukamnama-shs-date{margin:.2em 0 1em;opacity:.75}'
		. '.hukamnama-shs-lines p{font-size:1.4em;line-height:1.9;margin:0 0 .4em;overflow-wrap:anywhere}'
		. '.hukamnama-shs-label{font-size:.85em;opacity:.75;margin:1.2em 0 .3em}.hukamnama-shs-translit p{margin:0 0 .3em}'
		. '.hukamnama-shs-foot{font-size:.9em;opacity:.8;margin-top:1.2em;padding-top:.8em;border-top:1px solid currentColor}'
	);
}
add_action( 'init', 'hukamnama_shs_init' );
