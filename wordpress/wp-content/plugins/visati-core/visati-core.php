<?php
/**
 * Plugin Name:       Visati Core
 * Plugin URI:        https://visati.ae
 * Description:        Core engine for the Visati WordPress build — CPTs (visa types, FAQ, testimonials, jobs, discounts), custom tables (applications, status history, documents, payments, inquiries), REST API, Stripe payments, Cloudflare R2 document storage, email notifications, admin dashboard, and Elementor widgets/shortcodes.
 * Version:           0.2.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Visati
 * Author URI:        https://visati.ae
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       visati-core
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VISATI_CORE_VERSION', '0.2.0' );
define( 'VISATI_CORE_FILE', __FILE__ );
define( 'VISATI_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'VISATI_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'VISATI_CORE_BASENAME', plugin_basename( __FILE__ ) );

require_once VISATI_CORE_DIR . 'includes/class-visati-core.php';
require_once VISATI_CORE_DIR . 'includes/class-post-types.php';
require_once VISATI_CORE_DIR . 'includes/class-database.php';
require_once VISATI_CORE_DIR . 'includes/class-roles.php';
require_once VISATI_CORE_DIR . 'includes/helpers.php';
require_once VISATI_CORE_DIR . 'includes/class-settings.php';
require_once VISATI_CORE_DIR . 'includes/class-rest-api.php';
require_once VISATI_CORE_DIR . 'includes/class-email.php';
require_once VISATI_CORE_DIR . 'includes/class-r2-storage.php';
require_once VISATI_CORE_DIR . 'includes/class-stripe-payments.php';
require_once VISATI_CORE_DIR . 'admin/class-settings-page.php';

register_activation_hook( __FILE__, array( 'Visati_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Visati_Core', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Visati_Core', 'instance' ) );
