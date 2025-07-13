<?php
namespace AffiliateBloom;

if (!defined('ABSPATH')) {
  exit;
}

class Frontend {

  public static function init() {
      $instance = new self();
      return $instance;
  }

  public function __construct() {
      add_action('init', array($this, 'init_frontend'));
      add_shortcode('affiliate_bloom_dashboard', array($this, 'render_dashboard'));
      add_shortcode('affiliate_bloom_generate_link', array($this, 'render_generate_link'));
  }

  public function init_frontend() {
      // Add rewrite rules for affiliate links
      add_rewrite_rule('^affiliate/([^/]+)/?', 'index.php?affiliate_redirect=1&ref=$matches[1]', 'top');

      // Handle affiliate redirects
      add_action('template_redirect', array($this, 'handle_affiliate_redirect'));

      // Add query vars
      add_filter('query_vars', array($this, 'add_query_vars'));
  }

  public function add_query_vars($vars) {
      $vars[] = 'affiliate_redirect';
      $vars[] = 'ref';
      return $vars;
  }

  public function handle_affiliate_redirect() {
      if (get_query_var('affiliate_redirect')) {
          $ref_code = get_query_var('ref');
          if ($ref_code) {
              $this->track_click($ref_code);
              // Redirect to the actual product page
              $redirect_url = $this->get_redirect_url($ref_code);
              if ($redirect_url) {
                  wp_redirect($redirect_url);
                  exit;
              }
          }
      }
  }

  public function render_dashboard($atts = array()) {
      // Check if user is logged in
      if (!is_user_logged_in()) {
          return '<p>' . __('Please log in to access your affiliate dashboard.', 'affiliate-bloom') . '</p>';
      }

      // Check if user is approved affiliate
      $user_id = get_current_user_id();
      $status = get_user_meta($user_id, 'affiliate_status', true);

      if ($status !== 'approved') {
          return '<div class="affiliate-bloom-notice">' .
                 __('Your affiliate application is pending approval.', 'affiliate-bloom') .
                 '</div>';
      }

      // Get current user's affiliate balance
      $affiliate_balance = get_user_meta($user_id, 'affiliate_balance', true);
      $affiliate_balance = !empty($affiliate_balance) ? floatval($affiliate_balance) : 0;
      $formatted_balance = '$' . number_format($affiliate_balance, 2);

      ob_start();
      ?>
      <div class="affiliate-bloom-dashboard">
          <div class="dashboard-header">
              <h2><?php _e('Affiliate Dashboard', 'affiliate-bloom'); ?></h2>
          </div>

          <!-- Stats Overview -->
          <div class="stats-overview">
              <div class="stat-card">
                  <div class="stat-number" id="total-clicks">-</div>
                  <div class="stat-label"><?php _e('Total Clicks', 'affiliate-bloom'); ?></div>
              </div>
              <div class="stat-card">
                  <div class="stat-number" id="total-conversions">-</div>
                  <div class="stat-label"><?php _e('Conversions', 'affiliate-bloom'); ?></div>
              </div>
              <div class="stat-card">
                  <div class="stat-number" id="conversion-rate">-%</div>
                  <div class="stat-label"><?php _e('Conversion Rate', 'affiliate-bloom'); ?></div>
              </div>
              <div class="stat-card">
                  <div class="stat-number" id="total-earnings"><?php echo esc_html($formatted_balance); ?></div>
                  <div class="stat-label"><?php _e('Total Earnings', 'affiliate-bloom'); ?></div>
              </div>
          </div>

          <!-- Generate Link Section -->
          <div class="generate-link-section">
              <h3><?php _e('Generate Affiliate Link', 'affiliate-bloom'); ?></h3>
              <div class="generate-form">
                  <input type="url" id="product-url" placeholder="<?php _e('Enter product URL...', 'affiliate-bloom'); ?>">
                  <button id="generate-affiliate-link" class="btn-primary">
                      <?php _e('Generate Link', 'affiliate-bloom'); ?>
                  </button>
              </div>
              <div id="generated-link-result" style="display: none;">
                  <label><?php _e('Your Affiliate Link:', 'affiliate-bloom'); ?></label>
                  <div class="link-result">
                      <input type="text" id="generated-link" readonly>
                      <button id="copy-link" class="btn-secondary"><?php _e('Copy', 'affiliate-bloom'); ?></button>
                  </div>
              </div>
          </div>

          <!-- My Links Section -->
          <div class="affiliate-bloom-links">
              <div class="section-header">
                  <h3><?php _e('My Affiliate Links', 'affiliate-bloom'); ?></h3>
                  <button id="refresh-links" class="btn-secondary"><?php _e('Refresh', 'affiliate-bloom'); ?></button>
              </div>
              <div id="links-container">
                  <div class="loading-spinner">
                      <div class="spinner"></div>
                      <p><?php _e('Loading your links...', 'affiliate-bloom'); ?></p>
                  </div>
              </div>
              <div id="links-pagination"></div>
          </div>
      </div>
      <?php
      return ob_get_clean();
  }

  public function render_generate_link($atts = array()) {
      if (!is_user_logged_in()) {
          return '<p>' . __('Please log in to generate affiliate links.', 'affiliate-bloom') . '</p>';
      }

      $user_id = get_current_user_id();
      $status = get_user_meta($user_id, 'affiliate_status', true);

      if ($status !== 'approved') {
          return '<p>' . __('Your affiliate application is pending approval.', 'affiliate-bloom') . '</p>';
      }

      $atts = shortcode_atts(array(
          'product_id' => 0,
          'text' => __('Get Affiliate Link', 'affiliate-bloom')
      ), $atts);

      if (!$atts['product_id']) {
          return '<p>' . __('Product ID is required.', 'affiliate-bloom') . '</p>';
      }

      ob_start();
      ?>
      <div class="affiliate-bloom-generate-link">
          <button class="generate-link-btn" data-product-id="<?php echo esc_attr($atts['product_id']); ?>">
              <?php echo esc_html($atts['text']); ?>
          </button>
          <div class="generated-link-result" style="display: none;">
              <input type="text" class="generated-link" readonly>
              <button class="copy-link-btn"><?php _e('Copy', 'affiliate-bloom'); ?></button>
          </div>
      </div>
      <?php
      return ob_get_clean();
  }

  private function track_click($ref_code) {
      global $wpdb;

      // Get affiliate info from ref code
      $user_id = $this->get_user_id_from_ref($ref_code);
      if (!$user_id) {
          return false;
      }

      // Get visitor info
      $visitor_ip = $this->get_visitor_ip();
      $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
      $referrer = $_SERVER['HTTP_REFERER'] ?? '';

      // Insert click record
      $wpdb->insert(
          $wpdb->prefix . 'affiliate_bloom_clicks',
          array(
              'user_id' => $user_id,
              'ref_code' => $ref_code,
              'visitor_ip' => $visitor_ip,
              'user_agent' => $user_agent,
              'referrer' => $referrer,
              'click_date' => current_time('mysql')
          ),
          array('%d', '%s', '%s', '%s', '%s', '%s')
      );

      return true;
  }

  private function get_redirect_url($ref_code) {
      // This should be implemented based on your link structure
      // For now, return home URL
      return home_url();
  }

  private function get_user_id_from_ref($ref_code) {
      global $wpdb;

      $user_id = $wpdb->get_var($wpdb->prepare(
          "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'affiliate_code' AND meta_value = %s",
          $ref_code
      ));

      return $user_id;
  }

  private function get_visitor_ip() {
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
}