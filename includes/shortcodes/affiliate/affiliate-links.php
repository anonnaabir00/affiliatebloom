<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AffiliateLinks {

    public static function init() {
        $self = new self();
        add_shortcode( 'affiliate_links', array($self, 'affiliate_links_shortcode') );
    }

    public function affiliate_links_shortcode() {
        $unique_key = 'affiliate_links' . uniqid();
        return '<div class="affiliate_links" data-key="' . esc_attr($unique_key) . '">asa</div>';
    }
}
