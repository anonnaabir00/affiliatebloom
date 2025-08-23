<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReferralManager {

    private $referral_bonus = 10.00; // Bonus amount for successful referral

    public static function init() {
        $instance = new self();
        return $instance;
    }

    public function __construct() {
        $this->init_hooks();
        $this->init_ajax_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('user_register', array($this, 'handle_user_registration'));
        add_action('wp_login', array($this, 'handle_user_login'), 10, 2);
        add_action('init', array($this, 'capture_referral_code'));
        add_action('wp_footer', array($this, 'add_referral_registration_form'));
        add_shortcode('referral_registration_form', array($this, 'referral_registration_form_shortcode'));
    }

    /**
     * Initialize AJAX hooks
     */
    private function init_ajax_hooks() {
        add_action('wp_ajax_affiliate_bloom_generate_referral_link', array($this, 'generate_referral_link'));
        add_action('wp_ajax_affiliate_bloom_get_referral_stats', array($this, 'get_referral_stats'));
        add_action('wp_ajax_affiliate_bloom_get_referral_history', array($this, 'get_referral_history'));
        add_action('wp_ajax_affiliate_bloom_delete_link', array($this, 'delete_referral_link'));

        // Registration form AJAX
        add_action('wp_ajax_nopriv_affiliate_bloom_register_user', array($this, 'handle_referral_registration'));
        add_action('wp_ajax_affiliate_bloom_register_user', array($this, 'handle_referral_registration'));

        // Track referral clicks
        add_action('wp_ajax_nopriv_affiliate_bloom_track_click', array($this, 'track_referral_click'));
        add_action('wp_ajax_affiliate_bloom_track_click', array($this, 'track_referral_click'));
    }

    /**
     * Capture referral code from URL and show registration form
     */
    public function capture_referral_code() {
        if (isset($_GET['ref']) && !empty($_GET['ref'])) {
            $referral_code = sanitize_text_field($_GET['ref']);

            // Verify referral code exists
            $referrer_id = $this->get_user_by_referral_code($referral_code);
            if (!$referrer_id) {
                return; // Invalid referral code
            }

            // Store in session/cookie for later use during registration
            if (!session_id()) {
                session_start();
            }
            $_SESSION['referral_code'] = $referral_code;
            $_SESSION['show_referral_form'] = true;

            // Also store in cookie as backup
            setcookie('affiliate_referral', $referral_code, time() + (30 * 24 * 60 * 60), '/'); // 30 days

            // Track the click
            $this->track_click($referrer_id, $referral_code);
        }
    }

    /**
     * Track referral click
     */
    private function track_click($referrer_id, $referral_code) {
        $links = get_user_meta($referrer_id, 'referral_links', true);
        if (!is_array($links)) {
            return;
        }

        // Find and update the link click count
        foreach ($links as &$link) {
            if (strpos($link['affiliate_url'], 'ref=' . $referral_code) !== false) {
                $link['clicks'] = isset($link['clicks']) ? $link['clicks'] + 1 : 1;
                $link['last_clicked'] = current_time('mysql');
                break;
            }
        }

        update_user_meta($referrer_id, 'referral_links', $links);

        // Log click event
        $this->log_referral_event($referrer_id, 'click', array(
            'referral_code' => $referral_code,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Add referral registration form to footer when needed
     */
    public function add_referral_registration_form() {
        if (!session_id()) {
            session_start();
        }

        if (isset($_SESSION['show_referral_form']) && $_SESSION['show_referral_form'] && !is_user_logged_in()) {
            $referral_code = $_SESSION['referral_code'] ?? '';
            $referrer = $this->get_user_by_referral_code($referral_code);

            if ($referrer) {
                $referrer_info = get_user_by('ID', $referrer);
                echo $this->render_referral_registration_modal($referral_code, $referrer_info);
            }
        }
    }

    /**
     * Render referral registration modal
     */
    private function render_referral_registration_modal($referral_code, $referrer_info) {
        ob_start();
        ?>
        <div id="referral-registration-modal" class="referral-modal" style="display: none;">
            <div class="referral-modal-content">
                <div class="referral-modal-header">
                    <h2>🎉 You've been invited!</h2>
                    <span class="referral-modal-close">&times;</span>
                </div>
                <div class="referral-modal-body">
                    <p><strong><?php echo esc_html($referrer_info->display_name); ?></strong> has invited you to join our platform!</p>
                    <p>Sign up now and both you and <?php echo esc_html($referrer_info->display_name); ?> will get special bonuses!</p>

                    <form id="referral-registration-form" class="referral-form">
                        <?php wp_nonce_field('affiliate_bloom_register', 'referral_nonce'); ?>
                        <input type="hidden" name="referral_code" value="<?php echo esc_attr($referral_code); ?>">

                        <div class="form-group">
                            <label for="reg_username">Username *</label>
                            <input type="text" id="reg_username" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="reg_email">Email *</label>
                            <input type="email" id="reg_email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="reg_password">Password *</label>
                            <input type="password" id="reg_password" name="password" required minlength="8">
                        </div>

                        <div class="form-group">
                            <label for="reg_first_name">First Name</label>
                            <input type="text" id="reg_first_name" name="first_name">
                        </div>

                        <div class="form-group">
                            <label for="reg_last_name">Last Name</label>
                            <input type="text" id="reg_last_name" name="last_name">
                        </div>

                        <div class="referral-benefits">
                            <h4>🎁 Your Benefits:</h4>
                            <ul>
                                <li>✅ Welcome bonus when you sign up</li>
                                <li>✅ Access to exclusive content</li>
                                <li>✅ Special member pricing</li>
                            </ul>
                        </div>

                        <button type="submit" class="referral-submit-btn">Create My Account</button>

                        <p class="referral-terms">
                            By signing up, you agree to our <a href="#" target="_blank">Terms of Service</a>
                            and <a href="#" target="_blank">Privacy Policy</a>
                        </p>
                    </form>

                    <div id="referral-registration-result"></div>
                </div>
            </div>
        </div>

        <style>
        .referral-modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .referral-modal-content {
            background-color: #fff;
            margin: 5% auto;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .referral-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .referral-modal-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .referral-modal-close {
            font-size: 28px;
            cursor: pointer;
            transition: opacity 0.3s;
        }

        .referral-modal-close:hover {
            opacity: 0.7;
        }

        .referral-modal-body {
            padding: 20px;
        }

        .referral-form .form-group {
            margin-bottom: 15px;
        }

        .referral-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .referral-form input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .referral-form input:focus {
            border-color: #667eea;
            outline: none;
        }

        .referral-benefits {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }

        .referral-benefits h4 {
            margin: 0 0 10px 0;
            color: #28a745;
        }

        .referral-benefits ul {
            margin: 0;
            padding-left: 20px;
        }

        .referral-benefits li {
            margin-bottom: 5px;
            color: #28a745;
        }

        .referral-submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .referral-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .referral-submit-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .referral-terms {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 15px;
        }

        .referral-terms a {
            color: #667eea;
            text-decoration: none;
        }

        .referral-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }

        .referral-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }

        @media (max-width: 600px) {
            .referral-modal-content {
                margin: 10% auto;
                width: 95%;
            }

            .referral-modal-header {
                padding: 15px;
            }

            .referral-modal-header h2 {
                font-size: 20px;
            }

            .referral-modal-body {
                padding: 15px;
            }
        }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('referral-registration-modal');
            const closeBtn = document.querySelector('.referral-modal-close');
            const form = document.getElementById('referral-registration-form');

            // Show modal after a short delay
            setTimeout(function() {
                modal.style.display = 'block';
            }, 1000);

            // Close modal functionality
            closeBtn.onclick = function() {
                modal.style.display = 'none';
            }

            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }

            // Handle form submission
            form.onsubmit = function(e) {
                e.preventDefault();

                const submitBtn = form.querySelector('.referral-submit-btn');
                const resultDiv = document.getElementById('referral-registration-result');

                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating Account...';

                const formData = new FormData(form);
                formData.append('action', 'affiliate_bloom_register_user');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="referral-success">' +
                            '<h4>🎉 Welcome aboard!</h4>' +
                            '<p>' + data.data.message + '</p>' +
                            '<p>You can now <a href="' + data.data.login_url + '">login to your account</a>.</p>' +
                            '</div>';
                        form.style.display = 'none';

                        // Redirect after 3 seconds
                        setTimeout(function() {
                            window.location.href = data.data.redirect_url || '<?php echo home_url(); ?>';
                        }, 3000);
                    } else {
                        resultDiv.innerHTML = '<div class="referral-error">' +
                            '<strong>Error:</strong> ' + (data.data.message || 'Registration failed. Please try again.') +
                            '</div>';
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Create My Account';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultDiv.innerHTML = '<div class="referral-error">' +
                        '<strong>Error:</strong> Something went wrong. Please try again.' +
                        '</div>';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create My Account';
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle referral registration via AJAX
     */
    public function handle_referral_registration() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['referral_nonce'] ?? '', 'affiliate_bloom_register')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        $username = sanitize_user($_POST['username'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $referral_code = sanitize_text_field($_POST['referral_code'] ?? '');

        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(array('message' => 'Username, email, and password are required'));
        }

        if (username_exists($username)) {
            wp_send_json_error(array('message' => 'Username already exists'));
        }

        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'Email already exists'));
        }

        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => 'Password must be at least 8 characters'));
        }

        // Get referrer
        $referrer_id = $this->get_user_by_referral_code($referral_code);
        if (!$referrer_id) {
            wp_send_json_error(array('message' => 'Invalid referral code'));
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }

        // Update user meta
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name
        ));

        // Store referral relationship
        update_user_meta($user_id, 'referred_by', $referrer_id);
        update_user_meta($user_id, 'referral_code_used', $referral_code);
        update_user_meta($user_id, 'registration_source', 'referral');

        // Add referral record
        $this->add_referral_record($referrer_id, $user_id, 'pending');

        // Process referral bonus immediately (you can modify this logic)
        $this->process_referral_bonus($referrer_id, $user_id);

        // Clear session
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION['referral_code']);
        unset($_SESSION['show_referral_form']);

        // Send success response
        wp_send_json_success(array(
            'message' => 'Account created successfully! Welcome bonus has been applied.',
            'login_url' => wp_login_url(),
            'redirect_url' => home_url()
        ));
    }

    /**
     * Handle user registration (existing function - updated)
     */
    public function handle_user_registration($user_id) {
        $referral_code = $this->get_stored_referral_code();

        if ($referral_code) {
            $referrer_id = $this->get_user_by_referral_code($referral_code);

            if ($referrer_id && $referrer_id != $user_id) {
                // Store referral relationship
                update_user_meta($user_id, 'referred_by', $referrer_id);
                update_user_meta($user_id, 'referral_code_used', $referral_code);

                // Add referral record
                $this->add_referral_record($referrer_id, $user_id, 'registration');

                // Clear stored referral code
                $this->clear_stored_referral_code();
            }
        }
    }

    /**
     * Handle user login (for bonus activation) - updated
     */
    public function handle_user_login($user_login, $user) {
        $referred_by = get_user_meta($user->ID, 'referred_by', true);

        if ($referred_by && !get_user_meta($user->ID, 'referral_bonus_processed', true)) {
            // Check if new user has been active (you can add more conditions)
            $this->process_referral_bonus($referred_by, $user->ID);
        }
    }

    /**
     * Process referral bonus - updated
     */
    private function process_referral_bonus($referrer_id, $referred_user_id) {
        // Check if already processed
        if (get_user_meta($referred_user_id, 'referral_bonus_processed', true)) {
            return;
        }

        // Add bonus to referrer's account
        $current_balance = get_user_meta($referrer_id, 'affiliate_balance', true);
        $current_balance = $current_balance ? floatval($current_balance) : 0;
        $new_balance = $current_balance + $this->referral_bonus;

        update_user_meta($referrer_id, 'affiliate_balance', $new_balance);

        // Log the transaction
        $this->log_referral_transaction($referrer_id, $referred_user_id, $this->referral_bonus);

        // Mark as processed
        update_user_meta($referred_user_id, 'referral_bonus_processed', true);

        // Update referral record
        $this->update_referral_status($referrer_id, $referred_user_id, 'completed');

        // Update conversion count in referral links
        $this->update_referral_conversion($referrer_id, $referred_user_id);

        // Fire action hook
        do_action('affiliate_bloom_referral_bonus_added', $referrer_id, $referred_user_id, $this->referral_bonus);
    }

    /**
     * Update referral conversion count
     */
    private function update_referral_conversion($referrer_id, $referred_user_id) {
        $referral_code_used = get_user_meta($referred_user_id, 'referral_code_used', true);
        if (!$referral_code_used) {
            return;
        }

        $links = get_user_meta($referrer_id, 'referral_links', true);
        if (!is_array($links)) {
            return;
        }

        // Find and update the link conversion count
        foreach ($links as &$link) {
            if (strpos($link['affiliate_url'], 'ref=' . $referral_code_used) !== false) {
                $link['conversions'] = isset($link['conversions']) ? $link['conversions'] + 1 : 1;
                break;
            }
        }

        update_user_meta($referrer_id, 'referral_links', $links);
    }

    /**
     * Generate referral link AJAX handler - updated
     */
    public function generate_referral_link() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliate_bloom_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();

        // Check if user is approved affiliate
        if (get_user_meta($user_id, 'affiliate_status', true) !== 'approved') {
            wp_send_json_error('User is not an approved affiliate');
        }

        $target_url = sanitize_url($_POST['target_url'] ?? home_url());
        $referral_code = $this->get_or_create_referral_code($user_id);

        // Generate referral URL
        $referral_url = add_query_arg('ref', $referral_code, $target_url);

        // Store the link for tracking
        $link_id = $this->store_referral_link($user_id, $target_url, $referral_url);

        wp_send_json_success(array(
            'message' => 'Referral link generated successfully!',
            'referral_url' => $referral_url,
            'referral_code' => $referral_code,
            'target_url' => $target_url,
            'link_id' => $link_id
        ));
    }

    /**
     * Store referral link - updated
     */
    private function store_referral_link($user_id, $target_url, $referral_url) {
        $links = get_user_meta($user_id, 'referral_links', true);
        if (!is_array($links)) {
            $links = array();
        }

        $link = array(
            'id' => uniqid('link_'),
            'name' => 'Link to ' . parse_url($target_url, PHP_URL_HOST),
            'target_url' => $target_url,
            'affiliate_url' => $referral_url,
            'clicks' => 0,
            'conversions' => 0,
            'created_date' => current_time('mysql'),
            'last_clicked' => null,
            'status' => 'active'
        );

        $links[] = $link;
        update_user_meta($user_id, 'referral_links', $links);

        return $link['id'];
    }

    /**
     * Delete referral link
     */
    public function delete_referral_link() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliate_bloom_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();
        $link_id = sanitize_text_field($_POST['link_id'] ?? '');

        if (empty($link_id)) {
            wp_send_json_error('Link ID is required');
        }

        $links = get_user_meta($user_id, 'referral_links', true);
        if (!is_array($links)) {
            wp_send_json_error('No links found');
        }

        $updated_links = array_filter($links, function($link) use ($link_id) {
            return $link['id'] !== $link_id;
        });

        update_user_meta($user_id, 'referral_links', array_values($updated_links));

        wp_send_json_success('Link deleted successfully');
    }

    /**
     * Track referral click AJAX
     */
    public function track_referral_click() {
        $referral_code = sanitize_text_field($_POST['referral_code'] ?? '');

        if (empty($referral_code)) {
            wp_send_json_error('Referral code required');
        }

        $referrer_id = $this->get_user_by_referral_code($referral_code);
        if (!$referrer_id) {
            wp_send_json_error('Invalid referral code');
        }

        $this->track_click($referrer_id, $referral_code);

        wp_send_json_success('Click tracked');
    }

    /**
     * Get referral statistics - updated
     */
    public function get_referral_stats() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliate_bloom_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();

        $stats = array(
            'total_referrals' => $this->get_total_referrals($user_id),
            'active_referrals' => $this->get_active_referrals($user_id),
            'total_bonus_earned' => $this->get_total_referral_bonus($user_id),
            'pending_referrals' => $this->get_pending_referrals($user_id),
            'referral_code' => $this->get_or_create_referral_code($user_id),
            'bonus_per_referral' => $this->referral_bonus,
            'total_clicks' => $this->get_total_clicks($user_id),
            'conversion_rate' => $this->get_conversion_rate($user_id)
        );

        wp_send_json_success(array('stats' => $stats));
    }

    /**
     * Get referral history (returns referral links)
     */
    public function get_referral_history() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliate_bloom_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 10);

        $links = $this->get_user_referral_links($user_id, $page, $per_page);

        wp_send_json_success($links);
    }

    // Helper Methods

    /**
     * Get total clicks
     */
    private function get_total_clicks($user_id) {
        $links = get_user_meta($user_id, 'referral_links', true);
        if (!is_array($links)) {
            return 0;
        }

        $total_clicks = 0;
        foreach ($links as $link) {
            $total_clicks += isset($link['clicks']) ? $link['clicks'] : 0;
        }

        return $total_clicks;
    }

    /**
     * Get conversion rate
     */
    private function get_conversion_rate($user_id) {
        $total_clicks = $this->get_total_clicks($user_id);
        $total_referrals = $this->get_active_referrals($user_id);

        if ($total_clicks == 0) {
            return 0;
        }

        return round(($total_referrals / $total_clicks) * 100, 2);
    }

    /**
     * Log referral event
     */
    private function log_referral_event($referrer_id, $event_type, $data = array()) {
        $events = get_user_meta($referrer_id, 'referral_events', true);
        if (!is_array($events)) {
            $events = array();
        }

        $event = array(
            'id' => uniqid('event_'),
            'type' => $event_type,
            'data' => $data,
            'timestamp' => current_time('mysql')
        );

        $events[] = $event;

        // Keep only last 100 events
        if (count($events) > 100) {
            $events = array_slice($events, -100);
        }

        update_user_meta($referrer_id, 'referral_events', $events);
    }

    /**
     * Get or create referral code for user
     */
    private function get_or_create_referral_code($user_id) {
        $code = get_user_meta($user_id, 'referral_code', true);

        if (empty($code)) {
            $code = 'REF' . $user_id . '_' . wp_generate_password(6, false, false);
            update_user_meta($user_id, 'referral_code', $code);
        }

        return $code;
    }

    /**
     * Get user by referral code
     */
    private function get_user_by_referral_code($referral_code) {
        $users = get_users(array(
            'meta_key' => 'referral_code',
            'meta_value' => $referral_code,
            'number' => 1,
            'fields' => 'ID'
        ));

        return !empty($users) ? $users[0] : false;
    }

    /**
     * Get stored referral code
     */
    private function get_stored_referral_code() {
        if (!session_id()) {
            session_start();
        }

        // Try session first, then cookie
        if (!empty($_SESSION['referral_code'])) {
            return $_SESSION['referral_code'];
        }

        if (!empty($_COOKIE['affiliate_referral'])) {
            return sanitize_text_field($_COOKIE['affiliate_referral']);
        }

        return false;
    }

    /**
     * Clear stored referral code
     */
    private function clear_stored_referral_code() {
        if (!session_id()) {
            session_start();
        }

        unset($_SESSION['referral_code']);
        unset($_SESSION['show_referral_form']);
        setcookie('affiliate_referral', '', time() - 3600, '/');
    }

    /**
     * Add referral record
     */
    private function add_referral_record($referrer_id, $referred_user_id, $status = 'pending') {
        $referrals = get_user_meta($referrer_id, 'referral_history', true);
        if (!is_array($referrals)) {
            $referrals = array();
        }

        $referred_user = get_user_by('ID', $referred_user_id);
        $referral = array(
            'id' => uniqid('ref_'),
            'referred_user_id' => $referred_user_id,
            'referred_username' => $referred_user ? $referred_user->user_login : 'Unknown',
            'referred_email' => $referred_user ? $referred_user->user_email : '',
            'status' => $status,
            'bonus_amount' => $this->referral_bonus,
            'created_date' => current_time('mysql'),
            'completed_date' => null
        );

        $referrals[] = $referral;
        update_user_meta($referrer_id, 'referral_history', $referrals);

        return $referral['id'];
    }

    /**
     * Update referral status
     */
    private function update_referral_status($referrer_id, $referred_user_id, $status) {
        $referrals = get_user_meta($referrer_id, 'referral_history', true);
        if (!is_array($referrals)) {
            return false;
        }

        foreach ($referrals as &$referral) {
            if ($referral['referred_user_id'] == $referred_user_id) {
                $referral['status'] = $status;
                if ($status === 'completed') {
                    $referral['completed_date'] = current_time('mysql');
                }
                break;
            }
        }

        update_user_meta($referrer_id, 'referral_history', $referrals);
    }

    /**
     * Log referral transaction
     */
    private function log_referral_transaction($referrer_id, $referred_user_id, $amount) {
        $transaction_history = get_user_meta($referrer_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            $transaction_history = array();
        }

        $referred_user = get_user_by('ID', $referred_user_id);
        $referred_username = $referred_user ? $referred_user->user_login : 'Unknown';

        $transaction = array(
            'id' => uniqid('txn_'),
            'type' => 'referral_bonus',
            'amount' => $amount,
            'status' => 'completed',
            'description' => 'Referral bonus for user: ' . $referred_username,
            'referred_user_id' => $referred_user_id,
            'created_date' => current_time('mysql'),
            'date' => date('Y-m-d'),
            'timestamp' => current_time('timestamp')
        );

        $transaction_history[] = $transaction;
        update_user_meta($referrer_id, 'affiliate_transaction_history', $transaction_history);
    }

    /**
     * Get total referrals for user
     */
    private function get_total_referrals($user_id) {
        $referrals = get_user_meta($user_id, 'referral_history', true);
        return is_array($referrals) ? count($referrals) : 0;
    }

    /**
     * Get active referrals for user
     */
    private function get_active_referrals($user_id) {
        $referrals = get_user_meta($user_id, 'referral_history', true);
        if (!is_array($referrals)) {
            return 0;
        }

        return count(array_filter($referrals, function($referral) {
            return $referral['status'] === 'completed';
        }));
    }

    /**
     * Get pending referrals for user
     */
    private function get_pending_referrals($user_id) {
        $referrals = get_user_meta($user_id, 'referral_history', true);
        if (!is_array($referrals)) {
            return 0;
        }

        return count(array_filter($referrals, function($referral) {
            return $referral['status'] === 'pending';
        }));
    }

    /**
     * Get total referral bonus earned
     */
    private function get_total_referral_bonus($user_id) {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            return 0;
        }

        $total = 0;
        foreach ($transaction_history as $transaction) {
            if ($transaction['type'] === 'referral_bonus' && $transaction['status'] === 'completed') {
                $total += floatval($transaction['amount']);
            }
        }

        return $total;
    }

    /**
     * Get user referral links with pagination
     */
    private function get_user_referral_links($user_id, $page = 1, $per_page = 10) {
        $links = get_user_meta($user_id, 'referral_links', true);
        if (!is_array($links)) {
            return array(
                'referrals' => array(),
                'total' => 0,
                'pages' => 0,
                'current_page' => $page
            );
        }

        // Sort by date (newest first)
        usort($links, function($a, $b) {
            return strtotime($b['created_date']) - strtotime($a['created_date']);
        });

        $total = count($links);
        $pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $page_links = array_slice($links, $offset, $per_page);

        return array(
            'referrals' => $page_links,
            'total' => $total,
            'pages' => $pages,
            'current_page' => $page
        );
    }

    /**
     * Get user referrals with pagination (actual referrals, not links)
     */
    private function get_user_referrals($user_id, $page = 1, $per_page = 10) {
        $referrals = get_user_meta($user_id, 'referral_history', true);
        if (!is_array($referrals)) {
            return array(
                'referrals' => array(),
                'total' => 0,
                'pages' => 0,
                'current_page' => $page
            );
        }

        // Sort by date (newest first)
        usort($referrals, function($a, $b) {
            return strtotime($b['created_date']) - strtotime($a['created_date']);
        });

        $total = count($referrals);
        $pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $page_referrals = array_slice($referrals, $offset, $per_page);

        return array(
            'referrals' => $page_referrals,
            'total' => $total,
            'pages' => $pages,
            'current_page' => $page
        );
    }

    /**
     * Shortcode for referral registration form
     */
    public function referral_registration_form_shortcode($atts = array()) {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['referral_code']) || is_user_logged_in()) {
            return '<p>No active referral or already logged in.</p>';
        }

        $referral_code = $_SESSION['referral_code'];
        $referrer_id = $this->get_user_by_referral_code($referral_code);

        if (!$referrer_id) {
            return '<p>Invalid referral code.</p>';
        }

        $referrer_info = get_user_by('ID', $referrer_id);

        ob_start();
        ?>
        <div class="referral-registration-form-container">
            <h3>🎉 You've been invited by <?php echo esc_html($referrer_info->display_name); ?>!</h3>

            <?php echo $this->render_inline_registration_form($referral_code, $referrer_info); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render inline registration form
     */
    private function render_inline_registration_form($referral_code, $referrer_info) {
        ob_start();
        ?>
        <div class="referral-inline-form">
            <form id="referral-registration-form-inline" class="referral-form">
                <?php wp_nonce_field('affiliate_bloom_register', 'referral_nonce'); ?>
                <input type="hidden" name="referral_code" value="<?php echo esc_attr($referral_code); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="reg_username">Username *</label>
                        <input type="text" id="reg_username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="reg_email">Email *</label>
                        <input type="email" id="reg_email" name="email" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="reg_first_name">First Name</label>
                        <input type="text" id="reg_first_name" name="first_name">
                    </div>

                    <div class="form-group">
                        <label for="reg_last_name">Last Name</label>
                        <input type="text" id="reg_last_name" name="last_name">
                    </div>
                </div>

                <div class="form-group">
                    <label for="reg_password">Password *</label>
                    <input type="password" id="reg_password" name="password" required minlength="8">
                </div>

                <div class="referral-benefits">
                    <h4>🎁 Your Benefits:</h4>
                    <ul>
                        <li>✅ Welcome bonus when you sign up</li>
                        <li>✅ Access to exclusive content</li>
                        <li>✅ Special member pricing</li>
                    </ul>
                </div>

                <button type="submit" class="referral-submit-btn">Create My Account</button>
            </form>

            <div id="referral-registration-result-inline"></div>
        </div>

        <style>
        .referral-inline-form {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .referral-form .form-group {
            margin-bottom: 15px;
        }

        .referral-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .referral-form input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .referral-form input:focus {
            border-color: #667eea;
            outline: none;
        }

        .referral-benefits {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }

        .referral-benefits h4 {
            margin: 0 0 10px 0;
            color: #28a745;
        }

        .referral-benefits ul {
            margin: 0;
            padding-left: 20px;
        }

        .referral-benefits li {
            margin-bottom: 5px;
            color: #28a745;
        }

        .referral-submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .referral-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .referral-submit-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .referral-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }

        .referral-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .referral-inline-form {
                padding: 20px 15px;
            }
        }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('referral-registration-form-inline');
            if (!form) return;

            form.onsubmit = function(e) {
                e.preventDefault();

                const submitBtn = form.querySelector('.referral-submit-btn');
                const resultDiv = document.getElementById('referral-registration-result-inline');

                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating Account...';

                const formData = new FormData(form);
                formData.append('action', 'affiliate_bloom_register_user');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="referral-success">' +
                            '<h4>🎉 Welcome aboard!</h4>' +
                            '<p>' + data.data.message + '</p>' +
                            '<p>You can now <a href="' + data.data.login_url + '">login to your account</a>.</p>' +
                            '</div>';
                        form.style.display = 'none';

                        // Redirect after 3 seconds
                        setTimeout(function() {
                            window.location.href = data.data.redirect_url || '<?php echo home_url(); ?>';
                        }, 3000);
                    } else {
                        resultDiv.innerHTML = '<div class="referral-error">' +
                            '<strong>Error:</strong> ' + (data.data.message || 'Registration failed. Please try again.') +
                            '</div>';
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Create My Account';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultDiv.innerHTML = '<div class="referral-error">' +
                        '<strong>Error:</strong> Something went wrong. Please try again.' +
                        '</div>';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create My Account';
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
}