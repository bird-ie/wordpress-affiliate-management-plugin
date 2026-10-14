<?php
/**
 * Snippet 3: Affiliate System - Dashboard & Frontend Shortcodes
 * Place after Snippet 2
 */

// Register shortcodes
add_shortcode('affiliate_dashboard', 'act_core_dashboard');
add_shortcode('act_affiliate_dashboard', 'act_core_dashboard');
add_shortcode('affiliate_dashboard_page', 'act_core_dashboard');
add_shortcode('act_my_affiliate_orders', 'act_orders_table');
add_shortcode('affiliate_registration_form', 'act_reg_form');
add_shortcode('affiliate_login_form', 'act_login_form');
add_shortcode('affiliate_logout_link', 'act_logout_link');
add_shortcode('affiliate_account_info', 'act_affiliate_account_info');

/**
 * Give the affiliate role access to the affiliate dashboard.
 *
 * This uses a dedicated capability instead of relying solely
 * on the role name.
 */
function act_add_affiliate_dashboard_capability() {

    $affiliate_role = get_role( 'affiliate' );

    if ( $affiliate_role && ! $affiliate_role->has_cap( 'act_access_affiliate_dashboard' ) ) {
        $affiliate_role->add_cap( 'act_access_affiliate_dashboard' );
    }
}
add_action( 'init', 'act_add_affiliate_dashboard_capability' );

/**
 * Check whether the current user is an approved affiliate.
 *
 * Requires:
 * - Logged-in WordPress user
 * - Affiliate capability
 * - Matching affiliate database record
 * - Approved affiliate status
 *
 * @return object|false Affiliate row on success, false otherwise.
 */
function act_get_current_approved_affiliate() {

    if ( ! is_user_logged_in() ) {
        return false;
    }

    $user = wp_get_current_user();

    if ( ! $user || ! $user->ID ) {
        return false;
    }

    /*
     * Affiliate role/capability check.
     *
     * We use a custom capability rather than relying only on the
     * role name, which follows WordPress's recommended permission model.
     */
    if ( ! current_user_can( 'act_access_affiliate_dashboard' ) ) {
        return false;
    }

    global $wpdb;

    $affiliates_table = $wpdb->prefix . 'act_affiliates';

    $affiliate = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$affiliates_table}
             WHERE user_id = %d
             LIMIT 1",
            $user->ID
        )
    );

    if ( ! $affiliate ) {
        return false;
    }

    if ( 'approved' !== strtolower( trim( (string) $affiliate->affiliate_status ) ) ) {
        return false;
    }

    return $affiliate;
}

// ==============================================
// AFFILIATE DASHBOARD SHORTCODE
// ==============================================

