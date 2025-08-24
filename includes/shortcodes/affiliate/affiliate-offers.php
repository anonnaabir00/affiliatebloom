<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AffiliateOffers {

    public static function init() {
        $self = new self();
        add_shortcode( 'affiliate_offers', array($self, 'affiliate_offers_shortcode') );
    }

    public function affiliate_offers_shortcode() {
        $unique_key = 'affiliate_offers' . uniqid();
        return '<div class="affiliate_offers" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
