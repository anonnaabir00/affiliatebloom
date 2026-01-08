<?php

namespace AffiliateBloom;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * JWT Authentication Class for Affiliate Bloom
 */
class JWTAuth {

    private $secret_key;
    private $algorithm = 'HS256';
    private $token_expiry = 604800; // 7 days
    private $refresh_expiry = 2592000; // 30 days

    public function __construct() {
        // Get or generate secret key
        $this->secret_key = $this->get_secret_key();
    }

    /**
     * Get or generate secret key
     */
    private function get_secret_key() {
        // Check for defined constant first
        if (defined('AFFILIATE_BLOOM_JWT_SECRET')) {
            return AFFILIATE_BLOOM_JWT_SECRET;
        }

        // Check for saved option
        $saved_key = get_option('affiliate_bloom_jwt_secret');

        if (!$saved_key) {
            // Generate new key
            $saved_key = wp_generate_password(64, true, true);
            update_option('affiliate_bloom_jwt_secret', $saved_key);
        }

        return $saved_key;
    }

    /**
     * Initialize JWT endpoints
     */
    public static function init() {
        $self = new self();

        add_action('rest_api_init', function() use ($self) {
            // Login endpoint - generate token
            register_rest_route('affiliate-bloom/v1', '/auth/login', [
                'methods'             => 'POST',
                'callback'            => [$self, 'login'],
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
            ]);

            // Validate token endpoint
            register_rest_route('affiliate-bloom/v1', '/auth/validate', [
                'methods'             => 'POST',
                'callback'            => [$self, 'validate_token_endpoint'],
                'permission_callback' => '__return_true',
            ]);

            // Refresh token endpoint
            register_rest_route('affiliate-bloom/v1', '/auth/refresh', [
                'methods'             => 'POST',
                'callback'            => [$self, 'refresh_token_endpoint'],
                'permission_callback' => '__return_true',
            ]);

            // Get current user
            register_rest_route('affiliate-bloom/v1', '/auth/me', [
                'methods'             => 'GET',
                'callback'            => [$self, 'get_current_user_endpoint'],
                'permission_callback' => [$self, 'authenticate_request'],
            ]);

            // Logout endpoint
            register_rest_route('affiliate-bloom/v1', '/auth/logout', [
                'methods'             => 'POST',
                'callback'            => [$self, 'logout_endpoint'],
                'permission_callback' => [$self, 'authenticate_request'],
            ]);
        });
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
        $token = $this->generate_token($user);
        $refresh_token = $this->generate_refresh_token($user);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Login successful!',
            'data'    => [
                'token'         => $token,
                'refresh_token' => $refresh_token,
                'expires_in'    => $this->token_expiry,
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

    /**
     * Validate token endpoint
     */
    public function validate_token_endpoint(\WP_REST_Request $request) {
        $token = $this->get_token_from_request($request);

        if (!$token) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No token provided',
            ], 401);
        }

        $decoded = $this->validate_token($token);

