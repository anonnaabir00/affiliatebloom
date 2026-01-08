<?php
namespace AffiliateBloom;
use AffiliateBloom\JWTAuth;
use AffiliateBloom\ConversionTracker;

if (!defined('ABSPATH')) {
    exit;
}

class API {

    public static function init() {
        ConversionTracker::init();
        JWTAuth::init();
        $instance = new self();
        return $instance;
    }
}