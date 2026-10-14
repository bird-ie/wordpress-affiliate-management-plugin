<?php
/**
 * Snippet 2: Affiliate System - Admin Pages (Memory-Optimized)
 * Place after Snippet 1
 *
 * Version 6.0:
 * - Affiliates table now uses JOIN + pagination (fixes memory exhaustion)
 * - Removed per-row get_userdata() calls
 * - Replaced "Add New Affiliate" users dropdown with AJAX search
 */

// ==============================================
// ADMIN AFFILIATES PAGE
// ==============================================

function act_core_affiliates_admin_page() {
    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';

    // Handle Edit Affiliate Code
    if (isset($_POST['act_edit_code']) && isset($_POST['affiliate_id']) && isset($_POST['new_affiliate_code'])) {
        $affiliate_id = intval($_POST['affiliate_id']);
        $new_code = strtoupper(sanitize_text_field($_POST['new_affiliate_code']));

        $code_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $affiliates_table WHERE affiliate_code = %s AND id != %d",
            $new_code, $affiliate_id
        ));

        if (!$code_exists && !empty($new_code)) {
            $old_code = $wpdb->get_var($wpdb->prepare("SELECT affiliate_code FROM $affiliates_table WHERE id = %d", $affiliate_id));
            $wpdb->update($affiliates_table, array('affiliate_code' => $new_code), array('id' => $affiliate_id));

            $affiliate = $wpdb->get_row($wpdb->prepare("SELECT coupon_id, customer_discount_rate, affiliate_status FROM $affiliates_table WHERE id = %d", $affiliate_id));
            if ($affiliate && $affiliate->coupon_id) {
                if (function_exists('WC') && class_exists('WC_Coupon')) {
                    try {
                        $coupon = new WC_Coupon($affiliate->coupon_id);
                        $coupon->set_code($new_code);
                        $coupon->save();
                    } catch (Exception $e) {}
                }
            } elseif ($affiliate && $affiliate->affiliate_status === 'approved') {
                act_core_create_coupon($new_code, $affiliate->customer_discount_rate ?: 5, $affiliate_id);
            }

            echo '<div class="notice notice-success"><p>Affiliate code changed from <strong>' . esc_html($old_code) . '</strong> to <strong>' . esc_html($new_code) . '</strong>!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Code already taken or empty. Please choose another code.</p></div>';
        }
    }

    // Handle Approve
    if (isset($_POST['act_approve_affiliate']) && isset($_POST['affiliate_id'])) {
        $affiliate_id = intval($_POST['affiliate_id']);
        $affiliate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $affiliates_table WHERE id = %d", $affiliate_id));

        if ($affiliate) {
            $wpdb->update($affiliates_table, array('affiliate_status' => 'approved'), array('id' => $affiliate_id));
            act_core_create_coupon($affiliate->affiliate_code, $affiliate->customer_discount_rate ?: 5, $affiliate_id);
            echo '<div class="notice notice-success"><p>Affiliate approved! Coupon created.</p></div>';
        }
    }

    // Handle Reject
    if (isset($_POST['act_reject_affiliate']) && isset($_POST['affiliate_id'])) {
        $affiliate_id = intval($_POST['affiliate_id']);
        $wpdb->update($affiliates_table, array('affiliate_status' => 'rejected'), array('id' => $affiliate_id));
        echo '<div class="notice notice-warning"><p>Affiliate rejected.</p></div>';
    }

    // Handle Add Affiliate
    if (isset($_POST['act_add_affiliate'])) {
        $user_id = intval($_POST['user_id']);
        $commission_rate = floatval($_POST['commission_rate']);
        $customer_discount = isset($_POST['customer_discount']) ? floatval($_POST['customer_discount']) : 5.00;
        $affiliate_code = strtoupper(substr(md5($user_id . time()), 0, 8));

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $affiliates_table WHERE user_id = %d", $user_id));

        if (!$exists && $user_id > 0) {
            $wpdb->insert($affiliates_table, array(
                'user_id' => $user_id,
                'affiliate_code' => $affiliate_code,
                'commission_rate' => $commission_rate,
                'customer_discount_rate' => $customer_discount,
                'affiliate_status' => 'approved',
                'created_at' => current_time('mysql')
            ));
            $affiliate_id = $wpdb->insert_id;
            act_core_create_coupon($affiliate_code, $customer_discount, $affiliate_id);
            echo '<div class="notice notice-success"><p>Affiliate added! Code: <strong>' . esc_html($affiliate_code) . '</strong></p></div>';
        } else {
            echo '<div class="notice notice-error"><p>User is already an affiliate or no user selected.</p></div>';
        }
    }

    // Handle Update Commission & Discount
    if (isset($_POST['act_update_rate']) && isset($_POST['affiliate_id'])) {
        $affiliate_id = intval($_POST['affiliate_id']);
        $rate = floatval($_POST['commission_rate']);
        $discount = isset($_POST['customer_discount']) ? floatval($_POST['customer_discount']) : null;

        $update_data = array('commission_rate' => $rate);
        if ($discount !== null) {
            $update_data['customer_discount_rate'] = $discount;
            $affiliate = $wpdb->get_row($wpdb->prepare("SELECT affiliate_code, coupon_id FROM $affiliates_table WHERE id = %d", $affiliate_id));
            if ($affiliate) {
                if ($affiliate->coupon_id && function_exists('WC') && class_exists('WC_Coupon')) {
                    try { $coupon = new WC_Coupon($affiliate->coupon_id); $coupon->set_amount($discount); $coupon->save(); } catch (Exception $e) {}
                } else {
                    act_core_create_coupon($affiliate->affiliate_code, $discount, $affiliate_id);
                }
            }
        }

        $wpdb->update($affiliates_table, $update_data, array('id' => $affiliate_id));
        echo '<div class="notice notice-success"><p>Affiliate updated!</p></div>';
    }

    // Handle Sync Coupon
    if (isset($_POST['act_sync_coupon']) && isset($_POST['affiliate_id'])) {
        $affiliate_id = intval($_POST['affiliate_id']);
        $affiliate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $affiliates_table WHERE id = %d", $affiliate_id));
        if ($affiliate && $affiliate->affiliate_status === 'approved') {
            $discount_rate = $affiliate->customer_discount_rate ?: 5;
            $coupon_id = act_core_create_coupon($affiliate->affiliate_code, $discount_rate, $affiliate_id);
            if ($coupon_id) {
                echo '<div class="notice notice-success"><p>Coupon created: <strong>' . esc_html($affiliate->affiliate_code) . '</strong></p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to create coupon.</p></div>';
            }
        }
    }

    // Get sorting parameters
    $sort_by = isset($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : 'id';
    $sort_order = isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc' ? 'ASC' : 'DESC';

    $allowed_sort = array('id', 'affiliate_code', 'commission_rate', 'customer_discount_rate', 'created_at', 'affiliate_status');
    if (!in_array($sort_by, $allowed_sort)) {
        $sort_by = 'id';
    }

    // --- Pagination ---
    $per_page = 50;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Total count
    $total_affiliates = (int) $wpdb->get_var("SELECT COUNT(*) FROM $affiliates_table");
    $total_pages = max(1, (int) ceil($total_affiliates / $per_page));

    // Pending count
    $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $affiliates_table WHERE affiliate_status = 'pending'");

    // Fetch affiliates with JOIN (single query, no per-row get_userdata)
    $affiliates = $wpdb->get_results($wpdb->prepare("
        SELECT a.*, u.display_name, u.user_email
        FROM $affiliates_table a
        LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
        ORDER BY a.$sort_by $sort_order
        LIMIT %d OFFSET %d
    ", $per_page, $offset));

    $sort_url = function($column) use ($sort_by, $sort_order, $current_page) {
        $new_order = ($sort_by === $column && $sort_order === 'DESC') ? 'asc' : 'desc';
        return add_query_arg(array('sort_by' => $column, 'sort_order' => $new_order, 'paged' => $current_page));
    };

    $sort_indicator = function($column) use ($sort_by, $sort_order) {
        if ($sort_by !== $column) return '';
        return $sort_order === 'DESC' ? ' ↓' : ' ↑';
    };
    ?>
    <div class="wrap">
        <h1>Affiliates <?php if($pending_count > 0): ?><span class="awaiting-mod" style="background:#ffc107;color:#333;padding:3px 8px;border-radius:12px;"><?php echo $pending_count; ?> Pending</span><?php endif; ?></h1>

        <style>
            .act-affiliates-table-wrapper { overflow-x: auto; max-width: 100%; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px; }
            .act-affiliates-table { width: 100%; min-width: 800px; border-collapse: collapse; background: #fff; }
            .act-affiliates-table th { background: #f1f1f1; padding: 12px; text-align: left; font-weight: 600; white-space: nowrap; }
            .act-affiliates-table th a { text-decoration: none; color: #23282d; }
            .act-affiliates-table th a:hover { color: #0073aa; }
            .act-affiliates-table td { padding: 12px; border-bottom: 1px solid #e9ecef; vertical-align: middle; }
            .act-affiliates-table tr:hover { background: #f9f9f9; }
            .act-status-approved { color: #28a745; font-weight: bold; }
            .act-status-pending { color: #ffc107; font-weight: bold; }
            .act-status-rejected { color: #dc3545; font-weight: bold; }
            .act-edit-code-form { display: inline-flex; gap: 5px; align-items: center; }
            .act-edit-code-form input { width: 120px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 4px; }
            .act-code-display { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        </style>

        <div class="act-affiliates-table-wrapper">
            <table class="wp-list-table widefat fixed striped act-affiliates-table">
                <thead>
                    <tr>
                        <th><a href="<?php echo esc_url($sort_url('id')); ?>">ID<?php echo $sort_indicator('id'); ?></a></th>
                        <th>User</th>
                        <th><a href="<?php echo esc_url($sort_url('affiliate_code')); ?>">Affiliate Code<?php echo $sort_indicator('affiliate_code'); ?></a></th>
                        <th>Referral URL</th>
                        <th><a href="<?php echo esc_url($sort_url('commission_rate')); ?>">Commission<?php echo $sort_indicator('commission_rate'); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_url('customer_discount_rate')); ?>">Customer Discount<?php echo $sort_indicator('customer_discount_rate'); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_url('affiliate_status')); ?>">Status<?php echo $sort_indicator('affiliate_status'); ?></a></th>
                        <th><a href="<?php echo esc_url($sort_url('created_at')); ?>">Registered<?php echo $sort_indicator('created_at'); ?></a></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($affiliates as $affiliate):
                        $referral_url = add_query_arg('ref', $affiliate->affiliate_code, home_url());
                        $status_class = 'act-status-' . ($affiliate->affiliate_status ?: 'pending');
                    ?>
                        <tr>
                            <td><?php echo $affiliate->id; ?></td>
                            <td>
                                <strong><?php echo $affiliate->display_name ? esc_html($affiliate->display_name) : 'N/A'; ?></strong><br>
                                <small><?php echo esc_html($affiliate->user_email); ?></small><br>
                                <small>ID: <?php echo $affiliate->user_id; ?></small>
                            </td>
                            <td>
                                <div class="act-code-display">
                                    <code><?php echo esc_html($affiliate->affiliate_code); ?></code>
                                    <button type="button" class="button button-small" onclick="toggleEditCode(<?php echo $affiliate->id; ?>, '<?php echo esc_js($affiliate->affiliate_code); ?>')">✏️ Edit</button>
                                </div>
                                <form method="post" id="edit-code-form-<?php echo $affiliate->id; ?>" style="display:none; margin-top:8px;" class="act-edit-code-form">
                                    <input type="hidden" name="affiliate_id" value="<?php echo $affiliate->id; ?>">
                                    <input type="text" name="new_affiliate_code" value="<?php echo esc_attr($affiliate->affiliate_code); ?>" placeholder="Enter new code" required>
                                    <button type="submit" name="act_edit_code" class="button button-small button-primary">Save</button>
                                    <button type="button" class="button button-small" onclick="cancelEditCode(<?php echo $affiliate->id; ?>)">Cancel</button>
                                </form>
                            </td>
                            <td><code style="font-size: 11px; word-break: break-all;"><?php echo esc_url($referral_url); ?></code></td>
                            <td>
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="affiliate_id" value="<?php echo $affiliate->id; ?>">
                                    <input type="number" name="commission_rate" step="0.01" value="<?php echo $affiliate->commission_rate; ?>" style="width: 60px;">
                                    <input type="submit" name="act_update_rate" value="Update" class="button button-small">
                                </form>
                            </td>
                            <td>
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="affiliate_id" value="<?php echo $affiliate->id; ?>">
                                    <input type="number" name="customer_discount" step="0.01" value="<?php echo $affiliate->customer_discount_rate ?: 5; ?>" style="width: 60px;">
                                    <input type="submit" name="act_update_rate" value="Set" class="button button-small">
                                </form>
                            </td>
                            <td><span class="<?php echo $status_class; ?>"><?php echo ucfirst($affiliate->affiliate_status ?: 'pending'); ?></span></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($affiliate->created_at)); ?></td>
                            <td>
                                <?php if($affiliate->affiliate_status === 'pending'): ?>
                                    <form method="post" style="display: inline-block;">
                                        <input type="hidden" name="affiliate_id" value="<?php echo $affiliate->id; ?>">
                                        <input type="submit" name="act_approve_affiliate" value="Approve" class="button button-small button-primary">
                                    </form>
                                    <form method="post" style="display: inline-block;">
                                        <input type="hidden" name="affiliate_id" value="<?php echo $affiliate->id; ?>">
                                        <input type="submit" name="act_reject_affiliate" value="Reject" class="button button-small">
                                    </form>
                                <?php elseif($affiliate->affiliate_status === 'approved' && !$affiliate->coupon_id): ?>
                                    <form method="post" style="display: inline-block;">
                                        <input type="hidden" name="affiliate_id" value="<?php echo $affiliate->id; ?>">
                                        <input type="submit" name="act_sync_coupon" value="Create Coupon" class="button button-small">
                                    </form>
                                <?php else: ?>
                                    <span style="color:#28a745;">✓ Active</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($affiliates)): ?>
                        <tr><td colspan="9" style="text-align: center; padding: 40px;">No affiliates found. Add your first affiliate below.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div style="margin-top: 15px;">
                <?php
                $base = admin_url('admin.php?page=act-affiliates');
                if (!empty($sort_by)) {
                    $base = add_query_arg('sort_by', $sort_by, $base);
                    $base = add_query_arg('sort_order', strtolower($sort_order), $base);
                }
                echo paginate_links(array(
                    'base'      => add_query_arg('paged', '%#%', $base),
                    'format'    => '',
                    'current'   => $current_page,
                    'total'     => $total_pages,
                    'prev_text' => '« Prev',
                    'next_text' => 'Next »',
                ));
                ?>
            </div>
        <?php endif; ?>

        <h2>Add New Affiliate</h2>
        <p>Start typing a name or email to search for a user.</p>
        <form method="post">
            <input type="text" id="act-user-search" placeholder="Search user by name or email..." style="width:320px;" autocomplete="off">
            <input type="hidden" name="user_id" id="act-user-id" value="">
            <div id="act-user-results" style="margin:8px 0;"></div>

            <div style="margin-top:10px;">
                <input type="number" name="commission_rate" step="0.01" placeholder="Commission %" required style="width:120px;">
                <input type="number" name="customer_discount" step="0.01" placeholder="Discount %" value="5" style="width:120px;">
                <input type="submit" name="act_add_affiliate" value="Add Affiliate" class="button button-primary">
            </div>
        </form>
    </div>

    <script>
    function toggleEditCode(affiliateId, currentCode) {
        var displayDiv = document.querySelector('#edit-code-form-' + affiliateId).previousElementSibling;
        var form = document.getElementById('edit-code-form-' + affiliateId);

        if (form.style.display === 'none') {
            form.style.display = 'flex';
            if (displayDiv) displayDiv.style.display = 'none';
        } else {
            form.style.display = 'none';
            if (displayDiv) displayDiv.style.display = 'flex';
        }
    }

    function cancelEditCode(affiliateId) {
        var form = document.getElementById('edit-code-form-' + affiliateId);
        var displayDiv = form.previousElementSibling;

        form.style.display = 'none';
        if (displayDiv) displayDiv.style.display = 'flex';

        var originalCode = displayDiv.querySelector('code').innerText;
        form.querySelector('input[name="new_affiliate_code"]').value = originalCode;
    }

    jQuery(function($){
        var timer;
        $('#act-user-search').on('input', function(){
            clearTimeout(timer);
            var term = $(this).val();
            if (term.length < 2) { $('#act-user-results').empty(); return; }
            timer = setTimeout(function(){
                $.post(ajaxurl, {
                    action: 'act_search_users_for_affiliate',
                    nonce: '<?php echo esc_js(wp_create_nonce("act_search_users_nonce")); ?>',
                    term: term
                }, function(res){
                    if (res.success) {
                        var html = '';
                        $.each(res.data, function(i, u){
                            html += '<button type="button" class="button act-pick-user" data-id="' + u.id + '" style="margin:2px;">'
                                 + u.label + '</button> ';
                        });
                        $('#act-user-results').html(html || '<em>No users found</em>');
                    }
                });
            }, 300);
        });

        $(document).on('click', '.act-pick-user', function(){
            $('#act-user-id').val($(this).data('id'));
            $('#act-user-search').val($(this).text());
            $('#act-user-results').empty();
        });
    });
    </script>
    <?php
}

// ==============================================
// AJAX: SEARCH USERS FOR AFFILIATE ASSIGNMENT
// ==============================================

add_action('wp_ajax_act_search_users_for_affiliate', 'act_ajax_search_users_for_affiliate');

function act_ajax_search_users_for_affiliate() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    check_ajax_referer('act_search_users_nonce', 'nonce');

    $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
    if (strlen($term) < 2) {
        wp_send_json_success(array());
    }

    global $wpdb;
    $like = '%' . $wpdb->esc_like($term) . '%';

    // Direct SQL, no get_users() -> no meta cache priming
    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT ID, display_name, user_email
        FROM {$wpdb->users}
        WHERE (display_name LIKE %s OR user_email LIKE %s)
        ORDER BY display_name ASC
        LIMIT 20
    ", $like, $like));

    $out = array();
    foreach ($rows as $r) {
        $out[] = array(
            'id'    => (int) $r->ID,
            'label' => $r->display_name . ' (' . $r->user_email . ')',
        );
    }

    wp_send_json_success($out);
}

// ==============================================
// ADMIN SETTINGS PAGE
// ==============================================

function act_core_settings_page() {
    if (isset($_POST['act_save_settings']) && isset($_POST['act_settings_nonce']) && wp_verify_nonce($_POST['act_settings_nonce'], 'act_save_settings')) {
        update_option('act_dashboard_page_id', intval($_POST['dashboard_page_id']));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    ?>
    <div class="wrap"><h1>Affiliate Settings</h1>
        <form method="post">
            <?php wp_nonce_field('act_save_settings', 'act_settings_nonce'); ?>
            <table class="form-table">
                <tr><th><label for="dashboard_page">Affiliate Dashboard Page</label></th>
                <td><select name="dashboard_page_id" id="dashboard_page"><option value="">-- Select Page --</option>
                    <?php $pages = get_pages(); $selected = get_option('act_dashboard_page_id');
                    foreach ($pages as $page): ?>
                        <option value="<?php echo $page->ID; ?>" <?php selected($selected, $page->ID); ?>><?php echo $page->post_title; ?></option>
                    <?php endforeach; ?>
                </select><p class="description">Select the page with [affiliate_dashboard] shortcode</p></td></tr>
            </table>
            <?php submit_button('Save Settings', 'primary', 'act_save_settings'); ?>
        </form>
    </div>
    <?php
}

// ==============================================
// USER COLUMNS
// ==============================================

add_filter('manage_users_columns', 'act_core_user_columns');
function act_core_user_columns($columns) {
    $columns['affiliate_code'] = 'Affiliate Code';
    $columns['referral_url'] = 'Referral URL';
    $columns['commission_rate'] = 'Commission Rate';
    $columns['affiliate_status'] = 'Affiliate Status';
    return $columns;
}

add_action('manage_users_custom_column', 'act_core_user_column_values', 10, 3);
function act_core_user_column_values($value, $column_name, $user_id) {
    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $affiliate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $affiliates_table WHERE user_id = %d", $user_id));

    if ($column_name == 'affiliate_code') {
        return $affiliate ? '<code>' . esc_html($affiliate->affiliate_code) . '</code>' : '-';
    }
    if ($column_name == 'referral_url') {
        return $affiliate ? '<code style="font-size:11px;">' . esc_url(add_query_arg('ref', $affiliate->affiliate_code, home_url())) . '</code>' : '-';
    }
    if ($column_name == 'commission_rate') {
        return $affiliate ? $affiliate->commission_rate . '%' : '-';
    }
    if ($column_name == 'affiliate_status') {
        if (!$affiliate) return '-';
        if ($affiliate->affiliate_status === 'approved') return '<span style="color:#28a745;">Approved</span>';
        if ($affiliate->affiliate_status === 'pending') return '<span style="color:#ffc107;">Pending</span>';
        if ($affiliate->affiliate_status === 'rejected') return '<span style="color:#dc3545;">Rejected</span>';
        return '-';
    }
    return $value;
}

// ==============================================
// MENU REFRESH (removes Payouts submenu if it existed)
// ==============================================

add_action('admin_menu', 'act_core_remove_payouts_menu', 999);
function act_core_remove_payouts_menu() {
    remove_submenu_page('act-affiliates', 'act-payouts');
}

return;