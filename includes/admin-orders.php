<?php
/**
 * Snippet 4: Affiliate System - Additional Admin Pages
 * Version 5.0 — Uses WC_Order_Query (HPOS-agnostic), all-orders view
 * Cleaned up: manual meta box removed (superseded by Snippet 8).
 * Kept: Affiliate Orders admin page, Bulk Assignment admin page, orders-list quick action.
 * Bulk assignment uses EU7 override (flat 15%) + bundle cap (10%) via act_resolve_manual_commission_rate().
 */

// ==============================================
// SHARED RATE RESOLUTION FOR MANUAL ASSIGNMENTS
// ==============================================
/**
 * Resolve the effective commission rate for a manual assignment.
 *
 * Rules (evaluated in this order):
 *   1. Start with the affiliate's configured rate.
 *   2. If the order has the "eu7" coupon applied → override to 15% flat.
 *   3. If the order is a bundle order → cap at 10% (always wins, even over EU7).
 *
 * Reads coupon codes directly from the database to avoid
 * $order->get_coupons() reliability issues in admin save contexts.
 *
 * @param WC_Order $order
 * @param float    $affiliate_rate
 * @return float
 */
if ( ! function_exists( 'act_resolve_manual_commission_rate' ) ) :
function act_resolve_manual_commission_rate( $order, $affiliate_rate ) {

    $rate = floatval( $affiliate_rate );

    if ( ! $order instanceof WC_Order ) {
        return $rate;
    }

    global $wpdb;
    $order_id = $order->get_id();

    // --- Step 2: EU7 override (flat 15%) ---
    $coupon_code = $wpdb->get_var( $wpdb->prepare(
        "SELECT order_item_name
         FROM {$wpdb->prefix}woocommerce_order_items
         WHERE order_id = %d
           AND order_item_type = 'coupon'
         LIMIT 1",
        $order_id
    ) );

    if ( $coupon_code && strtolower( trim( $coupon_code ) ) === 'eu7' ) {
        $rate = 15;
    }

    // --- Step 3: Bundle cap (always last, always wins) ---
    if ( strtolower( trim( (string) $order->get_meta( '_retaeu_is_bundle_order' ) ) ) === 'yes' ) {
        if ( $rate > 10 ) {
            $rate = 10;
        }
    }

    return $rate;
}
endif;

// ==============================================
// AFFILIATE ORDERS ADMIN PAGE
// ==============================================

add_action('admin_menu', 'act_admin_orders_menu');
function act_admin_orders_menu() {
    if ( ! function_exists( 'act_core_affiliates_admin_page' ) ) return;
    add_submenu_page(
        'act-affiliates', 'Affiliate Orders', 'Affiliate Orders',
        'manage_options', 'act-orders-admin', 'act_admin_orders_page'
    );
}

