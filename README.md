
Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate
$ pwd
/f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate
$ ls -la
total 12
drwxr-xr-x 1 Win 10 197608    0 Oct 12 17:46 ./
drwxr-xr-x 1 Win 10 197608    0 Oct 12 18:00 ../
drwxr-xr-x 1 Win 10 197608    0 Oct 12 17:47 includes/
-rw-r--r-- 1 Win 10 197608 2344 Oct 12 17:48 retaeu-affiliate.php

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate
$ cat > .gitignore <<'EOF'
.DS_Store
Thumbs.db
*.log
*.sql
*.zip
.env
.env.*
!.env.example
vendor/
node_modules/
EOF

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate
$ cat .gitignore
.DS_Store
Thumbs.db
*.log
*.sql
*.zip
.env
.env.*
!.env.example
vendor/
node_modules/

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate
$ git --version
git version 2.55.0.windows.2

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate
$ git init -b main
Initialized empty Git repository in F:/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate/.git/

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ git status --short
?? .gitignore
?? includes/
?? retaeu-affiliate.php

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ ls -la
git --version
git status --short
total 17
drwxr-xr-x 1 Win 10 197608    0 Oct 15 01:36 ./
drwxr-xr-x 1 Win 10 197608    0 Oct 12 18:00 ../
drwxr-xr-x 1 Win 10 197608    0 Oct 15 01:36 .git/
-rw-r--r-- 1 Win 10 197608   86 Oct 15 01:36 .gitignore
drwxr-xr-x 1 Win 10 197608    0 Oct 12 17:47 includes/
-rw-r--r-- 1 Win 10 197608 2344 Oct 12 17:48 retaeu-affiliate.php
git version 2.55.0.windows.2
?? .gitignore
?? includes/
?? retaeu-affiliate.php

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ grep -RniE "password|api[_-]?key|secret|private[_-]?key|BEGIN .*PRIVATE KEY|sk_live_|pk_live_" --include="*.php" .
./includes/frontend-shortcodes.php:859:                <label>Password *</label>
./includes/frontend-shortcodes.php:860:                <input type="password" name="act_password" required>
./includes/frontend-shortcodes.php:864:                <label>Confirm Password *</label>
./includes/frontend-shortcodes.php:865:                <input type="password" name="act_confirm_password" required>
./includes/frontend-shortcodes.php:1009:    $password = $_POST['act_password'];
./includes/frontend-shortcodes.php:1010:    $confirm = $_POST['act_confirm_password'];
./includes/frontend-shortcodes.php:1013:    if ($password !== $confirm) return '<div class="act-error">Passwords do not match.</div>' . act_reg_form();
./includes/frontend-shortcodes.php:1014:    if (strlen($password) < 6) return '<div class="act-error">Password must be at least 6 characters.</div>' . act_reg_form();
./includes/frontend-shortcodes.php:1037:    $user_id = wp_create_user($username, $password, $email);
./includes/frontend-shortcodes.php:1082:        $password = $_POST['act_password'];
./includes/frontend-shortcodes.php:1085:        $creds = array('user_login' => $username, 'user_password' => $password, 'remember' => $remember);
./includes/frontend-shortcodes.php:1141:        .act-form-group input[type="password"] {
./includes/frontend-shortcodes.php:1221:                <label>Password</label>
./includes/frontend-shortcodes.php:1222:                <input type="password" name="act_password" required>
./includes/frontend-shortcodes.php:1237:            <p><a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Forgot password?</a></p>
./includes/shortcode-partner-page.php:186:    $password = isset($_POST['act_password']) ? $_POST['act_password'] : '';
./includes/shortcode-partner-page.php:187:    $confirm = isset($_POST['act_confirm_password']) ? $_POST['act_confirm_password'] : '';
./includes/shortcode-partner-page.php:192:    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
./includes/shortcode-partner-page.php:197:    if ($password !== $confirm) {
./includes/shortcode-partner-page.php:198:        wp_send_json_error(array('message' => 'Passwords do not match.'));
./includes/shortcode-partner-page.php:202:    if (strlen($password) < 6) {
./includes/shortcode-partner-page.php:203:        wp_send_json_error(array('message' => 'Password must be at least 6 characters.'));
./includes/shortcode-partner-page.php:246:    $user_id = wp_create_user($username, $password, $email);

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ find includes -maxdepth 3 -type f
includes/admin-affiliates.php
includes/admin-manage-users.php
includes/admin-orders.php
includes/admin-reassign-box.php
includes/admin-selective-payout.php
includes/class-db.php
includes/frontend-shortcodes.php
includes/my-account-banner.php
includes/shortcode-login-page.php
includes/shortcode-partner-page.php
includes/shortcode-share.php

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ git add .gitignore retaeu-affiliate.php includes/
warning: in the working copy of '.gitignore', LF will be replaced by CRLF the next time Git touches
it

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ git status
On branch main

No commits yet

Changes to be committed:
  (use "git rm --cached <file>..." to unstage)
        new file:   .gitignore
        new file:   includes/admin-affiliates.php
        new file:   includes/admin-manage-users.php
        new file:   includes/admin-orders.php
        new file:   includes/admin-reassign-box.php
        new file:   includes/admin-selective-payout.php
        new file:   includes/class-db.php
        new file:   includes/frontend-shortcodes.php
        new file:   includes/my-account-banner.php
        new file:   includes/shortcode-login-page.php
        new file:   includes/shortcode-partner-page.php
        new file:   includes/shortcode-share.php
        new file:   retaeu-affiliate.php


Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ git commit -m "Initial release of affiliate management plugin"
[main (root-commit) 039dff9] Initial release of affiliate management plugin
 13 files changed, 5582 insertions(+)
 create mode 100644 .gitignore
 create mode 100644 includes/admin-affiliates.php
 create mode 100644 includes/admin-manage-users.php
 create mode 100644 includes/admin-orders.php
 create mode 100644 includes/admin-reassign-box.php
 create mode 100644 includes/admin-selective-payout.php
 create mode 100644 includes/class-db.php
 create mode 100644 includes/frontend-shortcodes.php
 create mode 100644 includes/my-account-banner.php
 create mode 100644 includes/shortcode-login-page.php
 create mode 100644 includes/shortcode-partner-page.php
 create mode 100644 includes/shortcode-share.php
 create mode 100644 retaeu-affiliate.php

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ sed -n '1,180p' retaeu-affiliate.php
<?php
/**
 * Plugin Name:       RetaEU Affiliate System
 * Description:       Custom affiliate program with WooCommerce coupon integration, dashboards, admin management, payouts, and reporting.
 * Version:           1.0.0
 * Author:            RetaEU
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       retaeu-affiliate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ------------------------------------------------------------------
// WooCommerce dependency check + loader
// ------------------------------------------------------------------
add_action( 'plugins_loaded', 'retaeu_affiliate_init', 5 );

function retaeu_affiliate_init() {

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>RetaEU Affiliate System</strong> requires WooCommerce to be installed and active.</p></div>';
        } );
        return;
    }

    $dir = plugin_dir_path( __FILE__ ) . 'includes/';

    // ------------------------------------------------------------------
    // Load order matters:
    //   1. Core DB + coupon functions (class-db.php) must exist first.
    //   2. admin-affiliates.php defines act_core_affiliates_admin_page(),
    //      which class-db.php checks via function_exists() before
    //      registering the top-level admin menu.
    //   3. Everything else loads after that.
    // ------------------------------------------------------------------
    require_once $dir . 'class-db.php';                 // Snippet 1
    require_once $dir . 'admin-affiliates.php';         // Snippet 2
    require_once $dir . 'frontend-shortcodes.php';      // Snippet 3
    require_once $dir . 'admin-orders.php';             // Snippet 4
    require_once $dir . 'shortcode-share.php';          // Snippet 5
    require_once $dir . 'shortcode-partner-page.php';   // Snippet 6  (RetaEU landing)
    require_once $dir . 'shortcode-login-page.php';     // Snippet 7  (RetaEU login)
    require_once $dir . 'admin-reassign-box.php';       // Snippet 8
    require_once $dir . 'my-account-banner.php';        // Snippet 9
    require_once $dir . 'admin-selective-payout.php';   // Snippet 10
    require_once $dir . 'admin-manage-users.php';       // Snippet 11
}
Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ grep -RniE "Bearer |Authorization:|api[_-]?key[[:space:]]*=>|client_secret|access_token|sk_live_|pk_live_|BEGIN .*PRIVATE KEY|password[[:space:]]*=>" --include="*.php" --exclude-dir=.git .

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ git show --stat --oneline HEAD
039dff9 (HEAD -> main) Initial release of affiliate management plugin
 .gitignore                          |   10 +
 includes/admin-affiliates.php       |  489 ++++++++++++
 includes/admin-manage-users.php     |  651 ++++++++++++++++
 includes/admin-orders.php           |  691 +++++++++++++++++
 includes/admin-reassign-box.php     |  444 +++++++++++
 includes/admin-selective-payout.php |  635 ++++++++++++++++
 includes/class-db.php               |  434 +++++++++++
 includes/frontend-shortcodes.php    | 1402 +++++++++++++++++++++++++++++++++++
 includes/my-account-banner.php      |  332 +++++++++
 includes/shortcode-login-page.php   |   81 ++
 includes/shortcode-partner-page.php |  292 ++++++++
 includes/shortcode-share.php        |   70 ++
 retaeu-affiliate.php                |   51 ++
 13 files changed, 5582 insertions(+)

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ git remote add origin https://github.com/bird-ie/wordpress-affiliate-management-plugin.git

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ git remote -v
origin  https://github.com/bird-ie/wordpress-affiliate-management-plugin.git (fetch)
origin  https://github.com/bird-ie/wordpress-affiliate-management-plugin.git (push)

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$ git push -u origin main
Enumerating objects: 16, done.
Counting objects: 100% (16/16), done.
Delta compression using up to 4 threads
Compressing objects: 100% (16/16), done.
Writing objects: 100% (16/16), 57.04 KiB | 1.90 MiB/s, done.
Total 16 (delta 1), reused 0 (delta 0), pack-reused 0 (from 0)
remote: Resolving deltas: 100% (1/1), done.
To https://github.com/bird-ie/wordpress-affiliate-management-plugin.git
 * [new branch]      main -> main
branch 'main' set up to track 'origin/main'.

Win 10@DESKTOP-M8UH8BP MINGW64 /f/JOBS/RETAEU/COMPLETE AFFILIATE PLUGIN/retaeu-affiliate (main)
$
