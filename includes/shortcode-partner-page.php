<?php
/**
 * RetaEU - Affiliate Partner Landing Page with AJAX
 * Shortcode: [retaeu_affiliate_partner_page]
 */

add_shortcode('retaeu_affiliate_partner_page', 'retaeu_affiliate_partner_page_shortcode');

function retaeu_affiliate_partner_page_shortcode() {
    ob_start();
    ?>
    
    <div class="retaeu-affiliate-partner-page">
        <section class="retaeu-affiliate-hero">
            <div class="retaeu-affiliate-hero-inner">
                <span class="retaeu-affiliate-badge">Partner Program</span>
                <h1>Become a RetaEU Affiliate Partner</h1>
                <p>Join our affiliate program and earn commission by sharing your personal affiliate code with your audience, customers, or community.</p>
                <a href="#affiliate-apply-form" class="retaeu-affiliate-cta">Apply Now</a>
            </div>
        </section>

        <section class="retaeu-affiliate-content">
            <div class="retaeu-affiliate-info">
                <h2>Why become a partner?</h2>
                <div class="retaeu-affiliate-card">
                    <h3>Earn commission</h3>
                    <p>Receive commission when customers order using your affiliate code.</p>
                </div>
                <div class="retaeu-affiliate-card">
                    <h3>Easy to share</h3>
                    <p>You get your own affiliate code and referral link after approval.</p>
                </div>
                <div class="retaeu-affiliate-card">
                    <h3>Simple dashboard</h3>
                    <p>Track your orders, commission, and affiliate performance from your dashboard.</p>
                </div>
            </div>

            <div class="retaeu-affiliate-form-box" id="affiliate-apply-form">
                <h2>Apply to become a partner</h2>
                <p class="retaeu-affiliate-form-subtitle">Fill in the form below. Your application will be reviewed by our team.</p>
                
                <?php echo do_shortcode('[affiliate_registration_form]'); ?>
                
                <div id="retaeu-affiliate-response" style="margin-top:20px;"></div>
            </div>
        </section>
    </div>

    <style>
        .retaeu-affiliate-partner-page{font-family:inherit;color:#111827}
        .retaeu-affiliate-hero{background:linear-gradient(135deg,#e8f4fd,#f8fbff);padding:90px 24px;border-radius:0 0 34px 34px;text-align:center;border-bottom:1px solid #d9edf9}
        .retaeu-affiliate-hero-inner{max-width:850px;margin:0 auto}
        .retaeu-affiliate-badge{display:inline-block;background:#0073aa;color:#fff;padding:8px 16px;border-radius:999px;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;margin-bottom:18px}
        .retaeu-affiliate-hero h1{margin:0 0 18px;font-size:clamp(36px,6vw,68px);line-height:1.05;font-weight:900;color:#0b4f6c}
        .retaeu-affiliate-hero p{max-width:680px;margin:0 auto 28px;font-size:18px;line-height:1.7;color:#475569}
        .retaeu-affiliate-cta{display:inline-block;background:#0073aa;color:#fff;padding:14px 30px;border-radius:999px;text-decoration:none;font-weight:800;transition:.2s ease}
        .retaeu-affiliate-cta:hover{background:#005a87;color: white;transform:translateY(-1px)}
        .retaeu-affiliate-content{max-width:1180px;margin:60px auto;padding:0 24px;display:grid;grid-template-columns:.9fr 1.1fr;gap:34px;align-items:start}
        .retaeu-affiliate-info,.retaeu-affiliate-form-box{background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 18px 50px rgba(0,0,0,.06)}
        .retaeu-affiliate-info{padding:34px;position:sticky;top:30px}
        .retaeu-affiliate-info h2,.retaeu-affiliate-form-box>h2{margin:0 0 22px;font-size:30px;line-height:1.2;color:#0b4f6c}
        .retaeu-affiliate-card{padding:20px 0;border-bottom:1px solid #e2e8f0}
        .retaeu-affiliate-card:last-child{border-bottom:none}
        .retaeu-affiliate-card h3{margin:0 0 8px;color:#0073aa;font-size:18px}
        .retaeu-affiliate-card p{margin:0;color:#64748b;line-height:1.6}
        .retaeu-affiliate-form-box{padding:34px}
        .retaeu-affiliate-form-subtitle{margin:-10px 0 24px;color:#64748b;line-height:1.6}
        .retaeu-affiliate-success{background:#d1fae5;color:#065f46;padding:16px 20px;border-radius:12px;border-left:4px solid #059669;font-weight:500}
        .retaeu-affiliate-error{background:#fee2e2;color:#991b1b;padding:16px 20px;border-radius:12px;border-left:4px solid #dc2626;font-weight:500}
        .retaeu-affiliate-loading{background:#f3f4f6;color:#4b5563;padding:16px 20px;border-radius:12px;font-weight:500;display:flex;align-items:center;gap:12px}
        .retaeu-affiliate-loading .spinner{display:inline-block;width:20px;height:20px;border:3px solid #e5e7eb;border-top:3px solid #0073aa;border-radius:50%;animation:spin .8s linear infinite}
        @keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
        @media(max-width:900px){.retaeu-affiliate-content{grid-template-columns:1fr;margin:40px auto}.retaeu-affiliate-info{position:static}}
        @media(max-width:600px){.retaeu-affiliate-hero{padding:58px 18px}.retaeu-affiliate-content{padding:0 16px}.retaeu-affiliate-info,.retaeu-affiliate-form-box{padding:24px;border-radius:18px}}
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Find the registration form - it uses act_reg_submit as submit button name
        var form = document.querySelector('#affiliate-apply-form form');
        if (!form) {
            var container = document.getElementById('affiliate-apply-form');
            if (container) {
                form = container.querySelector('form');
            }
        }
        if (!form) {
            console.log('Form not found');
            return;
        }

        var responseDiv = document.getElementById('retaeu-affiliate-response');
        var submitBtn = form.querySelector('input[type="submit"], button[type="submit"]');
        var btnText = 'Register as Affiliate';
        if (submitBtn) {
            btnText = submitBtn.value || submitBtn.textContent || 'Register as Affiliate';
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Disable submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                if (submitBtn.tagName === 'INPUT') {
                    submitBtn.value = 'Submitting...';
                } else {
                    submitBtn.textContent = 'Submitting...';
                }
            }

            // Show loading
            responseDiv.innerHTML = '<div class="retaeu-affiliate-loading"><span class="spinner"></span> Submitting your application...</div>';
            responseDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

            var formData = new FormData(form);
            formData.append('action', 'retaeu_affiliate_registration');

            // Get the AJAX URL
            var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                // Re-enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (submitBtn.tagName === 'INPUT') {
                        submitBtn.value = btnText;
                    } else {
                        submitBtn.textContent = btnText;
                    }
                }

                if (data.success) {
                    responseDiv.innerHTML = '<div class="retaeu-affiliate-success">✅ ' + data.data.message + '</div>';
                    form.reset();
                    responseDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    var errorMsg = data.data.message || 'Something went wrong. Please try again.';
                    responseDiv.innerHTML = '<div class="retaeu-affiliate-error">❌ ' + errorMsg + '</div>';
                }
            })
            .catch(function(error) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    if (submitBtn.tagName === 'INPUT') {
                        submitBtn.value = btnText;
                    } else {
                        submitBtn.textContent = btnText;
                    }
                }
                responseDiv.innerHTML = '<div class="retaeu-affiliate-error">❌ An error occurred. Please try again.</div>';
                console.error('AJAX Error:', error);
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}

// AJAX Handler for registration
add_action('wp_ajax_retaeu_affiliate_registration', 'retaeu_affiliate_registration_ajax_handler');
add_action('wp_ajax_nopriv_retaeu_affiliate_registration', 'retaeu_affiliate_registration_ajax_handler');

function retaeu_affiliate_registration_ajax_handler() {
    // Check nonce from the form
    if (!isset($_POST['act_reg_nonce_field']) || !wp_verify_nonce($_POST['act_reg_nonce_field'], 'act_reg_nonce')) {
        wp_send_json_error(array('message' => 'Security verification failed. Please refresh and try again.'));
        return;
    }
    
    // Get form fields (matches your registration form)
    $username = isset($_POST['act_username']) ? sanitize_user($_POST['act_username']) : '';
    $email = isset($_POST['act_email']) ? sanitize_email($_POST['act_email']) : '';
    $password = isset($_POST['act_password']) ? $_POST['act_password'] : '';
    $confirm = isset($_POST['act_confirm_password']) ? $_POST['act_confirm_password'] : '';
    $custom_code = isset($_POST['act_custom_code']) ? strtoupper(sanitize_text_field($_POST['act_custom_code'])) : '';
    $terms = isset($_POST['act_terms']) ? true : false;
    
    // Validate
    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        wp_send_json_error(array('message' => 'Please fill in all required fields.'));
        return;
    }
    
    if ($password !== $confirm) {
        wp_send_json_error(array('message' => 'Passwords do not match.'));
        return;
    }
    
    if (strlen($password) < 6) {
        wp_send_json_error(array('message' => 'Password must be at least 6 characters.'));
        return;
    }
    
    if (!$terms) {
        wp_send_json_error(array('message' => 'You must agree to the Terms & Conditions.'));
        return;
    }
    
    if (username_exists($username)) {
        wp_send_json_error(array('message' => 'Username already exists. Please choose another.'));
        return;
    }
    
    if (email_exists($email)) {
        wp_send_json_error(array('message' => 'Email already registered. Please use another email.'));
        return;
    }
    
    global $wpdb;
    $affiliates_table = $wpdb->prefix . 'act_affiliates';
    
    // Check custom code
    if (!empty($custom_code)) {
        $code_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $affiliates_table WHERE affiliate_code = %s", $custom_code));
        if ($code_exists) {
            wp_send_json_error(array('message' => 'Affiliate code already taken. Please choose another or leave blank for auto-generation.'));
            return;
        }
        $affiliate_code = $custom_code;
    } else {
        $affiliate_code = strtoupper(substr(md5($username . time()), 0, 8));
    }
    
    // Get social fields
    $social_fields = array('facebook', 'instagram', 'twitter', 'tiktok', 'youtube', 'linkedin', 'website', 'pinterest');
    $social_data = array();
    foreach ($social_fields as $field) {
        $key = "social_$field";
        $social_data[$key] = isset($_POST[$key]) && !empty($_POST[$key]) ? esc_url_raw($_POST[$key]) : null;
    }
    
    // Create user
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => $user_id->get_error_message()));
        return;
    }
    
    // Set user role
    $user = new WP_User($user_id);
    $user->set_role('customer');
    $user->add_role('affiliate');
    
    // Insert affiliate
    $insert_data = array_merge(array(
        'user_id' => $user_id,
        'affiliate_code' => $affiliate_code,
        'commission_rate' => 15,
        'customer_discount_rate' => 5,
        'affiliate_status' => 'pending',
        'created_at' => current_time('mysql')
    ), $social_data);
    
    $result = $wpdb->insert($affiliates_table, $insert_data);
    
    if (!$result) {
        wp_send_json_error(array('message' => 'Failed to create affiliate account. Please try again.'));
        return;
    }
    
    // Send admin notification
    $admin_email = get_option('admin_email');
    $subject = 'New Affiliate Registration - Pending Approval';
    $message = "A new affiliate has registered and is pending approval.\n\n";
    $message .= "Username: $username\n";
    $message .= "Email: $email\n";
    $message .= "Affiliate Code: $affiliate_code\n\n";
    $message .= "Approve here: " . admin_url('admin.php?page=act-affiliates');
    wp_mail($admin_email, $subject, $message);
    
    // Log user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    
    // Return success
    wp_send_json_success(array(
        'message' => '🎉 Registration successful! Your affiliate code is <strong>' . $affiliate_code . '</strong>. Your account is pending approval. You will be redirected to your dashboard.'
    ));
}