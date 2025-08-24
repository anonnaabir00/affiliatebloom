<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardStats {

    public static function init() {
        $self = new self();
        add_shortcode( 'dashboard_stats', array($self, 'dashboard_stats_shortcode') );
    }

    public function dashboard_stats_shortcode() {
        $unique_key = 'dashboard_stats' . uniqid();
        return '<div class="dashboard_stats" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
