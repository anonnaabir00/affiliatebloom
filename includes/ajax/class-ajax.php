<?php
namespace AffiliateBloom;

if (!defined('ABSPATH')) {
  exit;
}

class Ajax {

  public static function init() {
      $instance = new self();
      return $instance;
  }

  public function __construct() {
      // AJAX handlers for logged-in users
      add_action('wp_ajax_generate_affiliate_link', array($this, 'generate_affiliate_link'));
      add_action('wp_ajax_get_user_affiliate_links', array($this, 'get_user_affiliate_links'));
      add_action('wp_ajax_delete_affiliate_link', array($this, 'delete_affiliate_link'));
      add_action('wp_ajax_get_affiliate_stats', array($this, 'get_affiliate_stats'));

      // Test handler
      add_action('wp_ajax_affiliate_bloom_test', array($this, 'test_ajax'));
      add_action('wp_ajax_nopriv_affiliate_bloom_test', array($this, 'test_ajax'));
  }

  public function test_ajax() {
      wp_send_json_success('AJAX is working!');
  }

  public function generate_affiliate_link() {
      // Verify nonce
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_nonce')) {
          wp_send_json_error('Invalid nonce');
      }

      // Check if user is logged in
      if (!is_user_logged_in()) {
          wp_send_json_error('User not logged in');
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

      // Save the link to database
      $link_id = $this->save_affiliate_link($user_id, $product_id, $product_url, $affiliate_url);

      if ($link_id) {
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
      // Verify nonce
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_nonce')) {
          wp_send_json_error('Invalid nonce');
      }

      if (!is_user_logged_in()) {
          wp_send_json_error('User not logged in');
      }

      $user_id = get_current_user_id();
      $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
      $per_page = 10;
      $offset = ($page - 1) * $per_page;

      global $wpdb;

      // Create table if it doesn't exist
      $this->maybe_create_tables();

      // Get total count
      $total_query = $wpdb->prepare(
          "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_links WHERE user_id = %d",
          $user_id
      );
      $total_links = $wpdb->get_var($total_query);
      $total_pages = ceil($total_links / $per_page);

      // Get links for current page
      $links_query = $wpdb->prepare(
          "SELECT * FROM {$wpdb->prefix}affiliate_bloom_links
           WHERE user_id = %d
           ORDER BY created_date DESC
           LIMIT %d OFFSET %d",
          $user_id, $per_page, $offset
      );

      $links = $wpdb->get_results($links_query);

      // Format the data
      $formatted_links = array();
      foreach ($links as $link) {
          $clicks = $this->get_link_clicks($link->id);
          $conversions = $this->get_link_conversions($link->id);
          $conversion_rate = $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0;

          $formatted_links[] = array(
              'id' => $link->id,
              'product_id' => $link->product_id,
              'product_url' => $link->product_url,
              'affiliate_url' => $link->affiliate_url,
              'clicks' => $clicks,
              'conversions' => $conversions,
              'conversion_rate' => $conversion_rate,
              'created_date' => $link->created_date
          );
      }

      wp_send_json_success(array(
          'links' => $formatted_links,
          'current_page' => $page,
          'total_pages' => $total_pages,
          'total_links' => $total_links
      ));
  }

  public function delete_affiliate_link() {
      // Verify nonce
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_nonce')) {
          wp_send_json_error('Invalid nonce');
      }

      if (!is_user_logged_in()) {
          wp_send_json_error('User not logged in');
      }

      $user_id = get_current_user_id();
      $link_id = intval($_POST['link_id']);

      global $wpdb;

      // Verify the link belongs to the current user
      $link = $wpdb->get_row($wpdb->prepare(
          "SELECT * FROM {$wpdb->prefix}affiliate_bloom_links WHERE id = %d AND user_id = %d",
          $link_id, $user_id
      ));

      if (!$link) {
          wp_send_json_error('Link not found or access denied');
      }

      // Delete the link
      $deleted = $wpdb->delete(
          $wpdb->prefix . 'affiliate_bloom_links',
          array('id' => $link_id, 'user_id' => $user_id),
          array('%d', '%d')
      );

      if ($deleted) {
          wp_send_json_success('Link deleted successfully');
      } else {
          wp_send_json_error('Failed to delete link');
      }
  }

  public function get_affiliate_stats() {
      // Verify nonce
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_nonce')) {
          wp_send_json_error('Invalid nonce');
      }

      if (!is_user_logged_in()) {
          wp_send_json_error('User not logged in');
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

  private function save_affiliate_link($user_id, $product_id, $product_url, $affiliate_url) {
      global $wpdb;

      $this->maybe_create_tables();

      $result = $wpdb->insert(
          $wpdb->prefix . 'affiliate_bloom_links',
          array(
              'user_id' => $user_id,
              'product_id' => $product_id,
              'product_url' => $product_url,
              'affiliate_url' => $affiliate_url,
              'created_date' => current_time('mysql')
          ),
          array('%d', '%d', '%s', '%s', '%s')
      );

      return $result ? $wpdb->insert_id : false;
  }

  private function maybe_create_tables() {
      global $wpdb;

      $charset_collate = $wpdb->get_charset_collate();

      // Links table
      $links_table = $wpdb->prefix . 'affiliate_bloom_links';
      $links_sql = "CREATE TABLE IF NOT EXISTS $links_table (
          id int(11) NOT NULL AUTO_INCREMENT,
          user_id int(11) NOT NULL,
          product_id int(11) DEFAULT 0,
          product_url text,
          affiliate_url text NOT NULL,
          created_date datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY user_id (user_id)
      ) $charset_collate;";

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

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($links_sql);
      dbDelta($clicks_sql);
      dbDelta($conversions_sql);
  }

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