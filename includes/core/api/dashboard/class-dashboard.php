<?php

namespace AffiliateBloom;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard REST API Class
 */
class Dashboard {

    private $jwt_auth;

    public function __construct() {
        $this->jwt_auth = new JWTAuth();
    }

    /**
     * Initialize Dashboard endpoints
     */
    public static function init() {
        $self = new self();

        add_action('rest_api_init', function() use ($self) {
            // Dashboard overview stats
            register_rest_route('affiliate-bloom/v1', '/dashboard/stats', [
                'methods'             => 'GET',
                'callback'            => [$self, 'get_stats'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
            ]);

            // Get affiliate links
            register_rest_route('affiliate-bloom/v1', '/dashboard/links', [
                'methods'             => 'GET',
                'callback'            => [$self, 'get_links'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
                'args'                => [
                    'page' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 10,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]);

            // Create affiliate link
            register_rest_route('affiliate-bloom/v1', '/dashboard/links', [
                'methods'             => 'POST',
                'callback'            => [$self, 'create_link'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
                'args'                => [
                    'product_url' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_url',
                    ],
                    'product_id' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'link_name' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);

            // Update affiliate link
            register_rest_route('affiliate-bloom/v1', '/dashboard/links/(?P<id>\d+)', [
                'methods'             => 'PUT',
                'callback'            => [$self, 'update_link'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'link_name' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);

            // Delete affiliate link
            register_rest_route('affiliate-bloom/v1', '/dashboard/links/(?P<id>\d+)', [
                'methods'             => 'DELETE',
                'callback'            => [$self, 'delete_link'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]);

            // Get referral stats
            register_rest_route('affiliate-bloom/v1', '/dashboard/referrals', [
                'methods'             => 'GET',
                'callback'            => [$self, 'get_referral_stats'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
            ]);

            // Get referral history
            register_rest_route('affiliate-bloom/v1', '/dashboard/referrals/history', [
                'methods'             => 'GET',
                'callback'            => [$self, 'get_referral_history'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
            ]);

            // Get earnings/transactions history
            register_rest_route('affiliate-bloom/v1', '/dashboard/earnings', [
                'methods'             => 'GET',
                'callback'            => [$self, 'get_earnings'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
                'args'                => [
                    'page' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 10,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]);
        });
    }

    /**
     * Get dashboard overview stats
     */
    public function get_stats(\WP_REST_Request $request) {
        $user = $this->jwt_auth->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user_id = $user->ID;

        $stats = [
            'total_clicks'      => $this->get_user_total_clicks($user_id),
            'total_conversions' => $this->get_user_total_conversions($user_id),
            'total_earnings'    => $this->get_user_total_earnings($user_id),
            'pending_earnings'  => $this->get_user_pending_earnings($user_id),
            'current_balance'   => $this->get_user_current_balance($user_id),
            'total_links'       => $this->get_user_total_links($user_id),
            'total_referrals'   => $this->get_user_total_referrals($user_id),
            'conversion_rate'   => 0,
        ];

        if ($stats['total_clicks'] > 0) {
            $stats['conversion_rate'] = round(($stats['total_conversions'] / $stats['total_clicks']) * 100, 2);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $stats,
        ], 200);
    }

    /**
     * Get user's affiliate links
     */
    public function get_links(\WP_REST_Request $request) {
        $user = $this->jwt_auth->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user_id = $user->ID;
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        $offset = ($page - 1) * $per_page;

        $args = [
            'post_type'      => 'affiliate_link',
            'author'         => $user_id,
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query($args);
        $links = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $clicks = $this->get_link_clicks($post_id);
                $conversions = $this->get_link_conversions($post_id);
                $conversion_rate = $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0;

                $links[] = [
                    'id'              => $post_id,
                    'name'            => get_the_title(),
                    'product_id'      => get_post_meta($post_id, 'product_id', true),
                    'product_url'     => get_post_meta($post_id, 'product_url', true),
                    'affiliate_url'   => get_post_meta($post_id, 'affiliate_url', true),
                    'link_code'       => get_post_meta($post_id, 'link_code', true),
                    'clicks'          => $clicks,
                    'conversions'     => $conversions,
                    'conversion_rate' => $conversion_rate,
                    'created_date'    => get_post_meta($post_id, 'created_date', true),
                ];
            }
        }

        wp_reset_postdata();

        $total_query = new \WP_Query([
            'post_type'      => 'affiliate_link',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        $total_links = $total_query->found_posts;
        $total_pages = ceil($total_links / $per_page);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'links'        => $links,
                'current_page' => $page,
                'total_pages'  => $total_pages,
                'total_links'  => $total_links,
            ],
        ], 200);
    }

    /**
     * Create affiliate link
     */
    public function create_link(\WP_REST_Request $request) {
        $user = $this->jwt_auth->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user_id = $user->ID;

        // Check if user is approved affiliate
        if (get_user_meta($user_id, 'affiliate_status', true) !== 'approved') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User is not an approved affiliate',
            ], 403);
        }

        $product_url = $request->get_param('product_url');
        $product_id = $request->get_param('product_id');
        $link_name = $request->get_param('link_name');

        if (empty($product_url) && empty($product_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Product URL or ID is required',
            ], 400);
        }

        // If we have URL, try to get product ID
        if (!empty($product_url) && empty($product_id)) {
            $product_id = url_to_postid($product_url);
        }

        // Generate unique link code
        $link_code = $this->generate_unique_link_code($user_id);
        $affiliate_url = home_url('/go/' . $link_code);

        $post_data = [
            'post_title'  => $link_name ?: ('Affiliate Link - ' . ($product_url ?: 'Product ID: ' . $product_id)),
            'post_type'   => 'affiliate_link',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input'  => [
                'product_id'    => $product_id,
                'product_url'   => $product_url,
                'affiliate_url' => $affiliate_url,
                'link_code'     => $link_code,
                'created_date'  => current_time('mysql'),
            ],
        ];

        $link_id = wp_insert_post($post_data);

        if ($link_id && !is_wp_error($link_id)) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Affiliate link created successfully!',
                'data'    => [
                    'link_id'       => $link_id,
                    'affiliate_url' => $affiliate_url,
                    'link_code'     => $link_code,
                ],
            ], 201);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to create affiliate link',
        ], 500);
    }

