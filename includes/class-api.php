<?php
namespace AffiliateBloom;
use AffiliateBloom\JWTAuth;
use AffiliateBloom\Dashboard;
use AffiliateBloom\AffiliateLinksAPI;
use AffiliateBloom\ConversionTracker;
use AffiliateBloom\MLMCommission;
use AffiliateBloom\MLMTeamAPI;
use AffiliateBloom\Leaderboard;
use AffiliateBloom\LeaderboardAPI;

if (!defined('ABSPATH')) {
    exit;
}

class API {

    public static function init() {
        ConversionTracker::init();
        JWTAuth::init();
        Dashboard::init();
        AffiliateLinksAPI::init();
        MLMCommission::init();
        MLMTeamAPI::init();
        Leaderboard::init();
        LeaderboardAPI::init();
        $instance = new self();
        return $instance;
    }
}