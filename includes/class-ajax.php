<?php
namespace AffiliateBloom;
use AffiliateBloom\LinkManager;
use AffiliateBloom\ReferralManager;
use AffiliateBloom\PartnerLogin;
use AffiliateBloom\PartnerRegister;

if (!defined('ABSPATH')) {
    exit;
}

class Ajax {

    public static function init() {
        LinkManager::init();
        ReferralManager::init();
        PartnerLogin::init();
        PartnerLogin::api_init();
        PartnerRegister::init();
        $instance = new self();
        return $instance;
    }
}