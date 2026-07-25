<?php
/**
 * Tracking injection — GA4, GTM, Facebook Pixel, TikTok from admin settings.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Visati_Core_Tracking {

	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'inject_head' ), 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'inject_body_open' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'inject_footer' ), 999 );
	}

	/**
	 * GA4 gtag.js — goes in <head>.
	 */
	public static function inject_head(): void {
		$ga4_id = Visati_Core_Settings::get( 'ga4_id', '' );
		if ( ! $ga4_id ) {
			return;
		}
		?>
<!-- Google Analytics 4 (GA4) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4_id ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?php echo esc_js( $ga4_id ); ?>');
</script>
		<?php
	}

	/**
	 * GTM <noscript> iframe — goes right after <body>.
	 */
	public static function inject_body_open(): void {
		$gtm_id = Visati_Core_Settings::get( 'gtm_container_id', '' );
		if ( ! $gtm_id ) {
			return;
		}
		?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
		<?php
	}

	/**
	 * GTM <script> + Facebook Pixel + TikTok Pixel — goes in </body>.
	 */
	public static function inject_footer(): void {
		$gtm_id = Visati_Core_Settings::get( 'gtm_container_id', '' );
		$fb_id  = Visati_Core_Settings::get( 'facebook_pixel_id', '' );
		$tiktok_id = Visati_Core_Settings::get( 'tiktok_pixel_id', '' );

		if ( $gtm_id ) :
		?>
<!-- Google Tag Manager (script) -->
<script>
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');
</script>
		<?php
		endif;

		if ( $fb_id ) :
		?>
<!-- Facebook Pixel -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo esc_js( $fb_id ); ?>');
fbq('track', 'PageView');
</script>
		<?php
		endif;

		if ( $tiktok_id ) :
		?>
<!-- TikTok Pixel -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){var e=ttq._i[t]||[];for(var n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._i=ttq._i,ttq._i[e].partner=o,ttq._o=ttq._o||{},ttq._o[e]=n||{};var a=document.createElement("script");a.type="text/javascript",a.async=!0,a.src=i+"?sdkid="+e+"&lib="+t;var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(a,s)};

  ttq.load('<?php echo esc_js( $tiktok_id ); ?>');
  ttq.page();
}(window, document, 'ttq');
</script>
		<?php
		endif;
	}
}