    /**
     * Update affiliate link
     */
    public function update_link(\WP_REST_Request $request) {
        $user = $this->jwt_auth->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user_id = $user->ID;
        $link_id = $request->get_param('id');
        $link_name = $request->get_param('link_name');

        $post = get_post($link_id);

        if (!$post || $post->post_type !== 'affiliate_link' || $post->post_author != $user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Link not found or access denied',
            ], 404);
        }

        $updated = wp_update_post([
            'ID'         => $link_id,
            'post_title' => $link_name ?: $post->post_title,
        ]);

        if ($updated) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Link updated successfully',
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to update link',
        ], 500);
    }

    /**
     * Delete affiliate link
     */
    public function delete_link(\WP_REST_Request $request) {
        $user = $this->jwt_auth->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user_id = $user->ID;
        $link_id = $request->get_param('id');

        $post = get_post($link_id);

        if (!$post || $post->post_type !== 'affiliate_link' || $post->post_author != $user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Link not found or access denied',
            ], 404);
        }

        $deleted = wp_delete_post($link_id, true);

        if ($deleted) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Link deleted successfully',
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to delete link',
        ], 500);
    }

    /**
     * Get referral stats
     */
    public function get_referral_stats(\WP_REST_Request $request) {
        $user = $this->jwt_auth->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user_id = $user->ID;

        $referral_code = get_user_meta($user_id, 'referral_code', true);
        if (empty($referral_code)) {
            $referral_code = 'REF' . $user_id . '_' . wp_generate_password(6, false, false);
            update_user_meta($user_id, 'referral_code', $referral_code);
        }

        $referrals = get_user_meta($user_id, 'referral_history', true);
        $referrals = is_array($referrals) ? $referrals : [];

        $balance = get_user_meta($user_id, 'affiliate_balance', true);
        $balance = $balance ? floatval($balance) : 0;

        $referral_bonus = get_option('affiliate_bloom_referral_bonus', 10.00);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'referral_code'      => $referral_code,
                'referral_url'       => add_query_arg('ref', $referral_code, home_url()),
                'total_referrals'    => count($referrals),
                'total_earnings'     => $balance,
                'bonus_per_referral' => floatval($referral_bonus),
            ],
        ], 200);
    }

    /**
     * Get referral history
     */
    public function get_referral_history(\WP_REST_Request $request) {
        $user = $this->jwt_auth->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user_id = $user->ID;

        $referrals = get_user_meta($user_id, 'referral_history', true);
        $referrals = is_array($referrals) ? $referrals : [];

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'referrals' => $referrals,
            ],
        ], 200);
    }

    /**
     * Get earnings/transactions history
     */
    public function get_earnings(\WP_REST_Request $request) {
        $user = $this->jwt_auth->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user_id = $user->ID;
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;

        global $wpdb;

        $offset = ($page - 1) * $per_page;

        // Get conversions from database
        $conversions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliate_bloom_conversions
             WHERE user_id = %d
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $user_id,
            $per_page,
            $offset
        ));

        // Get total count
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        ));

        $total_pages = ceil($total / $per_page);

        // Get transaction history from user meta (referral bonuses, etc.)
        $transactions = get_user_meta($user_id, 'affiliate_transaction_history', true);
        $transactions = is_array($transactions) ? $transactions : [];

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'conversions'   => $conversions,
                'transactions'  => $transactions,
                'current_page'  => $page,
                'total_pages'   => $total_pages,
                'total_records' => intval($total),
            ],
        ], 200);
    }

    // ================================
    // HELPER METHODS
    // ================================

    /**
     * Get total clicks for user
     */
    private function get_user_total_clicks($user_id) {
        global $wpdb;

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks WHERE user_id = %d",
            $user_id
        )));
    }

    /**
     * Get total conversions for user
     */
    private function get_user_total_conversions($user_id) {
        global $wpdb;

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        )));
    }

    /**
     * Get total earnings for user
     */
    private function get_user_total_earnings($user_id) {
        global $wpdb;

        $earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        ));

        return floatval($earnings ?: 0);
    }

    /**
     * Get pending earnings for user
     */
    private function get_user_pending_earnings($user_id) {
        global $wpdb;

        $earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));

        return floatval($earnings ?: 0);
    }

    /**
     * Get current balance for user
     */
    private function get_user_current_balance($user_id) {
        return floatval(get_user_meta($user_id, 'affiliate_balance', true) ?: 0);
    }

    /**
     * Get total links for user
     */
    private function get_user_total_links($user_id) {
        $query = new \WP_Query([
            'post_type'      => 'affiliate_link',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        return $query->found_posts;
    }

    /**
     * Get total referrals for user
     */
    private function get_user_total_referrals($user_id) {
        $referrals = get_user_meta($user_id, 'referral_history', true);
        return is_array($referrals) ? count($referrals) : 0;
    }

    /**
     * Get clicks for a specific link
     */
    private function get_link_clicks($link_id) {
        global $wpdb;

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks WHERE link_id = %d",
            $link_id
        )));
    }

    /**
     * Get conversions for a specific link
     */
    private function get_link_conversions($link_id) {
        global $wpdb;

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE link_id = %d",
            $link_id
        )));
    }

    /**
     * Generate unique link code
     */
    private function generate_unique_link_code($user_id) {
        $user_code = $this->get_or_create_affiliate_code($user_id);
        $unique_suffix = wp_generate_password(6, false, false);
        return $user_code . '_' . $unique_suffix;
    }

    /**
     * Get or create affiliate code for user
     */
    private function get_or_create_affiliate_code($user_id) {
        $code = get_user_meta($user_id, 'affiliate_code', true);

        if (empty($code)) {
            $code = 'AFF' . $user_id . '_' . wp_generate_password(6, false);
            update_user_meta($user_id, 'affiliate_code', $code);
        }

        return $code;
    }
}