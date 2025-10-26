<?php
namespace AffiliateBloom;
use AffiliateBloom\ConversionTracker;

if (!defined('ABSPATH')) {
    exit;
}

class API {

    public static function init() {
        ConversionTracker::init();
        $instance = new self();
        return $instance;
    }
}