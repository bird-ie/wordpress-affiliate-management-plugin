<?php
/**
 * RetaEU - Affiliate Login Landing Page
 * Uses existing login form shortcode.
 *
 * Shortcode: [retaeu_affiliate_login_page]
 */

add_shortcode('retaeu_affiliate_login_page', 'retaeu_affiliate_login_page_shortcode');

function retaeu_affiliate_login_page_shortcode() {
    ob_start();
    ?>

    <div class="retaeu-affiliate-login-page">
        <section class="retaeu-affiliate-login-hero">
            <div class="retaeu-affiliate-login-inner">
                <span class="retaeu-affiliate-login-badge">Affiliate Area</span>
                <h1>Login to Your Partner Dashboard</h1>
                <p>Access your affiliate code, referral orders, commission, and account details.</p>
            </div>
        </section>

        <section class="retaeu-affiliate-login-content">
            <?php echo do_shortcode('[affiliate_login_form]'); ?>
        </section>
    </div>

    <style>
        .retaeu-affiliate-login-hero {
            background: linear-gradient(135deg, #e8f4fd 0%, #f8fbff 100%) !important;
            padding: 80px 24px !important;
            text-align: center !important;
            border-radius: 0 0 34px 34px !important;
            border-bottom: 1px solid #d9edf9 !important;
        }

        .retaeu-affiliate-login-inner {
            max-width: 780px !important;
            margin: 0 auto !important;
        }

        .retaeu-affiliate-login-badge {
            display: inline-block !important;
            background: #0073aa !important;
            color: #fff !important;
            padding: 8px 16px !important;
            border-radius: 999px !important;
            font-size: 13px !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            letter-spacing: .05em !important;
            margin-bottom: 18px !important;
        }

        .retaeu-affiliate-login-hero h1 {
            margin: 0 0 16px !important;
            color: #0b4f6c !important;
            font-size: clamp(34px, 6vw, 62px) !important;
            line-height: 1.05 !important;
            font-weight: 900 !important;
        }

        .retaeu-affiliate-login-hero p {
            margin: 0 auto !important;
            max-width: 620px !important;
            color: #475569 !important;
            font-size: 18px !important;
            line-height: 1.7 !important;
        }

        .retaeu-affiliate-login-content {
            max-width: 760px !important;
            margin: 50px auto !important;
            padding: 0 20px !important;
        }
    </style>

    <?php
    return ob_get_clean();
}