function act_core_dashboard() {

    /*
     * 1. Not logged in:
     * Redirect directly to the affiliate login page.
     */
    if ( ! is_user_logged_in() ) {

        nocache_headers();

        wp_safe_redirect(
            home_url( '/affiliate-login/' ),
            302
        );

        exit;
    }

    global $wpdb;

    $current_user = wp_get_current_user();

    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $referrals_table  = $wpdb->prefix . 'act_referrals';

    /*
     * 2. Verify that this WordPress user is actually
     * an approved affiliate.
     */
    $affiliate = act_get_current_approved_affiliate();

    /*
     * Logged-in user but not an approved affiliate.
     *
     * We deliberately do NOT expose the affiliate dashboard.
     */
    if ( ! $affiliate ) {

        /*
         * Check whether the user has an affiliate record so
         * we can give the appropriate response for pending/rejected
         * users instead of treating everyone identically.
         */
        $affiliate_record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$affiliates_table}
                 WHERE user_id = %d
                 LIMIT 1",
                $current_user->ID
            )
        );

        /*
         * No affiliate record:
         * send them to registration.
         */
        if ( ! $affiliate_record ) {
            return '<p>You are not registered as an affiliate. <a href="' .
                esc_url( home_url( '/affiliate-register/' ) ) .
                '">Register here</a>.</p>';
        }

        /*
         * Pending affiliate.
         */
        if ( 'pending' === strtolower( trim( (string) $affiliate_record->affiliate_status ) ) ) {
            return '<div style="background:#fff3cd;padding:20px;border-radius:8px;text-align:center"><h3>⏳ Account Pending Approval</h3><p>Your affiliate account is pending approval. You will receive an email once approved.</p></div>';
        }

        /*
         * Rejected affiliate.
         */
        if ( 'rejected' === strtolower( trim( (string) $affiliate_record->affiliate_status ) ) ) {
            return '<div style="background:#f8d7da;padding:20px;border-radius:8px;text-align:center"><h3>❌ Account Rejected</h3><p>Your affiliate application has been rejected. Please contact support.</p></div>';
        }

        /*
         * Any unexpected/non-approved status:
         * do not expose the dashboard.
         */
        return '<p>Your affiliate account does not currently have access to the affiliate dashboard.</p>';
    }

    // Get ALL referrals
    $referrals = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $referrals_table WHERE affiliate_id = %d",
        $affiliate->id
    ));
    
    // Calculate totals from actual orders
    $total_commission = 0;
    $pending_commission = 0;
    $paid_commission = 0;
    $orders_data = array();
    
    foreach ($referrals as $referral) {
        $order = wc_get_order($referral->order_id);
        if ($order) {
            $order_status = $order->get_status();
            
            // Count completed, processing and labelled orders
            if (in_array($order_status, array('completed', 'processing', 'labelled'), true)) {
                $commission_amount = floatval($referral->commission_amount);
                $total_commission += $commission_amount;
                
                if ($referral->status === 'paid') {
                    $paid_commission += $commission_amount;
                } else {
                    $pending_commission += $commission_amount;
                }
                
                $orders_data[] = array(
                    'referral' => $referral,
                    'order' => $order,
                    'order_number' => $order->get_order_number(),
                    'customer_name' => $referral->customer_first_name . ' ' . $referral->customer_last_name,
                    'order_amount' => floatval($referral->order_amount),
                    'commission_amount' => $commission_amount,
                    'order_status' => $order_status,
                    'commission_status' => $referral->status,
                    'created_at' => strtotime($referral->created_at),
                    'date_display' => date_i18n(get_option('date_format'), strtotime($referral->created_at))
                );
            }
        }
    }
    
    // Get sorting, search, pagination parameters
    $sort_by = isset($_GET['act_sort']) ? sanitize_text_field($_GET['act_sort']) : 'created_at';
    $sort_order = isset($_GET['act_order']) && $_GET['act_order'] === 'asc' ? 'asc' : 'desc';
    $search_term = isset($_GET['act_search']) ? sanitize_text_field($_GET['act_search']) : '';
    $current_page = isset($_GET['act_page']) ? max(1, intval($_GET['act_page'])) : 1;
    $per_page = isset($_GET['act_per_page']) ? intval($_GET['act_per_page']) : 10;
    
    $allowed_per_page = array(10, 25, 50, 100);
    if (!in_array($per_page, $allowed_per_page)) {
        $per_page = 10;
    }
    
    // Apply search filter
    if (!empty($search_term)) {
        $orders_data = array_filter($orders_data, function($item) use ($search_term) {
            return stripos($item['order_number'], $search_term) !== false || 
                   stripos($item['customer_name'], $search_term) !== false;
        });
    }
    
    // Sort orders
    usort($orders_data, function($a, $b) use ($sort_by, $sort_order) {
        $result = 0;
        switch($sort_by) {
            case 'order_number':
                $result = strcmp($a['order_number'], $b['order_number']);
                break;
            case 'customer_name':
                $result = strcmp($a['customer_name'], $b['customer_name']);
                break;
            case 'order_amount':
                $result = $a['order_amount'] <=> $b['order_amount'];
                break;
            case 'commission_amount':
                $result = $a['commission_amount'] <=> $b['commission_amount'];
                break;
            case 'order_status':
                $status_priority = array(
                    'completed' => 1,
                    'processing' => 2,
                    'labelled' => 3,
                    'on-hold' => 4,
                    'pending' => 5,
                    'refunded' => 6,
                    'failed' => 7
                );
                $priority_a = isset($status_priority[$a['order_status']]) ? $status_priority[$a['order_status']] : 8;
                $priority_b = isset($status_priority[$b['order_status']]) ? $status_priority[$b['order_status']] : 8;
                $result = $priority_a <=> $priority_b;
                break;
            case 'commission_status':
                $status_priority = array('paid' => 1, 'pending' => 2);
                $priority_a = isset($status_priority[$a['commission_status']]) ? $status_priority[$a['commission_status']] : 2;
                $priority_b = isset($status_priority[$b['commission_status']]) ? $status_priority[$b['commission_status']] : 2;
                $result = $priority_a <=> $priority_b;
                break;
            case 'created_at':
            default:
                $result = $a['created_at'] <=> $b['created_at'];
                break;
        }
        return $sort_order === 'asc' ? $result : -$result;
    });
    
    $orders_data = array_values($orders_data);
    $total_items = count($orders_data);
    $total_pages = ceil($total_items / $per_page);
    $offset = ($current_page - 1) * $per_page;
    $current_orders = array_slice($orders_data, $offset, $per_page);
    
    $customer_discount = isset($affiliate->customer_discount_rate) ? $affiliate->customer_discount_rate : 5;
    $referral_link = add_query_arg('ref', $affiliate->affiliate_code, home_url());
    
    $sort_url = function($column) use ($sort_by, $sort_order, $search_term, $current_page, $per_page) {
        $new_order = ($sort_by === $column && $sort_order === 'desc') ? 'asc' : 'desc';
        $args = array('act_sort' => $column, 'act_order' => $new_order, 'act_page' => $current_page, 'act_per_page' => $per_page);
        if (!empty($search_term)) $args['act_search'] = $search_term;
        return add_query_arg($args);
    };
    
    $sort_indicator = function($column) use ($sort_by, $sort_order) {
        if ($sort_by !== $column) return '<span class="act-sort-icon">↕️</span>';
        return $sort_order === 'desc' ? '<span class="act-sort-icon act-sort-active">↓</span>' : '<span class="act-sort-icon act-sort-active">↑</span>';
    };
    
    $socials = array(
        'facebook' => $affiliate->social_facebook, 'instagram' => $affiliate->social_instagram,
        'twitter' => $affiliate->social_twitter, 'tiktok' => $affiliate->social_tiktok,
        'youtube' => $affiliate->social_youtube, 'linkedin' => $affiliate->social_linkedin,
        'website' => $affiliate->social_website, 'pinterest' => $affiliate->social_pinterest
    );
    
    ob_start();
    ?>
    <style>
        .act-dashboard-wrap { width: 100%; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .act-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        @media (max-width: 860px) { .act-stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 520px) { .act-stats-grid { grid-template-columns: 1fr; } }
        .act-stat-card { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; padding: 20px; text-align: center; box-shadow: 0 1px 6px rgba(0,0,0,.06); }
        .act-stat-card h3 { margin: 0 0 10px; font-size: 13px; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .act-stat-card .stat-value { font-size: 32px; font-weight: 800; color: #0073aa; line-height: 1.2; }
        .act-code-hero { background: linear-gradient(135deg, #0073aa 0%, #005a87 100%); border-radius: 20px; padding: 30px; margin-bottom: 30px; text-align: center; color: white; position: relative; overflow: hidden; }
        .act-code-hero::before { content: "🎯"; position: absolute; font-size: 120px; opacity: 0.1; bottom: -20px; right: -20px; transform: rotate(-15deg); }
        .act-code-hero h2 { margin: 0 0 10px; font-size: 18px; font-weight: 500; letter-spacing: 2px; opacity: 0.9; color: white; }
        .act-code-display { background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 20px 30px; border-radius: 16px; display: inline-block; margin: 15px 0; }
        .act-code-value { font-size: 48px; font-weight: 800; font-family: monospace; letter-spacing: 4px; background: white; color: #0073aa; padding: 15px 30px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .act-copy-btn { background: white; color: #0073aa; border: none; padding: 10px 25px; border-radius: 30px; font-size: 14px; font-weight: 600; cursor: pointer; margin-left: 15px; transition: all 0.2s; }
        .act-copy-btn:hover { transform: scale(1.05); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .act-coupon-card { background: #fff; border: 2px solid #000; border-radius: 16px; padding: 20px; margin-bottom: 30px; text-align: center; position: relative; }
        .act-coupon-badge { position: absolute; top: -12px; left: 20px; background: #000; color: white; font-size: 12px; font-weight: 700; padding: 4px 16px; border-radius: 20px; }
        .act-coupon-code { font-size: 32px; font-weight: 800; font-family: monospace; color: #000; background: #f0fdf4; padding: 12px 24px; border-radius: 12px; display: inline-block; letter-spacing: 2px; }
        .act-link-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 30px; margin-top: 30px; }
        .act-link-display { background: white; padding: 12px 15px; border-radius: 10px; word-break: break-all; font-family: monospace; font-size: 14px; border: 1px solid #e2e8f0; }
        .act-social-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 20px; margin-bottom: 30px; }
        .act-social-section h3 { margin: 0 0 15px; font-size: 16px; color: #333; }
        .act-social-grid { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
        .act-social-icon-link { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f1f5f9; border-radius: 40px; text-decoration: none; color: #333; font-size: 14px; transition: all 0.2s; }
        .act-social-icon-link:hover { transform: translateY(-2px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .act-orders-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; margin-top: 20px; }
        .act-orders-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 15px; }
        .act-orders-header h3 { margin: 0; font-size: 16px; }
        .act-search-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .act-search-input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; width: 200px; }
        .act-search-btn, .act-reset-btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; cursor: pointer; border: none; }
        .act-search-btn { background: #0073aa; color: white; }
        .act-reset-btn { background: #e2e8f0; color: #333; text-decoration: none; }
        .act-pagination-wrapper { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 15px; }
        .act-pagination-links { display: flex; gap: 5px; align-items: center; flex-wrap: wrap; }
        .act-pagination-links a, .act-pagination-links span { display: inline-block; padding: 6px 12px; margin: 0 2px; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #475569; font-size: 13px; }
        .act-pagination-links a:hover { background: #0073aa; border-color: #0073aa; color: white; }
        .act-pagination-links .current { background: #0073aa; border-color: #0073aa; color: white; font-weight: 600; }
        .act-per-page-selector select { padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 6px; background: white; cursor: pointer; }
        .act-orders-table { width: 100%; border-collapse: collapse; }
        .act-orders-table th { background: #f8fafc; padding: 12px 15px; text-align: left; font-weight: 600; font-size: 13px; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .act-orders-table th a { text-decoration: none; color: #475569; display: inline-flex; align-items: center; gap: 6px; }
        .act-orders-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .act-status-completed { color: #16a34a; font-weight: 600; }
        .act-status-processing { color: #f59e0b; font-weight: 600; }
        .act-status-labelled { color: #0b4f6c; font-weight: 600; }
        .act-commission-paid { color: #16a34a; font-weight: 700; }
        .act-commission-pending { color: #f59e0b; font-weight: 700; }
        .act-edit-profile-link { display: inline-block; margin-top: 15px; color: white; background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 30px; text-decoration: none; font-size: 14px; transition: transform 0.2s ease, box-shadow 0.2s ease; }
		.act-edit-profile-link:hover { color: white; background: rgb(0 0 0 / 23%); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(255, 255, 255, 0.25); }
        .act-sort-icon { font-size: 12px; opacity: 0.5; display: inline-block; }
        .act-sort-active { opacity: 1; font-weight: bold; color: #0073aa; }
        .act-no-orders { text-align: center; padding: 40px; color: #6b7280; }
        .act-dashboard-topbar { display:flex; justify-content:space-between; align-items:center; gap:15px; background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:14px 18px; margin-bottom:20px; box-shadow:0 1px 6px rgba(0,0,0,.05); }
        .act-dashboard-user { font-size:14px; color:#475569; }
        .act-dashboard-logout { background:#dc2626; color:#fff !important; padding:9px 18px; border-radius:30px; text-decoration:none; font-size:14px; font-weight:700; }
        .act-dashboard-logout:hover { background:#b91c1c; color:#fff !important; }
    </style>
    
    <div class="act-dashboard-wrap">
        <div class="act-dashboard-topbar">
            <div class="act-dashboard-user">
                Logged in as <strong><?php echo esc_html($current_user->display_name); ?></strong>
            </div>
            <a href="<?php echo esc_url(wp_logout_url(home_url('/affiliate-login?logged_out=1'))); ?>" class="act-dashboard-logout">
                Logout
            </a>
        </div>

        <div class="act-code-hero">
            <h2>YOUR AFFILIATE CODE</h2>
            <div class="act-code-display">
                <span class="act-code-value" id="affiliate-code-display"><?php echo esc_html($affiliate->affiliate_code); ?></span>
                <button class="act-copy-btn" onclick="copyAffiliateCode()">Copy Code</button>
            </div>
            <p>Share this code with customers to earn commissions!</p>
            <a href="/affiliate-account" class="act-edit-profile-link">Edit Profile & Change Code →</a>
        </div>
        
        <div class="act-stats-grid">
            <div class="act-stat-card"><h3>Commission Rate</h3><div class="stat-value"><?php echo $affiliate->commission_rate; ?>%</div></div>
            <div class="act-stat-card"><h3>Customer Discount</h3><div class="stat-value"><?php echo $customer_discount; ?>%</div></div>
            <div class="act-stat-card"><h3>Total Earned</h3><div class="stat-value">€<?php echo number_format($total_commission, 2); ?></div></div>
            <div class="act-stat-card"><h3>Pending</h3><div class="stat-value">€<?php echo number_format($pending_commission, 2); ?></div></div>
        </div>
        
        <div class="act-orders-section">
            <div class="act-orders-header">
                <h3>Your Referral Orders</h3>
                <form method="get" class="act-search-form">
                    <?php if (isset($_GET['act_sort'])) echo '<input type="hidden" name="act_sort" value="' . esc_attr($_GET['act_sort']) . '">'; ?>
                    <?php if (isset($_GET['act_order'])) echo '<input type="hidden" name="act_order" value="' . esc_attr($_GET['act_order']) . '">'; ?>
                    <?php if (isset($_GET['act_page'])) echo '<input type="hidden" name="act_page" value="' . esc_attr($_GET['act_page']) . '">'; ?>
                    <?php if (isset($_GET['act_per_page'])) echo '<input type="hidden" name="act_per_page" value="' . esc_attr($_GET['act_per_page']) . '">'; ?>
                    <input type="text" name="act_search" class="act-search-input" placeholder="🔍 Search by Order # or Customer..." value="<?php echo esc_attr($search_term); ?>">
                    <button type="submit" class="act-search-btn">Search</button>
                    <?php if (!empty($search_term)): ?><a href="?" class="act-reset-btn">Reset</a><?php endif; ?>
                </form>
            </div>

            <div class="act-orders-table-wrapper">
                <?php if (!empty($current_orders)): ?>
                    <table class="act-orders-table">
                        <thead>
                            <tr>
                                <th><a href="<?php echo esc_url($sort_url('order_number')); ?>">Order # <?php echo $sort_indicator('order_number'); ?></a></th>
                                <th><a href="<?php echo esc_url($sort_url('customer_name')); ?>">Customer <?php echo $sort_indicator('customer_name'); ?></a></th>
                                <th><a href="<?php echo esc_url($sort_url('order_amount')); ?>">Order Total <?php echo $sort_indicator('order_amount'); ?></a></th>
                                <th><a href="<?php echo esc_url($sort_url('commission_amount')); ?>">Commission <?php echo $sort_indicator('commission_amount'); ?></a></th>
                                <th><a href="<?php echo esc_url($sort_url('order_status')); ?>">Order Status <?php echo $sort_indicator('order_status'); ?></a></th>
                                <th><a href="<?php echo esc_url($sort_url('commission_status')); ?>">Commission Status <?php echo $sort_indicator('commission_status'); ?></a></th>
                                <th><a href="<?php echo esc_url($sort_url('created_at')); ?>">Date <?php echo $sort_indicator('created_at'); ?></a></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_orders as $item): 
                                $status_class = '';

                                if ($item['order_status'] === 'completed') {
                                    $status_class = 'act-status-completed';
                                } elseif ($item['order_status'] === 'processing') {
                                    $status_class = 'act-status-processing';
                                } elseif ($item['order_status'] === 'labelled') {
                                    $status_class = 'act-status-labelled';
                                }
                            ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html($item['order_number']); ?></strong></td>
                                    <td><?php echo esc_html($item['customer_name'] ?: 'Guest'); ?></td>
                                    <td>€<?php echo number_format($item['order_amount'], 2); ?></td>
                                    <td class="act-commission-<?php echo esc_attr($item['commission_status']); ?>">€<?php echo number_format($item['commission_amount'], 2); ?></td>
                                    <td class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucfirst($item['order_status'])); ?></td>
                                    <td class="act-commission-<?php echo esc_attr($item['commission_status']); ?>"><?php echo $item['commission_status'] === 'paid' ? '✓ Paid' : 'Pending'; ?></td>
                                    <td><?php echo esc_html($item['date_display']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" style="text-align:right;"><strong>Total Commission:</strong></td><td colspan="4"><strong>€<?php echo number_format($total_commission, 2); ?></strong></td></tr>
                        </tfoot>
                    </table>
                    
                    <div class="act-pagination-wrapper">
                        <div class="act-per-page-selector">
                            <label>Show:</label>
                            <select id="act-per-page" onchange="changePerPage(this.value)">
                                <option value="10" <?php selected($per_page, 10); ?>>10 per page</option>
                                <option value="25" <?php selected($per_page, 25); ?>>25 per page</option>
                                <option value="50" <?php selected($per_page, 50); ?>>50 per page</option>
                                <option value="100" <?php selected($per_page, 100); ?>>100 per page</option>
                            </select>
                        </div>
                        <div class="act-pagination-info">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_items); ?> of <?php echo $total_items; ?> orders</div>
                        <div class="act-pagination-links">
                            <?php
                            $base_args = array();
                            if (!empty($search_term)) $base_args['act_search'] = $search_term;
                            if (isset($_GET['act_sort'])) $base_args['act_sort'] = $_GET['act_sort'];
                            if (isset($_GET['act_order'])) $base_args['act_order'] = $_GET['act_order'];
                            $base_args['act_per_page'] = $per_page;
                            
                            if ($current_page > 1) echo '<a href="' . add_query_arg(array_merge($base_args, array('act_page' => $current_page - 1))) . '">&laquo; Previous</a>';
                            else echo '<span class="disabled">&laquo; Previous</span>';
                            
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) echo '<a href="' . add_query_arg(array_merge($base_args, array('act_page' => 1))) . '">1</a>';
                            if ($start_page > 2) echo '<span>...</span>';

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $current_page) echo '<span class="current">' . $i . '</span>';
                                else echo '<a href="' . add_query_arg(array_merge($base_args, array('act_page' => $i))) . '">' . $i . '</a>';
                            }

                            if ($end_page < $total_pages - 1) echo '<span>...</span>';
                            if ($end_page < $total_pages) echo '<a href="' . add_query_arg(array_merge($base_args, array('act_page' => $total_pages))) . '">' . $total_pages . '</a>';
                            
                            if ($current_page < $total_pages) echo '<a href="' . add_query_arg(array_merge($base_args, array('act_page' => $current_page + 1))) . '">Next &raquo;</a>';
                            else echo '<span class="disabled">Next &raquo;</span>';
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="act-no-orders"><p>No affiliate orders yet. Share your referral link or coupon code <strong><?php echo esc_html($affiliate->affiliate_code); ?></strong> to start earning!</p></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="act-link-card">
            <h3 style="margin:0 0 10px;">Your Referral Link</h3>
            <div class="act-link-display" id="referral-link-display"><?php echo esc_url($referral_link); ?></div>
            <button class="act-copy-btn" onclick="copyReferralLink()" style="background:#0073aa;color:white;margin-top:15px;">Copy Link</button>
        </div>
        
        <?php if (!empty(array_filter($socials))): ?>
        <div class="act-social-section">
            <h3>Connect With Me</h3>
            <div class="act-social-grid">
                <?php if($socials['facebook']): ?><a href="<?php echo esc_url($socials['facebook']); ?>" target="_blank" class="act-social-icon-link">📘 Facebook</a><?php endif; ?>
                <?php if($socials['instagram']): ?><a href="<?php echo esc_url($socials['instagram']); ?>" target="_blank" class="act-social-icon-link">📷 Instagram</a><?php endif; ?>
                <?php if($socials['twitter']): ?><a href="<?php echo esc_url($socials['twitter']); ?>" target="_blank" class="act-social-icon-link">🐦 Twitter</a><?php endif; ?>
                <?php if($socials['tiktok']): ?><a href="<?php echo esc_url($socials['tiktok']); ?>" target="_blank" class="act-social-icon-link">🎵 TikTok</a><?php endif; ?>
                <?php if($socials['youtube']): ?><a href="<?php echo esc_url($socials['youtube']); ?>" target="_blank" class="act-social-icon-link">📺 YouTube</a><?php endif; ?>
                <?php if($socials['linkedin']): ?><a href="<?php echo esc_url($socials['linkedin']); ?>" target="_blank" class="act-social-icon-link">🔗 LinkedIn</a><?php endif; ?>
                <?php if($socials['website']): ?><a href="<?php echo esc_url($socials['website']); ?>" target="_blank" class="act-social-icon-link">🌐 Website</a><?php endif; ?>
                <?php if($socials['pinterest']): ?><a href="<?php echo esc_url($socials['pinterest']); ?>" target="_blank" class="act-social-icon-link">📌 Pinterest</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="act-coupon-card">
            <div class="act-coupon-badge">CUSTOMER DISCOUNT</div>
            <h3 style="margin:0 0 15px;">Your Coupon Code</h3>
            <div class="act-coupon-code" id="coupon-code-display"><?php echo esc_html($affiliate->affiliate_code); ?></div>
            <button class="act-copy-btn" onclick="copyCouponCode()" style="background:#000;color:white;margin-top:15px;">Copy Coupon</button>
            <p style="margin-top:15px;font-size:13px;color:#666;">Customers get <strong><?php echo $customer_discount; ?>% OFF</strong> when they use this coupon at checkout!</p>
        </div>
    </div>
    
    <script>
        function copyAffiliateCode() {
            var code = document.getElementById('affiliate-code-display').innerText;
            navigator.clipboard.writeText(code).then(function() {
                alert('Affiliate code copied: ' + code);
            });
        }

        function copyCouponCode() {
            var code = document.getElementById('coupon-code-display').innerText;
            navigator.clipboard.writeText(code).then(function() {
                alert('Coupon code copied: ' + code);
            });
        }

        function copyReferralLink() {
            var link = document.getElementById('referral-link-display').innerText;
            navigator.clipboard.writeText(link).then(function() {
                alert('Referral link copied!');
            });
        }

        function changePerPage(value) {
            var url = new URL(window.location.href);
            url.searchParams.set('act_per_page', value);
            url.searchParams.set('act_page', 1);
            window.location.href = url.toString();
        }
    </script>
    <?php
    return ob_get_clean();
}

// ==============================================
// SIMPLE ORDERS TABLE SHORTCODE
// ==============================================

function act_orders_table() {
    if (!is_user_logged_in()) {
        return '<p style="text-align:center;padding:20px;">Please log in to view your affiliate orders.</p>';
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $referrals_table = $wpdb->prefix . 'act_referrals';
    
    $affiliate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $affiliates_table WHERE user_id = %d", $user_id));

    if (!$affiliate) {
        return '<p style="text-align:center;padding:20px;">You are not registered as an affiliate.</p>';
    }
    
    if ($affiliate->affiliate_status !== 'approved') {
        return '<p style="text-align:center;padding:20px;">Your account is pending approval. Orders will appear here once approved.</p>';
    }

    $referrals = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $referrals_table WHERE affiliate_id = %d ORDER BY created_at DESC",
        $affiliate->id
    ));

    ob_start();
    ?>
    <style>
        .act-orders-table-wrapper{overflow-x:auto;margin-top:20px}
        .act-orders-table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden}
        .act-orders-table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600}
        .act-orders-table td{padding:12px;border-bottom:1px solid #e9ecef}
        .act-status-completed{color:#28a745;font-weight:600}
        .act-status-pending{color:#ffc107;font-weight:600}
        .act-status-processing{color:#17a2b8;font-weight:600}
        .act-commission{color:#0073aa;font-weight:700}
        .act-no-orders{text-align:center;padding:40px;background:#f8f9fa;border-radius:8px}
    </style>
    <div class="act-orders-table-wrapper">
        <?php if (!empty($referrals)): ?>
            <table class="act-orders-table">
                <thead><tr><th>Order #</th><th>Customer</th><th>Order Total</th><th>Commission</th><th>Order Status</th><th>Commission Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($referrals as $referral): 
                        $order = wc_get_order($referral->order_id);
                        $order_number = $order ? $order->get_order_number() : $referral->order_number;
                        $order_status = $order ? $order->get_status() : 'unknown';
                        $customer_name = trim($referral->customer_first_name . ' ' . $referral->customer_last_name);
                        if (empty($customer_name)) $customer_name = $referral->customer_email ?: 'Guest';
                    ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($order_number); ?></strong></td>
                            <td><?php echo esc_html($customer_name); ?></td>
                            <td>€<?php echo number_format($referral->order_amount, 2); ?></td>
                            <td class="act-commission">€<?php echo number_format($referral->commission_amount, 2); ?></td>
                            <td><?php echo ucfirst($order_status); ?></td>
                            <td><?php echo $referral->status === 'paid' ? '<span style="color:#28a745;">✓ Paid</span>' : '<span style="color:#ffc107;">⏳ Pending</span>'; ?></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($referral->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="3" style="text-align:right;"><strong>Total Commission:</strong></td><td colspan="3"><strong>€<?php echo number_format(array_sum(array_column($referrals, 'commission_amount')), 2); ?></strong></td></tr></tfoot>
            </table>
        <?php else: ?>
            <div class="act-no-orders"><p>📦 No affiliate orders yet. Share your referral link or coupon code <strong><?php echo esc_html($affiliate->affiliate_code); ?></strong> to start earning!</p></div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ==============================================
// REGISTRATION FORM SHORTCODE
// ==============================================

function act_reg_form() {
    if (is_user_logged_in()) {
        global $wpdb;
        $affiliate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}act_affiliates WHERE user_id = %d", get_current_user_id()));
        if ($affiliate) {
            if ($affiliate->affiliate_status === 'approved') {
                return '<div style="background:#d4edda;padding:20px;border-radius:8px;text-align:center"><p>✅ You are already an approved affiliate!</p><a href="' . home_url('/affiliate-dashboard') . '" class="button button-primary">Go to Dashboard</a></div>';
            } elseif ($affiliate->affiliate_status === 'pending') {
                return '<div style="background:#fff3cd;padding:20px;border-radius:8px;text-align:center"><p>⏳ Your affiliate account is pending approval. You will receive an email once approved.</p></div>';
            }
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act_reg_submit'])) {
        return act_reg_process();
    }
    
    ob_start();
    ?>
    <style>
        .act-reg-container {
            max-width: 600px !important;
            margin: 30px auto !important;
//             padding: 30px !important;
            background: #fff !important;
            border-radius: 12px !important;
//             box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        }

        .act-reg-container h2 {
            text-align: center !important;
            margin-top: 0 !important;
            font-size: 30px !important;
            line-height: 1.2 !important;
            color: #0b4f6c !important;
        }

        .act-form-group {
            margin-bottom: 20px !important;
        }

        .act-form-group label {
            display: block !important;
            margin-bottom: 5px !important;
            font-weight: 600 !important;
        }

        .act-form-group input {
            width: 100% !important;
            padding: 10px !important;
            border: 1px solid #ddd !important;
            border-radius: 6px !important;
            box-sizing: border-box !important;
        }

        .act-button {
            background: #0073aa !important;
            color: #fff !important;
            border: none !important;
            padding: 12px 24px !important;
            border-radius: 999px !important;
            cursor: pointer !important;
            width: 100% !important;
            font-size: 16px !important;
            font-weight: 700 !important;
        }

        .act-button:hover {
            background: #005a87 !important;
        }

        .act-error {
            background: #f8d7da !important;
            color: #721c24 !important;
            padding: 12px !important;
            border-radius: 6px !important;
            margin-bottom: 20px !important;
        }

        .act-success {
            background: #d4edda !important;
            color: #155724 !important;
            padding: 12px !important;
            border-radius: 6px !important;
            margin-bottom: 20px !important;
        }

        .act-social-section {
            background: linear-gradient(135deg, #e8f4fd 0%, #f8fbff 100%) !important;
            border: 1px solid #cfe7f6 !important;
            border-radius: 20px !important;
            padding: 22px !important;
            margin: 26px 0 !important;
        }

        .act-social-section h3 {
            margin: 0 0 8px !important;
            color: #0b4f6c !important;
            font-size: 21px !important;
            font-weight: 900 !important;
        }

        .act-social-section h3::after {
            content: "Click a platform to add your profile link." !important;
            display: block !important;
            margin-top: 8px !important;
            color: #64748b !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            line-height: 1.5 !important;
        }

        .act-social-icons {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 12px !important;
            margin-top: 18px !important;
            margin-bottom: 18px !important;
        }

        .act-social-btn {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            justify-content: flex-start !important;
            background: #fff !important;
            color: #0b4f6c !important;
            border: 1px solid #d9edf9 !important;
            border-radius: 16px !important;
            padding: 13px 14px !important;
            min-height: 54px !important;
            font-size: 14px !important;
            font-weight: 900 !important;
            box-shadow: 0 8px 22px rgba(0, 115, 170, .08) !important;
            cursor: pointer !important;
            transition: .2s ease !important;
        }

        .act-social-btn:hover {
            transform: translateY(-2px) !important;
            border-color: #0073aa !important;
            box-shadow: 0 12px 28px rgba(0, 115, 170, .15) !important;
        }

        .act-social-btn svg {
            width: 18px !important;
            height: 18px !important;
            display: block !important;
        }

        .act-social-icon-wrap {
            width: 32px !important;
            height: 32px !important;
            min-width: 32px !important;
            border-radius: 999px !important;
            background: #0073aa !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #fff !important;
        }

        .act-social-field {
            display: none !important;
            margin-bottom: 15px !important;
        }

        .act-social-field.active {
            display: block !important;
        }

        .act-social-field label {
            display: block !important;
            margin-bottom: 7px !important;
            color: #0b4f6c !important;
            font-weight: 800 !important;
        }

        .act-social-field input {
            width: 100% !important;
            padding: 11px 13px !important;
            border: 1px solid #d9edf9 !important;
            border-radius: 12px !important;
            box-sizing: border-box !important;
        }

        @media (max-width: 600px) {
            .act-social-icons {
                grid-template-columns: 1fr !important;
            }
        }
    </style>

    <div class="act-reg-container">
        <h2>Become an Affiliate</h2>

        <form method="post" id="act-reg-form">
            <?php wp_nonce_field('act_reg_nonce', 'act_reg_nonce_field'); ?>

            <div class="act-form-group">
                <label>Username *</label>
                <input type="text" name="act_username" required>
            </div>

            <div class="act-form-group">
                <label>Email *</label>
                <input type="email" name="act_email" required>
            </div>

            <div class="act-form-group">
                <label>Password *</label>
                <input type="password" name="act_password" required>
            </div>

            <div class="act-form-group">
                <label>Confirm Password *</label>
                <input type="password" name="act_confirm_password" required>
            </div>

            <div class="act-form-group">
                <label>Custom Affiliate Code (Optional)</label>
                <input type="text" name="act_custom_code" placeholder="Leave blank for auto-generated code">
                <small style="color:#666;font-size:12px;">Leave empty to generate a unique code automatically</small>
            </div>

            <div class="act-social-section">
                <h3>Social Media Profiles</h3>

                <div class="act-social-icons">
                    <button type="button" class="act-social-btn" data-social="facebook">
                        <span class="act-social-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 8.5V6.8c0-.8.2-1.3 1.4-1.3H17V2.6c-.8-.1-1.7-.2-2.5-.2-2.5 0-4.2 1.5-4.2 4.3v1.8H7.5v3.2h2.8V21h3.4v-9.3h2.8l.5-3.2H14Z"/></svg>
                        </span>
                        Facebook
                    </button>

                    <button type="button" class="act-social-btn" data-social="instagram">
                        <span class="act-social-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="5"/><circle cx="12" cy="12" r="3.5"/><circle cx="17" cy="7" r="1" fill="currentColor" stroke="currentColor"/></svg>
                        </span>
                        Instagram
                    </button>

                    <button type="button" class="act-social-btn" data-social="twitter">
                        <span class="act-social-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.9 10.6 21.2 2h-1.7l-6.3 7.4L8.1 2H2.3l7.7 11.2L2.3 22h1.7l6.8-7.9 5.4 7.9H22l-8.1-11.4Zm-2.4 2.8-.8-1.1L4.5 3.3h2.8l5 7.1.8 1.1 6.5 9.3h-2.8l-5.3-7.4Z"/></svg>
                        </span>
                        Twitter / X
                    </button>

                    <button type="button" class="act-social-btn" data-social="tiktok">
                        <span class="act-social-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.2 3c.4 2.2 1.7 3.7 3.8 3.9v3.1c-1.4.1-2.7-.3-3.8-1.1v6.1c0 3.1-2.1 5.2-5.2 5.2-2.8 0-5-2-5-4.7 0-3 2.4-5 5.6-4.7v3.2c-1.4-.2-2.3.5-2.3 1.5 0 .9.7 1.6 1.7 1.6 1.2 0 1.9-.7 1.9-2.1V3h3.3Z"/></svg>
                        </span>
                        TikTok
                    </button>

                    <button type="button" class="act-social-btn" data-social="youtube">
                        <span class="act-social-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M21.6 7.2s-.2-1.5-.8-2.1c-.8-.8-1.7-.8-2.1-.9C15.8 4 12 4 12 4s-3.8 0-6.7.2c-.4.1-1.3.1-2.1.9-.6.6-.8 2.1-.8 2.1S2.2 9 2.2 10.9v1.8c0 1.9.2 3.7.2 3.7s.2 1.5.8 2.1c.8.8 1.9.8 2.4.9 1.8.2 6.4.2 6.4.2s3.8 0 6.7-.2c.4-.1 1.3-.1 2.1-.9.6-.6.8-2.1.8-2.1s.2-1.9.2-3.7v-1.8c0-1.9-.2-3.7-.2-3.7ZM10.1 14.8V8.6l5.8 3.1-5.8 3.1Z"/></svg>
                        </span>
                        YouTube
                    </button>

                    <button type="button" class="act-social-btn" data-social="linkedin">
                        <span class="act-social-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 8.8H3.3V21h3.3V8.8ZM5 3C3.9 3 3.1 3.8 3.1 4.8S3.9 6.6 5 6.6s1.9-.8 1.9-1.8S6.1 3 5 3Zm16 11c0-3.4-1.8-5-4.2-5-1.9 0-2.8 1.1-3.3 1.8v-2h-3.3V21h3.3v-6.8c0-1.8.3-3.5 2.5-3.5 2.1 0 2.1 2 2.1 3.6V21H21v-7Z"/></svg>
                        </span>
                        LinkedIn
                    </button>

                    <button type="button" class="act-social-btn" data-social="website">
                        <span class="act-social-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9S14.5 18.3 12 21c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3Z"/></svg>
                        </span>
                        Website
                    </button>

                    <button type="button" class="act-social-btn" data-social="pinterest">
                        <span class="act-social-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.2 2.5C7 2.5 4.3 6 4.3 9.8c0 2 .8 3.8 2.6 4.5.3.1.5 0 .6-.3l.3-1.2c.1-.4.1-.5-.2-.8-.5-.6-.8-1.3-.8-2.3 0-2.9 2.2-5.5 5.7-5.5 3.1 0 4.8 1.9 4.8 4.4 0 3.3-1.5 6.1-3.7 6.1-1.2 0-2.1-1-1.8-2.2.3-1.4 1-2.9 1-3.9 0-.9-.5-1.7-1.5-1.7-1.2 0-2.1 1.2-2.1 2.9 0 1.1.4 1.8.4 1.8l-1.5 6.3c-.4 1.9-.1 4.1 0 4.3 0 .1.2.2.3.1.1 0 1.7-2.1 2.2-4l.6-2.4c.6 1.1 1.7 1.7 3 1.7 4 0 6.7-3.6 6.7-8.5 0-3.7-3.1-7.1-7.8-7.1Z"/></svg>
                        </span>
                        Pinterest
                    </button>
                </div>

                <div id="act-social-fields"></div>
            </div>
            
            <div class="act-form-group">
                <label>
                    <input type="checkbox" name="act_terms" required>
                    I agree to the <a href="/affiliate-terms" target="_blank">Terms & Conditions</a> *
                </label>
            </div>

            <button type="submit" name="act_reg_submit" class="act-button">Register as Affiliate</button>
        </form>

        <p style="text-align:center;margin-top:20px">Already have an account? <a href="/affiliate-login">Login here</a></p>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const socialFields = document.getElementById('act-social-fields');
        const socialBtns = document.querySelectorAll('.act-social-btn');
        
        socialBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const social = this.dataset.social;
                const existingField = document.getElementById(`social_${social}_field`);
                
                if (existingField) {
                    existingField.remove();
                    return;
                }
                
                const fieldDiv = document.createElement('div');
                fieldDiv.id = `social_${social}_field`;
                fieldDiv.className = 'act-social-field active';
                
                const labels = {
                    facebook: 'Facebook URL',
                    instagram: 'Instagram URL / Username',
                    twitter: 'Twitter / X URL',
                    tiktok: 'TikTok URL / Username',
                    youtube: 'YouTube URL',
                    linkedin: 'LinkedIn URL',
                    website: 'Personal Website',
                    pinterest: 'Pinterest URL'
                };

                const placeholders = {
                    facebook: 'https://facebook.com/yourpage',
                    instagram: 'https://instagram.com/username',
                    twitter: 'https://twitter.com/username',
                    tiktok: 'https://tiktok.com/@username',
                    youtube: 'https://youtube.com/@channel',
                    linkedin: 'https://linkedin.com/in/username',
                    website: 'https://yourwebsite.com',
                    pinterest: 'https://pinterest.com/username'
                };
                
                fieldDiv.innerHTML = `<label>${labels[social]}</label><input type="url" name="social_${social}" placeholder="${placeholders[social]}">`;
                socialFields.appendChild(fieldDiv);
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
										
function act_reg_process() {
    if (!isset($_POST['act_reg_nonce_field']) || !wp_verify_nonce($_POST['act_reg_nonce_field'], 'act_reg_nonce')) {
        return '<div class="act-error">Security verification failed.</div>' . act_reg_form();
    }
    
    $username = sanitize_user($_POST['act_username']);
    $email = sanitize_email($_POST['act_email']);
    $password = $_POST['act_password'];
    $confirm = $_POST['act_confirm_password'];
    $custom_code = isset($_POST['act_custom_code']) ? strtoupper(sanitize_text_field($_POST['act_custom_code'])) : '';
    
    if ($password !== $confirm) return '<div class="act-error">Passwords do not match.</div>' . act_reg_form();
    if (strlen($password) < 6) return '<div class="act-error">Password must be at least 6 characters.</div>' . act_reg_form();
    if (username_exists($username)) return '<div class="act-error">Username already exists.</div>' . act_reg_form();
    if (email_exists($email)) return '<div class="act-error">Email already registered.</div>' . act_reg_form();
    
    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    
    if (!empty($custom_code)) {
        $code_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $affiliates_table WHERE affiliate_code = %s", $custom_code));
        if ($code_exists) {
            return '<div class="act-error">Affiliate code already taken. Please choose another or leave blank for auto-generation.</div>' . act_reg_form();
        }
        $affiliate_code = $custom_code;
    } else {
        $affiliate_code = strtoupper(substr(md5($username . time()), 0, 8));
    }
    
    $social_fields = ['facebook', 'instagram', 'twitter', 'tiktok', 'youtube', 'linkedin', 'website', 'pinterest'];
    $social_data = array();
    foreach ($social_fields as $field) {
        $social_data["social_$field"] = isset($_POST["social_$field"]) && !empty($_POST["social_$field"]) ? esc_url_raw($_POST["social_$field"]) : null;
    }
    
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) return '<div class="act-error">' . $user_id->get_error_message() . '</div>' . act_reg_form();
    
    $user = new WP_User($user_id);
    $user->set_role('customer');
    $user->add_role('affiliate');
    
    $wpdb->insert($affiliates_table, array_merge(array(
        'user_id' => $user_id,
        'affiliate_code' => $affiliate_code,
        'commission_rate' => 15,
        'customer_discount_rate' => 5,
        'affiliate_status' => 'pending',
        'created_at' => current_time('mysql')
    ), $social_data));
    
    $admin_email = get_option('admin_email');
    $subject = 'New Affiliate Registration - Pending Approval';
    $message = "A new affiliate has registered and is pending approval.\n\nUsername: $username\nEmail: $email\nAffiliate Code: $affiliate_code\n\nApprove here: " . admin_url('admin.php?page=act-affiliates');
    wp_mail($admin_email, $subject, $message);
    
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    
    wp_redirect(home_url('/affiliate-dashboard?registered=success'));
    exit;
}

// ==============================================
// LOGIN FORM SHORTCODE
// ==============================================

function act_login_form() {
    if (is_user_logged_in()) {
        global $wpdb;
        $affiliate = $wpdb->get_row($wpdb->prepare("SELECT affiliate_status FROM {$wpdb->prefix}act_affiliates WHERE user_id = %d", get_current_user_id()));
        if ($affiliate && $affiliate->affiliate_status === 'approved') {
            return '<div><p>Welcome back! <a href="' . home_url('/affiliate-dashboard') . '" class="button button-primary">Go to Dashboard</a> <a href="' . wp_logout_url(home_url('/affiliate-login')) . '" class="button">Logout</a></p></div>';
        } elseif ($affiliate && $affiliate->affiliate_status === 'pending') {
            return '<div style="background:#fff3cd;padding:20px;border-radius:8px;text-align:center"><p>⏳ Your affiliate account is pending approval. You will be notified once approved.</p><a href="' . wp_logout_url(home_url('/affiliate-login')) . '" class="button">Logout</a></div>';
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act_login_submit'])) {
        $username = sanitize_user($_POST['act_username']);
        $password = $_POST['act_password'];
        $remember = isset($_POST['act_remember']);
        
        $creds = array('user_login' => $username, 'user_password' => $password, 'remember' => $remember);
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            return '<div class="act-error">Invalid credentials.</div>' . act_login_form_html();
        }
        
        wp_redirect(home_url('/affiliate-dashboard'));
        exit;
    }
    
    return act_login_form_html();
}

function act_login_form_html() {
    ob_start();
    ?>
    <style>
        .act-login-container {
            max-width: 460px !important;
            margin: 30px auto !important;
            padding: 34px !important;
            background: #fff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 24px !important;
            box-shadow: 0 18px 50px rgba(0,0,0,.06) !important;
        }

        .act-login-container h2 {
            margin: 0 0 10px !important;
            text-align: center !important;
            color: #0b4f6c !important;
            font-size: 30px !important;
            font-weight: 900 !important;
            line-height: 1.2 !important;
        }

        .act-login-subtitle {
            text-align: center !important;
            color: #64748b !important;
            line-height: 1.6 !important;
            margin: 0 0 26px !important;
        }

        .act-form-group {
            margin-bottom: 20px !important;
        }

        .act-form-group label {
            display: block !important;
            margin-bottom: 7px !important;
            color: #0b4f6c !important;
            font-weight: 800 !important;
        }

        .act-form-group input[type="text"],
        .act-form-group input[type="password"] {
            width: 100% !important;
            padding: 13px 14px !important;
            border: 1px solid #d9edf9 !important;
            border-radius: 14px !important;
            box-sizing: border-box !important;
            font-size: 15px !important;
            background: #fff !important;
        }

        .act-form-group input:focus {
            outline: none !important;
            border-color: #0073aa !important;
            box-shadow: 0 0 0 3px rgba(0,115,170,.12) !important;
        }

        .act-login-remember label {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            color: #475569 !important;
            font-weight: 600 !important;
        }

        .act-button {
            width: 100% !important;
            background: #0073aa !important;
            color: #fff !important;
            border: none !important;
            padding: 14px 24px !important;
            border-radius: 999px !important;
            cursor: pointer !important;
            font-size: 16px !important;
            font-weight: 800 !important;
            transition: .2s ease !important;
        }

        .act-button:hover {
            background: #005a87 !important;
            transform: translateY(-1px) !important;
        }

        .act-error {
            background: #f8d7da !important;
            color: #721c24 !important;
            padding: 12px !important;
            border-radius: 12px !important;
            margin-bottom: 20px !important;
        }

        .act-login-links {
            text-align: center !important;
            margin-top: 22px !important;
            color: #64748b !important;
            line-height: 1.7 !important;
        }

        .act-login-links a {
            color: #0073aa !important;
            font-weight: 800 !important;
            text-decoration: none !important;
        }

        .act-login-links a:hover {
            color: #005a87 !important;
            text-decoration: underline !important;
        }
    </style>

    <div class="act-login-container">
        <h2>Affiliate Login</h2>
        <p class="act-login-subtitle">Login to view your affiliate dashboard, orders, commission, and referral details.</p>

        <form method="post">
            <div class="act-form-group">
                <label>Username or Email</label>
                <input type="text" name="act_username" required>
            </div>

            <div class="act-form-group">
                <label>Password</label>
                <input type="password" name="act_password" required>
            </div>

            <div class="act-form-group act-login-remember">
                <label>
                    <input type="checkbox" name="act_remember">
                    Remember Me
                </label>
            </div>

            <button type="submit" name="act_login_submit" class="act-button">Login</button>
        </form>

        <div class="act-login-links">
            <p>Don't have an account? <a href="/affiliate-register">Register here</a></p>
            <p><a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Forgot password?</a></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


// ==============================================
// ACCOUNT INFO SHORTCODE
// ==============================================

function act_affiliate_account_info() {
    if (!is_user_logged_in()) {
        return '<div class="act-error-message"><p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to view your affiliate account info.</p></div>';
    }
    
    global $wpdb;
    $user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    
    $affiliate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $affiliates_table WHERE user_id = %d", $user_id));
    
    if (!$affiliate) {
        return '<div class="act-error-message"><p>You are not registered as an affiliate. <a href="' . home_url('/affiliate-register') . '">Register here</a></p></div>';
    }
    
    $message = '';
    $error = '';
    
    if (isset($_POST['act_update_account']) && isset($_POST['act_account_nonce']) && wp_verify_nonce($_POST['act_account_nonce'], 'act_account_update')) {
        if (isset($_POST['affiliate_code']) && !empty($_POST['affiliate_code'])) {
            $new_code = strtoupper(sanitize_text_field($_POST['affiliate_code']));
            $code_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $affiliates_table WHERE affiliate_code = %s AND user_id != %d", $new_code, $user_id));
            
            if (!$code_exists) {
                $old_code = $affiliate->affiliate_code;
                $wpdb->update($affiliates_table, array('affiliate_code' => $new_code), array('user_id' => $user_id));
                
                if ($affiliate->coupon_id) {
                    try { $coupon = new WC_Coupon($affiliate->coupon_id); $coupon->set_code($new_code); $coupon->save(); } catch (Exception $e) {}
                } else {
                    act_core_create_coupon($new_code, $affiliate->customer_discount_rate, $affiliate->id);
                }
                
                $affiliate->affiliate_code = $new_code;
                $message = 'Affiliate code updated successfully!';
            } else {
                $error = 'Code already taken. Please choose another code.';
            }
        }
        
        $social_fields = ['facebook', 'instagram', 'twitter', 'tiktok', 'youtube', 'linkedin', 'website', 'pinterest'];
        $update_data = array();
        foreach ($social_fields as $field) {
            if (isset($_POST["social_$field"])) {
                $value = sanitize_text_field($_POST["social_$field"]);
                if (!empty($value) && !preg_match('/^https?:\/\//', $value) && !in_array($field, ['instagram', 'twitter', 'tiktok', 'pinterest'])) {
                    $value = 'https://' . $value;
                }
                $update_data["social_$field"] = !empty($value) ? $value : null;
            }
        }
        
        if (!empty($update_data)) {
            $wpdb->update($affiliates_table, $update_data, array('user_id' => $user_id));
            foreach ($update_data as $key => $value) { $affiliate->$key = $value; }
            $message = $message ? $message . ' Social links updated!' : 'Social links updated!';
        }
        
        if (isset($_POST['display_name']) && !empty($_POST['display_name'])) {
            wp_update_user(array('ID' => $user_id, 'display_name' => sanitize_text_field($_POST['display_name']), 'user_email' => sanitize_email($_POST['email'])));
            $current_user->display_name = sanitize_text_field($_POST['display_name']);
            $current_user->user_email = sanitize_email($_POST['email']);
            $message = $message ? $message . ' Profile updated!' : 'Profile updated!';
        }
    }
    
    $socials = array(
        'facebook' => $affiliate->social_facebook, 'instagram' => $affiliate->social_instagram,
        'twitter' => $affiliate->social_twitter, 'tiktok' => $affiliate->social_tiktok,
        'youtube' => $affiliate->social_youtube, 'linkedin' => $affiliate->social_linkedin,
        'website' => $affiliate->social_website, 'pinterest' => $affiliate->social_pinterest
    );
    
    $referral_link = add_query_arg('ref', $affiliate->affiliate_code, home_url());
    
    ob_start();
    ?>
    <style>
        .act-account-container { max-width: 800px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); overflow: hidden; }
        .act-account-header { background: linear-gradient(135deg, #0073aa 0%, #005a87 100%); color: white; padding: 30px; text-align: center; }
        .act-account-header h2 { margin: 0; color: white; font-size: 24px; }
        .act-account-body { padding: 30px; }
        .act-form-section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .act-form-section h3 { margin: 0 0 20px 0; color: #0073aa; font-size: 18px; }
        .act-form-row { display: grid; grid-template-columns: 150px 1fr; gap: 15px; margin-bottom: 15px; align-items: center; }
        .act-form-row label { font-weight: 600; color: #555; }
        .act-form-row input { padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; }
        .act-social-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .act-social-item { display: flex; align-items: center; gap: 10px; }
        .act-social-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; flex-shrink: 0; }
        .act-social-item input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .act-info-box { background: #e8f4fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .act-info-box p { margin: 5px 0; }
        .act-button-save { background: #0073aa; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; }
        .act-success-message { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .act-error-message { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .act-readonly-code { background: #e9ecef; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 18px; text-align: center; }
        @media (max-width: 768px) { .act-form-row { grid-template-columns: 1fr; } .act-social-grid { grid-template-columns: 1fr; } }
    </style>
    
    <div class="act-account-container">
        <div class="act-account-header"><h2>My Affiliate Account</h2><p>Manage your affiliate profile, code, and social links</p></div>
        <div class="act-account-body">
            <?php if ($message): ?><div class="act-success-message">✅ <?php echo esc_html($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="act-error-message">❌ <?php echo esc_html($error); ?></div><?php endif; ?>
            
            <div class="act-info-box">
                <p><strong>📊 Your Stats:</strong></p>
                <p>💰 Total Earned: <strong>€<?php echo number_format($affiliate->total_earned, 2); ?></strong></p>
                <p>📈 Commission Rate: <strong><?php echo $affiliate->commission_rate; ?>%</strong></p>
                <p>🎫 Customer Discount: <strong><?php echo $affiliate->customer_discount_rate ?: 5; ?>%</strong></p>
                <p>🔗 Your Referral Link: <code style="font-size:12px"><?php echo esc_url($referral_link); ?></code></p>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('act_account_update', 'act_account_nonce'); ?>
                
                <div class="act-form-section">
                    <h3>👤 Personal Information</h3>
                    <div class="act-form-row"><label>Display Name</label><input type="text" name="display_name" value="<?php echo esc_attr($current_user->display_name); ?>"></div>
                    <div class="act-form-row"><label>Email Address</label><input type="email" name="email" value="<?php echo esc_attr($current_user->user_email); ?>"></div>
                    <div class="act-form-row"><label>Username</label><input type="text" value="<?php echo esc_attr($current_user->user_login); ?>" readonly disabled style="background:#f0f0f0"><span class="description">Username cannot be changed</span></div>
                </div>
                
                <div class="act-form-section">
                    <h3>🔑 Affiliate Code & Coupon</h3>
                    <div class="act-form-row"><label>Current Affiliate Code</label><div class="act-readonly-code"><?php echo esc_html($affiliate->affiliate_code); ?></div></div>
                    <div class="act-form-row"><label>New Affiliate Code</label><input type="text" name="affiliate_code" placeholder="Enter new code (4-20 letters/numbers)" pattern="[A-Za-z0-9]{4,20}"><span class="description">Warning: Changing your code will update your coupon code as well.</span></div>
                </div>
                
                <div class="act-form-section">
                    <h3>📱 Social Media Profiles</h3>
                    <div class="act-social-grid">
                        <div class="act-social-item"><div class="act-social-icon" style="background:#1877f2;">📘</div><input type="text" name="social_facebook" placeholder="Facebook URL" value="<?php echo esc_attr($socials['facebook']); ?>"></div>
                        <div class="act-social-item"><div class="act-social-icon" style="background:#e4405f;">📷</div><input type="text" name="social_instagram" placeholder="Instagram URL" value="<?php echo esc_attr($socials['instagram']); ?>"></div>
                        <div class="act-social-item"><div class="act-social-icon" style="background:#1da1f2;">🐦</div><input type="text" name="social_twitter" placeholder="Twitter/X URL" value="<?php echo esc_attr($socials['twitter']); ?>"></div>
                        <div class="act-social-item"><div class="act-social-icon" style="background:#000;">🎵</div><input type="text" name="social_tiktok" placeholder="TikTok URL" value="<?php echo esc_attr($socials['tiktok']); ?>"></div>
                        <div class="act-social-item"><div class="act-social-icon" style="background:#ff0000;">📺</div><input type="text" name="social_youtube" placeholder="YouTube URL" value="<?php echo esc_attr($socials['youtube']); ?>"></div>
                        <div class="act-social-item"><div class="act-social-icon" style="background:#0077b5;">🔗</div><input type="text" name="social_linkedin" placeholder="LinkedIn URL" value="<?php echo esc_attr($socials['linkedin']); ?>"></div>
                        <div class="act-social-item"><div class="act-social-icon" style="background:#28a745;">🌐</div><input type="text" name="social_website" placeholder="Personal Website" value="<?php echo esc_attr($socials['website']); ?>"></div>
                        <div class="act-social-item"><div class="act-social-icon" style="background:#bd081c;">📌</div><input type="text" name="social_pinterest" placeholder="Pinterest URL" value="<?php echo esc_attr($socials['pinterest']); ?>"></div>
                    </div>
                </div>
                
                <button type="submit" name="act_update_account" class="act-button-save">💾 Save All Changes</button>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

return;