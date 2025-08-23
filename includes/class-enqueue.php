<?php
namespace AffiliateBloom;

if (!defined('ABSPATH')) {
  exit;
}

class Enqueue {

  public static function init() {
      $instance = new self();
      return $instance;
  }

  public function __construct() {
      add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
      add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
  }

  public function enqueue_frontend_scripts() {
      // Enqueue jQuery
      wp_enqueue_script('jquery');

      // Enqueue frontend script
      wp_enqueue_script(
        'affiliate-bloom-frontend-build',
        AFFILIATE_BLOOM_ROOT_DIR_URL . 'includes/assets/build/frontend.js',
        array('jquery'),
        AFFILIATE_BLOOM_VERSION,
        true
        );
      wp_enqueue_script(
          'affiliate-bloom-frontend',
          AFFILIATE_BLOOM_ROOT_DIR_URL . 'includes/assets/frontend.js',
          array('jquery'),
          AFFILIATE_BLOOM_VERSION,
          true
      );

      // Enqueue frontend styles
      wp_enqueue_style(
        'affiliate-bloom-frontend-style',
        AFFILIATE_BLOOM_ROOT_DIR_URL . 'includes/assets/build/frontend.css',
        array(),
        AFFILIATE_BLOOM_VERSION
        );
      wp_enqueue_style(
          'affiliate-bloom-frontend',
          AFFILIATE_BLOOM_ROOT_DIR_URL . 'includes/assets/frontend.css',
          array(),
          AFFILIATE_BLOOM_VERSION
      );

      wp_localize_script('affiliate-bloom-frontend', 'affiliate_bloom_ajax', array(
              'ajax_url' => admin_url('admin-ajax.php'),
              'nonce' => wp_create_nonce('affiliate_bloom_nonce')
          ));

      // Get login bonus data
      $login_bonus_data = $this->get_login_bonus_data();

      // Localize script
      wp_localize_script('affiliate-bloom-frontend', 'affiliateBloom', array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('affiliate_bloom_nonce'),
          'is_affiliate' => $this->is_user_affiliate(),
          'user_id' => get_current_user_id(),
          'login_bonus' => $login_bonus_data, // Add login bonus data here
          'messages' => array(
              'generating' => __('Generating...', 'affiliate-bloom'),
              'generated' => __('Generated!', 'affiliate-bloom'),
              'copied' => __('Copied!', 'affiliate-bloom'),
              'loading' => __('Loading...', 'affiliate-bloom'),
              'error' => __('An error occurred. Please try again.', 'affiliate-bloom'),
              'no_links' => __('No affiliate links found.', 'affiliate-bloom'),
              'confirm_delete' => __('Are you sure you want to delete this link?', 'affiliate-bloom'),
              'deleted' => __('Link deleted successfully.', 'affiliate-bloom'),
              'invalid_url' => __('Please enter a valid URL.', 'affiliate-bloom'),
              'url_required' => __('Product URL is required.', 'affiliate-bloom')
          )
      ));

      add_filter('script_loader_tag', array($this, 'add_module_type_to_script'), 10, 3);
  }

  /**
   * Get login bonus data for the current user
   */
  private function get_login_bonus_data() {
      if (!is_user_logged_in()) {
          return null;
      }

      $user_id = get_current_user_id();

      // Initialize the BonusAfterLogin class
      $bonus_handler = new BonusAfterLogin();

      // Get user login stats
      $login_stats = $bonus_handler->get_user_login_stats($user_id);

      // Get recent login bonus transactions
      $recent_transactions = $bonus_handler->get_user_transaction_history($user_id, 'login_bonus');
      $latest_transaction = !empty($recent_transactions) ? $recent_transactions[0] : null;

      return array(
          'user_id' => $user_id,
          'current_balance' => $bonus_handler->get_user_balance($user_id),
          'bonus_amount' => $bonus_handler->get_bonus_amount(),
          'can_claim_today' => $bonus_handler->can_get_login_bonus($user_id),
          'last_bonus_date' => $bonus_handler->get_last_bonus_date($user_id),
          'stats' => array(
              'total_bonuses' => $login_stats['total_bonuses'],
              'total_amount' => $login_stats['total_amount'],
              'streak_days' => $login_stats['streak_days'],
              'last_bonus_date' => $login_stats['last_bonus_date']
          ),
          'latest_transaction' => $latest_transaction ? array(
              'id' => $latest_transaction['id'],
              'amount' => $latest_transaction['amount'],
              'date' => $latest_transaction['date'],
              'created_date' => $latest_transaction['created_date'],
              'description' => $latest_transaction['description']
          ) : null
      );
  }

  public function add_module_type_to_script( $tag, $handle, $src ) {
      	$module_handles = array( 'affiliate-bloom-frontend-build');

      	if ( in_array( $handle, $module_handles, true ) ) {
      		$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
      	}

      	return $tag;
 }

  public function enqueue_admin_scripts($hook) {
      // Only load on affiliate bloom admin pages
      if (strpos($hook, 'affiliate-bloom') === false) {
          return;
      }

      wp_enqueue_script('jquery');

      wp_enqueue_script(
          'affiliate-bloom-admin',
          AFFILIATE_BLOOM_ROOT_DIR_URL . 'assets/js/admin.js',
          array('jquery'),
          AFFILIATE_BLOOM_VERSION,
          true
      );

      wp_enqueue_style(
          'affiliate-bloom-admin',
          AFFILIATE_BLOOM_ROOT_DIR_URL . 'assets/css/admin.css',
          array(),
          AFFILIATE_BLOOM_VERSION
      );

      wp_localize_script('affiliate-bloom-admin', 'affiliateBloomAdmin', array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('affiliate_bloom_admin_nonce')
      ));
  }

  private function is_user_affiliate() {
      if (!is_user_logged_in()) {
          return false;
      }

      $user_id = get_current_user_id();
      $status = get_user_meta($user_id, 'affiliate_status', true);

      return $status === 'approved';
  }
}
