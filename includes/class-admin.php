<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public static function init() {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'add_admin_menu' ) );
	}

	public function add_admin_menu() {
		$parent = 'affiliate-bloom-admin';

		add_menu_page(
			__( 'Proyozone', 'affiliate-bloom' ),
			'Proyozone',
			'manage_options',
			$parent,
			array( $this, 'afbloom_callback' ),
			plugin_dir_url( __FILE__ ) . 'library/icon-16x16.png',
			30
		);
	}

	public function afbloom_callback() {
		?>
		<div id="affiliate-bloom-admin"></div>
		<?php
	}
}