function act_admin_orders_page() {
    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $referrals_table  = $wpdb->prefix . 'act_referrals';

    $affiliate_filter = isset($_GET['affiliate_id']) ? intval($_GET['affiliate_id']) : 0;
    $status_filter    = isset($_GET['commission_status']) ? sanitize_text_field($_GET['commission_status']) : 'all';

    $all_affiliates = $wpdb->get_results("SELECT id, user_id, affiliate_code FROM $affiliates_table ORDER BY id DESC LIMIT 500");

    $where = array();
    if ($affiliate_filter > 0) $where[] = $wpdb->prepare("affiliate_id = %d", $affiliate_filter);
    if ($status_filter !== 'all') $where[] = $wpdb->prepare("status = %s", $status_filter);
    $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $per_page     = 100;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset       = ($current_page - 1) * $per_page;

    $total_referrals = (int) $wpdb->get_var("SELECT COUNT(*) FROM $referrals_table r $where_sql");
    $total_pages     = max(1, (int) ceil($total_referrals / $per_page));

    $referrals = $wpdb->get_results($wpdb->prepare(
        "SELECT r.* FROM $referrals_table r $where_sql ORDER BY r.created_at DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    $filtered = array();
    $total_commission = 0; $pending_total = 0; $paid_total = 0;
    foreach ($referrals as $r) {
        $order = wc_get_order($r->order_id);
        if (!$order) continue;
        $st = $order->get_status();
        if ($st !== 'completed' && $st !== 'processing') continue;
        $filtered[] = $r;
        $amt = floatval($r->commission_amount);
        $total_commission += $amt;
        if ($r->status === 'pending') $pending_total += $amt;
        elseif ($r->status === 'paid') $paid_total += $amt;
    }

    $affiliate_ids = array_unique(array_map(function($r){ return (int)$r->affiliate_id; }, $filtered));
    $affiliate_map = array();
    if (!empty($affiliate_ids)) {
        $in = implode(',', array_map('intval', $affiliate_ids));
        $rows = $wpdb->get_results("SELECT a.id, a.user_id, a.affiliate_code, u.display_name FROM $affiliates_table a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID WHERE a.id IN ($in)");
        foreach ($rows as $row) $affiliate_map[$row->id] = $row;
    }
    ?>
    <div class="wrap">
        <h1>Affiliate Orders</h1>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:20px 0;">
            <div style="background:#fff;padding:20px;border-radius:8px;border-left:4px solid #0073aa;">
                <h3 style="margin:0 0 10px;color:#666;">Total (page)</h3>
                <div style="font-size:28px;font-weight:bold;color:#0073aa;">€<?php echo number_format($total_commission, 2); ?></div>
            </div>
            <div style="background:#fff;padding:20px;border-radius:8px;border-left:4px solid #ffc107;">
                <h3 style="margin:0 0 10px;color:#666;">Pending</h3>
                <div style="font-size:28px;font-weight:bold;color:#ffc107;">€<?php echo number_format($pending_total, 2); ?></div>
            </div>
            <div style="background:#fff;padding:20px;border-radius:8px;border-left:4px solid #28a745;">
                <h3 style="margin:0 0 10px;color:#666;">Paid</h3>
                <div style="font-size:28px;font-weight:bold;color:#28a745;">€<?php echo number_format($paid_total, 2); ?></div>
            </div>
        </div>

        <form method="GET" style="background:#fff;padding:15px 20px;border-radius:8px;margin:20px 0;">
            <input type="hidden" name="page" value="act-orders-admin">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <select name="affiliate_id">
                    <option value="0">All Affiliates</option>
                    <?php foreach($all_affiliates as $a): $u = get_userdata($a->user_id); if($u): ?>
                        <option value="<?php echo esc_attr($a->id); ?>" <?php selected($affiliate_filter, $a->id); ?>>
                            <?php echo esc_html($u->display_name . ' (' . $a->affiliate_code . ')'); ?>
                        </option>
                    <?php endif; endforeach; ?>
                </select>
                <select name="commission_status">
                    <option value="all" <?php selected($status_filter, 'all'); ?>>All</option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                    <option value="paid" <?php selected($status_filter, 'paid'); ?>>Paid</option>
                </select>
                <button type="submit" class="button button-primary">Filter</button>
                <a href="?page=act-orders-admin" class="button">Reset</a>
            </div>
        </form>

        <div style="overflow-x:auto;background:#fff;border-radius:8px;margin-top:20px;">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>Order #</th><th>Customer</th><th>Email</th><th>Order Total</th>
                    <th>Commission</th><th>Order Status</th><th>Commission Status</th>
                    <th>Affiliate</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php if (!empty($filtered)): foreach($filtered as $r):
                    $order = wc_get_order($r->order_id);
                    $aff = isset($affiliate_map[$r->affiliate_id]) ? $affiliate_map[$r->affiliate_id] : null;
                ?>
                    <tr>
                        <td><a href="<?php echo esc_url(admin_url('post.php?post='.$r->order_id.'&action=edit')); ?>" target="_blank">#<?php echo esc_html($r->order_number); ?></a></td>
                        <td><?php echo esc_html(trim($r->customer_first_name . ' ' . $r->customer_last_name) ?: 'Guest'); ?></td>
                        <td><?php echo esc_html($r->customer_email ?: '—'); ?></td>
                        <td>€<?php echo number_format($r->order_amount, 2); ?></td>
                        <td>€<?php echo number_format($r->commission_amount, 2); ?></td>
                        <td><?php echo esc_html(ucfirst($order ? $order->get_status() : '?')); ?></td>
                        <td><?php echo $r->status === 'paid' ? '<span style="color:#28a745;">✓ Paid</span>' : '<span style="color:#ffc107;">⏳ Pending</span>'; ?></td>
                        <td><?php echo $aff ? esc_html($aff->display_name . ' (' . $aff->affiliate_code . ')') : 'N/A'; ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($r->created_at))); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="9" style="text-align:center;padding:40px;">No completed or processing orders found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div style="margin-top:15px;">
                <?php
                $base = admin_url('admin.php?page=act-orders-admin');
                if ($affiliate_filter > 0) $base = add_query_arg('affiliate_id', $affiliate_filter, $base);
                if ($status_filter !== 'all') $base = add_query_arg('commission_status', $status_filter, $base);
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%', $base),
                    'format' => '', 'current' => $current_page, 'total' => $total_pages,
                    'prev_text' => '« Prev', 'next_text' => 'Next »',
                ));
                ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// ==============================================
