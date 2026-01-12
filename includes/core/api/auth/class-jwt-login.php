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
            ],
        ], 200);
    }
}