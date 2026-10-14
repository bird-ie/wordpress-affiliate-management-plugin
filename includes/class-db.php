<?php
/**
 * Snippet 1: Affiliate Commission Tracker - Core System
 * Version: 5.2 - Admin menu registers only if page callbacks exist
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ACT_VERSION', '5.2');

// ==============================================
// DATABASE SETUP
// ==============================================

function act_core_check_and_create_tables() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'act_affiliates';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $affiliates_table = $wpdb->prefix . 'act_affiliates';
        $sql1 = "CREATE TABLE IF NOT EXISTS $affiliates_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            affiliate_code varchar(50) NOT NULL,
            commission_rate decimal(5,2) NOT NULL DEFAULT 15.00,
            total_earned decimal(10,2) NOT NULL DEFAULT 0.00,
            total_paid decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            coupon_id bigint(20) DEFAULT NULL,
            customer_discount_rate decimal(5,2) NOT NULL DEFAULT 5.00,
            affiliate_status varchar(20) DEFAULT 'pending',
            social_facebook varchar(255) DEFAULT NULL,
            social_instagram varchar(255) DEFAULT NULL,
            social_twitter varchar(255) DEFAULT NULL,
            social_tiktok varchar(255) DEFAULT NULL,
            social_youtube varchar(255) DEFAULT NULL,
            social_linkedin varchar(255) DEFAULT NULL,
            social_website varchar(255) DEFAULT NULL,
            social_pinterest varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY affiliate_code (affiliate_code),
            KEY user_id (user_id),
            KEY coupon_id (coupon_id)
        ) $charset_collate;";
        
        $referrals_table = $wpdb->prefix . 'act_referrals';
        $sql2 = "CREATE TABLE IF NOT EXISTS $referrals_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            affiliate_id int(11) NOT NULL,
            order_id bigint(20) NOT NULL,
            order_number varchar(100) NOT NULL,
            customer_first_name varchar(100),
            customer_last_name varchar(100),
            customer_email varchar(200),
            order_amount decimal(10,2) NOT NULL,
            commission_amount decimal(10,2) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY order_id (order_id),
            KEY order_number (order_number)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        
        if (!get_role('affiliate')) {
            add_role('affiliate', 'Affiliate', array(
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'upload_files' => false,
            ));
        }
    } else {
        act_core_upgrade_existing_table();
    }
}
add_action('init', 'act_core_check_and_create_tables');

function act_core_upgrade_existing_table() {
    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    
    $existing_columns = $wpdb->get_col("DESCRIBE $affiliates_table");
    
    if (!in_array('coupon_id', $existing_columns)) {
        $wpdb->query("ALTER TABLE $affiliates_table ADD COLUMN coupon_id bigint(20) DEFAULT NULL");
    }
    
    if (!in_array('customer_discount_rate', $existing_columns)) {
        $wpdb->query("ALTER TABLE $affiliates_table ADD COLUMN customer_discount_rate decimal(5,2) NOT NULL DEFAULT 5.00");
    }
    
    if (!in_array('affiliate_status', $existing_columns)) {
        $wpdb->query("ALTER TABLE $affiliates_table ADD COLUMN affiliate_status varchar(20) DEFAULT 'pending'");
    }
    
    $social_fields = ['social_facebook', 'social_instagram', 'social_twitter', 'social_tiktok', 
                      'social_youtube', 'social_linkedin', 'social_website', 'social_pinterest'];
    
    foreach ($social_fields as $field) {
        if (!in_array($field, $existing_columns)) {
            $wpdb->query("ALTER TABLE $affiliates_table ADD COLUMN $field varchar(255) DEFAULT NULL");
        }
    }
}

// ==============================================
// COUPON FUNCTIONS
// ==============================================

function act_core_create_coupon($affiliate_code, $discount_rate = 5, $affiliate_id = null) {
    if (!function_exists('WC') || !class_exists('WC_Coupon')) {
        return false;
    }
    
    $affiliate_code = strtoupper($affiliate_code);
    $discount_rate = floatval($discount_rate);
    
    $existing_coupon_id = function_exists('wc_get_coupon_id_by_code') ? wc_get_coupon_id_by_code($affiliate_code) : false;
    
    if ($existing_coupon_id) {
        try {
            $coupon = new WC_Coupon($existing_coupon_id);
            $coupon->set_amount($discount_rate);
            $coupon->save();
            
            if ($affiliate_id) {
                global $wpdb;
                $wpdb->update($wpdb->prefix . 'act_affiliates', 
                    array('coupon_id' => $existing_coupon_id, 'customer_discount_rate' => $discount_rate), 
                    array('id' => $affiliate_id)
                );
            }
            return $existing_coupon_id;
        } catch (Exception $e) {
            return false;
        }
    }
    
    try {
        $coupon = new WC_Coupon();
        $coupon->set_code($affiliate_code);
        $coupon->set_discount_type('percent');
        $coupon->set_amount($discount_rate);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit_per_user(1);
        $coupon->set_description('Affiliate coupon for ' . $affiliate_code);
        $coupon_id = $coupon->save();
        
        if ($coupon_id && $affiliate_id) {
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'act_affiliates', 
                array('coupon_id' => $coupon_id, 'customer_discount_rate' => $discount_rate), 
                array('id' => $affiliate_id)
            );
        }
        
        return $coupon_id;
    } catch (Exception $e) {
        return false;
    }
}

// ==============================================
// TRACKING FUNCTIONS
// ==============================================

function act_core_track_click() {
    if (isset($_GET['ref']) && !empty($_GET['ref'])) {
        $affiliate_code = sanitize_text_field($_GET['ref']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'act_affiliates';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE affiliate_code = %s",
            $affiliate_code
        ));
        
        if ($exists) {
            setcookie('act_affiliate_ref', $affiliate_code, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE['act_affiliate_ref'] = $affiliate_code;
            
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('act_affiliate_ref', $affiliate_code);
            }
        }
    }
}
add_action('init', 'act_core_track_click', 1);

function act_core_get_current_code() {
    if (isset($_POST['act_affiliate_code']) && !empty($_POST['act_affiliate_code'])) {
        return sanitize_text_field($_POST['act_affiliate_code']);
    }
    
    if (function_exists('WC') && WC()->session) {
        $session_code = WC()->session->get('act_affiliate_ref');
        if (!empty($session_code)) {
            return sanitize_text_field($session_code);
        }
    }
    
    if (isset($_COOKIE['act_affiliate_ref'])) {
        return sanitize_text_field($_COOKIE['act_affiliate_ref']);
    }
    
    return false;
}

function act_core_add_code_to_order($order_id) {
    $affiliate_code = act_core_get_current_code();
    
    if (!$affiliate_code) {
        return;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $order->update_meta_data('_act_affiliate_code', $affiliate_code);
    $order->save();
}
add_action('woocommerce_checkout_order_processed', 'act_core_add_code_to_order', 10, 1);
add_action('woocommerce_store_api_checkout_order_processed', 'act_core_add_code_to_order', 10, 1);

// ==============================================
// REFERRAL PROCESSING
// ==============================================

function act_core_process_referral($order_id, $old_status, $new_status) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $referrals_table = $wpdb->prefix . 'act_referrals';
    
    $affiliate_code = $order->get_meta('_act_affiliate_code');
    
    // If no code in meta, check coupons
    if (!$affiliate_code) {
        $applied_coupons = $order->get_coupons();
        foreach ($applied_coupons as $coupon) {
            $coupon_code = $coupon->get_code();
            $affiliate = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $affiliates_table WHERE affiliate_code = %s",
                $coupon_code
            ));
            if ($affiliate) {
                $affiliate_code = $coupon_code;
                $order->update_meta_data('_act_affiliate_code', $affiliate_code);
                $order->save();
                break;
            }
        }
    }
    
    if (!$affiliate_code) {
        return;
    }
    
    $affiliate = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $affiliates_table WHERE affiliate_code = %s",
        $affiliate_code
    ));
    
    if (!$affiliate || $affiliate->affiliate_status !== 'approved') {
        return;
    }
    
    // Check if referral already exists
    $existing_referral = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $referrals_table WHERE order_id = %d",
        $order_id
    ));
    
    $order_total = $order->get_total();
    
    /*
     * Bundle commission cap.
     *
     * If the order carries the bundle flag (set by the bundle snippet
     * when a bundle was added to the cart), cap the affiliate rate
     * at 10%. Otherwise, use the affiliate's normal commission rate.
     */
    $effective_rate = floatval($affiliate->commission_rate);
    
    if ( strtolower( trim( (string) $order->get_meta( '_retaeu_is_bundle_order' ) ) ) === 'yes' ) {
        if ( $effective_rate > 10 ) {
            $effective_rate = 10;
        }
    }
    
    $commission = $order_total * ( $effective_rate / 100 );
    
    // Determine if this order should count toward earnings (completed or processing only)
    $should_count = ($new_status === 'completed' || $new_status === 'processing');
    $old_should_count = ($old_status === 'completed' || $old_status === 'processing');
    
    if ($existing_referral) {
        // Update existing referral with latest order data
        $old_commission = floatval($existing_referral->commission_amount);
        
        $wpdb->update($referrals_table, array(
            'order_number' => $order->get_order_number(),
            'customer_first_name' => $order->get_billing_first_name(),
            'customer_last_name' => $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'order_amount' => $order_total,
            'commission_amount' => $commission,
        ), array('order_id' => $order_id));
        
        // Adjust total_earned based on status changes
        if ($should_count && !$old_should_count) {
            // Order changed from non-qualifying to qualifying
            $wpdb->query($wpdb->prepare(
                "UPDATE $affiliates_table SET total_earned = total_earned + %f WHERE id = %d",
                $commission,
                $affiliate->id
            ));
        } elseif (!$should_count && $old_should_count) {
            // Order changed from qualifying to non-qualifying (cancelled/refunded)
            $wpdb->query($wpdb->prepare(
                "UPDATE $affiliates_table SET total_earned = total_earned - %f WHERE id = %d",
                $old_commission,
                $affiliate->id
            ));
        } elseif ($should_count && $old_should_count && abs($commission - $old_commission) > 0.01) {
            // Commission changed (order total modified)
            $diff = $commission - $old_commission;
            $wpdb->query($wpdb->prepare(
                "UPDATE $affiliates_table SET total_earned = total_earned + %f WHERE id = %d",
                $diff,
                $affiliate->id
            ));
        }
    } else {
        // Insert new referral
        $wpdb->insert($referrals_table, array(
            'affiliate_id' => $affiliate->id,
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'customer_first_name' => $order->get_billing_first_name(),
            'customer_last_name' => $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'order_amount' => $order_total,
            'commission_amount' => $commission,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ));
        
        // Only add to total_earned if order qualifies
        if ($should_count) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $affiliates_table SET total_earned = total_earned + %f WHERE id = %d",
                $commission,
                $affiliate->id
            ));
        }
    }
    
    update_post_meta($order_id, '_act_referral_processed', true);
}
add_action('woocommerce_order_status_changed', 'act_core_process_referral', 10, 3);

