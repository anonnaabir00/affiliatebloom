<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database {

    public static function init() {
        $self = new self();
        add_action( 'init', array( $self, 'register_custom_post_types' ) );
        add_action( 'init', array( $self, 'maybe_create_tables' ) );
        return $self;
    }

    /**
     * Register custom post types for affiliate system
     */
    public function register_custom_post_types() {
        // Register Affiliate Links post type
        register_post_type( 'affiliate_link', array(
            'labels' => array(
                'name' => __( 'Affiliate Links', 'affiliate-bloom' ),
                'singular_name' => __( 'Affiliate Link', 'affiliate-bloom' ),
                'add_new' => __( 'Add New Link', 'affiliate-bloom' ),
                'add_new_item' => __( 'Add New Affiliate Link', 'affiliate-bloom' ),
                'edit_item' => __( 'Edit Affiliate Link', 'affiliate-bloom' ),
                'new_item' => __( 'New Affiliate Link', 'affiliate-bloom' ),
                'view_item' => __( 'View Affiliate Link', 'affiliate-bloom' ),
                'search_items' => __( 'Search Affiliate Links', 'affiliate-bloom' ),
                'not_found' => __( 'No affiliate links found', 'affiliate-bloom' ),
                'not_found_in_trash' => __( 'No affiliate links found in trash', 'affiliate-bloom' ),
            ),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array( 'title', 'author', 'custom-fields' ),
            'show_in_rest' => false,
        ) );
    }

    /**
     * Create necessary database tables (clicks and conversions still need tables)
     */
    public function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Clicks table
        $clicks_table = $wpdb->prefix . 'affiliate_bloom_clicks';
        $clicks_sql = "CREATE TABLE IF NOT EXISTS $clicks_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            link_id int(11),
            user_id int(11) NOT NULL,
            ref_code varchar(100),
            visitor_ip varchar(45),
            user_agent text,
            referrer text,
            click_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY link_id (link_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Conversions table
        $conversions_table = $wpdb->prefix . 'affiliate_bloom_conversions';
        $conversions_sql = "CREATE TABLE IF NOT EXISTS $conversions_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            link_id int(11),
            user_id int(11) NOT NULL,
            order_id int(11),
            commission_amount decimal(10,2) DEFAULT 0.00,
            status varchar(20) DEFAULT 'pending',
            conversion_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY link_id (link_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $clicks_sql );
        dbDelta( $conversions_sql );
    }

    /**
     * Save affiliate link as custom post
     */
    public function save_affiliate_link( $user_id, $product_id, $product_url, $affiliate_url ) {
        $post_data = array(
            'post_title' => 'Affiliate Link - ' . ( $product_url ?: 'Product ID: ' . $product_id ),
            'post_type' => 'affiliate_link',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input' => array(
                'product_id' => $product_id,
                'product_url' => $product_url,
                'affiliate_url' => $affiliate_url,
                'created_date' => current_time( 'mysql' ),
            )
        );

        $post_id = wp_insert_post( $post_data );

        return $post_id ? $post_id : false;
    }

    /**
     * Get user affiliate links with pagination
     */
    public function get_user_affiliate_links( $user_id, $page = 1, $per_page = 10 ) {
        $offset = ( $page - 1 ) * $per_page;

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

                $clicks = $this->get_link_clicks( $post_id );
                $conversions = $this->get_link_conversions( $post_id );
                $conversion_rate = $clicks > 0 ? round( ( $conversions / $clicks ) * 100, 2 ) : 0;

                $links[] = array(
                    'id' => $post_id,
                    'product_id' => get_post_meta( $post_id, 'product_id', true ),
                    'product_url' => get_post_meta( $post_id, 'product_url', true ),
                    'affiliate_url' => get_post_meta( $post_id, 'affiliate_url', true ),
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

        return array(
            'links' => $links,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_links' => $total_links
        );
    }

    /**
     * Delete affiliate link
     */
    public function delete_affiliate_link( $link_id, $user_id ) {
        $post = get_post( $link_id );

        if ( ! $post || $post->post_type !== 'affiliate_link' || $post->post_author != $user_id ) {
            return false;
        }

        return wp_delete_post( $link_id, true );
    }

    /**
     * Get link clicks count
     */
    public function get_link_clicks( $link_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks WHERE link_id = %d",
            $link_id
        ) ) ?: 0;
    }

    /**
     * Get link conversions count
     */
    public function get_link_conversions( $link_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE link_id = %d",
            $link_id
        ) ) ?: 0;
    }

    /**
     * Get user total clicks
     */
    public function get_user_total_clicks( $user_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks WHERE user_id = %d",
            $user_id
        ) ) ?: 0;
    }

    /**
     * Get user total conversions
     */
    public function get_user_total_conversions( $user_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        ) ) ?: 0;
    }

    /**
     * Get user total earnings
     */
    public function get_user_total_earnings( $user_id ) {
        global $wpdb;

        $earnings = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        ) );

        return $earnings ?: 0;
    }

    /**
     * Get user pending earnings
     */
    public function get_user_pending_earnings( $user_id ) {
        global $wpdb;

        $earnings = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d AND status = 'pending'",
            $user_id
        ) );

        return $earnings ?: 0;
    }

    /**
     * Get user current balance
     */
    public function get_user_current_balance( $user_id ) {
        return get_user_meta( $user_id, 'affiliate_balance', true ) ?: 0;
    }

    /**
     * Record a click
     */
    public function record_click( $link_id, $user_id, $ref_code, $visitor_ip, $user_agent, $referrer ) {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'affiliate_bloom_clicks',
            array(
                'link_id' => $link_id,
                'user_id' => $user_id,
                'ref_code' => $ref_code,
                'visitor_ip' => $visitor_ip,
                'user_agent' => $user_agent,
                'referrer' => $referrer,
                'click_date' => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Record a conversion
     */
    public function record_conversion( $link_id, $user_id, $order_id, $commission_amount, $status = 'pending' ) {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'affiliate_bloom_conversions',
            array(
                'link_id' => $link_id,
                'user_id' => $user_id,
                'order_id' => $order_id,
                'commission_amount' => $commission_amount,
                'status' => $status,
                'conversion_date' => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%d', '%f', '%s', '%s' )
        );
    }
}