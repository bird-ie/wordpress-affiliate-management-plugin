<?php
/**
 * Snippet 5: Additional Affiliate Shortcode - Share Code & Social Links
 */

add_shortcode('affiliate_share_code', 'act_share_code');
function act_share_code() {
    if (!is_user_logged_in()) return '';
    
    global $wpdb;
    $affiliate = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}act_affiliates WHERE user_id = %d",
        get_current_user_id()
    ));
    
    if (!$affiliate) return '<p>You are not an affiliate. <a href="/affiliate-register">Register here</a></p>';
    
    if ($affiliate->affiliate_status !== 'approved') {
        return '<div style="background:#fff3cd;padding:20px;border-radius:8px;text-align:center"><p>Your account is pending approval. Once approved, you can share your code.</p></div>';
    }
    
    $socials = array(
        'facebook' => $affiliate->social_facebook,
        'instagram' => $affiliate->social_instagram,
        'twitter' => $affiliate->social_twitter,
        'tiktok' => $affiliate->social_tiktok,
        'youtube' => $affiliate->social_youtube,
        'linkedin' => $affiliate->social_linkedin,
        'website' => $affiliate->social_website,
        'pinterest' => $affiliate->social_pinterest
    );
    
    ob_start();
    ?>
    <style>
        .act-share-container{background:#f0f7ff;padding:25px;border-radius:12px;margin:20px 0;text-align:center}
        .act-share-code{font-size:36px;font-weight:bold;font-family:monospace;background:#fff;padding:15px 25px;border-radius:10px;display:inline-block;margin:15px 0;letter-spacing:2px}
        .act-share-link{background:#fff;padding:12px;border-radius:8px;margin:15px 0;word-break:break-all}
        .act-copy-btn{background:#0073aa;color:#fff;border:none;padding:8px 20px;border-radius:5px;cursor:pointer;margin-left:10px}
        .act-social-share{display:flex;justify-content:center;gap:15px;margin:20px 0;flex-wrap:wrap}
        .act-social-share a{display:inline-block;padding:10px 20px;border-radius:6px;text-decoration:none;color:#fff}
    </style>
    <div class="act-share-container">
        <h3>Share Your Affiliate Code</h3>
        <div class="act-share-code"><?php echo esc_html($affiliate->affiliate_code); ?></div>
        <button class="act-copy-btn" onclick="copyCode()">Copy Code</button>
        
        <h3>Share Your Referral Link</h3>
        <div class="act-share-link">
            <code id="share-link" style="font-size:14px"><?php echo esc_url(add_query_arg('ref', $affiliate->affiliate_code, home_url())); ?></code>
            <button class="act-copy-btn" onclick="copyLink()">Copy Link</button>
        </div>
        
        <h3>Share on Social Media</h3>
        <div class="act-social-share">
            <?php if($socials['facebook']): ?><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(add_query_arg('ref', $affiliate->affiliate_code, home_url())); ?>" target="_blank" style="background:#1877f2">📘 Share</a><?php endif; ?>
            <?php if($socials['twitter']): ?><a href="https://twitter.com/intent/tweet?text=Get%20<?php echo $affiliate->customer_discount_rate ?: 5; ?>%25%20off%20using%20my%20code%20<?php echo $affiliate->affiliate_code; ?>%20at%20<?php echo urlencode(home_url()); ?>" target="_blank" style="background:#1da1f2">🐦 Tweet</a><?php endif; ?>
            <?php if($socials['instagram'] && $socials['instagram']): ?><a href="<?php echo esc_url($socials['instagram']); ?>" target="_blank" style="background:#e4405f">📷 Instagram</a><?php endif; ?>
        </div>
        
        <p><strong><?php echo $affiliate->customer_discount_rate ?: 5; ?>% OFF</strong> for customers using your code!</p>
    </div>
    
    <script>
        function copyCode(){var code='<?php echo esc_js($affiliate->affiliate_code); ?>';navigator.clipboard.writeText(code).then(function(){alert('Code copied: '+code);});}
        function copyLink(){var link=document.getElementById('share-link').innerText;navigator.clipboard.writeText(link).then(function(){alert('Link copied!');});}
    </script>
    <?php
    return ob_get_clean();
}