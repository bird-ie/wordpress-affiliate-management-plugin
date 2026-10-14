<?php
/**
 * Snippet 6: Selective Payout System for Affiliates
 * FIXED: Now counts only eligible orders (completed/processing) in the dropdown
 */

// ==============================================
// ADD SELECTIVE PAYOUT SUBMENU PAGE
// ==============================================

add_action('admin_menu', 'act_add_selective_payout_menu');
function act_add_selective_payout_menu() {
    add_submenu_page(
        'act-affiliates',
        'Selective Payout',
        'Selective Payout',
        'manage_options',
        'act-selective-payout',
        'act_selective_payout_page'
    );
}

// ==============================================
// HELPER FUNCTION: Get eligible pending orders count for an affiliate
// ==============================================

function act_get_eligible_pending_count($affiliate_id) {
    global $wpdb;
    $referrals_table = $wpdb->prefix . 'act_referrals';
    
    $pending_referrals = $wpdb->get_results($wpdb->prepare("
        SELECT r.*
        FROM $referrals_table r
        WHERE r.affiliate_id = %d AND r.status = 'pending'
    ", $affiliate_id));
    
    $eligible_count = 0;
    foreach ($pending_referrals as $referral) {
        $order = wc_get_order($referral->order_id);
        if ($order) {
            $order_status = $order->get_status();
            if ($order_status === 'completed' || $order_status === 'processing') {
                $eligible_count++;
            }
        }
    }
    
    return $eligible_count;
}

// ==============================================
// MAIN SELECTIVE PAYOUT PAGE
// ==============================================

function act_selective_payout_page() {
    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $referrals_table = $wpdb->prefix . 'act_referrals';
    
    $selected_affiliate_id = isset($_GET['affiliate_id']) ? intval($_GET['affiliate_id']) : 0;
    $message = '';
    $error = '';
    
    // Handle payout submission
    if (isset($_POST['act_process_selected_payout']) && isset($_POST['selected_orders']) && isset($_POST['affiliate_id'])) {
        $affiliate_id = intval($_POST['affiliate_id']);
        $selected_order_ids = array_map('intval', $_POST['selected_orders']);
        
        if (empty($selected_order_ids)) {
            $error = 'No orders selected for payout.';
        } else {
            $total_paid = 0;
            $paid_count = 0;
            $skipped_count = 0;
            
            foreach ($selected_order_ids as $referral_id) {
                $referral = $wpdb->get_row($wpdb->prepare("SELECT * FROM $referrals_table WHERE id = %d AND affiliate_id = %d AND status = 'pending'", $referral_id, $affiliate_id));
                
                if ($referral) {
                    // Double-check order is still eligible
                    $order = wc_get_order($referral->order_id);
                    $is_eligible = false;
                    if ($order) {
                        $order_status = $order->get_status();
                        if ($order_status === 'completed' || $order_status === 'processing') {
                            $is_eligible = true;
                        }
                    }
                    
                    if ($is_eligible) {
                        // Mark this referral as paid
                        $wpdb->update($referrals_table, array('status' => 'paid'), array('id' => $referral_id));
                        
                        // Update affiliate's total_paid
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $affiliates_table SET total_paid = total_paid + %f WHERE id = %d",
                            $referral->commission_amount,
                            $affiliate_id
                        ));
                        
                        $total_paid += $referral->commission_amount;
                        $paid_count++;
                        
                        // Add order note
                        if ($order) {
                            $order->add_order_note(sprintf(
                                'Commission for this order (€%s) has been paid to affiliate.',
                                number_format($referral->commission_amount, 2)
                            ));
                        }
                    } else {
                        $skipped_count++;
                    }
                }
            }
            
            if ($paid_count > 0) {
                $message = sprintf('Successfully paid €%s for %s order(s)!', number_format($total_paid, 2), $paid_count);
                if ($skipped_count > 0) {
                    $message .= sprintf(' (%s order(s) were skipped because they are no longer eligible)', $skipped_count);
                }
            } else {
                $error = 'No valid pending orders were selected.';
            }
        }
    }
    
    // Get all affiliates with their ELIGIBLE pending count
    $affiliates = $wpdb->get_results("
        SELECT a.*, u.display_name, u.user_email 
        FROM $affiliates_table a 
        LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
        WHERE a.affiliate_status = 'approved' 
        ORDER BY u.display_name ASC
    ");
    
    // Add eligible pending count to each affiliate
    foreach ($affiliates as $affiliate) {
        $affiliate->eligible_pending_count = act_get_eligible_pending_count($affiliate->id);
    }
    
    // Get pending referrals for selected affiliate (already filtered to eligible)
    $pending_referrals = array();
    $affiliate_info = null;
    $total_pending_amount = 0;
    
    if ($selected_affiliate_id > 0) {
        $affiliate_info = $wpdb->get_row($wpdb->prepare("SELECT * FROM $affiliates_table WHERE id = %d", $selected_affiliate_id));
        
        $all_pending = $wpdb->get_results($wpdb->prepare("
            SELECT r.*
            FROM $referrals_table r
            WHERE r.affiliate_id = %d AND r.status = 'pending'
            ORDER BY r.created_at ASC
        ", $selected_affiliate_id));
        
        // Filter to only completed/processing orders (eligible for payout)
        foreach ($all_pending as $referral) {
            $order = wc_get_order($referral->order_id);
            if ($order) {
                $order_status = $order->get_status();
                if ($order_status === 'completed' || $order_status === 'processing') {
                    $pending_referrals[] = $referral;
                    $total_pending_amount += floatval($referral->commission_amount);
                }
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Selective Payout System</h1>
        <p>Select specific orders to pay to affiliates instead of paying all pending earnings at once.</p>
        
        <?php if ($message): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>
        
        <style>
            .act-payout-container {
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                margin-top: 20px;
                overflow: hidden;
            }
            .act-payout-header {
                background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                color: white;
                padding: 20px 25px;
            }
            .act-payout-header h2 {
                margin: 0;
                color: white;
                font-size: 20px;
            }
            .act-payout-header p {
                margin: 5px 0 0;
                opacity: 0.9;
            }
            .act-payout-body {
                padding: 25px;
            }
            .act-affiliate-selector {
                background: #f8fafc;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 25px;
                border: 1px solid #e2e8f0;
            }
            .act-affiliate-selector select {
                min-width: 350px;
                padding: 10px;
                border-radius: 6px;
                border: 1px solid #ddd;
                margin-right: 10px;
            }
            .act-warning-box {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 12px 15px;
                margin-bottom: 20px;
                border-radius: 6px;
                font-size: 13px;
            }
            .act-warning-box p {
                margin: 0;
            }
            .act-stats-card {
                background: #f0fdf4;
                border-left: 4px solid #22c55e;
                padding: 15px 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            .act-stats-card h3 {
                margin: 0 0 5px;
                font-size: 14px;
                color: #166534;
            }
            .act-stats-card .amount {
                font-size: 32px;
                font-weight: bold;
                color: #16a34a;
            }
            .act-orders-table-wrapper {
                overflow-x: auto;
                margin-top: 20px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
            }
            .act-orders-table {
                width: 100%;
                border-collapse: collapse;
            }
            .act-orders-table th {
                background: #f8fafc;
                padding: 14px 12px;
                text-align: left;
                font-weight: 600;
                border-bottom: 1px solid #e2e8f0;
            }
            .act-orders-table td {
                padding: 12px;
                border-bottom: 1px solid #f1f5f9;
            }
            .act-orders-table tr:hover {
                background: #f8fafc;
            }
            .act-checkbox-cell {
                width: 40px;
                text-align: center;
            }
            .act-checkbox-cell input {
                width: 18px;
                height: 18px;
            }
            .act-payout-actions {
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
                display: flex;
                gap: 15px;
                align-items: center;
                flex-wrap: wrap;
            }
            .act-payout-summary {
                background: #f1f5f9;
                padding: 15px 20px;
                border-radius: 8px;
                flex: 1;
            }
            .act-payout-summary strong {
                font-size: 18px;
                color: #0073aa;
            }
            .act-btn-primary {
                background: #0073aa;
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 6px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }
            .act-btn-primary:hover {
                background: #005a87;
                transform: translateY(-1px);
            }
            .act-btn-secondary {
                background: #64748b;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 6px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
            }
            .act-btn-secondary:hover {
                background: #475569;
            }
            .act-status-paid {
                color: #16a34a;
                font-weight: 600;
            }
            .act-status-pending {
                color: #f59e0b;
                font-weight: 600;
            }
            .act-status-cancelled {
                color: #ef4444;
                font-weight: 600;
                text-decoration: line-through;
            }
            .act-empty-state {
                text-align: center;
                padding: 50px;
                color: #64748b;
            }
            .act-order-link {
                color: #0073aa;
                text-decoration: none;
            }
            .act-order-link:hover {
                text-decoration: underline;
            }
            .act-select-all {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 15px;
                padding: 10px 0;
            }
            .act-info-badge {
                background: #e0f2fe;
                color: #0369a1;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                margin-left: 8px;
            }
        </style>
        
        <div class="act-payout-container">
            <div class="act-payout-header">
                <h2>💰 Selective Payout</h2>
                <p>Choose which orders to pay for each affiliate individually</p>
            </div>
            
            <div class="act-payout-body">
                <!-- Affiliate Selector -->
                <div class="act-affiliate-selector">
                    <form method="GET" action="">
                        <input type="hidden" name="page" value="act-selective-payout">
                        <label style="font-weight:600; margin-right:10px;">Select Affiliate:</label>
                        <select name="affiliate_id" onchange="this.form.submit()">
                            <option value="">-- Choose an Affiliate --</option>
                            <?php foreach ($affiliates as $affiliate): 
                                $user = get_userdata($affiliate->user_id);
                                if ($user):
                                    $eligible_count = $affiliate->eligible_pending_count;
                                    $display_text = esc_html($user->display_name . ' (' . $affiliate->affiliate_code . ')');
                                    if ($eligible_count > 0) {
                                        $display_text .= ' - ' . $eligible_count . ' eligible order(s)';
                                    } else {
                                        $display_text .= ' - 0 eligible orders';
                                    }
                            ?>
                                <option value="<?php echo $affiliate->id; ?>" <?php selected($selected_affiliate_id, $affiliate->id); ?>>
                                    <?php echo $display_text; ?>
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <?php if ($selected_affiliate_id > 0 && $affiliate_info): 
                    $affiliate_user = get_userdata($affiliate_info->user_id);
                    $total_all_pending = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $referrals_table WHERE affiliate_id = %d AND status = 'pending'",
                        $selected_affiliate_id
                    ));
                    $ineligible_count = $total_all_pending - count($pending_referrals);
                ?>
                    <!-- Affiliate Stats -->
                    <div class="act-stats-card">
                        <h3>Affiliate: <?php echo esc_html($affiliate_user ? $affiliate_user->display_name : 'N/A'); ?></h3>
                        <div class="amount">€<?php echo number_format($total_pending_amount, 2); ?></div>
                        <p><?php echo count($pending_referrals); ?> eligible order(s) ready for payout</p>
                    </div>
                    
                    <?php if ($ineligible_count > 0): ?>
                        <div class="act-warning-box">
                            <p>⚠️ <strong>Note:</strong> <?php echo $ineligible_count; ?> pending order(s) are not showing because they have been cancelled, refunded, or are not in "Completed/Processing" status. Only eligible orders can be paid.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($pending_referrals)): ?>
                        <form method="post" id="selective-payout-form">
                            <input type="hidden" name="affiliate_id" value="<?php echo $selected_affiliate_id; ?>">
                            
                            <div class="act-select-all">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="select-all-orders"> 
                                    <strong>Select All Eligible Orders</strong>
                                    <span class="act-info-badge"><?php echo count($pending_referrals); ?> orders total</span>
                                </label>
                            </div>
                            
                            <div class="act-orders-table-wrapper">
                                <table class="act-orders-table">
                                    <thead>
                                        <tr>
                                            <th class="act-checkbox-cell"><input type="checkbox" id="select-all-header"></th>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Email</th>
                                            <th>Order Total</th>
                                            <th>Commission</th>
                                            <th>Order Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_referrals as $referral):
                                            $order = wc_get_order($referral->order_id);
                                            $order_number = $order ? $order->get_order_number() : $referral->order_number;
                                            $order_status = $order ? $order->get_status() : 'unknown';
                                            $customer_name = trim($referral->customer_first_name . ' ' . $referral->customer_last_name);
                                            if (empty($customer_name)) $customer_name = $referral->customer_email ?: 'Guest';
                                        ?>
                                            <tr>
                                                <td class="act-checkbox-cell">
                                                    <input type="checkbox" name="selected_orders[]" value="<?php echo $referral->id; ?>" class="order-checkbox" data-amount="<?php echo $referral->commission_amount; ?>">
                                                </td>
                                                <td>
                                                    <a href="<?php echo admin_url('post.php?post=' . $referral->order_id . '&action=edit'); ?>" target="_blank" class="act-order-link">
                                                        #<?php echo esc_html($order_number); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo esc_html($customer_name); ?></td>
                                                <td><?php echo esc_html($referral->customer_email); ?></td>
                                                <td>€<?php echo number_format($referral->order_amount, 2); ?></td>
                                                <td><strong>€<?php echo number_format($referral->commission_amount, 2); ?></strong></td>
                                                <td><span class="act-status-pending"><?php echo ucfirst($order_status); ?></span></td>
                                                <td><?php echo date_i18n(get_option('date_format'), strtotime($referral->created_at)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot style="background: #f8fafc;">
                                        <tr>
                                            <td colspan="5" style="text-align: right;"><strong>Total Selected Commission:</strong></td>
                                            <td colspan="3"><strong id="selected-total">€0.00</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="act-payout-actions">
                                <div class="act-payout-summary">
                                    <strong id="summary-text">No orders selected</strong>
                                </div>
                                <button type="submit" name="act_process_selected_payout" class="act-btn-primary" onclick="return confirmSelectedPayout()">
                                    💰 Pay Selected Orders
                                </button>
                                <a href="?page=act-selective-payout&affiliate_id=<?php echo $selected_affiliate_id; ?>" class="act-btn-secondary">Reset</a>
                            </div>
                        </form>
                        
                        <script>
                            // Update total when checkboxes change
                            function updateSelectedTotal() {
                                const checkboxes = document.querySelectorAll('.order-checkbox:checked');
                                let total = 0;
                                checkboxes.forEach(cb => {
                                    total += parseFloat(cb.dataset.amount || 0);
                                });
                                const totalElement = document.getElementById('selected-total');
                                const summaryText = document.getElementById('summary-text');
                                if (totalElement) {
                                    totalElement.innerHTML = '€' + total.toFixed(2);
                                }
                                if (summaryText) {
                                    if (checkboxes.length === 0) {
                                        summaryText.innerHTML = 'No orders selected';
                                    } else if (checkboxes.length === 1) {
                                        summaryText.innerHTML = '1 order selected for payment (€' + total.toFixed(2) + ')';
                                    } else {
                                        summaryText.innerHTML = checkboxes.length + ' orders selected for payment (€' + total.toFixed(2) + ')';
                                    }
                                }
                            }
                            
                            // Select/Deselect all
                            function initSelectAll() {
                                const selectAllHeader = document.getElementById('select-all-header');
                                const selectAllOrders = document.getElementById('select-all-orders');
                                const allCheckboxes = document.querySelectorAll('.order-checkbox');
                                
                                const updateSelectAll = () => {
                                    const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
                                    if (selectAllHeader) selectAllHeader.checked = allChecked;
                                    if (selectAllOrders) selectAllOrders.checked = allChecked;
                                    updateSelectedTotal();
                                };
                                
                                if (selectAllHeader) {
                                    selectAllHeader.addEventListener('change', function(e) {
                                        allCheckboxes.forEach(cb => cb.checked = e.target.checked);
                                        updateSelectAll();
                                    });
                                }
                                
                                if (selectAllOrders) {
                                    selectAllOrders.addEventListener('change', function(e) {
                                        allCheckboxes.forEach(cb => cb.checked = e.target.checked);
                                        updateSelectAll();
                                    });
                                }
                                
                                allCheckboxes.forEach(cb => {
                                    cb.addEventListener('change', updateSelectAll);
                                });
                                
                                updateSelectAll();
                            }
                            
                            function confirmSelectedPayout() {
                                const selectedCount = document.querySelectorAll('.order-checkbox:checked').length;
                                if (selectedCount === 0) {
                                    alert('Please select at least one order to pay.');
                                    return false;
                                }
                                const totalElement = document.getElementById('selected-total');
                                const total = totalElement ? totalElement.innerText : '€0.00';
                                return confirm(`Are you sure you want to pay ${selectedCount} order(s) totaling ${total}?\n\nThis action cannot be undone.`);
                            }
                            
                            document.addEventListener('DOMContentLoaded', initSelectAll);
                        </script>
                        
                    <?php else: ?>
                        <div class="act-empty-state">
                            <p>🎉 No eligible pending orders found for this affiliate!</p>
                            <?php if ($total_all_pending > 0): ?>
                                <p>⚠️ However, this affiliate has <strong><?php echo $total_all_pending; ?> pending order(s)</strong> that are NOT eligible because they are cancelled, refunded, or not in "Completed/Processing" status.</p>
                                <p>Only orders with "Completed" or "Processing" status can be paid.</p>
                            <?php else: ?>
                                <p>All commissions have been paid or there are no orders.</p>
                            <?php endif; ?>
                            <a href="?page=act-selective-payout" class="act-btn-secondary" style="margin-top:15px; display:inline-block;">Go Back</a>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($selected_affiliate_id > 0): ?>
                    <div class="notice notice-error"><p>Affiliate not found.</p></div>
                <?php else: ?>
                    <div class="act-empty-state">
                        <p>👈 Select an affiliate from the dropdown above to view their eligible pending orders.</p>
                        <p>Only orders with <strong>"Completed"</strong> or <strong>"Processing"</strong> status can be paid.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// ==============================================
// AJAX HANDLER FOR GETTING PENDING ORDERS (Optional)
// ==============================================

add_action('wp_ajax_act_get_pending_orders', 'act_ajax_get_pending_orders');
function act_ajax_get_pending_orders() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $affiliate_id = intval($_POST['affiliate_id']);
    global $wpdb;
    $referrals_table = $wpdb->prefix . 'act_referrals';
    
    $pending_referrals = $wpdb->get_results($wpdb->prepare("
        SELECT r.*
        FROM $referrals_table r
        WHERE r.affiliate_id = %d AND r.status = 'pending'
    ", $affiliate_id));
    
    $eligible_total = 0;
    $eligible_count = 0;
    foreach ($pending_referrals as $referral) {
        $order = wc_get_order($referral->order_id);
        if ($order) {
            $order_status = $order->get_status();
            if ($order_status === 'completed' || $order_status === 'processing') {
                $eligible_count++;
                $eligible_total += floatval($referral->commission_amount);
            }
        }
    }
    
    wp_send_json_success(array(
        'count' => $eligible_count,
        'total' => $eligible_total,
        'total_raw_pending' => count($pending_referrals)
    ));
}

return;