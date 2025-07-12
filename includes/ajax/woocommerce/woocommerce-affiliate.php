<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class WooCommerceAffiliate {

  public static function init() {
      $self = new self();

      // AJAX for logged-in users
      add_action('wp_ajax_generate_affiliate_link', array($self, 'generate_affiliate_link'));
      add_action('wp_ajax_get_user_affiliate_links', array($self, 'get_user_affiliate_links'));
      add_action('wp_ajax_delete_affiliate_link', array($self, 'delete_affiliate_link'));
      add_action('wp_ajax_get_affiliate_stats', array($self, 'get_affiliate_stats'));

      // AJAX for both logged-in and non-logged-in users
      add_action('wp_ajax_track_affiliate_click', array($self, 'track_affiliate_click'));
      add_action('wp_ajax_nopriv_track_affiliate_click', array($self, 'track_affiliate_click'));
  }

  /**
   * Generate new affiliate link for a product
   */
  public function generate_affiliate_link() {
      // Verify nonce
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_nonce')) {
          wp_send_json_error('Invalid nonce');
          wp_die();
      }

      if (!is_user_logged_in()) {
          wp_send_json_error('User not logged in');
          wp_die();
      }

      $user_id = get_current_user_id();
      $product_id = intval($_POST['product_id']);

      // Check if user is approved affiliate
      if (!AffiliateHelper::is_approved_affiliate($user_id)) {
          wp_send_json_error('User is not an approved affiliate');
          wp_die();
      }

      // Check if product exists
      $product = wc_get_product($product_id);
      if (!$product) {
          wp_send_json_error('Product not found');
          wp_die();
      }

      // Check if affiliate link already exists for this user/product
      $existing_link = get_posts(array(
          'post_type' => 'affiliate_links',
          'author' => $user_id,
          'meta_query' => array(
              array(
                  'key' => 'product_id',
                  'value' => $product_id,
                  'compare' => '='
              )
          ),
          'posts_per_page' => 1
      ));

      if (!empty($existing_link)) {
          $affiliate_code = get_post_meta($existing_link[0]->ID, 'affiliate_code', true);
          $affiliate_url = home_url('/?aff=' . $affiliate_code);

          wp_send_json_success(array(
              'affiliate_url' => $affiliate_url,
              'affiliate_code' => $affiliate_code,
              'message' => 'Existing affiliate link retrieved'
          ));
          wp_die();
      }

      // Generate unique affiliate code
      $affiliate_code = AffiliateHelper::generate_unique_code($user_id, $product_id);

      // Create affiliate link post
      $post_data = array(
          'post_title' => sprintf('Affiliate Link - %s', $product->get_name()),
          'post_type' => 'affiliate_links',
          'post_status' => 'publish',
          'post_author' => $user_id,
          'meta_input' => array(
              'product_id' => $product_id,
              'affiliate_code' => $affiliate_code,
              'commission_rate' => AffiliateHelper::get_user_commission_rate($user_id),
              'clicks' => 0,
              'conversions' => 0,
              'created_date' => current_time('mysql')
          )
      );

      $post_id = wp_insert_post($post_data);

      if ($post_id && !is_wp_error($post_id)) {
          $affiliate_url = home_url('/?aff=' . $affiliate_code);

          wp_send_json_success(array(
              'affiliate_url' => $affiliate_url,
              'affiliate_code' => $affiliate_code,
              'post_id' => $post_id,
              'message' => 'Affiliate link created successfully'
          ));
      } else {
          wp_send_json_error('Failed to create affiliate link');
      }

      wp_die();
  }

  /**
   * Get all affiliate links for current user
   */
  public function get_user_affiliate_links() {
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_nonce')) {
          wp_send_json_error('Invalid nonce');
          wp_die();
      }

      if (!is_user_logged_in()) {
          wp_send_json_error('User not logged in');
          wp_die();
      }

      $user_id = get_current_user_id();
      $page = intval($_POST['page']) ?: 1;
      $per_page = 10;

      $args = array(
          'post_type' => 'affiliate_links',
          'author' => $user_id,
          'posts_per_page' => $per_page,
          'paged' => $page,
          'post_status' => 'publish',
          'orderby' => 'date',
          'order' => 'DESC'
      );

      $affiliate_links = get_posts($args);
      $total_posts = wp_count_posts('affiliate_links');

      $links_data = array();
      foreach ($affiliate_links as $link) {
          $product_id = get_post_meta($link->ID, 'product_id', true);
          $product = wc_get_product($product_id);
          $affiliate_code = get_post_meta($link->ID, 'affiliate_code', true);
          $clicks = get_post_meta($link->ID, 'clicks', true) ?: 0;
          $conversions = get_post_meta($link->ID, 'conversions', true) ?: 0;

          $links_data[] = array(
              'id' => $link->ID,
              'product_name' => $product ? $product->get_name() : 'Product Not Found',
              'product_id' => $product_id,
              'affiliate_code' => $affiliate_code,
              'affiliate_url' => home_url('/?aff=' . $affiliate_code),
              'clicks' => $clicks,
              'conversions' => $conversions,
              'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0,
              'created_date' => get_the_date('Y-m-d H:i:s', $link->ID)
          );
      }

      wp_send_json_success(array(
          'links' => $links_data,
          'total_pages' => ceil($total_posts->publish / $per_page),
          'current_page' => $page
      ));

      wp_die();
  }

  /**
   * Delete affiliate link
   */
  public function delete_affiliate_link() {
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_nonce')) {
          wp_send_json_error('Invalid nonce');
          wp_die();
      }

      if (!is_user_logged_in()) {
          wp_send_json_error('User not logged in');
          wp_die();
      }

      $link_id = intval($_POST['link_id']);
      $user_id = get_current_user_id();

      // Verify ownership
      $link = get_post($link_id);
      if (!$link || $link->post_author != $user_id || $link->post_type !== 'affiliate_links') {
          wp_send_json_error('Unauthorized or link not found');
          wp_die();
      }

      $deleted = wp_delete_post($link_id, true);

      if ($deleted) {
          wp_send_json_success('Affiliate link deleted successfully');
      } else {
          wp_send_json_error('Failed to delete affiliate link');
      }

      wp_die();
  }

  /**
   * Track affiliate link clicks
   */
  public function track_affiliate_click() {
      $affiliate_code = sanitize_text_field($_POST['affiliate_code']);

      if (empty($affiliate_code)) {
          wp_send_json_error('Invalid affiliate code');
          wp_die();
      }

      // Get affiliate link post
      $affiliate_posts = get_posts(array(
          'post_type' => 'affiliate_links',
          'meta_query' => array(
              array(
                  'key' => 'affiliate_code',
                  'value' => $affiliate_code,
                  'compare' => '='
              )
          ),
          'posts_per_page' => 1
      ));

      if (empty($affiliate_posts)) {
          wp_send_json_error('Affiliate link not found');
          wp_die();
      }

      $affiliate_post = $affiliate_posts[0];
      $affiliate_id = $affiliate_post->post_author;
      $product_id = get_post_meta($affiliate_post->ID, 'product_id', true);

      // Insert tracking record
      global $wpdb;
      $tracking_result = $wpdb->insert(
          $wpdb->prefix . 'affiliate_tracking',
          array(
              'affiliate_id' => $affiliate_id,
              'product_id' => $product_id,
              'affiliate_link_id' => $affiliate_post->ID,
              'user_ip' => AffiliateHelper::get_user_ip(),
              'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT']),
              'referrer' => sanitize_text_field($_SERVER['HTTP_REFERER'] ?? ''),
              'click_time' => current_time('mysql')
          ),
          array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
      );

      if ($tracking_result) {
          // Update click count
          $current_clicks = get_post_meta($affiliate_post->ID, 'clicks', true) ?: 0;
          update_post_meta($affiliate_post->ID, 'clicks', $current_clicks + 1);

          // Set cookie for conversion tracking (30 days)
          setcookie('affiliate_bloom_code', $affiliate_code, time() + (30 * 24 * 60 * 60), '/');

          wp_send_json_success('Click tracked successfully');
      } else {
          wp_send_json_error('Failed to track click');
      }

      wp_die();
  }

  /**
   * Get affiliate statistics for current user
   */
  public function get_affiliate_stats() {
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_nonce')) {
          wp_send_json_error('Invalid nonce');
          wp_die();
      }

      if (!is_user_logged_in()) {
          wp_send_json_error('User not logged in');
          wp_die();
      }

      $user_id = get_current_user_id();
      $stats = AffiliateHelper::get_user_stats($user_id);

      wp_send_json_success($stats);
      wp_die();
  }
}