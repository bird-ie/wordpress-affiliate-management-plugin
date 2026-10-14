<?php
/**
 * Snippet 7: Manage Affiliate Users
 * Adds a "Manage Affiliates" submenu under Affiliates.
 * Bulk + individual: Approve / Reject / Reset to Pending / Delete Record / Delete User
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==============================================
// REGISTER SUBMENU
// ==============================================

add_action('admin_menu', 'act_mng_register_menu', 30);
function act_mng_register_menu() {
    if (!function_exists('act_core_affiliates_admin_page')) {
        return;
    }

    add_submenu_page(
        'act-affiliates',
        'Manage Affiliates',
        'Manage Affiliates',
        'manage_options',
        'act-manage-affiliates',
        'act_mng_render_page'
    );
}

// ==============================================
// ACTION HANDLER
// ==============================================

add_action('admin_init', 'act_mng_handle_actions');
function act_mng_handle_actions() {
    if (!is_admin()) return;
    if (!isset($_GET['page']) || $_GET['page'] !== 'act-manage-affiliates') return;
    if (!current_user_can('manage_options')) return;

    // --- BULK action (POST) ---
    if (isset($_POST['act_mng_bulk_action']) && isset($_POST['act_mng_bulk_ids'])) {
        check_admin_referer('act_mng_bulk');

        $action = sanitize_key($_POST['act_mng_bulk_action']);
        $ids    = array_filter(array_map('intval', (array) $_POST['act_mng_bulk_ids']));

        if (empty($ids) || empty($action) || $action === '-1') {
            act_mng_redirect('no_selection');
        }

        $result = act_mng_run_action($action, $ids);
        act_mng_redirect($action, $result);
    }

    // --- SINGLE row action (GET with nonce) ---
    if (isset($_GET['act_mng_single'], $_GET['affiliate_id'], $_GET['_wpnonce'])) {
        $action = sanitize_key($_GET['act_mng_single']);
        $aff_id = intval($_GET['affiliate_id']);

        if (!wp_verify_nonce($_GET['_wpnonce'], 'act_mng_single_' . $action . '_' . $aff_id)) {
            act_mng_redirect('bad_nonce');
        }

        if ($aff_id <= 0) {
            act_mng_redirect('no_selection');
        }

        $result = act_mng_run_action($action, array($aff_id));
        act_mng_redirect($action, $result);
    }
}

// ==============================================
// RUN ACTION
// ==============================================

if ( ! function_exists( 'act_mng_run_action' ) ) :
function act_mng_run_action($action, $ids) {
    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';

    $allowed = array('approve', 'reject', 'pending', 'delete_record', 'delete_user');
    if (!in_array($action, $allowed, true)) {
        return array('ok' => 0, 'skip' => count($ids));
    }

    $ok = 0; $skip = 0;

    foreach ($ids as $id) {
        $affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $affiliates_table WHERE id = %d", $id
        ));

        if (!$affiliate) { $skip++; continue; }

        switch ($action) {

            case 'approve':
                $wpdb->update(
                    $affiliates_table,
                    array('affiliate_status' => 'approved'),
                    array('id' => $id)
                );
                if (empty($affiliate->coupon_id) && function_exists('act_core_create_coupon')) {
                    act_core_create_coupon(
                        $affiliate->affiliate_code,
                        $affiliate->customer_discount_rate ?: 5,
                        $id
                    );
                }
                $ok++;
                break;

            case 'reject':
                $wpdb->update(
                    $affiliates_table,
                    array('affiliate_status' => 'rejected'),
                    array('id' => $id)
                );
                $ok++;
                break;

            case 'pending':
                $wpdb->update(
                    $affiliates_table,
                    array('affiliate_status' => 'pending'),
                    array('id' => $id)
                );
                $ok++;
                break;

            case 'delete_record':
                // Delete coupon
                if (!empty($affiliate->coupon_id) && function_exists('WC') && class_exists('WC_Coupon')) {
                    try {
                        $coupon = new WC_Coupon($affiliate->coupon_id);
                        $coupon->delete(true);
                    } catch (Exception $e) {}
                }

                // Remove affiliate role from user, keep them as customer
                $user = get_userdata($affiliate->user_id);
                if ($user) {
                    $user->remove_role('affiliate');
                    $roles = (array) $user->roles;
                    if (empty($roles)) {
                        $user->set_role('customer');
                    }
                }

                // Delete affiliate record (referrals preserved as historical orphans)
                $wpdb->delete($affiliates_table, array('id' => $id));
                $ok++;
                break;

            case 'delete_user':
                if (empty($affiliate->user_id)) { $skip++; continue 2; }

                // Safety: don't delete the current admin or any administrator
                if ((int) $affiliate->user_id === (int) get_current_user_id()) { $skip++; continue 2; }
                $user = get_userdata($affiliate->user_id);
                if ($user && in_array('administrator', (array) $user->roles, true)) { $skip++; continue 2; }

                // Delete coupon
                if (!empty($affiliate->coupon_id) && function_exists('WC') && class_exists('WC_Coupon')) {
                    try {
                        $coupon = new WC_Coupon($affiliate->coupon_id);
                        $coupon->delete(true);
                    } catch (Exception $e) {}
                }

                // Delete affiliate record
                $wpdb->delete($affiliates_table, array('id' => $id));

                // Delete the WordPress user account (and their posts/meta)
                require_once ABSPATH . 'wp-admin/includes/user.php';
                if (function_exists('wp_delete_user')) {
                    wp_delete_user($affiliate->user_id); // reassign = null => delete their content
                }
                $ok++;
                break;
        }
    }

    return array('ok' => $ok, 'skip' => $skip);
}
endif;

// ==============================================
// REDIRECT HELPER
// ==============================================

if ( ! function_exists( 'act_mng_redirect' ) ) :
function act_mng_redirect($msg, $result = null) {
    $args = array(
        'page' => 'act-manage-affiliates',
        'msg'  => $msg,
    );

    if (is_array($result)) {
        $args['ok']   = isset($result['ok'])   ? (int) $result['ok']   : 0;
        $args['skip'] = isset($result['skip']) ? (int) $result['skip'] : 0;
    }

    wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
    exit;
}
endif;

// ==============================================
// NOTICE MESSAGES
// ==============================================

if ( ! function_exists( 'act_mng_notice' ) ) :
function act_mng_notice() {
    if (empty($_GET['msg'])) return;

    $msg  = sanitize_key($_GET['msg']);
    $ok   = isset($_GET['ok'])   ? intval($_GET['ok'])   : 0;
    $skip = isset($_GET['skip']) ? intval($_GET['skip']) : 0;

    $label = array(
        'approve'       => 'approved',
        'reject'        => 'rejected',
        'pending'       => 'reset to pending',
        'delete_record' => 'affiliate record(s) deleted (user accounts kept)',
        'delete_user'   => 'affiliate(s) and user account(s) deleted',
    );

    if ($msg === 'no_selection') {
        echo '<div class="notice notice-warning is-dismissible"><p>No affiliates selected.</p></div>';
        return;
    }

    if ($msg === 'bad_nonce') {
        echo '<div class="notice notice-error is-dismissible"><p>Security check failed. Please try again.</p></div>';
        return;
    }

    if (isset($label[$msg])) {
        $text = '<strong>' . esc_html($ok) . '</strong> affiliate(s) ' . esc_html($label[$msg]) . '.';
        if ($skip > 0) {
            $text .= ' <span style="color:#856404;">' . esc_html($skip) . ' skipped (already gone, admin, or current user).</span>';
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . $text . '</p></div>';
    }
}
endif;

// ==============================================
// RENDER PAGE
// ==============================================

if ( ! function_exists( 'act_mng_render_page' ) ) :
function act_mng_render_page() {
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $referrals_table  = $wpdb->prefix . 'act_referrals';

    // Filters
    $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
    if (!in_array($status_filter, array('all', 'pending', 'approved', 'rejected'), true)) {
        $status_filter = 'all';
    }
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

    $per_page     = 50;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset       = ($current_page - 1) * $per_page;

    // WHERE clause
    $where  = '1=1';
    $params = array();

    if ($status_filter !== 'all') {
        $where   .= ' AND a.affiliate_status = %s';
        $params[] = $status_filter;
    }

    if ($search !== '') {
        $like     = '%' . $wpdb->esc_like($search) . '%';
        $where   .= ' AND (a.affiliate_code LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    // Total count
    $count_sql = "SELECT COUNT(*)
                  FROM $affiliates_table a
                  LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                  WHERE $where";

    $total = $params
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params))
        : (int) $wpdb->get_var($count_sql);

    $total_pages = max(1, (int) ceil($total / $per_page));
    if ($current_page > $total_pages) $current_page = $total_pages;

    // Main query with referral_count subquery
    $sql = "SELECT a.*, u.user_email, u.display_name, u.user_registered,
                (SELECT COUNT(*) FROM $referrals_table r WHERE r.affiliate_id = a.id) as referral_count
            FROM $affiliates_table a
            LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
            WHERE $where
            ORDER BY a.id DESC
            LIMIT %d OFFSET %d";

    $query_params = $params;
    $query_params[] = $per_page;
    $query_params[] = $offset;

    $rows = $wpdb->get_results($wpdb->prepare($sql, $query_params));

    // Stats
    $stats = $wpdb->get_row("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN affiliate_status='pending'  THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN affiliate_status='approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN affiliate_status='rejected' THEN 1 ELSE 0 END) as rejected
        FROM $affiliates_table
    ");

    $pending_count  = (int) ($stats->pending  ?? 0);
    $approved_count = (int) ($stats->approved ?? 0);
    $rejected_count = (int) ($stats->rejected ?? 0);
    $all_count      = (int) ($stats->total    ?? 0);

    $base_url = admin_url('admin.php?page=act-manage-affiliates');
    $filter_args = array();
    if ($status_filter !== 'all') $filter_args['status'] = $status_filter;
    if ($search !== '')           $filter_args['s']      = $search;
    $filtered_url = $filter_args ? add_query_arg($filter_args, $base_url) : $base_url;
    ?>
    <div class="wrap">
        <h1>Manage Affiliates</h1>

        <?php act_mng_notice(); ?>

        <style>
            .act-mng-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:14px; margin:20px 0; }
            .act-mng-stat  { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:14px 18px; }
            .act-mng-stat h3 { margin:0 0 6px; font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.5px; }
            .act-mng-stat .num { font-size:26px; font-weight:800; line-height:1; }
            .act-mng-stat--pending  .num { color:#d97706; }
            .act-mng-stat--approved .num { color:#16a34a; }
            .act-mng-stat--rejected .num { color:#dc2626; }
            .act-mng-stat--total    .num { color:#2271b1; }

            .act-mng-filters { background:#fff; padding:14px 20px; border-radius:8px; margin:20px 0; display:flex; gap:12px; flex-wrap:wrap; align-items:center; border:1px solid #e2e8f0; }

            .act-mng-status { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
            .act-mng-status--pending  { background:#fef3c7; color:#92400e; }
            .act-mng-status--approved { background:#dcfce7; color:#166534; }
            .act-mng-status--rejected { background:#fee2e2; color:#991b1b; }

            .act-mng-table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; border:1px solid #e2e8f0; }
            .act-mng-table th, .act-mng-table td { padding:11px 14px; text-align:left; border-bottom:1px solid #e9ecef; font-size:13.5px; vertical-align:middle; }
            .act-mng-table thead th { background:#f8fafc; font-weight:600; white-space:nowrap; }
            .act-mng-table tbody tr:last-child td { border-bottom:none; }
            .act-mng-table tbody tr:hover { background:#f9fafb; }
            .act-mng-table .col-check { width:40px; text-align:center; }

            .act-mng-code { font-family:monospace; background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:12.5px; }

            .act-mng-row-actions { display:flex; gap:6px; flex-wrap:wrap; }
            .act-mng-row-actions .button { font-size:12px !important; padding:2px 10px !important; height:auto !important; line-height:1.7 !important; }
            .act-mng-row-actions .button-link-delete { color:#b91c1c !important; border-color:#fecaca !important; background:#fef2f2 !important; }

            .act-mng-bulk-bar { background:#fff; padding:14px 20px; border-radius:8px; margin:16px 0; border:1px solid #e2e8f0; display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
            .act-mng-bulk-bar select { min-width:240px; }

            .act-mng-pagination { margin-top:16px; display:flex; gap:6px; flex-wrap:wrap; }
            .act-mng-pagination a, .act-mng-pagination span {
                display:inline-block; padding:5px 11px; background:#fff; border:1px solid #d0d4d8;
                border-radius:4px; text-decoration:none; color:#2271b1;
            }
            .act-mng-pagination .current { background:#2271b1; color:#fff; border-color:#2271b1; font-weight:600; }
            .act-mng-pagination .disabled { opacity:.45; color:#888; }

            .act-mng-danger-hint { font-size:11.5px; color:#b45309; margin-top:4px; display:block; }
        </style>

        <div class="act-mng-stats">
            <div class="act-mng-stat act-mng-stat--total">
                <h3>All</h3>
                <div class="num"><?php echo $all_count; ?></div>
            </div>
            <div class="act-mng-stat act-mng-stat--pending">
                <h3>Pending</h3>
                <div class="num"><?php echo $pending_count; ?></div>
            </div>
            <div class="act-mng-stat act-mng-stat--approved">
                <h3>Approved</h3>
                <div class="num"><?php echo $approved_count; ?></div>
            </div>
            <div class="act-mng-stat act-mng-stat--rejected">
                <h3>Rejected</h3>
                <div class="num"><?php echo $rejected_count; ?></div>
            </div>
        </div>

        <form method="get" class="act-mng-filters">
            <input type="hidden" name="page" value="act-manage-affiliates">

            <select name="status">
                <option value="all"      <?php selected($status_filter, 'all'); ?>>All Statuses</option>
                <option value="pending"  <?php selected($status_filter, 'pending'); ?>>Pending (<?php echo $pending_count; ?>)</option>
                <option value="approved" <?php selected($status_filter, 'approved'); ?>>Approved (<?php echo $approved_count; ?>)</option>
                <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>Rejected (<?php echo $rejected_count; ?>)</option>
            </select>

            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by code, email or name...">

            <button type="submit" class="button button-primary">Filter</button>
            <?php if ($status_filter !== 'all' || $search !== ''): ?>
                <a href="<?php echo esc_url($base_url); ?>" class="button">Reset</a>
            <?php endif; ?>
        </form>

        <?php if (empty($rows)): ?>
            <div class="notice notice-info"><p>No affiliates found for the current filter.</p></div>
        <?php else: ?>

        <form method="post" id="act-mng-form">
            <?php wp_nonce_field('act_mng_bulk'); ?>

            <div class="act-mng-bulk-bar">
                <label style="font-weight:600;">
                    <input type="checkbox" id="act-mng-select-all"> Select All on This Page
                </label>

                <select name="act_mng_bulk_action" required>
                    <option value="-1">— Bulk Action —</option>
                    <option value="approve">Approve</option>
                    <option value="reject">Reject</option>
                    <option value="pending">Reset to Pending</option>
                    <option value="delete_record">Delete Affiliate Record (keep user)</option>
                    <option value="delete_user">Delete Affiliate + User Account</option>
                </select>

                <button type="submit" class="button button-primary"
                        onclick="return act_mng_confirm_bulk(event);">
                    Apply to Selected
                </button>

                <span class="act-mng-danger-hint">
                    ⚠️ "Delete Affiliate + User Account" permanently removes the WordPress user and all their content.
                </span>
            </div>

            <table class="act-mng-table">
                <thead>
                    <tr>
                        <th class="col-check"><input type="checkbox" id="act-mng-select-all-header"></th>
                        <th>ID</th>
                        <th>User</th>
                        <th>Affiliate Code</th>
                        <th>Commission</th>
                        <th>Discount</th>
                        <th>Earned</th>
                        <th>Referrals</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $status_class = 'act-mng-status--' . ($r->affiliate_status ?: 'pending');
                    $status_label = ucfirst($r->affiliate_status ?: 'pending');
                    $nonce_base   = 'act_mng_single_%s_' . $r->id;
                    $single_url   = function ($action) use ($r, $base_url, $filter_args) {
                        return wp_nonce_url(
                            add_query_arg(array_merge($filter_args, array(
                                'page'          => 'act-manage-affiliates',
                                'act_mng_single'=> $action,
                                'affiliate_id'  => $r->id,
                            )), admin_url('admin.php')),
                            'act_mng_single_' . $action . '_' . $r->id
                        );
                    };
                ?>
                    <tr>
                        <td class="col-check">
                            <input type="checkbox" name="act_mng_bulk_ids[]" value="<?php echo (int) $r->id; ?>" class="act-mng-row-check">
                        </td>
                        <td><?php echo (int) $r->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($r->display_name ?: '—'); ?></strong><br>
                            <small style="color:#64748b;"><?php echo esc_html($r->user_email); ?></small><br>
                            <small style="color:#94a3b8;">User ID: <?php echo (int) $r->user_id; ?></small>
                        </td>
                        <td><span class="act-mng-code"><?php echo esc_html($r->affiliate_code); ?></span></td>
                        <td><?php echo esc_html($r->commission_rate); ?>%</td>
                        <td><?php echo esc_html($r->customer_discount_rate ?: 5); ?>%</td>
                        <td>€<?php echo number_format((float) $r->total_earned, 2); ?></td>
                        <td><?php echo (int) $r->referral_count; ?></td>
                        <td><span class="act-mng-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                        <td><?php echo esc_html($r->user_registered ? date_i18n(get_option('date_format'), strtotime($r->user_registered)) : '—'); ?></td>
                        <td>
                            <div class="act-mng-row-actions">
                                <?php if ($r->affiliate_status !== 'approved'): ?>
                                    <a class="button button-small" href="<?php echo esc_url($single_url('approve')); ?>">Approve</a>
                                <?php endif; ?>

                                <?php if ($r->affiliate_status !== 'rejected'): ?>
                                    <a class="button button-small" href="<?php echo esc_url($single_url('reject')); ?>">Reject</a>
                                <?php endif; ?>

                                <?php if ($r->affiliate_status !== 'pending'): ?>
                                    <a class="button button-small" href="<?php echo esc_url($single_url('pending')); ?>">Pending</a>
                                <?php endif; ?>

                                <a class="button button-small button-link-delete"
                                   href="<?php echo esc_url($single_url('delete_record')); ?>"
                                   onclick="return confirm('Delete the affiliate record for <?php echo esc_js($r->affiliate_code); ?>?\nThe user account is preserved, but the affiliate record and coupon will be removed.');">
                                    Delete Record
                                </a>

                                <a class="button button-small button-link-delete"
                                   href="<?php echo esc_url($single_url('delete_user')); ?>"
                                   onclick="return confirm('DELETE the user account AND affiliate record for <?php echo esc_js($r->user_email); ?>?\n\nThis permanently removes the WordPress user, all their content, and the affiliate record.\n\nThis cannot be undone.');">
                                    Delete + User
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </form>

        <?php if ($total_pages > 1): ?>
            <div class="act-mng-pagination">
                <?php
                $page_base = $filtered_url;

                if ($current_page > 1) {
                    echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $page_base)) . '">« Prev</a>';
                } else {
                    echo '<span class="disabled">« Prev</span>';
                }

                $start = max(1, $current_page - 3);
                $end   = min($total_pages, $current_page + 3);

                if ($start > 1) {
                    echo '<a href="' . esc_url(add_query_arg('paged', 1, $page_base)) . '">1</a>';
                    if ($start > 2) echo '<span class="disabled">…</span>';
                }

                for ($i = $start; $i <= $end; $i++) {
                    if ($i === $current_page) {
                        echo '<span class="current">' . $i . '</span>';
                    } else {
                        echo '<a href="' . esc_url(add_query_arg('paged', $i, $page_base)) . '">' . $i . '</a>';
                    }
                }

                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<span class="disabled">…</span>';
                    echo '<a href="' . esc_url(add_query_arg('paged', $total_pages, $page_base)) . '">' . $total_pages . '</a>';
                }

                if ($current_page < $total_pages) {
                    echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $page_base)) . '">Next »</a>';
                } else {
                    echo '<span class="disabled">Next »</span>';
                }
                ?>
            </div>
        <?php endif; ?>

        <?php endif; ?>

        <div style="margin-top:30px;padding:18px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
            <h3 style="margin-top:0;">💡 Notes</h3>
            <ul style="margin-left:20px;font-size:13.5px;line-height:1.7;">
                <li><strong>Approve</strong> sets the affiliate to <code>approved</code> and creates their WooCommerce coupon if it doesn't exist yet.</li>
                <li><strong>Reject</strong> marks them as <code>rejected</code> — the coupon is <em>not</em> deleted, so you can re-approve later.</li>
                <li><strong>Reset to Pending</strong> puts them back in the pending queue.</li>
                <li><strong>Delete Affiliate Record</strong> removes the affiliate row and coupon, and strips the <code>affiliate</code> role from the user. Referral history stays in the database as orphans, so past orders keep their affiliate IDs. The user can still log in as a customer.</li>
                <li><strong>Delete Affiliate + User Account</strong> additionally removes the WordPress user (and all their posts, meta, sessions). Admins and your own account are protected — the action will skip them.</li>
                <li>You cannot delete a user that is currently logged in or has the <code>administrator</code> role.</li>
            </ul>
        </div>
    </div>

    <script>
        function act_mng_confirm_bulk(e) {
            var form = document.getElementById('act-mng-form');
            var action = form.querySelector('select[name="act_mng_bulk_action"]').value;
            var checked = form.querySelectorAll('.act-mng-row-check:checked').length;

            if (action === '-1') {
                alert('Please choose a bulk action first.');
                e.preventDefault();
                return false;
            }
            if (checked === 0) {
                alert('Please select at least one affiliate.');
                e.preventDefault();
                return false;
            }

            var msg = 'Apply "' + action + '" to ' + checked + ' affiliate(s)?';

            if (action === 'delete_user') {
                msg += '\n\n⚠️ This permanently deletes the WordPress user accounts and all their content. This cannot be undone.';
            } else if (action === 'delete_record') {
                msg += '\n\nThe affiliate records and coupons will be deleted. User accounts will be kept.';
            }

            return confirm(msg);
        }

        (function () {
            var selectAllHeader = document.getElementById('act-mng-select-all-header');
            var selectAllBar    = document.getElementById('act-mng-select-all');
            var rowChecks       = document.querySelectorAll('.act-mng-row-check');

            function syncFrom(source) {
                rowChecks.forEach(function (cb) { cb.checked = source.checked; });
                if (selectAllHeader) selectAllHeader.checked = source.checked;
                if (selectAllBar)    selectAllBar.checked    = source.checked;
            }

            if (selectAllHeader) selectAllHeader.addEventListener('change', function () { syncFrom(this); });
            if (selectAllBar)    selectAllBar.addEventListener('change',    function () { syncFrom(this); });

            rowChecks.forEach(function (cb) {
                cb.addEventListener('change', function () {
                    var allChecked = Array.prototype.every.call(rowChecks, function (c) { return c.checked; });
                    if (selectAllHeader) selectAllHeader.checked = allChecked;
                    if (selectAllBar)    selectAllBar.checked    = allChecked;
                });
            });
        })();
    </script>
    <?php
}
endif;

return;