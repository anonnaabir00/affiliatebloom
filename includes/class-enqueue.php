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
          'affiliate-bloom-frontend',
          AFFILIATE_BLOOM_ROOT_DIR_URL . 'includes/assets/frontend.js',
          array('jquery'),
          AFFILIATE_BLOOM_VERSION,
          true
      );

      // Enqueue frontend styles
      wp_enqueue_style(
          'affiliate-bloom-frontend',
          AFFILIATE_BLOOM_ROOT_DIR_URL . 'includes/assets/frontend.css',
          array(),
          AFFILIATE_BLOOM_VERSION
      );

      // Localize script
      wp_localize_script('affiliate-bloom-frontend', 'affiliateBloom', array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('affiliate_bloom_nonce'),
          'is_affiliate' => $this->is_user_affiliate(),
          'user_id' => get_current_user_id(),
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