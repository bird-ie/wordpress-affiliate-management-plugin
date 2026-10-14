<?php
/**
 * Snippet 8: Reassign Affiliate Order Tool
 * Adds an admin box on WooCommerce order edit page to move an order to another affiliate.
 *
 * v4 — EU7 override (flat 15%) + bundle cap (10%) for manual assignments.
 *      Same-affiliate reselection now recalculates the commission (fixes rate changes).
 *      Diagnostic logging to wp-content/retaeu-rate.log.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==============================================
// DIAGNOSTIC LOGGER — writes to wp-content/retaeu-rate.log
// ==============================================
if (!function_exists('retaeu_rate_log')) {
    function retaeu_rate_log($msg) {
        @file_put_contents(WP_CONTENT_DIR . '/retaeu-rate.log', '[' . gmdate('c') . '] ' . $msg . "\n", FILE_APPEND);
    }
}

// ==============================================
// RATE RESOLVER (EU7 override + bundle cap)
// ==============================================
if (!function_exists('retaeu_resolve_manual_rate')) {
    function retaeu_resolve_manual_rate($order, $affiliate_rate) {

        $rate = floatval($affiliate_rate);

        if (!$order instanceof WC_Order) {
            return $rate;
        }

        global $wpdb;
        $order_id = $order->get_id();

        // --- Step 1: EU7 override (flat 15%) ---
        $coupon_code = $wpdb->get_var($wpdb->prepare(
            "SELECT order_item_name
             FROM {$wpdb->prefix}woocommerce_order_items
             WHERE order_id = %d
               AND order_item_type = 'coupon'
             LIMIT 1",
            $order_id
        ));

        if ($coupon_code && strtolower(trim($coupon_code)) === 'eu7') {
            $rate = 15;
        }

        // --- Step 2: Bundle cap (always wins) ---
        if (strtolower(trim((string) $order->get_meta('_retaeu_is_bundle_order'))) === 'yes') {
            if ($rate > 10) {
                $rate = 10;
            }
        }

        return $rate;
    }
}

/**
 * Add affiliate reassignment box on order edit page.
 */
add_action('add_meta_boxes', 'retaeu_add_affiliate_reassign_box');
function retaeu_add_affiliate_reassign_box() {
    add_meta_box(
        'retaeu_affiliate_reassign_box',
        'Affiliate Reassignment',
        'retaeu_affiliate_reassign_box_html',
        'shop_order',
        'side',
        'high'
    );

    // HPOS support.
    if (function_exists('wc_get_page_screen_id')) {
        add_meta_box(
            'retaeu_affiliate_reassign_box',
            'Affiliate Reassignment',
            'retaeu_affiliate_reassign_box_html',
            wc_get_page_screen_id('shop-order'),
            'side',
            'high'
        );
    }
}

function retaeu_affiliate_reassign_box_html($post_or_order) {
    global $wpdb;

    $order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID);

    if (!$order) {
        echo '<p>Order not found.</p>';
        return;
    }

    $order_id         = $order->get_id();
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $referrals_table  = $wpdb->prefix . 'act_referrals';

    $current_referral = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$referrals_table} WHERE order_id = %d",
        $order_id
    ));

    $current_affiliate_text = 'Not assigned';

    if ($current_referral) {
        $current_affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, u.display_name 
             FROM {$affiliates_table} a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.id = %d",
            $current_referral->affiliate_id
        ));

        if ($current_affiliate) {
            $current_affiliate_text = $current_affiliate->display_name . ' (' . $current_affiliate->affiliate_code . ')';
        }
    }

    $affiliates = $wpdb->get_results(
        "SELECT a.*, u.display_name, u.user_email
         FROM {$affiliates_table} a
         LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
         WHERE a.affiliate_status = 'approved'
         ORDER BY u.display_name ASC"
    );

    wp_nonce_field('retaeu_affiliate_reassign_action', 'retaeu_affiliate_reassign_nonce');
    ?>

    <p><strong>Current Affiliate:</strong><br>
        <?php echo esc_html($current_affiliate_text); ?>
    </p>

    <?php if ($current_referral): ?>
        <p>
            <strong>Current Commission:</strong><br>
            €<?php echo esc_html(number_format((float) $current_referral->commission_amount, 2)); ?>
            <br>
            <strong>Status:</strong>
            <?php echo esc_html(ucfirst($current_referral->status)); ?>
        </p>
    <?php endif; ?>

    <p>
        <label for="retaeu_new_affiliate_id"><strong>Move order to:</strong></label>
        <select name="retaeu_new_affiliate_id" id="retaeu_new_affiliate_id" style="width:100%;margin-top:6px;">
            <option value="">— Select Affiliate —</option>
            <?php foreach ($affiliates as $affiliate): ?>
                <option value="<?php echo esc_attr($affiliate->id); ?>" <?php selected($current_referral && (int) $current_referral->affiliate_id === (int) $affiliate->id); ?>>
                    <?php
                    echo esc_html(
                        ($affiliate->display_name ?: $affiliate->user_email) .
                        ' (' . $affiliate->affiliate_code . ') - ' .
                        $affiliate->commission_rate . '%'
                    );
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p style="font-size:12px;color:#666;">
        This changes affiliate tracking and commission owner only. It does not change the customer’s coupon/order total.
    </p>

    <button type="submit" name="retaeu_reassign_affiliate_order" class="button button-primary" style="width:100%;">
        Move / Update Affiliate
    </button>

    <?php if ($current_referral): ?>
        <button type="submit" name="retaeu_remove_affiliate_order" class="button" style="width:100%;margin-top:6px;" onclick="return confirm('Remove affiliate assignment from this order?');">
            Remove Affiliate
        </button>
    <?php endif; ?>

    <?php
}

