<?php
/**
 * Tests for the plugin, in plain PHP: no WordPress install needed.
 *
 *   php wordpress/tests/run.php
 *
 * WordPress's functions are replaced by small stand-ins below, and the API by the files in
 * fixtures/. What is proved: the whole shabad is shown, escaped and unchanged; nothing is shown
 * when the hash does not match; a day is never called today's on another date; the last checked
 * day covers an outage; and the API is not asked on every page view.
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['t']      = array();   // transients: key => [value, expires]
$GLOBALS['api']    = array();   // path => decoded answer (or null: the request fails)
$GLOBALS['asked']  = array();   // the paths requested
$GLOBALS['clock']  = strtotime( '2026-11-11 02:00:00 UTC' );   // 07:30 in Amritsar on the 11th
$GLOBALS['styles'] = array();

function add_action( $hook, $fn ) { if ( 'init' === $hook ) { $GLOBALS['init'] = $fn; } }
function add_shortcode( $tag, $fn ) { $GLOBALS['shortcode'][ $tag ] = $fn; }
function shortcode_atts( $defaults, $atts ) { return array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) ); }
function wp_register_style( $h ) { $GLOBALS['styles'][ $h ] = ''; }
function wp_add_inline_style( $h, $css ) { $GLOBALS['styles'][ $h ] .= $css; }
function wp_enqueue_style( $h ) {}
function get_transient( $k ) {
	if ( ! isset( $GLOBALS['t'][ $k ] ) || $GLOBALS['t'][ $k ][1] <= $GLOBALS['clock'] ) { return false; }
	return $GLOBALS['t'][ $k ][0];
}
function set_transient( $k, $v, $ttl ) { $GLOBALS['t'][ $k ] = array( $v, $GLOBALS['clock'] + $ttl ); return true; }
function wp_remote_get( $url ) {
	$path = substr( $url, strlen( 'https://api.gurbanisoul.com' ) );
	$GLOBALS['asked'][] = $path;
	$body = array_key_exists( $path, $GLOBALS['api'] ) ? $GLOBALS['api'][ $path ] : null;
	return null === $body ? array( 'code' => 503, 'body' => '' ) : array( 'code' => 200, 'body' => json_encode( $body ) );
}
function is_wp_error( $r ) { return false; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function get_option( $k ) { return 'j F Y'; }
function wp_date( $format, $ts, $tz ) { return gmdate( $format, $ts ); }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function apply_filters( $tag, $value ) { return 'hukamnama_shs_now' === $tag ? $GLOBALS['clock'] : $value; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }

// The plugin reads the clock through the hukamnama_shs_now filter, which the stand-in above answers.
require dirname( __DIR__ ) . '/hukamnama-sri-harmandir-sahib/hukamnama-sri-harmandir-sahib.php';
call_user_func( $GLOBALS['init'] );

$fix     = dirname( __DIR__, 2 ) . '/fixtures/';
$day     = json_decode( file_get_contents( $fix . 'today.json' ), true );
$pending = json_decode( file_get_contents( $fix . 'pending.json' ), true );
$date    = $day['amritsar_date'];
$failed  = 0;

function check( $ok, $what ) {
	global $failed;
	echo ( $ok ? 'ok    ' : 'FAIL  ' ) . $what . "\n";
	if ( ! $ok ) { $failed++; }
}
function fresh( $api ) {
	$GLOBALS['t'] = array(); $GLOBALS['asked'] = array(); $GLOBALS['api'] = $api;
}
function show( $atts = array() ) { return call_user_func( $GLOBALS['shortcode']['hukamnama'], $atts ); }
function rehash( $d ) {
	$d['range_sha256'] = hash( 'sha256', implode( "\n", array_column( $d['lines'], 'gurmukhi' ) ) );
	return $d;
}

// --- the checking class ---------------------------------------------------------------------
check( Hukamnama_SHS_Client::is_proved( $day ), 'the fixture day is proved by its hash' );
$bad = $day; $bad['lines'][1]['gurmukhi'] .= ' ';
check( ! Hukamnama_SHS_Client::is_proved( $bad ), 'one added space is caught' );
$bad = $day; array_pop( $bad['lines'] );
check( ! Hukamnama_SHS_Client::is_proved( $bad ), 'a missing line is caught' );
$bad = $day; $bad['lines'] = array_reverse( $bad['lines'] );
check( ! Hukamnama_SHS_Client::is_proved( $bad ), 'reordered lines are caught' );
check( ! Hukamnama_SHS_Client::is_proved( $pending ), 'a pending answer is not a day' );
check( ! Hukamnama_SHS_Client::is_proved( false ) && ! Hukamnama_SHS_Client::is_proved( array() ), 'nothing is not a day' );
check( '2026-11-11' === Hukamnama_SHS_Client::amritsar_date( strtotime( '2026-11-10 18:30:00 UTC' ) ), 'midnight in Amritsar is 18:30 UTC' );
check( '2026-11-10' === Hukamnama_SHS_Client::amritsar_date( strtotime( '2026-11-10 18:29:59 UTC' ) ), 'one second before it is still the day before' );

// --- published today --------------------------------------------------------------------------
fresh( array( '/v1/hukamnama/today' => $day ) );
$html = show();
$all  = true;
foreach ( $day['lines'] as $line ) { $all = $all && false !== strpos( $html, '<p>' . esc_html( $line['gurmukhi'] ) . '</p>' ); }
check( $all, 'every line is shown, whole and unchanged' );
check( substr_count( $html, '<p>' ) === count( $day['lines'] ), 'exactly as many lines as the day has' );
check( false !== strpos( $html, 'lang="pa"' ), 'the Gurmukhi is marked as Punjabi' );
check( false !== strpos( $html, esc_html( "Today's Hukamnama, 11 November 2026 (Amritsar)" ) ), 'it is today\'s on its own Amritsar date' );
check( false !== strpos( $html, esc_html( $day['citation'] ) ), 'the citation is shown' );
check( false !== strpos( $html, 'href="' . $day['attribution']['url'] . '"' ) && false !== strpos( $html, esc_html( $day['attribution']['text'] ) ), 'the credit and its link are shown' );
check( false === strpos( $html, 'Transliteration' ), 'no transliteration unless asked' );
show(); show();
check( array( '/v1/hukamnama/today' ) === $GLOBALS['asked'], 'three page views, one request' );
$html = show( array( 'translit' => 'yes' ) );
check( false !== strpos( $html, 'Transliteration' ) && false !== strpos( $html, 'lang="pa-Latn"' ) && strpos( $html, 'hukamnama-shs-translit' ) > strpos( $html, 'hukamnama-shs-lines' ), 'the transliteration is a separate, labelled block after the Gurmukhi' );

// --- the next Amritsar day, before publication --------------------------------------------------
$GLOBALS['clock'] = strtotime( '2026-11-12 00:10:00 UTC' );
fresh( array( '/v1/hukamnama/today' => $pending, '/v1/hukamnama/' . $date => $day ) );
$html = show();
check( false !== strpos( $html, esc_html( $day['lines'][1]['gurmukhi'] ) ), 'before publication the latest Hukamnama is shown' );
check( false === strpos( $html, 'Today' ) && false !== strpos( $html, 'Hukamnama, 11 November 2026 (Amritsar)' ), 'under its own date, not as today\'s' );
check( array( '/v1/hukamnama/today', '/v1/hukamnama/' . $date ) === $GLOBALS['asked'], 'it asked for the latest date by name' );
$other = $day; $other['amritsar_date'] = '2026-11-09';
fresh( array( '/v1/hukamnama/today' => $pending, '/v1/hukamnama/' . $date => $other ) );
check( false === strpos( show(), esc_html( $day['lines'][1]['gurmukhi'] ) ), 'an answer for another date than the one asked is refused' );
$GLOBALS['clock'] = strtotime( '2026-11-11 02:00:00 UTC' );

// --- a day that does not prove ------------------------------------------------------------------
$tampered = $day; $tampered['lines'][2]['gurmukhi'] .= ' ';
fresh( array( '/v1/hukamnama/today' => $tampered ) );
$html = show();
$none = true;
foreach ( $day['lines'] as $line ) { $none = $none && false === strpos( $html, esc_html( $line['gurmukhi'] ) ); }
check( $none && false !== strpos( $html, 'cannot be shown right now' ), 'a day that fails its hash shows no line at all' );
show();
check( 1 === count( $GLOBALS['asked'] ), 'after a failure it waits before asking again' );

// --- an outage ------------------------------------------------------------------------------------
fresh( array( '/v1/hukamnama/today' => $day ) );
show();
$GLOBALS['clock'] += 6 * 60;                      // the fresh copy has expired
$GLOBALS['api'] = array();                         // and the API is down
$html = show();
check( false !== strpos( $html, esc_html( $day['lines'][1]['gurmukhi'] ) ), 'during an outage the last checked day stays up' );
$GLOBALS['clock'] += 86400;                        // the next day, still down
$html = show();
check( false !== strpos( $html, esc_html( $day['lines'][1]['gurmukhi'] ) ) && false === strpos( $html, 'Today' ), 'and the next day it is no longer called today\'s' );
$GLOBALS['clock'] += 2 * 86400;
check( false === strpos( show(), esc_html( $day['lines'][1]['gurmukhi'] ) ), 'after two days without the API nothing is shown' );
$GLOBALS['clock'] = strtotime( '2026-11-11 02:00:00 UTC' );
fresh( array() );
check( false !== strpos( show(), 'cannot be shown right now' ), 'with no API and no copy it says so' );

// --- a poisoned cache -----------------------------------------------------------------------------
fresh( array( '/v1/hukamnama/today' => $day ) );
set_transient( HUKAMNAMA_SHS_FRESH, $tampered, 300 );
check( false === strpos( show(), esc_html( $day['lines'][1]['gurmukhi'] ) ), 'a cached copy is proved again before every display' );

// --- hostile content ------------------------------------------------------------------------------
$evil = $day;
$evil['lines'][1]['gurmukhi']   = '<script>alert(1)</script>';
$evil['citation']               = '<img src=x onerror=alert(2)>';
$evil['attribution']['text']    = '<b>x</b>';
$evil['attribution']['url']     = 'javascript:alert(3)';
$evil                           = rehash( $evil );
fresh( array( '/v1/hukamnama/today' => $evil ) );
$html = show( array( 'translit' => 'yes' ) );
check( false === strpos( $html, '<script' ) && false === strpos( $html, '<img' ) && false === strpos( $html, '<b>' ), 'markup in an answer is escaped, never rendered' );
check( false === strpos( $html, 'javascript:' ) && false !== strpos( $html, 'href="https://gurbanisoul.com/en/hukamnama"' ), 'a credit link that is not gurbanisoul.com is replaced' );

// --- the embed --------------------------------------------------------------------------------------
fresh( array() );
$html = show( array( 'mode' => 'embed', 'lang' => 'pa', 'theme' => 'dark' ) );
check( false !== strpos( $html, 'src="https://gurbanisoul.com/embed/hukamnama?lang=pa&amp;theme=dark"' ) && array() === $GLOBALS['asked'], 'the embed is a frame of gurbanisoul.com and asks the API nothing' );
$html = show( array( 'mode' => 'embed', 'lang' => '"><script>', 'theme' => 'x' ) );
check( false !== strpos( $html, '?lang=en&amp;theme=auto"' ) && false === strpos( $html, '<script' ), 'unknown embed options fall back to the defaults' );

echo $failed ? "\n$failed test(s) failed\n" : "\nall tests passed\n";
exit( $failed ? 1 : 0 );
