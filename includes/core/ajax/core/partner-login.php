<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PartnerLogin {

    public static function init() {
        $self = new self();
        add_action( 'wp_ajax_affiliate_bloom_partner_login', array( $self, 'affiliate_bloom_partner_login' ) );
        add_action( 'wp_ajax_nopriv_affiliate_bloom_partner_login', array( $self, 'affiliate_bloom_partner_login' ) );
    }

    public static function api_init() {
            $self = new self();

            add_action('rest_api_init', function() use ($self) {
                // Get all backups
                register_rest_route($self->namespace, '/backups', [
                    'methods'             => 'GET',
                    'callback'            => [$self, 'api_get_backups'],
                    'permission_callback' => [$self, 'api_check_permission'],
                ]);
            });
        }

    public function affiliate_bloom_partner_login() {
        // Verify nonce
//         if ( ! wp_verify_nonce( $_POST['nonce'], 'affiliate_bloom_nonce' ) ) {
//             wp_send_json_error( array( 'message' => 'Security check failed.' ) );
//         }

        $username = sanitize_text_field( $_POST['username'] );
        $password = $_POST['password'];
        $remember = isset( $_POST['remember'] ) ? true : false;

        // Attempt login
        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember
        );

        $user = wp_signon( $creds, false );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( array(
                'message' => $user->get_error_message()
            ));
        } else {
            wp_send_json_success( array(
                'message' => 'Login successful!',
                'redirect_url' => home_url( '/dashboard/' )
            ));
        }
    }
}