// BULK ASSIGNMENT ADMIN PAGE — HPOS-AGNOSTIC, ALL ORDERS
// ==============================================

if ( ! function_exists( 'act_manual_add_bulk_menu' ) ) :
function act_manual_add_bulk_menu() {
    if ( ! function_exists( 'act_core_affiliates_admin_page' ) ) return;
    add_submenu_page('act-affiliates', 'Manual Order Assignment', 'Manual Assignment',
        'manage_options', 'act-manual-assignment', 'act_manual_bulk_page');
}
endif;
add_action('admin_menu', 'act_manual_add_bulk_menu');

if ( ! function_exists( 'act_manual_bulk_page' ) ) :
function act_manual_bulk_page() {
    global $wpdb;

    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $referrals_table  = $wpdb->prefix . 'act_referrals';

    // ----- Handle bulk assign / reassign -----
    if (isset($_POST['act_bulk_assign']) && !empty($_POST['selected_orders']) && !empty($_POST['bulk_affiliate_id'])) {
        $bulk_affiliate_id = intval($_POST['bulk_affiliate_id']);
        $selected_orders   = array_map('intval', $_POST['selected_orders']);
        $assigned_count = 0; $reassigned_count = 0; $skipped_count = 0;

        $affiliate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $affiliates_table WHERE id = %d", $bulk_affiliate_id));

        if ($affiliate && $affiliate->affiliate_status === 'approved') {
            $affiliate_code  = $affiliate->affiliate_code;
            $commission_rate = floatval($affiliate->commission_rate);

            foreach ($selected_orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) { $skipped_count++; continue; }

                $order_total = $order->get_total();

                /*
                 * Effective rate resolution via helper:
                 *   - affiliate's normal rate by default
                 *   - overridden to 15% if the order has EU7
                 *   - capped at 10% if the order is a bundle order
                 */
                $effective_rate = act_resolve_manual_commission_rate( $order, $commission_rate );
                $commission     = $order_total * ( $effective_rate / 100 );

                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $referrals_table WHERE order_id = %d", $order_id
                ));

                if ($existing) {
                    if ((int) $existing->affiliate_id !== (int) $bulk_affiliate_id) {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $affiliates_table SET total_earned = GREATEST(total_earned - %f, 0) WHERE id = %d",
                            $existing->commission_amount, $existing->affiliate_id
                        ));
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $affiliates_table SET total_earned = total_earned + %f WHERE id = %d",
                            $commission, $bulk_affiliate_id
                        ));
                    } else {
                        $diff = $commission - $existing->commission_amount;
                        if (abs($diff) > 0.001) {
                            $wpdb->query($wpdb->prepare(
                                "UPDATE $affiliates_table SET total_earned = total_earned + %f WHERE id = %d",
                                $diff, $bulk_affiliate_id
                            ));
                        }
                    }

                    $wpdb->update($referrals_table, array(
                        'affiliate_id'        => $bulk_affiliate_id,
                        'order_number'        => $order->get_order_number(),
                        'customer_first_name' => $order->get_billing_first_name(),
                        'customer_last_name'  => $order->get_billing_last_name(),
                        'customer_email'      => $order->get_billing_email(),
                        'order_amount'        => $order_total,
                        'commission_amount'   => $commission,
                    ), array('order_id' => $order_id));

                    $order->update_meta_data('_act_affiliate_code', $affiliate_code);
                    $order->save();
                    $reassigned_count++;

                } else {
                    $wpdb->insert($referrals_table, array(
                        'affiliate_id' => $bulk_affiliate_id,
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
                    $wpdb->query($wpdb->prepare("UPDATE $affiliates_table SET total_earned = total_earned + %f WHERE id = %d", $commission, $bulk_affiliate_id));
                    $order->update_meta_data('_act_affiliate_code', $affiliate_code);
                    $order->save();
                    $assigned_count++;
                }
            }

            echo '<div class="notice notice-success"><p>';
            echo 'Assigned <strong>' . esc_html($assigned_count) . '</strong> new order(s), ';
            echo 'reassigned <strong>' . esc_html($reassigned_count) . '</strong> order(s) to ';
            echo '<strong>' . esc_html($affiliate_code) . '</strong>.';
            if ($skipped_count > 0) echo ' (' . esc_html($skipped_count) . ' skipped)';
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Invalid affiliate selected.</p></div>';
        }
    }

    // ----- Filters -----
    $search_query = isset($_GET['act_order_search']) ? sanitize_text_field(wp_unslash($_GET['act_order_search'])) : '';
    $sort_by      = isset($_GET['act_order_sort']) ? sanitize_text_field(wp_unslash($_GET['act_order_sort'])) : 'date_desc';
    $per_page     = isset($_GET['act_orders_per_page']) ? absint($_GET['act_orders_per_page']) : 25;
    $current_page = isset($_GET['act_paged']) ? max(1, absint($_GET['act_paged'])) : 1;
    $show_filter  = isset($_GET['act_show']) ? sanitize_key($_GET['act_show']) : 'all';

    if (!in_array($per_page, array(10, 25, 50, 100), true)) $per_page = 25;
    if (!in_array($show_filter, array('all', 'unassigned', 'assigned'), true)) $show_filter = 'all';

    // ----- Use WooCommerce's native query (works with HPOS and legacy) -----

    $query_args = array(
        'limit'    => $per_page,
        'page'     => $current_page,
        'paginate' => true,
        'status'   => array('completed', 'processing', 'pending', 'on-hold'),
        'orderby'  => 'date',
        'order'    => 'DESC',
    );

    if ($search_query !== '') {
        $query_args['limit'] = $per_page * 3;
    }

    if ($show_filter === 'unassigned') {
        $query_args['meta_query'] = array(
            'relation' => 'AND',
            array(
                'key'     => '_act_affiliate_code',
                'compare' => 'NOT EXISTS',
            ),
        );
    } elseif ($show_filter === 'assigned') {
        $query_args['meta_query'] = array(
            'relation' => 'AND',
            array(
                'key'     => '_act_affiliate_code',
                'compare' => 'EXISTS',
            ),
        );
    }

    $results = wc_get_orders($query_args);

    $orders_list = $results->orders;
    $total_orders = $results->total;
    $total_pages = $results->max_num_pages;

    if ($search_query !== '') {
        $needle = strtolower($search_query);
        $kept = array();
        foreach ($orders_list as $o) {
            $hay = strtolower(
                $o->get_order_number() . ' ' .
                $o->get_billing_first_name() . ' ' .
                $o->get_billing_last_name() . ' ' .
                $o->get_billing_email() . ' ' .
                $o->get_status()
            );
            if (strpos($hay, $needle) !== false) $kept[] = $o;
        }
        $orders_list = array_slice($kept, 0, $per_page);
    }

    if ($total_pages < 1) $total_pages = 1;
    if ($current_page > $total_pages) $current_page = $total_pages;

    usort($orders_list, function ($a, $b) use ($sort_by) {
        $a_name = trim($a->get_billing_first_name() . ' ' . $a->get_billing_last_name());
        $b_name = trim($b->get_billing_first_name() . ' ' . $b->get_billing_last_name());
        $a_date = $a->get_date_created() ? $a->get_date_created()->getTimestamp() : 0;
        $b_date = $b->get_date_created() ? $b->get_date_created()->getTimestamp() : 0;

        switch ($sort_by) {
            case 'customer_az':       return strcasecmp($a_name, $b_name);
            case 'customer_za':       return strcasecmp($b_name, $a_name);
            case 'order_number_asc':  return strnatcasecmp($a->get_order_number(), $b->get_order_number());
            case 'order_number_desc': return strnatcasecmp($b->get_order_number(), $a->get_order_number());
            case 'total_low_high':    return floatval($a->get_total()) <=> floatval($b->get_total());
            case 'total_high_low':    return floatval($b->get_total()) <=> floatval($a->get_total());
            case 'date_asc':          return $a_date <=> $b_date;
            case 'date_desc':
            default:                  return $b_date <=> $a_date;
        }
    });

    $paged_orders = array();
    $order_ids_on_page = array_map(function($o){ return $o->get_id(); }, $orders_list);
    $referral_map = array();
    if (!empty($order_ids_on_page)) {
        $in = implode(',', array_map('intval', $order_ids_on_page));
        $ref_rows = $wpdb->get_results("
            SELECT r.order_id, r.affiliate_id, a.affiliate_code, u.display_name
            FROM $referrals_table r
            LEFT JOIN $affiliates_table a ON a.id = r.affiliate_id
            LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
            WHERE r.order_id IN ($in)
        ");
        foreach ($ref_rows as $row) $referral_map[$row->order_id] = $row;
    }

    foreach ($orders_list as $o) {
        $ref = isset($referral_map[$o->get_id()]) ? $referral_map[$o->get_id()] : null;
        $date_created = $o->get_date_created();
        $paged_orders[] = array(
            'id'                     => $o->get_id(),
            'order'                  => $o,
            'order_number'           => $o->get_order_number(),
            'customer_name'          => trim($o->get_billing_first_name() . ' ' . $o->get_billing_last_name()) ?: 'Guest',
            'email'                  => $o->get_billing_email() ?: '—',
            'total'                  => floatval($o->get_total()),
            'status'                 => $o->get_status(),
            'date'                   => $date_created ? $date_created->getTimestamp() : 0,
            'current_affiliate_id'   => $ref ? (int) $ref->affiliate_id : 0,
            'current_affiliate_code' => $ref ? $ref->affiliate_code : '',
            'current_affiliate_name' => $ref ? $ref->display_name : '',
        );
    }

    $affiliates = $wpdb->get_results(
        "SELECT a.id, a.user_id, a.affiliate_code, a.commission_rate, u.display_name
         FROM $affiliates_table a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
         WHERE a.affiliate_status = 'approved' ORDER BY u.display_name ASC LIMIT 200"
    );

    $base_args = array(
        'page'                => 'act-manual-assignment',
        'act_order_search'    => $search_query,
        'act_order_sort'      => $sort_by,
        'act_orders_per_page' => $per_page,
        'act_show'            => $show_filter,
    );
    ?>

    <div class="wrap">
        <h1>Manual Order Assignment</h1>
        <p>Assign <em>or reassign</em> any order to any affiliate.</p>

        <style>
            .act-bulk-filters, .act-bulk-actions { background:#fff; padding:20px; margin:20px 0; border-radius:8px; display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
            .act-bulk-filters input[type="search"] { min-width: 220px; max-width: 100%; }
            .act-stats { background:#d4edda; padding:10px 20px; border-radius:8px; margin:10px 0 15px; display:inline-block; }
            .act-pagination { display:flex; gap:6px; flex-wrap:wrap; align-items:center; margin:15px 0; }
            .act-pagination a, .act-pagination span { padding:6px 10px; background:#fff; border:1px solid #ccd0d4; border-radius:4px; text-decoration:none; line-height:1.4; }
            .act-pagination .current { background:#2271b1; border-color:#2271b1; color:#fff; }
            .act-pagination .disabled { opacity:.5; cursor:not-allowed; }
            .act-bulk-table-wrapper { width:100%; max-width:100%; overflow-x:auto; background:#fff; border-radius:8px; margin-top:20px; }
            .act-bulk-table { width:max-content; min-width:100%; border-collapse:collapse; }
            .act-bulk-table th, .act-bulk-table td { padding:12px; border-bottom:1px solid #e9ecef; white-space:nowrap; vertical-align:middle; }
            .act-bulk-table th { background:#f1f1f1; text-align:left; font-weight:600; }
            .act-bulk-table tr:hover { background:#f9f9f9; }
            .act-bulk-table th:first-child, .act-bulk-table td:first-child { width:50px; text-align:center; }
            .aff-pill { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; }
            .aff-pill--none { background:#f1f5f9; color:#64748b; }
            .aff-pill--assigned { background:#d1fae5; color:#065f46; }
        </style>

        <form method="get" class="act-bulk-filters">
            <input type="hidden" name="page" value="act-manual-assignment">

            <input type="search" name="act_order_search" value="<?php echo esc_attr($search_query); ?>" placeholder="Search by order #, email, name">

            <select name="act_show">
                <option value="all" <?php selected($show_filter, 'all'); ?>>Show: All orders</option>
                <option value="unassigned" <?php selected($show_filter, 'unassigned'); ?>>Show: Unassigned only</option>
                <option value="assigned" <?php selected($show_filter, 'assigned'); ?>>Show: Assigned only</option>
            </select>

            <select name="act_order_sort">
                <option value="date_desc" <?php selected($sort_by, 'date_desc'); ?>>Newest first</option>
                <option value="date_asc" <?php selected($sort_by, 'date_asc'); ?>>Oldest first</option>
                <option value="customer_az" <?php selected($sort_by, 'customer_az'); ?>>Customer A-Z</option>
                <option value="customer_za" <?php selected($sort_by, 'customer_za'); ?>>Customer Z-A</option>
                <option value="order_number_asc" <?php selected($sort_by, 'order_number_asc'); ?>>Order #: Low to High</option>
                <option value="order_number_desc" <?php selected($sort_by, 'order_number_desc'); ?>>Order #: High to Low</option>
                <option value="total_low_high" <?php selected($sort_by, 'total_low_high'); ?>>Total: Low to High</option>
                <option value="total_high_low" <?php selected($sort_by, 'total_high_low'); ?>>Total: High to Low</option>
            </select>

            <select name="act_orders_per_page">
                <option value="10" <?php selected($per_page, 10); ?>>10 per page</option>
                <option value="25" <?php selected($per_page, 25); ?>>25 per page</option>
                <option value="50" <?php selected($per_page, 50); ?>>50 per page</option>
                <option value="100" <?php selected($per_page, 100); ?>>100 per page</option>
            </select>

            <button type="submit" class="button button-primary">Apply</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=act-manual-assignment')); ?>" class="button">Reset</a>
        </form>

        <div class="act-stats">
            📊 <strong><?php echo esc_html($total_orders); ?></strong> matching order(s).
            Page <strong><?php echo esc_html($current_page); ?></strong> of <strong><?php echo esc_html($total_pages); ?></strong>.
        </div>

        <div class="act-pagination">
            <?php
            if ($current_page > 1) {
                echo '<a href="' . esc_url(add_query_arg(array_merge($base_args, array('act_paged' => $current_page - 1)), admin_url('admin.php'))) . '">Previous</a>';
            } else {
                echo '<span class="disabled">Previous</span>';
            }

            $start_page = max(1, $current_page - 2);
            $end_page   = min($total_pages, $current_page + 2);

            if ($start_page > 1) {
                echo '<a href="' . esc_url(add_query_arg(array_merge($base_args, array('act_paged' => 1)), admin_url('admin.php'))) . '">1</a>';
                if ($start_page > 2) echo '<span>...</span>';
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i === $current_page) {
                    echo '<span class="current">' . esc_html($i) . '</span>';
                } else {
                    echo '<a href="' . esc_url(add_query_arg(array_merge($base_args, array('act_paged' => $i)), admin_url('admin.php'))) . '">' . esc_html($i) . '</a>';
                }
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) echo '<span>...</span>';
                echo '<a href="' . esc_url(add_query_arg(array_merge($base_args, array('act_paged' => $total_pages)), admin_url('admin.php'))) . '">' . esc_html($total_pages) . '</a>';
            }

            if ($current_page < $total_pages) {
                echo '<a href="' . esc_url(add_query_arg(array_merge($base_args, array('act_paged' => $current_page + 1)), admin_url('admin.php'))) . '">Next</a>';
            } else {
                echo '<span class="disabled">Next</span>';
            }
            ?>
        </div>

        <?php if (!empty($paged_orders)): ?>
            <form method="post" id="bulk-assign-form">
                <div class="act-bulk-actions">
                    <label><input type="checkbox" id="select-all-orders"> Select All on This Page</label>
                    <select name="bulk_affiliate_id" required>
                        <option value="">-- Select Affiliate --</option>
                        <?php foreach ($affiliates as $affiliate):
                            if (empty($affiliate->display_name)) continue; ?>
                            <option value="<?php echo esc_attr($affiliate->id); ?>">
                                <?php echo esc_html($affiliate->display_name . ' (' . $affiliate->affiliate_code . ') - ' . $affiliate->commission_rate . '%'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="act_bulk_assign" class="button button-primary" onclick="return confirm('Assign / reassign selected orders to this affiliate?');">
                        Assign / Reassign Selected Orders
                    </button>
                </div>

                <div class="act-bulk-table-wrapper">
                    <table class="wp-list-table widefat fixed striped act-bulk-table">
                        <thead><tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Order Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Currently Assigned To</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($paged_orders as $row):
                                $date_created = $row['order']->get_date_created();
                                $has_aff = $row['current_affiliate_id'] > 0;
                            ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_orders[]" value="<?php echo esc_attr($row['id']); ?>" class="order-checkbox"></td>
                                    <td><strong><a href="<?php echo esc_url(admin_url('post.php?post=' . $row['id'] . '&action=edit')); ?>" target="_blank">#<?php echo esc_html($row['order_number']); ?></a></strong></td>
                                    <td><?php echo esc_html($row['customer_name']); ?></td>
                                    <td><?php echo esc_html($row['email']); ?></td>
                                    <td>€<?php echo number_format($row['total'], 2); ?></td>
                                    <td><?php echo esc_html(ucfirst($row['status'])); ?></td>
                                    <td><?php echo $date_created ? esc_html($date_created->date_i18n(get_option('date_format'))) : '—'; ?></td>
                                    <td>
                                        <?php if ($has_aff): ?>
                                            <span class="aff-pill aff-pill--assigned">
                                                <?php echo esc_html($row['current_affiliate_name'] . ' (' . $row['current_affiliate_code'] . ')'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="aff-pill aff-pill--none">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php else: ?>
            <div class="notice notice-info"><p>✅ No matching orders.</p></div>
        <?php endif; ?>

        <div class="act-pagination">
            <?php
            if ($current_page > 1) {
                echo '<a href="' . esc_url(add_query_arg(array_merge($base_args, array('act_paged' => $current_page - 1)), admin_url('admin.php'))) . '">Previous</a>';
            } else {
                echo '<span class="disabled">Previous</span>';
            }
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i === $current_page) echo '<span class="current">' . esc_html($i) . '</span>';
                else echo '<a href="' . esc_url(add_query_arg(array_merge($base_args, array('act_paged' => $i)), admin_url('admin.php'))) . '">' . esc_html($i) . '</a>';
            }
            if ($current_page < $total_pages) {
                echo '<a href="' . esc_url(add_query_arg(array_merge($base_args, array('act_paged' => $current_page + 1)), admin_url('admin.php'))) . '">Next</a>';
            } else {
                echo '<span class="disabled">Next</span>';
            }
            ?>
        </div>

        <script>
            document.getElementById('select-all')?.addEventListener('change', function(e) {
                document.querySelectorAll('.order-checkbox').forEach(function(cb) { cb.checked = e.target.checked; });
            });
            document.getElementById('select-all-orders')?.addEventListener('change', function(e) {
                document.querySelectorAll('.order-checkbox').forEach(function(cb) { cb.checked = e.target.checked; });
            });
        </script>

        <div style="margin-top:30px;padding:20px;background:#f0f7ff;border-radius:8px;">
            <h3>📖 How Bulk Assignment Works:</h3>
            <ol>
                <li><strong>Bulk:</strong> Tick orders → pick affiliate → "Assign / Reassign Selected Orders"</li>
                <li><strong>Reassignment:</strong> Already-assigned orders can be reassigned — commission is moved automatically from old to new affiliate.</li>
                <li><strong>Show filter:</strong> All / Unassigned only / Assigned only.</li>
                <li><strong>EU7 orders:</strong> When an order has the "eu7" coupon applied, commission pays a flat 15% — unless it's a bundle order, which still caps at 10%.</li>
                <li><strong>Individual order assignment:</strong> Use the "Affiliate Reassignment" box on the order edit page (Snippet 8).</li>
            </ol>
        </div>
    </div>
    <?php
}
endif;

// ==============================================
// QUICK ACTION ON ORDERS LIST
// ==============================================

if ( ! function_exists( 'act_manual_add_order_list_action' ) ) :
function act_manual_add_order_list_action($actions, $order) {
    global $wpdb;
    $referrals_table = $wpdb->prefix . 'act_referrals';

    $is_assigned = $wpdb->get_var($wpdb->prepare("SELECT id FROM $referrals_table WHERE order_id = %d", $order->get_id()));

    if (!$is_assigned) {
        $actions['assign_affiliate'] = array(
            'url' => admin_url('admin.php?page=act-manual-assignment&act_order_search=' . $order->get_order_number()),
            'name' => 'Assign to Affiliate',
            'action' => 'assign_affiliate',
        );
    }
    return $actions;
}
endif;
add_filter('woocommerce_shop_order_list_table_actions', 'act_manual_add_order_list_action', 10, 2);

return;