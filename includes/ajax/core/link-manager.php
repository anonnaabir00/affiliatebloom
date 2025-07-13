<?php

namespace AffiliateBloom;

if (! defined('ABSPATH')) {
    exit;
}

class SubmitComment
{

    public static function init()
    {
        $self = new self();

        // AJAX for logged-in users
//         add_action('wp_ajax_afbloom_generate_affiliate_link', array($this, 'generate_affiliate_link'));
//         add_action('wp_ajax_afbloom_get_user_affiliate_links', array($this, 'get_user_affiliate_links'));
//         // AJAX for non-logged-in users
//         add_action('wp_ajax_nopriv_afbloom_generate_affiliate_link', array($self, 'generate_affiliate_link'));
//         add_action('wp_ajax_noptiv_afbloom_get_user_affiliate_links', array($self, 'get_user_affiliate_links'));
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
}
