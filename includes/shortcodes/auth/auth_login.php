<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuthLogin {

    public static function init() {
        $self = new self();
        add_shortcode( 'auth_login', array($self, 'auth_login_shortcode') );
    }

    public function auth_login_shortcode() {
        $unique_key = 'auth_login' . uniqid();
        return '<div class="auth_login" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