/**
 * Save reassignment.
 */
add_action('save_post_shop_order', 'retaeu_save_affiliate_reassignment', 20, 3);
function retaeu_save_affiliate_reassignment($post_id, $post, $update) {
    retaeu_process_affiliate_reassignment($post_id);
}

// HPOS support.
add_action('woocommerce_process_shop_order_meta', 'retaeu_process_affiliate_reassignment', 20);

function retaeu_process_affiliate_reassignment($order_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_shop_order', $order_id)) {
        return;
    }

    // Read from $_REQUEST first (HPOS + security plugins can strip $_POST).
    $nonce = $_REQUEST['retaeu_affiliate_reassign_nonce'] ?? ($_POST['retaeu_affiliate_reassign_nonce'] ?? '');

    if (empty($nonce)) {
        return;
    }

    if (!wp_verify_nonce($nonce, 'retaeu_affiliate_reassign_action')) {
        return;
    }

    $reassign_flag  = isset($_REQUEST['retaeu_reassign_affiliate_order']) || isset($_POST['retaeu_reassign_affiliate_order']);
    $remove_flag    = isset($_REQUEST['retaeu_remove_affiliate_order'])   || isset($_POST['retaeu_remove_affiliate_order']);

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    global $wpdb;

    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    $referrals_table  = $wpdb->prefix . 'act_referrals';

    $old_referral = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$referrals_table} WHERE order_id = %d",
        $order_id
    ));

    // ---------- Remove assignment ----------
    if ($remove_flag) {
        if ($old_referral) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$affiliates_table} SET total_earned = GREATEST(total_earned - %f, 0) WHERE id = %d",
                (float) $old_referral->commission_amount,
                (int) $old_referral->affiliate_id
            ));

            if ($old_referral->status === 'paid') {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$affiliates_table} SET total_paid = GREATEST(total_paid - %f, 0) WHERE id = %d",
                    (float) $old_referral->commission_amount,
                    (int) $old_referral->affiliate_id
                ));
            }

            $wpdb->delete($referrals_table, array('order_id' => $order_id), array('%d'));
            $order->delete_meta_data('_act_affiliate_code');
            $order->save();

            $order->add_order_note('Affiliate assignment removed manually by admin.');
        }

        return;
    }

    if (!$reassign_flag) {
        return;
    }

    // ---------- Assign / reassign ----------
    $new_affiliate_id = isset($_REQUEST['retaeu_new_affiliate_id']) ? absint($_REQUEST['retaeu_new_affiliate_id']) : 0;
    if (!$new_affiliate_id) {
        $new_affiliate_id = isset($_POST['retaeu_new_affiliate_id']) ? absint($_POST['retaeu_new_affiliate_id']) : 0;
    }

    if (!$new_affiliate_id) {
        return;
    }

    $new_affiliate = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$affiliates_table} WHERE id = %d AND affiliate_status = 'approved'",
        $new_affiliate_id
    ));

    if (!$new_affiliate) {
        $order->add_order_note('Affiliate reassignment failed: selected affiliate not found or not approved.');
        return;
    }

    $order_total = (float) $order->get_total();

    // Rate resolution — EU7 flat 15%, bundle cap 10%, else affiliate rate.
    $effective_rate = retaeu_resolve_manual_rate($order, $new_affiliate->commission_rate);
    $new_commission = $order_total * ($effective_rate / 100);
    $new_status     = $old_referral ? $old_referral->status : 'pending';

    // =================================================================
    // SAME AFFILIATE — recalculate commission (but don't double-credit)
    // =================================================================
    if ($old_referral && (int) $old_referral->affiliate_id === (int) $new_affiliate_id) {

        $old_amount = (float) $old_referral->commission_amount;
        $diff       = $new_commission - $old_amount;

        // If nothing changed, bail out.
        if (abs($diff) < 0.01) {
            return;
        }

        // Adjust total_earned by the delta only.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$affiliates_table} SET total_earned = total_earned + %f WHERE id = %d",
            $diff,
            $new_affiliate_id
        ));

        // If commission was already paid, adjust total_paid by the delta.
        if ($old_referral->status === 'paid') {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$affiliates_table} SET total_paid = total_paid + %f WHERE id = %d",
                $diff,
                $new_affiliate_id
            ));
        }

        $wpdb->update(
            $referrals_table,
            array(
                'order_number'       => $order->get_order_number(),
                'customer_first_name'=> $order->get_billing_first_name(),
                'customer_last_name' => $order->get_billing_last_name(),
                'customer_email'     => $order->get_billing_email(),
                'order_amount'       => $order_total,
                'commission_amount'  => $new_commission,
            ),
            array('order_id' => $order_id),
            array('%s', '%s', '%s', '%s', '%f', '%f'),
            array('%d')
        );

        $order->add_order_note(sprintf(
            'Affiliate commission recalculated on order %s. New commission: €%s (was €%s).',
            $order_id,
            number_format($new_commission, 2),
            number_format($old_amount, 2)
        ));

        return;
    }

    // =================================================================
    // DIFFERENT AFFILIATE — full swap
    // =================================================================
    if ($old_referral) {

        $old_affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$affiliates_table} WHERE id = %d",
            (int) $old_referral->affiliate_id
        ));

        $old_name = $old_affiliate ? $old_affiliate->affiliate_code : 'Unknown';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$affiliates_table} SET total_earned = GREATEST(total_earned - %f, 0) WHERE id = %d",
            (float) $old_referral->commission_amount,
            (int) $old_referral->affiliate_id
        ));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$affiliates_table} SET total_earned = total_earned + %f WHERE id = %d",
            $new_commission,
            $new_affiliate_id
        ));

        if ($old_referral->status === 'paid') {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$affiliates_table} SET total_paid = GREATEST(total_paid - %f, 0) WHERE id = %d",
                (float) $old_referral->commission_amount,
                (int) $old_referral->affiliate_id
            ));

            $wpdb->query($wpdb->prepare(
                "UPDATE {$affiliates_table} SET total_paid = total_paid + %f WHERE id = %d",
                $new_commission,
                $new_affiliate_id
            ));
        }

        $wpdb->update(
            $referrals_table,
            array(
                'affiliate_id'       => $new_affiliate_id,
                'order_number'       => $order->get_order_number(),
                'customer_first_name'=> $order->get_billing_first_name(),
                'customer_last_name' => $order->get_billing_last_name(),
                'customer_email'     => $order->get_billing_email(),
                'order_amount'       => $order_total,
                'commission_amount'  => $new_commission,
                'status'             => $new_status,
            ),
            array('order_id' => $order_id),
            array('%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s'),
            array('%d')
        );

        $order->add_order_note(sprintf(
            'Affiliate reassigned manually from %s to %s. New commission: €%s.',
            $old_name,
            $new_affiliate->affiliate_code,
            number_format($new_commission, 2)
        ));
    } else {
        // Brand new assignment.
        $wpdb->insert(
            $referrals_table,
            array(
                'affiliate_id'        => $new_affiliate_id,
                'order_id'            => $order_id,
                'order_number'        => $order->get_order_number(),
                'customer_first_name' => $order->get_billing_first_name(),
                'customer_last_name'  => $order->get_billing_last_name(),
                'customer_email'      => $order->get_billing_email(),
                'order_amount'        => $order_total,
                'commission_amount'   => $new_commission,
                'status'              => 'pending',
                'created_at'          => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s')
        );

        $wpdb->query($wpdb->prepare(
            "UPDATE {$affiliates_table} SET total_earned = total_earned + %f WHERE id = %d",
            $new_commission,
            $new_affiliate_id
        ));

        $order->add_order_note(sprintf(
            'Affiliate assigned manually to %s. Commission: €%s.',
            $new_affiliate->affiliate_code,
            number_format($new_commission, 2)
        ));
    }

    $order->update_meta_data('_act_affiliate_code', $new_affiliate->affiliate_code);
    $order->update_meta_data('_act_referral_processed', true);
    $order->save();
}