<?php

namespace AffiliateBloom;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Affiliate Links REST API Class
 */
class AffiliateLinksAPI {

    private $jwt_auth;

    public function __construct() {
        $this->jwt_auth = new JWTAuth();
    }

    /**
     * Initialize Affiliate Links endpoints
     */
    public static function init() {
        $self = new self();

        add_action('rest_api_init', function() use ($self) {
            // Get all user's affiliate links
            register_rest_route('affiliate-bloom/v1', '/links', [
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
                    'orderby' => [
                        'required'          => false,
                        'type'              => 'string',
                        'default'           => 'date',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'order' => [
                        'required'          => false,
                        'type'              => 'string',
                        'default'           => 'DESC',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);

            // Create new affiliate link
            register_rest_route('affiliate-bloom/v1', '/links', [
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

            // Get single affiliate link
            register_rest_route('affiliate-bloom/v1', '/links/(?P<id>\d+)', [
                'methods'             => 'GET',
                'callback'            => [$self, 'get_link'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]);

            // Update affiliate link
            register_rest_route('affiliate-bloom/v1', '/links/(?P<id>\d+)', [
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
                    'product_url' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_url',
                    ],
                ],
            ]);

            // Delete affiliate link
            register_rest_route('affiliate-bloom/v1', '/links/(?P<id>\d+)', [
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

            // Get link statistics
            register_rest_route('affiliate-bloom/v1', '/links/(?P<id>\d+)/stats', [
                'methods'             => 'GET',
                'callback'            => [$self, 'get_link_stats'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]);

            // Get user's overall affiliate stats
            register_rest_route('affiliate-bloom/v1', '/links/stats', [
                'methods'             => 'GET',
                'callback'            => [$self, 'get_user_stats'],
                'permission_callback' => [$self->jwt_auth, 'authenticate_request'],
            ]);
        });
    }

    /**
     * Get all user's affiliate links
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
        $orderby = $request->get_param('orderby') ?: 'date';
        $order = $request->get_param('order') ?: 'DESC';
        $offset = ($page - 1) * $per_page;

        $args = [
            'post_type'      => 'affiliate_link',
            'author'         => $user_id,
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'post_status'    => 'publish',
            'orderby'        => $orderby,
            'order'          => strtoupper($order),
        ];

        $query = new \WP_Query($args);
        $links = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $links[] = $this->format_link_data($post_id);
            }
        }

        wp_reset_postdata();

        // Get total count
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
                'current_page' => intval($page),
                'per_page'     => intval($per_page),
                'total_pages'  => intval($total_pages),
                'total_links'  => intval($total_links),
            ],
        ], 200);
    }

    /**
     * Get single affiliate link
     */
    public function get_link(\WP_REST_Request $request) {
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

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $this->format_link_data($link_id),
        ], 200);
    }

    /**
     * Create new affiliate link
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

        // If we have product ID but no URL, get the permalink
        if (!empty($product_id) && empty($product_url)) {
            $product_url = get_permalink($product_id);
        }

        // Generate unique link code
        $link_code = $this->generate_unique_link_code($user_id);
        $base_url = AffiliateHelper::get_shortlink_base_url();
        $affiliate_url = rtrim($base_url, '/') . '/go/' . $link_code;

        // Generate link name if not provided
        if (empty($link_name)) {
            if ($product_id) {
                $product = get_post($product_id);
                $link_name = $product ? 'Affiliate Link - ' . $product->post_title : 'Affiliate Link - ' . $product_url;
            } else {
                $link_name = 'Affiliate Link - ' . $product_url;
            }
        }

        $post_data = [
            'post_title'  => $link_name,
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
                'data'    => $this->format_link_data($link_id),
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
        $product_url = $request->get_param('product_url');

        $post = get_post($link_id);

        if (!$post || $post->post_type !== 'affiliate_link' || $post->post_author != $user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Link not found or access denied',
            ], 404);
        }

        $update_data = ['ID' => $link_id];

        if (!empty($link_name)) {
            $update_data['post_title'] = $link_name;
        }

        $updated = wp_update_post($update_data);

        if ($updated && !is_wp_error($updated)) {
            // Update product URL if provided
            if (!empty($product_url)) {
                update_post_meta($link_id, 'product_url', $product_url);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Link updated successfully',
                'data'    => $this->format_link_data($link_id),
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
     * Get link statistics
     */
    public function get_link_stats(\WP_REST_Request $request) {
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

        global $wpdb;

        $clicks = $this->get_link_clicks($link_id);
        $conversions = $this->get_link_conversions($link_id);
        $conversion_rate = $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0;

        // Get earnings for this link
        $earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE link_id = %d",
            $link_id
        ));

        // Get recent clicks (last 30 days)
        $recent_clicks = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(click_date) as date, COUNT(*) as count
             FROM {$wpdb->prefix}affiliate_bloom_clicks
             WHERE link_id = %d AND click_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(click_date)
             ORDER BY date ASC",
            $link_id
        ));

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'link_id'         => $link_id,
                'clicks'          => intval($clicks),
                'conversions'     => intval($conversions),
                'conversion_rate' => $conversion_rate,
                'earnings'        => floatval($earnings ?: 0),
                'recent_clicks'   => $recent_clicks,
            ],
        ], 200);
    }

    /**
     * Get user's overall affiliate stats
     */
    public function get_user_stats(\WP_REST_Request $request) {
        $user = $this->jwt_auth->get_current_user_from_token($request);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user_id = $user->ID;

        global $wpdb;

        $total_clicks = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks WHERE user_id = %d",
            $user_id
        )));

        $total_conversions = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        )));

        $total_earnings = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        )) ?: 0);

        $pending_earnings = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d AND status = 'pending'",
            $user_id
        )) ?: 0);

        $current_balance = floatval(get_user_meta($user_id, 'affiliate_balance', true) ?: 0);

        // Get total links count
        $total_links = intval((new \WP_Query([
            'post_type'      => 'affiliate_link',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]))->found_posts);

        $conversion_rate = $total_clicks > 0 ? round(($total_conversions / $total_clicks) * 100, 2) : 0;

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'total_clicks'      => $total_clicks,
                'total_conversions' => $total_conversions,
                'total_earnings'    => $total_earnings,
                'pending_earnings'  => $pending_earnings,
                'current_balance'   => $current_balance,
                'total_links'       => $total_links,
                'conversion_rate'   => $conversion_rate,
            ],
        ], 200);
    }

    // ================================
    // HELPER METHODS
    // ================================

    /**
     * Format link data for response
     */
    private function format_link_data($link_id) {
        $post = get_post($link_id);
        $clicks = $this->get_link_clicks($link_id);
        $conversions = $this->get_link_conversions($link_id);
        $conversion_rate = $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0;

        return [
            'id'              => intval($link_id),
            'name'            => $post->post_title,
            'product_id'      => intval(get_post_meta($link_id, 'product_id', true)),
            'product_url'     => get_post_meta($link_id, 'product_url', true),
            'affiliate_url'   => get_post_meta($link_id, 'affiliate_url', true),
            'link_code'       => get_post_meta($link_id, 'link_code', true),
            'clicks'          => intval($clicks),
            'conversions'     => intval($conversions),
            'conversion_rate' => $conversion_rate,
            'created_date'    => get_post_meta($link_id, 'created_date', true),
        ];
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
