<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class AffiliateHelper {

  /**
   * Check if user is approved affiliate
   */
  public static function is_approved_affiliate($user_id) {
      $affiliate_status = get_user_meta($user_id, 'affiliate_bloom_status', true);
      return $affiliate_status === 'approved';
  }

  /**
   * Generate unique affiliate code
   */
  public static function generate_unique_code($user_id, $product_id) {
      $timestamp = time();
      $random = wp_generate_password(6, false, false);
      return sprintf('ab_%d_%d_%s_%s', $user_id, $product_id, $timestamp, $random);
  }

  /**
   * Get user commission rate
   */
  public static function get_user_commission_rate($user_id) {
      $user_rate = get_user_meta($user_id, 'affiliate_bloom_commission_rate', true);
      $default_rate = get_option('affiliate_bloom_default_commission_rate', 10);

      return !empty($user_rate) ? floatval($user_rate) : floatval($default_rate);
  }

  /**
   * Get user IP address
   */
  public static function get_user_ip() {
      $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

      foreach ($ip_keys as $key) {
          if (array_key_exists($key, $_SERVER) === true) {
              foreach (explode(',', $_SERVER[$key]) as $ip) {
                  $ip = trim($ip);
                  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                      return $ip;
                  }
              }
          }
      }

      return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  }

  /**
   * Get comprehensive user statistics
   */
  public static function get_user_stats($user_id) {
      global $wpdb;

      // Get total clicks
      $total_clicks = $wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_tracking WHERE affiliate_id = %d",
          $user_id
      ));

      // Get total conversions
      $total_conversions = $wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_commissions WHERE affiliate_id = %d",
          $user_id
      ));

      // Get total earnings
      $total_earnings = $wpdb->get_var($wpdb->prepare(
          "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_commissions WHERE affiliate_id = %d AND status = 'approved'",
          $user_id
      )) ?: 0;

      // Get pending earnings
      $pending_earnings = $wpdb->get_var($wpdb->prepare(
          "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_commissions WHERE affiliate_id = %d AND status = 'pending'",
          $user_id
      )) ?: 0;

      // Get current balance
      $current_balance = get_user_meta($user_id, 'affiliate_bloom_balance', true) ?: 0;

      // Calculate conversion rate
      $conversion_rate = $total_clicks > 0 ? round(($total_conversions / $total_clicks) * 100, 2) : 0;

      // Get monthly stats
      $monthly_stats = self::get_monthly_stats($user_id);

      // Get top performing links
      $top_links = self::get_top_performing_links($user_id, 5);

      return array(
          'total_clicks' => intval($total_clicks),
          'total_conversions' => intval($total_conversions),
          'total_earnings' => floatval($total_earnings),
          'pending_earnings' => floatval($pending_earnings),
          'current_balance' => floatval($current_balance),
          'conversion_rate' => $conversion_rate,
          'monthly_stats' => $monthly_stats,
          'top_links' => $top_links
      );
  }

  /**
   * Get monthly statistics for charts
   */
  public static function get_monthly_stats($user_id) {
      global $wpdb;

      $monthly_data = $wpdb->get_results($wpdb->prepare(
          "SELECT
              DATE_FORMAT(click_time, '%%Y-%%m') as month,
              COUNT(*) as clicks,
              COUNT(CASE WHEN converted = 1 THEN 1 END) as conversions
          FROM {$wpdb->prefix}affiliate_tracking
          WHERE affiliate_id = %d
              AND click_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(click_time, '%%Y-%%m')
          ORDER BY month ASC",
          $user_id
      ));

      $formatted_data = array();
      foreach ($monthly_data as $data) {
          $formatted_data[] = array(
              'month' => $data->month,
              'clicks' => intval($data->clicks),
              'conversions' => intval($data->conversions)
          );
      }

      return $formatted_data;
  }

  /**
   * Get top performing affiliate links
   */
  public static function get_top_performing_links($user_id, $limit = 5) {
      $args = array(
          'post_type' => 'affiliate_links',
          'author' => $user_id,
          'posts_per_page' => $limit,
          'post_status' => 'publish',
          'meta_key' => 'clicks',
          'orderby' => 'meta_value_num',
          'order' => 'DESC'
      );

      $top_links = get_posts($args);
      $links_data = array();

      foreach ($top_links as $link) {
          $product_id = get_post_meta($link->ID, 'product_id', true);
          $product = wc_get_product($product_id);
          $clicks = get_post_meta($link->ID, 'clicks', true) ?: 0;
          $conversions = get_post_meta($link->ID, 'conversions', true) ?: 0;

          $links_data[] = array(
              'id' => $link->ID,
              'product_name' => $product ? $product->get_name() : 'Product Not Found',
              'clicks' => intval($clicks),
              'conversions' => intval($conversions),
              'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0
          );
      }

      return $links_data;
  }

  /**
   * Calculate commission for order
   */
  public static function calculate_commission($order_id, $affiliate_code) {
      global $wpdb;

      $order = wc_get_order($order_id);
      if (!$order) {
          return false;
      }

      // Get affiliate link details
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
          return false;
      }

      $affiliate_post = $affiliate_posts[0];
      $affiliate_id = $affiliate_post->post_author;
      $commission_rate = get_post_meta($affiliate_post->ID, 'commission_rate', true);

      $total_commission = 0;

      foreach ($order->get_items() as $item) {
          $product_id = $item->get_product_id();
          $line_total = $item->get_total();
          $commission_amount = ($line_total * $commission_rate) / 100;

          // Insert commission record
          $commission_id = $wpdb->insert(
              $wpdb->prefix . 'affiliate_commissions',
              array(
                  'affiliate_id' => $affiliate_id,
                  'order_id' => $order_id,
                  'product_id' => $product_id,
                  'affiliate_link_id' => $affiliate_post->ID,
                  'commission_amount' => $commission_amount,
                  'commission_rate' => $commission_rate,
                  'status' => 'pending',
                  'created_at' => current_time('mysql')
              ),
              array('%d', '%d', '%d', '%d', '%f', '%f', '%s', '%s')
          );

          if ($commission_id) {
              $total_commission += $commission_amount;
          }
      }

      // Update conversion count
      $current_conversions = get_post_meta($affiliate_post->ID, 'conversions', true) ?: 0;
      update_post_meta($affiliate_post->ID, 'conversions', $current_conversions + 1);

      // Update tracking record as converted
      $wpdb->update(
          $wpdb->prefix . 'affiliate_tracking',
          array('converted' => 1, 'commission_amount' => $total_commission),
          array('affiliate_id' => $affiliate_id, 'converted' => 0),
          array('%d', '%f'),
          array('%d', '%d')
      );

      return $total_commission;
  }

  /**
   * Process login bonus
   */
  public static function process_login_bonus($user_id) {
      if (!self::is_approved_affiliate($user_id)) {
          return false;
      }

      $login_bonus = get_option('affiliate_bloom_login_bonus', 0);
      if ($login_bonus <= 0) {
          return false;
      }

      // Check if already rewarded today
      $last_login_reward = get_user_meta($user_id, 'affiliate_bloom_last_login_reward', true);
      $today = date('Y-m-d');

      if ($last_login_reward === $today) {
          return false;
      }

      // Add bonus to balance
      $current_balance = get_user_meta($user_id, 'affiliate_bloom_balance', true) ?: 0;
      $new_balance = $current_balance + $login_bonus;

      update_user_meta($user_id, 'affiliate_bloom_balance', $new_balance);
      update_user_meta($user_id, 'affiliate_bloom_last_login_reward', $today);

      return $login_bonus;
  }

  /**
   * Approve affiliate application
   */
  public static function approve_affiliate($user_id, $commission_rate = null) {
      update_user_meta($user_id, 'affiliate_bloom_status', 'approved');
      update_user_meta($user_id, 'affiliate_bloom_approved_date', current_time('mysql'));

      if ($commission_rate !== null) {
          update_user_meta($user_id, 'affiliate_bloom_commission_rate', floatval($commission_rate));
      }

      // Send approval email
      self::send_approval_email($user_id);

      return true;
  }

  /**
   * Send affiliate approval email
   */
  private static function send_approval_email($user_id) {
      $user = get_user_by('ID', $user_id);
      if (!$user) {
          return false;
      }

      $subject = 'Your Affiliate Application Has Been Approved!';
      $message = sprintf(
          'Hi %s,

Congratulations! Your affiliate application has been approved.

You can now start creating affiliate links and earning commissions.

Login to your account to get started: %s

Best regards,
The Affiliate Team',
          $user->display_name,
          wp_login_url()
      );

      return wp_mail($user->user_email, $subject, $message);
  }
}