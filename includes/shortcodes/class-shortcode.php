<?php
namespace AffiliateBloom;

use AffiliateBloom\DashboardSidebar;
use AffiliateBloom\AffiliateLinks;
use AffiliateBloom\ReferralLinks;
use AffiliateBloom\LoginBonus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {

	public static function init() {
		$self = new self();
		DashboardSidebar::init();
		AffiliateLinks::init();
		ReferralLinks::init();
		LoginBonus::init();
	}
}
