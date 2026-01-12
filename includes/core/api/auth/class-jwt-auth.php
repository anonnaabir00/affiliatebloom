<?php

namespace AffiliateBloom;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-jwt-login.php';
require_once __DIR__ . '/class-jwt-register.php';

/**
 * JWT Authentication Class for Affiliate Bloom
 */
class JWTAuth {

    private $secret_key;
    private $algorithm = 'HS256';
    private $token_expiry = 604800; // 7 days
    private $refresh_expiry = 2592000; // 30 days

    private $login_handler;
    private $register_handler;

    public function __construct() {
        $this->secret_key = $this->get_secret_key();
        $this->login_handler = new JWTLogin($this);
        $this->register_handler = new JWTRegister($this);
    }

    /**
     * Get or generate secret key
     */
    private function get_secret_key() {
        if (defined('AFFILIATE_BLOOM_JWT_SECRET')) {
            return AFFILIATE_BLOOM_JWT_SECRET;
        }

        $saved_key = get_option('affiliate_bloom_jwt_secret');

        if (!$saved_key) {
            $saved_key = wp_generate_password(64, true, true);
            update_option('affiliate_bloom_jwt_secret', $saved_key);
        }

        return $saved_key;
    }

    /**
     * Get token expiry time
     */
    public function get_token_expiry() {
        return $this->token_expiry;
    }

    /**
     * Initialize JWT endpoints
     */
    public static function init() {
        $self = new self();

        add_action('rest_api_init', function() use ($self) {
            // Login endpoint
            register_rest_route('affiliate-bloom/v1', '/auth/login', $self->login_handler->get_route_config());

            // Register endpoint
            register_rest_route('affiliate-bloom/v1', '/auth/register', $self->register_handler->get_route_config());

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

        // Get or create referral code
        $referral_code = get_user_meta($user->ID, 'referral_code', true);
        if (empty($referral_code)) {
            $referral_code = 'REF' . $user->ID . '_' . wp_generate_password(6, false, false);
            update_user_meta($user->ID, 'referral_code', $referral_code);
        }

        // Get affiliate code
        $affiliate_code = get_user_meta($user->ID, 'affiliate_code', true);
        if (empty($affiliate_code)) {
            $affiliate_code = 'AFF' . $user->ID . '_' . wp_generate_password(6, false, false);
            update_user_meta($user->ID, 'affiliate_code', $affiliate_code);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'id'              => $user->ID,
                'name'            => $user->display_name,
                'email'           => $user->user_email,
                'username'        => $user->user_login,
                'phone'           => get_user_meta($user->ID, 'phone_number', true),
                'zilla'           => get_user_meta($user->ID, 'zilla', true),
                'affiliate_id'    => get_user_meta($user->ID, 'affiliate_id', true),
                'affiliate_code'  => $affiliate_code,
                'referral_code'   => $referral_code,
                'referral_url'    => add_query_arg('ref', $referral_code, home_url()),
                'affiliate_status' => get_user_meta($user->ID, 'affiliate_status', true),
                'current_balance' => floatval(get_user_meta($user->ID, 'affiliate_balance', true) ?: 0),
                'role'            => isset($user->roles[0]) ? $user->roles[0] : '',
            ],
        ], 200);
    }

    /**
     * Logout endpoint
     */
    public function logout_endpoint(\WP_REST_Request $request) {
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

            if (isset($decoded->exp) && $decoded->exp < time()) {
                return new \WP_Error(
                    'token_expired',
                    'Token has expired',
                    ['status' => 401]
                );
            }

            if (!isset($decoded->iss) || $decoded->iss !== get_bloginfo('url')) {
                return new \WP_Error(
                    'invalid_issuer',
                    'Invalid token issuer',
                    ['status' => 401]
                );
            }

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

        $user = get_user_by('id', $decoded->data->user_id);

        if (!$user) {
            return new \WP_Error(
                'user_not_found',
                'User not found',
                ['status' => 404]
            );
        }

        return true;
    }

    /**
     * Get token from request headers
     */
    private function get_token_from_request(\WP_REST_Request $request) {
        $auth_header = $request->get_header('Authorization');

        if (!$auth_header) {
            $auth_header = $request->get_header('authorization');
        }

        if (!$auth_header) {
            return null;
        }

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