// ==============================================
// ADMIN ORDER DISPLAY
// ==============================================

function act_core_admin_order_meta($order) {
    $affiliate_code = $order->get_meta('_act_affiliate_code');
    if ($affiliate_code) {
        global $wpdb;
        $affiliates_table = $wpdb->prefix . 'act_affiliates';
        $affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, commission_rate FROM $affiliates_table WHERE affiliate_code = %s",
            $affiliate_code
        ));
        
        if ($affiliate) {
            $user = get_userdata($affiliate->user_id);
            echo '<div class="address"><p><strong>Affiliate Information:</strong><br>';
            echo 'Affiliate: ' . ($user ? $user->display_name : 'N/A') . '<br>';
            echo 'Affiliate Code: <code>' . esc_html($affiliate_code) . '</code><br>';
            echo 'Commission Rate: ' . esc_html($affiliate->commission_rate) . '%</p></div>';
        }
    }
}
add_action('woocommerce_admin_order_data_after_order_details', 'act_core_admin_order_meta');

// ==============================================
// ADMIN MENUS
// ==============================================

add_action('admin_menu', 'act_core_add_admin_menus');
function act_core_add_admin_menus() {
    if ( ! function_exists( 'act_core_affiliates_admin_page' ) ) {
        return;
    }

    add_menu_page(
        'Affiliates',
        'Affiliates',
        'manage_options',
        'act-affiliates',
        'act_core_affiliates_admin_page',
        'dashicons-groups',
        30
    );

    if ( function_exists( 'act_core_settings_page' ) ) {
        add_submenu_page(
            'act-affiliates',
            'Settings',
            'Settings',
            'manage_options',
            'act-settings',
            'act_core_settings_page'
        );
    }
}

return;