<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Enqueue {

	public static function init() {
		$self = new self();
		add_action( 'admin_enqueue_scripts', array( $self, 'afbloom_scripts' ) );
	}

	public function afbloom_scripts() {
			wp_enqueue_style( 'affiliate-bloom-style', AFFILIATE_BLOOM_ROOT_DIR_URL . 'includes/assets/build/main.css' );
			wp_enqueue_script( 'affiliate-bloom-script', AFFILIATE_BLOOM_ROOT_DIR_URL . 'includes/assets/build/main.js', 'jquery', '0.0.1', true );
			wp_localize_script(
				'affiliate-bloom-script',
				'affiliate_bloom_settings',
				array(
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'frohub_nonce' ),
				)
			);

		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_script' ), 10, 3 );
	}

	public function add_module_type_to_script( $tag, $handle, $src ) {
		if ( 'affiliate-bloom-script' === $handle ) {
			$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
		}
		return $tag;
	}
}
