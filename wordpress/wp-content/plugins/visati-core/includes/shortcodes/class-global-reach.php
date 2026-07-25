<?php
/**
 * [visati_global_reach] — animated world-map section (dashed arcs from Dubai
 * to 10 regions + pulsing pins), ported faithfully from the original
 * Next.js GlobalReach component (src/components/home/GlobalReach.tsx).
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Global_Reach_Shortcode {

	/**
	 * Pin coordinates on a 107x54 viewBox, matching the original component exactly.
	 */
	const PINS = array(
		'uae' => array( 'cx' => 62, 'cy' => 23, 'label' => 'Dubai, UAE' ),
		'uk'  => array( 'cx' => 53, 'cy' => 14, 'label' => 'United Kingdom' ),
		'us'  => array( 'cx' => 25, 'cy' => 15, 'label' => 'United States' ),
		'ca'  => array( 'cx' => 18, 'cy' => 9,  'label' => 'Canada' ),
		'eu'  => array( 'cx' => 53, 'cy' => 20, 'label' => 'Germany' ),
		'in'  => array( 'cx' => 72, 'cy' => 24, 'label' => 'India' ),
		'jp'  => array( 'cx' => 92, 'cy' => 16, 'label' => 'Japan' ),
		'sg'  => array( 'cx' => 87, 'cy' => 32, 'label' => 'Singapore' ),
		'au'  => array( 'cx' => 95, 'cy' => 42, 'label' => 'Australia' ),
		'za'  => array( 'cx' => 60, 'cy' => 44, 'label' => 'South Africa' ),
		'br'  => array( 'cx' => 36, 'cy' => 40, 'label' => 'Brazil' ),
	);

	const ARC_CURVES = array(
		'uk' => -14, 'us' => 22, 'ca' => -26, 'eu' => 20, 'in' => 6,
		'jp' => -10, 'sg' => -24, 'au' => 16, 'za' => 12, 'br' => 28,
	);

	/**
	 * Per-stat icon paths (lucide-style, 24x24 viewBox), matching the original
	 * component's Users / CheckCircle / Star / Globe icon order exactly.
	 */
	const STAT_ICONS = array(
		'users'        => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
		'star'         => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
		'globe'        => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a14.5 14.5 0 0 1 4 10 14.5 14.5 0 0 1-4 10 14.5 14.5 0 0 1-4-10 14.5 14.5 0 0 1 4-10z"/>',
	);

	public static function init(): void {
		add_shortcode( 'visati_global_reach', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_script( 'visati-global-reach', VISATI_CORE_URL . 'assets/js/global-reach.js', array(), visati_asset_version( 'assets/js/global-reach.js' ), true );
	}

	/**
	 * Quadratic-bezier arc path from Dubai (uae) to a target pin — matches
	 * getArcPath() in the original TSX exactly.
	 */
	private static function arc_path( string $to_id ): string {
		$from = self::PINS['uae'];
		$to   = self::PINS[ $to_id ];
		$mx   = ( $from['cx'] + $to['cx'] ) / 2;
		$my   = ( $from['cy'] + $to['cy'] ) / 2;
		$curve = self::ARC_CURVES[ $to_id ] ?? -8;
		return sprintf( 'M %d %d Q %s %s %d %d', $from['cx'], $from['cy'], $mx, $my + $curve, $to['cx'], $to['cy'] );
	}

	/**
	 * Inner markup of world-map.svg (just the <circle> dots, viewBox 0 0 107 54),
	 * with the outer <svg> wrapper stripped so it can be inlined as a <g> inside
	 * the same SVG as the arcs/pins. Inlining (instead of an <img>) guarantees
	 * the dot background and the animated overlay share one coordinate system
	 * and one preserveAspectRatio calculation, so they can never scale apart.
	 */
	private static function map_dots_markup(): string {
		static $dots = null;
		if ( null !== $dots ) {
			return $dots;
		}
		$path = get_theme_file_path( 'assets/images/world-map.svg' );
		$raw  = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
		$raw  = preg_replace( '/^<svg[^>]*>/', '', $raw, 1 );
		$raw  = preg_replace( '/<\/svg>\s*$/', '', (string) $raw, 1 );
		$dots = (string) $raw;
		return $dots;
	}

	public static function render(): string {
		$s = class_exists( 'Visati_Core_Settings' ) ? array( 'Visati_Core_Settings', 'get' ) : null;
		$g = function ( string $key, string $default ) use ( $s ) {
			return ( $s ? call_user_func( $s, $key, $default ) : '' ) ?: $default;
		};

		$about_url = esc_url( $g( 'global_reach_cta_url', '/about' ) );
		if ( ! preg_match( '#^https?://#', $about_url ) ) {
			$about_url = esc_url( home_url( $about_url ) );
		}
		$label     = $g( 'global_reach_label', 'Global Reach' );
		$title     = $g( 'global_reach_title', 'Trusted by Applicants Across the Globe' );
		$desc      = $g( 'global_reach_desc', 'We are a Dubai based visa agency serving residents of all nationalities across USA, UK, Canada, Australia and Europe.' );
		$cta_label = $g( 'global_reach_cta_label', 'More About Us' );

		$stats = array(
			array( 'number' => $g( 'global_reach_stat1_number', '1000+' ), 'label' => $g( 'global_reach_stat1_label', 'Happy Travelers' ), 'icon' => 'users' ),
			array( 'number' => $g( 'global_reach_stat2_number', '99%' ),   'label' => $g( 'global_reach_stat2_label', 'Approval Rate' ),   'icon' => 'check-circle' ),
			array( 'number' => $g( 'global_reach_stat3_number', '100+' ),  'label' => $g( 'global_reach_stat3_label', 'Google Reviews' ),  'icon' => 'star' ),
			array( 'number' => $g( 'global_reach_stat4_number', '4.9/5' ), 'label' => $g( 'global_reach_stat4_label', 'Customer Rating' ), 'icon' => 'globe' ),
		);

		ob_start();
		?>
		<section class="visati-global-reach">
				<div class="visati-global-reach__panel">
					<div class="visati-global-reach__map-layer" aria-hidden="true">
						<svg viewBox="0 0 107 54" class="visati-global-reach__svg" preserveAspectRatio="xMidYMid meet">
							<g class="visati-global-reach__map-dots"><?php echo self::map_dots_markup(); // phpcs:ignore WordPress.Security.EscapeOutput -- static local SVG asset, not user input. ?></g>

							<?php foreach ( self::PINS as $id => $pin ) :
								if ( 'uae' === $id ) { continue; }
								$path = self::arc_path( $id );
							?>
								<g>
									<path d="<?php echo esc_attr( $path ); ?>" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="0.25" stroke-linecap="round"></path>
									<path d="<?php echo esc_attr( $path ); ?>" fill="none" stroke="rgba(201,168,76,0.4)" stroke-width="0.3" stroke-linecap="round" stroke-dasharray="3 7" class="visati-global-reach__arc-dash"></path>
								</g>
							<?php endforeach; ?>

							<?php foreach ( self::PINS as $id => $pin ) : ?>
								<circle cx="<?php echo esc_attr( $pin['cx'] ); ?>" cy="<?php echo esc_attr( $pin['cy'] ); ?>" r="0.8" fill="#C49A2C" stroke="#e8d5a0" stroke-width="0.3"></circle>
							<?php endforeach; ?>
						</svg>
					</div>

					<div class="visati-global-reach__content">
						<div class="visati-global-reach__copy">
							<p class="visati-global-reach__label"><?php echo esc_html( $label ); ?></p>
							<h2 class="visati-global-reach__title"><?php echo esc_html( $title ); ?></h2>
							<p class="visati-global-reach__desc"><?php echo esc_html( $desc ); ?></p>
							<a href="<?php echo $about_url; ?>" class="visati-global-reach__cta"><?php echo esc_html( $cta_label ); ?></a>
						</div>

						<div class="visati-global-reach__stats">
							<?php foreach ( $stats as $stat ) : ?>
								<div class="visati-global-reach__stat">
									<div class="visati-global-reach__stat-icon" aria-hidden="true">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo self::STAT_ICONS[ $stat['icon'] ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed internal icon-path constant, not user input. ?></svg>
									</div>
									<div>
										<p class="visati-global-reach__stat-num" data-count-text="<?php echo esc_attr( $stat['number'] ); ?>">0</p>
										<p class="visati-global-reach__stat-label"><?php echo esc_html( $stat['label'] ); ?></p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
		</section>
		<?php
		if ( ! wp_script_is( 'visati-global-reach', 'enqueued' ) ) {
			wp_enqueue_script( 'visati-global-reach' );
		}
		return (string) ob_get_clean();
	}
}
