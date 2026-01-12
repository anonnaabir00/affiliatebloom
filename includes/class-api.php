<?php
namespace AffiliateBloom;
use AffiliateBloom\JWTAuth;
use AffiliateBloom\Dashboard;
use AffiliateBloom\AffiliateLinksAPI;
use AffiliateBloom\ConversionTracker;

if (!defined('ABSPATH')) {
    exit;
}

class API {

    public static function init() {
        ConversionTracker::init();
        JWTAuth::init();
        Dashboard::init();
        AffiliateLinksAPI::init();
        $instance = new self();
        return $instance;
    }
}