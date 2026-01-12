<?php

namespace AffiliateBloom;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * JWT Register Handler Class
 */
class JWTRegister {

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
            'callback'            => [$this, 'register'],
            'permission_callback' => '__return_true',
            'args'                => [
                'name' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'phone' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'zilla' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'referral_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ];
    }

    /**
     * Register endpoint - Create new affiliate account
     */
    public function register(\WP_REST_Request $request) {
        $name = sanitize_text_field($request->get_param('name'));
        $email = sanitize_email($request->get_param('email'));
        $phone = sanitize_text_field($request->get_param('phone'));
        $zilla = sanitize_text_field($request->get_param('zilla'));
        $password = $request->get_param('password');
        $referral_id = sanitize_text_field($request->get_param('referral_id') ?? '');

        // Validation
        $errors = $this->validate_registration_data($name, $email, $phone, $zilla, $password);

        // Return errors if any
        if (!empty($errors)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => implode(' ', $errors),
            ], 400);
        }

        // Generate username from email
        $username = $this->generate_unique_username($email);

        // Create user
        $user_data = [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name,
            'role'         => 'subscriber',
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $user_id->get_error_message(),
            ], 400);
        }

        // Generate unique affiliate/referral code for the new user
        $affiliate_code = $this->generate_affiliate_code($user_id);

        // Save affiliate meta data
        update_user_meta($user_id, 'phone_number', $phone);
        update_user_meta($user_id, 'zilla', $zilla);
        update_user_meta($user_id, 'is_affiliate_partner', true);
        update_user_meta($user_id, 'affiliate_status', 'approved');
        update_user_meta($user_id, 'registration_date', current_time('mysql'));
        update_user_meta($user_id, 'affiliate_id', $affiliate_code);
        update_user_meta($user_id, 'referral_code', $affiliate_code);

        // Handle referrer if provided
        if (!empty($referral_id)) {
            $this->process_referral($user_id, $referral_id);
        }

        // Get the created user
        $user = get_user_by('id', $user_id);

        // Generate tokens
        $token = $this->jwt_auth->generate_token($user);
        $refresh_token = $this->jwt_auth->generate_refresh_token($user);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Account created successfully!',
            'data'    => [
                'token'         => $token,
                'refresh_token' => $refresh_token,
                'expires_in'    => $this->jwt_auth->get_token_expiry(),
                'user'          => [
                    'id'           => $user->ID,
                    'name'         => $user->display_name,
                    'email'        => $user->user_email,
                    'username'     => $user->user_login,
                    'phone'        => $phone,
                    'zilla'        => $zilla,
                    'affiliate_id' => $affiliate_code,
                ],
            ],
        ], 201);
    }

    /**
     * Validate registration data
     */
    private function validate_registration_data($name, $email, $phone, $zilla, $password) {
        $errors = [];

        if (empty($name)) {
            $errors[] = 'Name is required.';
        }

        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!is_email($email)) {
            $errors[] = 'Invalid email format.';
        } elseif (email_exists($email)) {
            $errors[] = 'Email already exists.';
        }

        if (empty($phone)) {
            $errors[] = 'Phone number is required.';
        }

        if (empty($zilla)) {
            $errors[] = 'Zilla is required.';
        }

        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        return $errors;
    }

    /**
     * Generate unique username from email
     */
    private function generate_unique_username($email) {
        $username = sanitize_user(strtok($email, '@'));
        $original_username = $username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Generate unique affiliate code for user
     */
    private function generate_affiliate_code($user_id) {
        return 'REF' . $user_id . '_' . wp_generate_password(6, false, false);
    }

    /**
     * Process referral relationship
     */
    private function process_referral($user_id, $referral_code) {
        // Find the referrer by their referral code
        $referrer = $this->get_user_by_referral_code($referral_code);

        if ($referrer && $referrer != $user_id) {
            // Store referral relationship
            update_user_meta($user_id, 'referred_by', $referrer);
            update_user_meta($user_id, 'referral_code_used', $referral_code);
            update_user_meta($user_id, 'registration_source', 'referral');

            // Fire action hook for extensibility (ReferralManager will handle bonus)
            do_action('affiliate_bloom_user_referred', $referrer, $user_id, $referral_code);
        }
    }

    /**
     * Get user by referral code
     */
    private function get_user_by_referral_code($referral_code) {
        $users = get_users([
            'meta_key'   => 'referral_code',
            'meta_value' => $referral_code,
            'number'     => 1,
            'fields'     => 'ID',
        ]);

        return !empty($users) ? $users[0] : false;
    }
}