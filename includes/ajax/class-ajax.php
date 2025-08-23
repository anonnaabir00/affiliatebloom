<?php
namespace AffiliateBloom;
use AffiliateBloom\LinkManager;
use AffiliateBloom\ReferralManager;
use AffiliateBloom\PartnerLogin;
use AffiliateBloom\PartnerRegister;

if (!defined('ABSPATH')) {
    exit;
}

class Ajax {

    public static function init() {
        LinkManager::init();
        ReferralManager::init();
        PartnerLogin::init();
        PartnerRegister::init();
        $instance = new self();
        return $instance;
    }

//     public function __construct() {
//         // Database class is initialized elsewhere, we'll access it via new instance when needed
//
//         // AJAX handlers for logged-in users
//         add_action('wp_ajax_generate_affiliate_link', array($this, 'generate_affiliate_link'));
//         add_action('wp_ajax_get_user_affiliate_links', array($this, 'get_user_affiliate_links'));
//         add_action('wp_ajax_delete_affiliate_link', array($this, 'delete_affiliate_link'));
//         add_action('wp_ajax_get_affiliate_stats', array($this, 'get_affiliate_stats'));
//
//         // Test handler
//         add_action('wp_ajax_affiliate_bloom_test', array($this, 'test_ajax'));
//         add_action('wp_ajax_nopriv_affiliate_bloom_test', array($this, 'test_ajax'));
//     }

    public function test_ajax() {
        wp_send_json_success('AJAX is working!');
    }

    public function generate_affiliate_link() {
        // Check if user is logged in first
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        // Verify nonce - make sure the nonce key matches what's sent from frontend
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliate_bloom_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();

        // Check if user is approved affiliate
        if (get_user_meta($user_id, 'affiliate_status', true) !== 'approved') {
            wp_send_json_error('User is not an approved affiliate');
        }

        // Get product URL or ID
        $product_url = sanitize_url($_POST['product_url'] ?? '');
        $product_id = intval($_POST['product_id'] ?? 0);

        if (empty($product_url) && empty($product_id)) {
            wp_send_json_error('Product URL or ID is required');
        }

        // If we have URL, try to get product ID
        if (!empty($product_url) && empty($product_id)) {
            $product_id = url_to_postid($product_url);
        }

        // Generate affiliate link
        $affiliate_code = $this->get_or_create_affiliate_code($user_id);
        $affiliate_url = $this->generate_affiliate_url($product_url ?: get_permalink($product_id), $affiliate_code);

        // Save the link directly using wp_insert_post
        $post_data = array(
            'post_title' => 'Affiliate Link - ' . ($product_url ?: 'Product ID: ' . $product_id),
            'post_type' => 'affiliate_link',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input' => array(
                'product_id' => $product_id,
                'product_url' => $product_url,
                'affiliate_url' => $affiliate_url,
                'created_date' => current_time('mysql'),
            )
        );

        $link_id = wp_insert_post($post_data);

        if ($link_id && !is_wp_error($link_id)) {
            wp_send_json_success(array(
                'affiliate_url' => $affiliate_url,
                'link_id' => $link_id,
                'message' => 'Affiliate link generated successfully!'
            ));
        } else {
            wp_send_json_error('Failed to save affiliate link');
        }
    }

    public function get_user_affiliate_links() {
        // Check if user is logged in first
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliate_bloom_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        // Get links using WP_Query directly
        $args = array(
            'post_type' => 'affiliate_link',
            'author' => $user_id,
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $query = new \WP_Query($args);
        $links = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Get clicks and conversions (you'll need to implement these methods)
                $clicks = $this->get_link_clicks($post_id);
                $conversions = $this->get_link_conversions($post_id);
                $conversion_rate = $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0;

                $links[] = array(
                    'id' => $post_id,
                    'product_id' => get_post_meta($post_id, 'product_id', true),
                    'product_url' => get_post_meta($post_id, 'product_url', true),
                    'affiliate_url' => get_post_meta($post_id, 'affiliate_url', true),
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'conversion_rate' => $conversion_rate,
                    'created_date' => get_post_meta($post_id, 'created_date', true)
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

        $total_query = new \WP_Query($total_args);
        $total_links = $total_query->found_posts;
        $total_pages = ceil($total_links / $per_page);

        wp_send_json_success(array(
            'links' => $links,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_links' => $total_links
        ));
    }

    public function delete_affiliate_link() {
        // Check if user is logged in first
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliate_bloom_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();
        $link_id = intval($_POST['link_id']);

        // Verify the post exists and belongs to the user
        $post = get_post($link_id);

        if (!$post || $post->post_type !== 'affiliate_link' || $post->post_author != $user_id) {
            wp_send_json_error('Link not found or access denied');
        }

        // Delete using wp_delete_post
        $deleted = wp_delete_post($link_id, true);

        if ($deleted) {
            wp_send_json_success('Link deleted successfully');
        } else {
            wp_send_json_error('Failed to delete link');
        }
    }

    public function get_affiliate_stats() {
        // Check if user is logged in first
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'affiliate_bloom_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = get_current_user_id();

        // Get overall stats
        $stats = array(
            'total_clicks' => $this->get_user_total_clicks($user_id),
            'total_conversions' => $this->get_user_total_conversions($user_id),
            'total_earnings' => $this->get_user_total_earnings($user_id),
            'pending_earnings' => $this->get_user_pending_earnings($user_id),
            'current_balance' => $this->get_user_current_balance($user_id),
            'conversion_rate' => 0
        );

        // Calculate conversion rate
        if ($stats['total_clicks'] > 0) {
            $stats['conversion_rate'] = round(($stats['total_conversions'] / $stats['total_clicks']) * 100, 2);
        }

        wp_send_json_success(array(
            'stats' => $stats
        ));
    }

    // Helper methods
    private function get_or_create_affiliate_code($user_id) {
        $code = get_user_meta($user_id, 'affiliate_code', true);

        if (empty($code)) {
            $code = 'AFF' . $user_id . '_' . wp_generate_password(6, false);
            update_user_meta($user_id, 'affiliate_code', $code);
        }

        return $code;
    }

    private function generate_affiliate_url($original_url, $affiliate_code) {
        $separator = strpos($original_url, '?') !== false ? '&' : '?';
        return $original_url . $separator . 'ref=' . $affiliate_code;
    }

    // Helper methods for clicks and conversions
    private function get_link_clicks($link_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks WHERE link_id = %d",
            $link_id
        )) ?: 0;
    }

    private function get_link_conversions($link_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE link_id = %d",
            $link_id
        )) ?: 0;
    }

    private function get_user_total_clicks($user_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks WHERE user_id = %d",
            $user_id
        )) ?: 0;
    }

    private function get_user_total_conversions($user_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        )) ?: 0;
    }

    private function get_user_total_earnings($user_id) {
        global $wpdb;

        $earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d",
            $user_id
        ));

        return $earnings ?: 0;
    }

    private function get_user_pending_earnings($user_id) {
        global $wpdb;

        $earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));

        return $earnings ?: 0;
    }

    private function get_user_current_balance($user_id) {
        return get_user_meta($user_id, 'affiliate_balance', true) ?: 0;
    }
}