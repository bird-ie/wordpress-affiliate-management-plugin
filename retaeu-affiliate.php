<?php
/**
 * Plugin Name:       RetaEU Affiliate System
 * Description:       Custom affiliate program with WooCommerce coupon integration, dashboards, admin management, payouts, and reporting.
 * Version:           1.0.0
 * Author:            RetaEU
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       retaeu-affiliate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ------------------------------------------------------------------
// WooCommerce dependency check + loader
// ------------------------------------------------------------------
add_action( 'plugins_loaded', 'retaeu_affiliate_init', 5 );

function retaeu_affiliate_init() {

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>RetaEU Affiliate System</strong> requires WooCommerce to be installed and active.</p></div>';
        } );
        return;
    }

    $dir = plugin_dir_path( __FILE__ ) . 'includes/';

    // ------------------------------------------------------------------
    // Load order matters:
    //   1. Core DB + coupon functions (class-db.php) must exist first.
    //   2. admin-affiliates.php defines act_core_affiliates_admin_page(),
    //      which class-db.php checks via function_exists() before
    //      registering the top-level admin menu.
    //   3. Everything else loads after that.
    // ------------------------------------------------------------------
    require_once $dir . 'class-db.php';                 // Snippet 1
    require_once $dir . 'admin-affiliates.php';         // Snippet 2
    require_once $dir . 'frontend-shortcodes.php';      // Snippet 3
    require_once $dir . 'admin-orders.php';             // Snippet 4
    require_once $dir . 'shortcode-share.php';          // Snippet 5
    require_once $dir . 'shortcode-partner-page.php';   // Snippet 6  (RetaEU landing)
    require_once $dir . 'shortcode-login-page.php';     // Snippet 7  (RetaEU login)
    require_once $dir . 'admin-reassign-box.php';       // Snippet 8
    require_once $dir . 'my-account-banner.php';        // Snippet 9
    require_once $dir . 'admin-selective-payout.php';   // Snippet 10
    require_once $dir . 'admin-manage-users.php';       // Snippet 11
}