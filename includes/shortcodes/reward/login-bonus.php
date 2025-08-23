<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LoginBonus {

    public static function init() {
        $self = new self();
        add_shortcode( 'login_bonus', array($self, 'login_bonus_shortcode') );
    }

    public function login_bonus_shortcode() {
        $unique_key = 'login_bonus' . uniqid();
        return '<div class="login_bonus" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
