<?php

	/**
	 *
	 * @link              https://affiliatebloom.com/
	 * @since             1.0.4
	 * @package           Affiliate Bloom Plugin
	 *
	 * @wordpress-plugin
	 * Plugin Name:       Affiliate Bloom
	 * Plugin URI:        https://affiliatebloom.com/
	 * Description:       Powerful affiliate marketing plugin with advanced tracking and management features
	 * Version:           1.0.4
	 * Author:            Affiliate Bloom
	 * Author URI:        https://affiliatebloom.com/
	 * License:           GPL-2.0+
	 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
	 * Text Domain:       affiliate-bloom
	 * Tested up to:      6.9
	 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AffiliateBloom {

	private function __construct() {
		$this->define_constants();
		$this->load_dependency();
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
	}

	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

			return $instance;
	}

	public function define_constants() {
		define( 'AFFILIATE_BLOOM_VERSION', '1.0.4' );
		define( 'AFFILIATE_BLOOM_PLUGIN_FILE', __FILE__ );
		define( 'AFFILIATE_BLOOM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'AFFILIATE_BLOOM_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
		define( 'AFFILIATE_BLOOM_ROOT_DIR_URL', plugin_dir_url( __FILE__ ) );
		define( 'AFFILIATE_BLOOM_INCLUDES_DIR_PATH', AFFILIATE_BLOOM_ROOT_DIR_PATH . 'includes/' );
		define( 'AFFILIATE_BLOOM_PLUGIN_SLUG', 'affiliate-bloom' );
	}

	public function on_plugins_loaded() {
		do_action( 'affiliate_bloom_loaded' );
	}

	public function init_plugin() {
		$this->load_textdomain();
		$this->dispatch_hooks();
	}

	public function dispatch_hooks() {
		AffiliateBloom\Autoload::init();
		AffiliateBloom\Database::init();
		AffiliateBloom\Enqueue::init();
		AffiliateBloom\Frontend::init();
		AffiliateBloom\Admin::init();
        AffiliateBloom\Ajax::init();
        AffiliateBloom\API::init();
        AffiliateBloom\Application::init();
        AffiliateBloom\AdminApplications::init();
        AffiliateBloom\Actions::init();
		AffiliateBloom\Shortcodes::init();
// 		AffiliateBloom\API::init();
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'affiliate-bloom',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	public function load_dependency() {
		require_once AFFILIATE_BLOOM_INCLUDES_DIR_PATH . 'class-autoload.php';
	}

	public function activate() {
	}

	public function deactivate() {
	}
}

function affiliate_bloom_start() {
	return AffiliateBloom::init();
}

affiliate_bloom_start();