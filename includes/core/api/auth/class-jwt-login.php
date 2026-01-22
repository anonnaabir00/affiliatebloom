<?php

namespace AffiliateBloom;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * JWT Login Handler Class
 */
class JWTLogin {

    private $jwt_auth;

    public function __construct(JWTAuth $jwt_auth) {
        $this->jwt_auth = $jwt_auth;
    }

    /**
     * Get REST route configuration
     */
    public function get_route_config() {
        return [
            'methods'             => 'POST',
            'callback'            => [$this, 'login'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ];
    }

    /**
     * Login endpoint - Generate JWT token
     */
    public function login(\WP_REST_Request $request) {
        $email    = sanitize_text_field($request->get_param('email'));
        $password = $request->get_param('password');

        if (empty($email) || empty($password)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Email and password are required',
            ], 400);
        }

        // Try to get user by email first
        $user = get_user_by('email', $email);

        // If not found, try by username
        if (!$user) {
            $user = get_user_by('login', $email);
        }

        // If user not found
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        // Check password
        if (!wp_check_password($password, $user->data->user_pass, $user->ID)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        // Check if user is affiliate partner (optional)
        $is_partner = get_user_meta($user->ID, 'is_affiliate_partner', true);
        if (!$is_partner) {
            // Set user as partner
            update_user_meta($user->ID, 'is_affiliate_partner', true);
        }

        // Generate tokens
        $token = $this->jwt_auth->generate_token($user);
        $refresh_token = $this->jwt_auth->generate_refresh_token($user);

        // Process login bonus for API-based logins
        $login_bonus_data = $this->process_login_bonus($user->ID);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Login successful!',
            'data'    => [
                'token'         => $token,
                'refresh_token' => $refresh_token,
                'expires_in'    => $this->jwt_auth->get_token_expiry(),
                'user'          => [
                    'id'           => $user->ID,
                    'name'         => $user->display_name,
                    'email'        => $user->user_email,
                    'username'     => $user->user_login,
                    'affiliate_id' => get_user_meta($user->ID, 'affiliate_id', true),
                    'phone'        => get_user_meta($user->ID, 'phone_number', true),
                    'zilla'        => get_user_meta($user->ID, 'zilla', true),
                ],
                'login_bonus'   => $login_bonus_data,
            ],
        ], 200);
    }

    /**
     * Process login bonus for API-based logins
     */
    private function process_login_bonus($user_id) {
        $bonus_amount = 5.00;
        $today = date('Y-m-d');
        $last_bonus_date = get_user_meta($user_id, 'last_login_bonus_date', true);

        // If user already got bonus today, return status without adding
        if ($last_bonus_date === $today) {
            return [
                'awarded' => false,
                'message' => 'Login bonus already claimed today',
                'amount'  => 0,
            ];
        }

        // Add login bonus
        $current_balance = get_user_meta($user_id, 'affiliate_balance', true);
        $current_balance = $current_balance ? floatval($current_balance) : 0;
        $new_balance = $current_balance + $bonus_amount;

        update_user_meta($user_id, 'affiliate_balance', $new_balance);
        update_user_meta($user_id, 'last_login_bonus_date', $today);

        // Log transaction
        $transaction_id = $this->log_bonus_transaction($user_id, $bonus_amount, 'login_bonus', 'Daily login bonus');

        // Fire action for extensibility
        do_action('affiliate_bloom_login_bonus_added', $user_id, $bonus_amount, $transaction_id);

        return [
            'awarded'        => true,
            'message'        => 'Login bonus awarded!',
            'amount'         => $bonus_amount,
            'new_balance'    => $new_balance,
            'transaction_id' => $transaction_id,
        ];
    }

    /**
     * Log bonus transaction to user's transaction history
     */
    private function log_bonus_transaction($user_id, $amount, $type, $description) {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            $transaction_history = [];
        }

        $transaction = [
            'id'           => uniqid('txn_'),
            'type'         => $type,
            'amount'       => $amount,
            'status'       => 'completed',
            'description'  => $description,
            'created_date' => current_time('mysql'),
            'date'         => date('Y-m-d'),
            'timestamp'    => current_time('timestamp'),
        ];

        $transaction_history[] = $transaction;
        update_user_meta($user_id, 'affiliate_transaction_history', $transaction_history);

        return $transaction['id'];
    }
}