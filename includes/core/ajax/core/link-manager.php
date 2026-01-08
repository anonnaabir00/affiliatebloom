<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LinkManager {

    public static function init() {
        $instance = new self();
        return $instance;
    }

    public function __construct() {
        $this->init_ajax_hooks();
        $this->init_url_rewrite();
        add_action( 'template_redirect', array( $this, 'handle_affiliate_redirect' ) );

        // Add activation hook to flush rewrite rules
        register_activation_hook( __FILE__, array( $this, 'flush_rewrite_rules' ) );
    }

    /**
     * Initialize AJAX hooks
     */
    private function init_ajax_hooks() {
        // For logged-in users
        add_action( 'wp_ajax_affiliate_bloom_generate_link', array( $this, 'generate_affiliate_link' ) );
        add_action( 'wp_ajax_affiliate_bloom_get_user_links', array( $this, 'get_user_affiliate_links' ) );
        add_action( 'wp_ajax_affiliate_bloom_delete_link', array( $this, 'delete_affiliate_link' ) );
        add_action( 'wp_ajax_affiliate_bloom_get_user_stats', array( $this, 'get_affiliate_stats' ) );
        add_action( 'wp_ajax_affiliate_bloom_update_link', array( $this, 'update_affiliate_link' ) );
    }

    /**
     * Initialize URL rewrite rules
     */
    private function init_url_rewrite() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        // Flush rules if needed
        add_action( 'wp_loaded', array( $this, 'maybe_flush_rewrite_rules' ) );
    }

    /**
     * Add rewrite rules for affiliate URLs
     */
    public function add_rewrite_rules() {
        // Add the rewrite rule
        add_rewrite_rule( '^go/([^/]+)/?$', 'index.php?affiliate_redirect=$matches[1]', 'top' );

        // Register query variable
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
    }

    /**
     * Add custom query variables
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'affiliate_redirect';
        return $vars;
    }

    /**
     * Maybe flush rewrite rules if needed
     */
    public function maybe_flush_rewrite_rules() {
        // Check if we need to flush rules (you can set this option when plugin activates)
        if ( get_option( 'affiliate_bloom_flush_rewrite_rules' ) ) {
            flush_rewrite_rules();
            delete_option( 'affiliate_bloom_flush_rewrite_rules' );
        }
    }

    /**
     * Flush rewrite rules (call this on plugin activation)
     */
    public function flush_rewrite_rules() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Handle affiliate redirect
     */
     /**
      * Handle affiliate redirect - UPDATED VERSION
      * Replace the handle_affiliate_redirect() method in your LinkManager class
      */
     public function handle_affiliate_redirect() {
         global $wp_query;

         // Check if query var exists
         if ( isset( $wp_query->query_vars['affiliate_redirect'] ) ) {
             $link_code = sanitize_text_field( $wp_query->query_vars['affiliate_redirect'] );

             // Debug log
             error_log( 'Affiliate redirect triggered for code: ' . $link_code );

             // Find the affiliate link by code
             $link_data = $this->get_link_by_affiliate_code( $link_code );

             if ( $link_data ) {
                 // Debug log
                 error_log( 'Link found, redirecting to: ' . $link_data['product_url'] );

                 // Track the click
                 $this->track_affiliate_click( $link_data['link_id'], $link_data['user_id'] );

                 // Build redirect URL with affiliate code as parameter
                 $redirect_url = $link_data['product_url'];

                 // Add ref parameter to URL
                 $separator = ( parse_url( $redirect_url, PHP_URL_QUERY ) == NULL ) ? '?' : '&';
                 $redirect_url .= $separator . 'ref=' . urlencode( $link_code );

                 // Redirect to the URL with ref parameter
                 wp_redirect( $redirect_url, 302 );
                 exit;
             } else {
                 // Debug log
                 error_log( 'Link not found for code: ' . $link_code );

                 // Redirect to home if link not found
                 wp_redirect( home_url(), 302 );
                 exit;
             }
         }
     }

    /**
     * Generate affiliate link
     */
    public function generate_affiliate_link() {
        // Check if user is logged in first
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'User not logged in' );
        }

        // Verify nonce - make sure the nonce key matches what's sent from frontend
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'affiliate_bloom_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $user_id = get_current_user_id();

        // Check if user is approved affiliate
        if ( get_user_meta( $user_id, 'affiliate_status', true ) !== 'approved' ) {
            wp_send_json_error( 'User is not an approved affiliate' );
        }

        // Get product URL or ID
        $product_url = sanitize_url( $_POST['product_url'] ?? '' );
        $product_id = intval( $_POST['product_id'] ?? 0 );
        $link_name = sanitize_text_field( $_POST['link_name'] ?? '' );

        if ( empty( $product_url ) && empty( $product_id ) ) {
            wp_send_json_error( 'Product URL or ID is required' );
        }

        // If we have URL, try to get product ID
        if ( ! empty( $product_url ) && empty( $product_id ) ) {
            $product_id = url_to_postid( $product_url );
        }

        // Generate unique affiliate link code for this specific link
        $link_code = $this->generate_unique_link_code( $user_id );
        $affiliate_url = $this->generate_affiliate_url( $product_url ?: get_permalink( $product_id ), $link_code );

        // Save the link directly using wp_insert_post (same as original)
        $post_data = array(
            'post_title' => $link_name ?: ( 'Affiliate Link - ' . ( $product_url ?: 'Product ID: ' . $product_id ) ),
            'post_type' => 'affiliate_link',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input' => array(
                'product_id' => $product_id,
                'product_url' => $product_url,
                'affiliate_url' => $affiliate_url,
                'link_code' => $link_code, // Store the unique link code
                'created_date' => current_time( 'mysql' ),
            )
        );

        $link_id = wp_insert_post( $post_data );

        if ( $link_id && ! is_wp_error( $link_id ) ) {
            wp_send_json_success( array(
                'affiliate_url' => $affiliate_url,
                'link_id' => $link_id,
                'message' => 'Affiliate link generated successfully!'
            ) );
        } else {
            wp_send_json_error( 'Failed to save affiliate link' );
        }
    }

    /**
     * Get user's affiliate links
     */
    public function get_user_affiliate_links() {
        // Check if user is logged in first
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'User not logged in' );
        }

        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'affiliate_bloom_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $user_id = get_current_user_id();
        $page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
        $per_page = 10;
        $offset = ( $page - 1 ) * $per_page;

        // Get links using WP_Query directly (same as original)
        $args = array(
            'post_type' => 'affiliate_link',
            'author' => $user_id,
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $query = new \WP_Query( $args );
        $links = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();

                // Get clicks and conversions
                $clicks = $this->get_link_clicks( $post_id );
                $conversions = $this->get_link_conversions( $post_id );
                $conversion_rate = $clicks > 0 ? round( ( $conversions / $clicks ) * 100, 2 ) : 0;

                $links[] = array(
                    'id' => $post_id,
                    'name' => get_the_title(),
                    'product_id' => get_post_meta( $post_id, 'product_id', true ),
                    'product_url' => get_post_meta( $post_id, 'product_url', true ),
                    'affiliate_url' => get_post_meta( $post_id, 'affiliate_url', true ),
                    'link_code' => get_post_meta( $post_id, 'link_code', true ),
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'conversion_rate' => $conversion_rate,
                    'created_date' => get_post_meta( $post_id, 'created_date', true )
                );
            }
        }

        wp_reset_postdata();

        // Get total count
        $total_args = array(
            'post_type' => 'affiliate_link',
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        );

        $total_query = new \WP_Query( $total_args );
        $total_links = $total_query->found_posts;
        $total_pages = ceil( $total_links / $per_page );

        wp_send_json_success( array(
            'links' => $links,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_links' => $total_links
        ) );
    }

    /**
     * Delete affiliate link
     */
    public function delete_affiliate_link() {
        // Check if user is logged in first
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'User not logged in' );
        }

        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'affiliate_bloom_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $user_id = get_current_user_id();
        $link_id = intval( $_POST['link_id'] );

        // Verify the post exists and belongs to the user
        $post = get_post( $link_id );

        if ( ! $post || $post->post_type !== 'affiliate_link' || $post->post_author != $user_id ) {
            wp_send_json_error( 'Link not found or access denied' );
        }

        // Delete using wp_delete_post
        $deleted = wp_delete_post( $link_id, true );

        if ( $deleted ) {
            wp_send_json_success( 'Link deleted successfully' );
        } else {
            wp_send_json_error( 'Failed to delete link' );
        }
    }

    /**
     * Get user statistics
     */
    public function get_affiliate_stats() {
        // Check if user is logged in first
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'User not logged in' );
        }

        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'affiliate_bloom_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $user_id = get_current_user_id();

        // Get overall stats
        $stats = array(
            'total_clicks' => $this->get_user_total_clicks( $user_id ),
            'total_conversions' => $this->get_user_total_conversions( $user_id ),
            'total_earnings' => $this->get_user_total_earnings( $user_id ),
            'pending_earnings' => $this->get_user_pending_earnings( $user_id ),
            'current_balance' => $this->get_user_current_balance( $user_id ),
            'conversion_rate' => 0
        );

        // Calculate conversion rate
        if ( $stats['total_clicks'] > 0 ) {
            $stats['conversion_rate'] = round( ( $stats['total_conversions'] / $stats['total_clicks'] ) * 100, 2 );
        }

        wp_send_json_success( array(
            'stats' => $stats
        ) );
    }

    /**
     * Update affiliate link
     */
    public function update_affiliate_link() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'affiliate_bloom_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Please log in.' ) );
        }

        $user_id = get_current_user_id();
        $link_id = intval( $_POST['link_id'] ?? 0 );
        $link_name = sanitize_text_field( $_POST['link_name'] ?? '' );

        if ( ! $link_id ) {
            wp_send_json_error( array( 'message' => 'Invalid link ID.' ) );
        }

        // Verify ownership
        $post = get_post( $link_id );
        if ( ! $post || $post->post_type !== 'affiliate_link' || $post->post_author != $user_id ) {
            wp_send_json_error( array( 'message' => 'Unauthorized access.' ) );
        }

        // Update post
        $updated = wp_update_post( array(
            'ID' => $link_id,
            'post_title' => $link_name ?: $post->post_title
        ) );

        if ( $updated ) {
            wp_send_json_success( array( 'message' => 'Link updated successfully.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to update link.' ) );
        }
    }

    // ================================
    // AFFILIATE LINK & REDIRECT METHODS
    // ================================

    /**
     * Generate affiliate URL - creates a redirect URL instead of appending parameters
     */
    private function generate_affiliate_url( $original_url, $link_code ) {
        // Create a redirect URL like: yoursite.com/go/ABC123_xyz789_linkID
        return home_url( '/go/' . $link_code );
    }

    /**
     * Generate unique link code for each affiliate link
     */
    private function generate_unique_link_code( $user_id ) {
        $user_code = $this->get_or_create_affiliate_code( $user_id );
        $unique_suffix = wp_generate_password( 6, false, false );
        return $user_code . '_' . $unique_suffix;
    }

    /**
     * Get affiliate link data by affiliate code
     */
    private function get_link_by_affiliate_code( $link_code ) {
        // Find the affiliate link by the unique link code
        $args = array(
            'post_type' => 'affiliate_link',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'link_code',
                    'value' => $link_code,
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
                'user_id' => get_post_field( 'post_author', $post_id ),
                'product_url' => get_post_meta( $post_id, 'product_url', true ),
                'link_code' => $link_code
            );

            wp_reset_postdata();
            return $link_data;
        }

        return false;
    }

    /**
     * Track affiliate click
     */
    private function track_affiliate_click( $link_id, $user_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affiliate_bloom_clicks';

        // Get visitor information
        $ip_address = $this->get_visitor_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';

        // Insert click record - FIXED: Using correct column names
        $result = $wpdb->insert(
            $table_name,
            array(
                'link_id' => $link_id,
                'user_id' => $user_id,
                'visitor_ip' => $ip_address,  // Changed from 'ip_address' to 'visitor_ip'
                'user_agent' => $user_agent,
                'referrer' => $referrer,
                'click_date' => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        // Log error if insert failed
        if ( $result === false ) {
            error_log( 'Failed to insert affiliate click: ' . $wpdb->last_error );
        }
    }

    /**
     * Get visitor IP address
     */
    private function get_visitor_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        } else {
            return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
        }
    }

    // ================================
    // HELPER METHODS
    // ================================

    /**
     * Get or create affiliate code for user
     */
    private function get_or_create_affiliate_code( $user_id ) {
        $code = get_user_meta( $user_id, 'affiliate_code', true );

        if ( empty( $code ) ) {
            $code = 'AFF' . $user_id . '_' . wp_generate_password( 6, false );
            update_user_meta( $user_id, 'affiliate_code', $code );
        }

        return $code;
    }

    /**
     * Get clicks for a specific link
     */
    private function get_link_clicks( $link_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks WHERE link_id = %d",
            $link_id
        ) ) ?: 0;
    }

    /**
     * Get conversions for a specific link
     */
    private function get_link_conversions( $link_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE link_id = %d",
            $link_id
        ) ) ?: 0;
    }

    /**
     * Get total clicks for user
     */
    private function get_user_total_clicks( $user_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks WHERE user_id = %d",
            $user_id
        ) ) ?: 0;
    }

    /**
     * Get total conversions for user
     */
    private function get_user_total_conversions( $user_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        ) ) ?: 0;
    }

    /**
     * Get total earnings for user
     */
    private function get_user_total_earnings( $user_id ) {
        global $wpdb;

        $earnings = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        ) );

        return $earnings ?: 0;
    }

    /**
     * Get pending earnings for user
     */
    private function get_user_pending_earnings( $user_id ) {
        global $wpdb;

        $earnings = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d AND status = 'pending'",
            $user_id
        ) );

        return $earnings ?: 0;
    }

    /**
     * Get current balance for user
     */
    private function get_user_current_balance( $user_id ) {
        return get_user_meta( $user_id, 'affiliate_balance', true ) ?: 0;
    }

    /**
     * Get affiliate link by ID (helper method)
     */
    public function get_affiliate_link( $link_id, $user_id ) {
        $post = get_post( $link_id );

        if ( ! $post || $post->post_type !== 'affiliate_link' || $post->post_author != $user_id ) {
            return false;
        }

        $clicks = $this->get_link_clicks( $link_id );
        $conversions = $this->get_link_conversions( $link_id );
        $conversion_rate = $clicks > 0 ? round( ( $conversions / $clicks ) * 100, 2 ) : 0;

        return array(
            'id' => $link_id,
            'name' => $post->post_title,
            'product_id' => get_post_meta( $link_id, 'product_id', true ),
            'product_url' => get_post_meta( $link_id, 'product_url', true ),
            'affiliate_url' => get_post_meta( $link_id, 'affiliate_url', true ),
            'link_code' => get_post_meta( $link_id, 'link_code', true ),
            'clicks' => $clicks,
            'conversions' => $conversions,
            'conversion_rate' => $conversion_rate,
            'created_date' => get_post_meta( $link_id, 'created_date', true )
        );
    }
}