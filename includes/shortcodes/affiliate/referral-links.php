<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReferralLinks {

    public static function init() {
        $self = new self();
        add_shortcode( 'referral_links', array($self, 'referral_links_shortcode') );
    }

    public function referral_links_shortcode() {
        $unique_key = 'referral_links' . uniqid();
        return '<div class="referral_links" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