        if (is_wp_error($decoded)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $decoded->get_error_message(),
            ], 401);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Token is valid',
            'data'    => [
                'user_id'    => $decoded->data->user_id,
                'expires_at' => $decoded->exp,
            ],
        ], 200);
    }

    /**
     * Refresh token endpoint
     */
    public function refresh_token_endpoint(\WP_REST_Request $request) {
        $refresh_token = $request->get_param('refresh_token');

        if (!$refresh_token) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Refresh token is required',
            ], 400);
        }

        $decoded = $this->validate_token($refresh_token, true);

        if (is_wp_error($decoded)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $decoded->get_error_message(),
            ], 401);
        }

        $user = get_user_by('id', $decoded->data->user_id);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Generate new tokens
        $new_token = $this->generate_token($user);
        $new_refresh_token = $this->generate_refresh_token($user);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data'    => [
                'token'         => $new_token,
                'refresh_token' => $new_refresh_token,
                'expires_in'    => $this->token_expiry,
            ],
        ], 200);
    }

    /**
     * Get current user endpoint
     */
    public function get_current_user_endpoint(\WP_REST_Request $request) {
        $user = $this->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'id'           => $user->ID,
                'name'         => $user->display_name,
                'email'        => $user->user_email,
                'username'     => $user->user_login,
                'phone'        => get_user_meta($user->ID, 'phone_number', true),
                'zilla'        => get_user_meta($user->ID, 'zilla', true),
                'affiliate_id' => get_user_meta($user->ID, 'affiliate_id', true),
                'role'         => isset($user->roles[0]) ? $user->roles[0] : '',
            ],
        ], 200);
    }

    /**
     * Logout endpoint
     */
    public function logout_endpoint(\WP_REST_Request $request) {
        // Optionally invalidate the token here by storing it in a blacklist
        // For now, just return success as JWT is stateless

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Generate JWT token
     */
    public function generate_token($user) {
        $issued_at = time();
        $expiration = $issued_at + $this->token_expiry;

        $payload = [
            'iss' => get_bloginfo('url'),
            'iat' => $issued_at,
            'exp' => $expiration,
            'data' => [
                'user_id' => $user->ID,
                'email'   => $user->user_email,
            ]
        ];

        return $this->encode($payload);
    }

    /**
     * Generate refresh token (longer expiry)
     */
    public function generate_refresh_token($user) {
        $issued_at = time();
        $expiration = $issued_at + $this->refresh_expiry;

        $payload = [
            'iss' => get_bloginfo('url'),
            'iat' => $issued_at,
            'exp' => $expiration,
            'type' => 'refresh',
            'data' => [
                'user_id' => $user->ID,
            ]
        ];

        return $this->encode($payload);
    }

    /**
     * Validate JWT token
     */
    public function validate_token($token, $is_refresh = false) {
        try {
            $decoded = $this->decode($token);

            // Check if token has expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return new \WP_Error(
                    'token_expired',
                    'Token has expired',
                    ['status' => 401]
                );
            }

            // Validate issuer
            if (!isset($decoded->iss) || $decoded->iss !== get_bloginfo('url')) {
                return new \WP_Error(
                    'invalid_issuer',
                    'Invalid token issuer',
                    ['status' => 401]
                );
            }

            // Check if it's a refresh token when expected
            if ($is_refresh && (!isset($decoded->type) || $decoded->type !== 'refresh')) {
                return new \WP_Error(
                    'invalid_token_type',
                    'Invalid token type',
                    ['status' => 401]
                );
            }

            return $decoded;

        } catch (\Exception $e) {
            return new \WP_Error(
                'invalid_token',
                'Invalid token: ' . $e->getMessage(),
                ['status' => 401]
            );
        }
    }

    /**
     * Check if request has valid JWT token
     */
    public function authenticate_request(\WP_REST_Request $request) {
        $token = $this->get_token_from_request($request);

        if (!$token) {
            return new \WP_Error(
                'no_token',
                'Authentication token is required',
                ['status' => 401]
            );
        }

        $decoded = $this->validate_token($token);

        if (is_wp_error($decoded)) {
            return $decoded;
        }

        // Check if user still exists
        $user = get_user_by('id', $decoded->data->user_id);

        if (!$user) {
            return new \WP_Error(
                'user_not_found',
                'User not found',
                ['status' => 404]
            );
        }

        // Authentication successful
        return true;
    }

    /**
     * Get token from request headers
     */
    private function get_token_from_request(\WP_REST_Request $request) {
        $auth_header = $request->get_header('Authorization');

        if (!$auth_header) {
            // Try lowercase
            $auth_header = $request->get_header('authorization');
        }

        if (!$auth_header) {
            return null;
        }

        // Extract token from "Bearer TOKEN" format
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Encode JWT token
     */
    private function encode($payload) {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];

        $header_encoded = $this->base64url_encode(json_encode($header));
        $payload_encoded = $this->base64url_encode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            $header_encoded . '.' . $payload_encoded,
            $this->secret_key,
            true
        );
        $signature_encoded = $this->base64url_encode($signature);

        return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
    }

    /**
     * Decode JWT token
     */
    private function decode($token) {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \Exception('Invalid token structure');
        }

        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;

        // Verify signature
        $signature = hash_hmac(
            'sha256',
            $header_encoded . '.' . $payload_encoded,
            $this->secret_key,
            true
        );
        $signature_check = $this->base64url_encode($signature);

        if (!hash_equals($signature_check, $signature_encoded)) {
            throw new \Exception('Invalid token signature');
        }

        // Decode payload
        $payload = json_decode($this->base64url_decode($payload_encoded));

        if (!$payload) {
            throw new \Exception('Invalid token payload');
        }

        return $payload;
    }

    /**
     * Base64 URL encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get current user from token
     */
    public function get_current_user_from_token(\WP_REST_Request $request) {
        $token = $this->get_token_from_request($request);

        if (!$token) {
            return null;
        }

        $decoded = $this->validate_token($token);

        if (is_wp_error($decoded)) {
            return null;
        }

        return get_user_by('id', $decoded->data->user_id);
    }
}