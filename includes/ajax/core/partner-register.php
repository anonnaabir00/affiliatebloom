<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PartnerRegister {

    public static function init() {
        $self = new self();
        add_action( 'wp_ajax_affiliate_bloom_partner_register', array( $self, 'affiliate_bloom_partner_register' ) );
        add_action( 'wp_ajax_nopriv_affiliate_bloom_partner_register', array( $self, 'affiliate_bloom_partner_register' ) );
    }

    public function affiliate_bloom_partner_register() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'affiliate_bloom_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }

        // Sanitize input data
        $username = sanitize_user( $_POST['username'] );
        $email = sanitize_email( $_POST['email'] );
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = sanitize_text_field( $_POST['first_name'] );
        $last_name = sanitize_text_field( $_POST['last_name'] );
        $phone = sanitize_text_field( $_POST['phone'] );
        $website = esc_url_raw( $_POST['website'] );
        $terms = isset( $_POST['terms'] ) ? true : false;

        // Validation
        $errors = array();

        if ( empty( $username ) ) {
            $errors[] = 'Username is required.';
        } elseif ( username_exists( $username ) ) {
            $errors[] = 'Username already exists.';
        } elseif ( ! validate_username( $username ) ) {
            $errors[] = 'Invalid username format.';
        }

        if ( empty( $email ) ) {
            $errors[] = 'Email is required.';
        } elseif ( ! is_email( $email ) ) {
            $errors[] = 'Invalid email format.';
        } elseif ( email_exists( $email ) ) {
            $errors[] = 'Email already exists.';
        }

        if ( empty( $password ) ) {
            $errors[] = 'Password is required.';
        } elseif ( strlen( $password ) < 6 ) {
            $errors[] = 'Password must be at least 6 characters long.';
        }

        if ( $password !== $confirm_password ) {
            $errors[] = 'Passwords do not match.';
        }

        if ( empty( $first_name ) ) {
            $errors[] = 'First name is required.';
        }

        if ( empty( $last_name ) ) {
            $errors[] = 'Last name is required.';
        }

        if ( ! $terms ) {
            $errors[] = 'You must accept the terms and conditions.';
        }

        // If there are errors, return them
        if ( ! empty( $errors ) ) {
            wp_send_json_error( array(
                'message' => implode( '<br>', $errors )
            ));
        }

        // Create user
        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'role'       => 'subscriber', // or custom partner role
        );

        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array(
                'message' => $user_id->get_error_message()
            ));
        }

        // Add custom user meta for partner-specific data
        if ( ! empty( $phone ) ) {
            update_user_meta( $user_id, 'phone', $phone );
        }
        if ( ! empty( $website ) ) {
            update_user_meta( $user_id, 'website', $website );
        }

        // Mark as partner
        update_user_meta( $user_id, 'is_affiliate_partner', true );
        update_user_meta( $user_id, 'partner_status', 'pending' ); // pending, approved, rejected
        update_user_meta( $user_id, 'registration_date', current_time( 'mysql' ) );

        // Send notification emails
        $this->send_registration_emails( $user_id, $user_data );

        // Auto-login user (optional)
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        wp_send_json_success( array(
            'message' => 'Registration successful! Welcome to our partner program.',
            'redirect_url' => home_url( '/partner-dashboard/' ) // Customize as needed
        ));
    }

    private function send_registration_emails( $user_id, $user_data ) {
        // Send welcome email to user
        $user_email = $user_data['user_email'];
        $user_name = $user_data['first_name'] . ' ' . $user_data['last_name'];

        $subject = 'Welcome to Our Partner Program';
        $message = "Hello {$user_name},\n\n";
        $message .= "Thank you for registering as a partner with us!\n\n";
        $message .= "Your account is currently pending approval. We'll notify you once it's activated.\n\n";
        $message .= "Best regards,\nThe Team";

        wp_mail( $user_email, $subject, $message );

        // Send notification to admin
        $admin_email = get_option( 'admin_email' );
        $admin_subject = 'New Partner Registration';
        $admin_message = "A new partner has registered:\n\n";
        $admin_message .= "Name: {$user_name}\n";
        $admin_message .= "Email: {$user_email}\n";
        $admin_message .= "Username: {$user_data['user_login']}\n\n";
        $admin_message .= "Please review and approve their account.";

        wp_mail( $admin_email, $admin_subject, $admin_message );
    }
}
