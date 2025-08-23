<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuthRegister {

    public static function init() {
        $self = new self();
        add_shortcode( 'auth_register', array($self, 'auth_register_shortcode') );
    }

    public function auth_register_shortcode() {
        $unique_key = 'auth_register' . uniqid();
        return '<div class="auth_register" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
