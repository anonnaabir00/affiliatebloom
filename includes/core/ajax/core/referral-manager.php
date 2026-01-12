<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReferralManager {

    private $referral_bonus = 0.00; // Account opening bonus disabled

    public static function init() {
        $instance = new self();
        return $instance;
    }

    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('user_register', array($this, 'handle_user_registration'));
        add_action('init', array($this, 'capture_referral_code'));
        add_action('affiliate_bloom_user_referred', array($this, 'handle_user_referred'), 10, 3);
        add_action('wp_ajax_affiliate_bloom_get_referral_stats', array($this, 'get_referral_stats'));
        add_action('wp_ajax_affiliate_bloom_get_referral_history', array($this, 'get_referral_history'));
    }

//     private function init_ajax_hooks() {
//
//     }

    public function get_referral_stats() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

//         if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliateBloom')) {
//             wp_send_json_error('Invalid nonce');
//         }

        $user_id = get_current_user_id();
        $stats = $this->get_user_stats($user_id);

        wp_send_json_success(array('stats' => $stats));
    }

    public function get_referral_history() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

//         if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliateBloom')) {
//             wp_send_json_error('Invalid nonce');
//         }

        $user_id = get_current_user_id();
        $referrals = get_user_meta($user_id, 'referral_history', true);
        $referrals = is_array($referrals) ? $referrals : array();

        wp_send_json_success(array('referrals' => $referrals));
    }

    /**
     * Capture referral code from URL
     */
    public function capture_referral_code() {
        if (isset($_GET['ref']) && !empty($_GET['ref'])) {
            $referral_code = sanitize_text_field($_GET['ref']);

            // Verify referral code exists
            $referrer_id = $this->get_user_by_referral_code($referral_code);
            if (!$referrer_id) {
                return; // Invalid referral code
            }

            // Store in session for later use during registration
            if (!session_id()) {
                session_start();
            }
            $_SESSION['referral_code'] = $referral_code;

            // Also store in cookie as backup
            setcookie('affiliate_referral', $referral_code, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
    }

    /**
     * Handle user registration
     */
    public function handle_user_registration($user_id) {
        $referral_code = $this->get_stored_referral_code();

        if ($referral_code) {
            $referrer_id = $this->get_user_by_referral_code($referral_code);

            if ($referrer_id && $referrer_id != $user_id) {
                // Store referral relationship
                update_user_meta($user_id, 'referred_by', $referrer_id);
                update_user_meta($user_id, 'referral_code_used', $referral_code);
                update_user_meta($user_id, 'registration_source', 'referral');

                // Set MLM sponsor relationship
                $mlm = MLMCommission::init();
                $mlm->set_user_sponsor($user_id, $referrer_id);

                // Process referral bonus
                $this->process_referral_bonus($referrer_id, $user_id);

                // Clear stored referral code
                $this->clear_stored_referral_code();
            }
        }
    }

    /**
     * Handle referrals created via API registration (JWT)
     */
    public function handle_user_referred($referrer_id, $user_id, $referral_code) {
        if (empty($referrer_id) || empty($user_id) || $referrer_id == $user_id) {
            return;
        }

        $mlm = MLMCommission::init();
        $mlm->set_user_sponsor($user_id, $referrer_id);

        $this->process_referral_bonus($referrer_id, $user_id);
    }

    /**
     * Process referral bonus
     */
    private function process_referral_bonus($referrer_id, $referred_user_id) {
        // Check if already processed
        if (get_user_meta($referred_user_id, 'referral_bonus_processed', true)) {
            return;
        }

        $bonus_amount = floatval($this->referral_bonus);
        if ($bonus_amount > 0) {
            // Add bonus to referrer's account
            $current_balance = get_user_meta($referrer_id, 'affiliate_balance', true);
            $current_balance = $current_balance ? floatval($current_balance) : 0;
            $new_balance = $current_balance + $bonus_amount;

            update_user_meta($referrer_id, 'affiliate_balance', $new_balance);

            // Log the transaction
            $this->log_referral_transaction($referrer_id, $referred_user_id, $bonus_amount);
        }

        // Mark as processed
        update_user_meta($referred_user_id, 'referral_bonus_processed', true);

        // Add simple referral record
        $this->add_referral_record($referrer_id, $referred_user_id, $bonus_amount);

        if ($bonus_amount > 0) {
            // Fire action hook for extensibility
            do_action('affiliate_bloom_referral_bonus_added', $referrer_id, $referred_user_id, $bonus_amount);
        }
    }

    /**
     * Get or create referral code for user
     */
    public function get_user_referral_code($user_id) {
        $code = get_user_meta($user_id, 'referral_code', true);

        if (empty($code)) {
            $code = 'REF' . $user_id . '_' . wp_generate_password(6, false, false);
            update_user_meta($user_id, 'referral_code', $code);
        }

        return $code;
    }

    /**
     * Generate referral URL
     */
    public function generate_referral_url($user_id, $target_url = '') {
        if (empty($target_url)) {
            $target_url = AffiliateHelper::get_frontend_base_url();
        }

        $referral_code = $this->get_user_referral_code($user_id);
        return add_query_arg('ref', $referral_code, $target_url);
    }

    /**
     * Get user referral stats
     */
    public function get_user_stats($user_id) {
        $referrals = get_user_meta($user_id, 'referral_history', true);
        $referrals = is_array($referrals) ? $referrals : array();

        $balance = get_user_meta($user_id, 'affiliate_balance', true);
        $balance = $balance ? floatval($balance) : 0;

        return array(
            'referral_code' => $this->get_user_referral_code($user_id),
            'total_referrals' => count($referrals),
            'total_earnings' => $balance,
            'bonus_per_referral' => $this->referral_bonus
        );
    }

    // Helper Methods

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
        setcookie('affiliate_referral', '', time() - 3600, '/');
    }

    /**
     * Add referral record
     */
    private function add_referral_record($referrer_id, $referred_user_id, $bonus_amount = null) {
        $referrals = get_user_meta($referrer_id, 'referral_history', true);
        if (!is_array($referrals)) {
            $referrals = array();
        }

        $referred_user = get_user_by('ID', $referred_user_id);
        $bonus_amount = $bonus_amount !== null ? floatval($bonus_amount) : floatval($this->referral_bonus);
        $referral = array(
            'id' => uniqid('ref_'),
            'referred_user_id' => $referred_user_id,
            'referred_username' => $referred_user ? $referred_user->user_login : 'Unknown',
            'referred_email' => $referred_user ? $referred_user->user_email : '',
            'bonus_amount' => $bonus_amount,
            'created_date' => current_time('mysql')
        );

        $referrals[] = $referral;
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
            'description' => 'Referral bonus for user: ' . $referred_username,
            'referred_user_id' => $referred_user_id,
            'created_date' => current_time('mysql'),
            'timestamp' => current_time('timestamp')
        );

        $transaction_history[] = $transaction;
        update_user_meta($referrer_id, 'affiliate_transaction_history', $transaction_history);
    }
}
