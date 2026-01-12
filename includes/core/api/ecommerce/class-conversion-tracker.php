<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversionTracker {

    private $namespace = 'affiliate-bloom/v1';
    private $route = 'conversion';

    public static function init() {
        $self = new self();
        return $self;
    }

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Simple conversion endpoint
        register_rest_route( $this->namespace, '/' . $this->route, array(
            'methods' => 'POST',
            'callback' => array( $this, 'track_conversion' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'affiliate_code' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'The affiliate reference code'
                ),
                'commission_amount' => array(
                    'required' => true,
                    'type' => 'number',
                    'sanitize_callback' => array( $this, 'sanitize_float' ),
                    'description' => 'Commission amount to add'
                ),
                'order_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'Order ID (optional)'
                )
            )
        ) );
    }

    /**
     * Track conversion - Simple version
     */
    public function track_conversion( $request ) {
        global $wpdb;

        $affiliate_code = $request->get_param( 'affiliate_code' );
        $commission_amount = $request->get_param( 'commission_amount' );
        $order_id = $request->get_param( 'order_id' );

        // Validate inputs
        if ( empty( $affiliate_code ) ) {
            return new \WP_Error(
                'missing_code',
                __( 'Affiliate code is required.', 'affiliate-bloom' ),
                array( 'status' => 400 )
            );
        }

        if ( $commission_amount <= 0 ) {
            return new \WP_Error(
                'invalid_amount',
                __( 'Commission amount must be greater than 0.', 'affiliate-bloom' ),
                array( 'status' => 400 )
            );
        }

        // Get link data by affiliate code
        $link_data = $this->get_link_by_code( $affiliate_code );

        if ( ! $link_data ) {
            return new \WP_Error(
                'link_not_found',
                __( 'Affiliate link not found.', 'affiliate-bloom' ),
                array( 'status' => 404 )
            );
        }

        // Check for duplicate conversion
        if ( $order_id ) {
            $table_name = $wpdb->prefix . 'affiliate_bloom_conversions';
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE order_id = %d LIMIT 1",
                $order_id
            ) );

            if ( $existing ) {
                return new \WP_Error(
                    'duplicate_conversion',
                    __( 'This order has already been tracked.', 'affiliate-bloom' ),
                    array( 'status' => 409 )
                );
            }
        }

        // Insert conversion record
        $table_name = $wpdb->prefix . 'affiliate_bloom_conversions';
        $result = $wpdb->insert(
            $table_name,
            array(
                'link_id' => $link_data['link_id'],
                'user_id' => $link_data['user_id'],
                'order_id' => $order_id ?: null,
                'commission_amount' => $commission_amount,
                'status' => 'pending',
                'conversion_date' => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%d', '%f', '%s', '%s' )
        );

        if ( $result === false ) {
            error_log( 'Failed to insert conversion: ' . $wpdb->last_error );

            return new \WP_Error(
                'conversion_failed',
                __( 'Failed to track conversion.', 'affiliate-bloom' ),
                array( 'status' => 500 )
            );
        }

        $conversion_id = $wpdb->insert_id;

        // Update user's pending balance (direct commission)
        $this->update_user_pending_balance( $link_data['user_id'], $commission_amount );

        // Process MLM multi-level commissions
        $mlm = MLMCommission::init();
        $mlm_commissions = $mlm->process_mlm_commissions(
            $conversion_id,
            $link_data['user_id'],
            $commission_amount,
            $order_id
        );

        return rest_ensure_response( array(
            'success' => true,
            'message' => __( 'Conversion tracked successfully!', 'affiliate-bloom' ),
            'data' => array(
                'conversion_id' => $conversion_id,
                'affiliate_code' => $affiliate_code,
                'user_id' => $link_data['user_id'],
                'commission_amount' => $commission_amount,
                'mlm_commissions' => $mlm_commissions
            )
        ) );
    }

    /**
     * Get link data by affiliate code
     */
    private function get_link_by_code( $affiliate_code ) {
        $args = array(
            'post_type' => 'affiliate_link',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'link_code',
                    'value' => $affiliate_code,
                    'compare' => '='
                )
            )
        );

        $query = new \WP_Query( $args );

        if ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $link_data = array(
                'link_id' => $post_id,
                'user_id' => get_post_field( 'post_author', $post_id )
            );

            wp_reset_postdata();
            return $link_data;
        }

        return false;
    }

    /**
     * Update user's pending balance
     */
    private function update_user_pending_balance( $user_id, $amount ) {
        $current_balance = get_user_meta( $user_id, 'affiliate_pending_balance', true ) ?: 0;
        $new_balance = $current_balance + $amount;
        update_user_meta( $user_id, 'affiliate_pending_balance', $new_balance );
    }

    /**
     * Sanitize float value for REST API
     */
    public function sanitize_float( $value, $request, $param ) {
        return floatval( $value );
    }
}