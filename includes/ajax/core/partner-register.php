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
        // Verify nonce (uncomment when needed)
        // if ( ! wp_verify_nonce( $_POST['nonce'], 'affiliate_bloom_nonce' ) ) {
        //     wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        // }

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

        // Additional affiliate fields (optional)
        $monthly_visitors = sanitize_text_field( $_POST['monthly_visitors'] ?? '' );
        $niche = sanitize_text_field( $_POST['niche'] ?? '' );
        $social_media = sanitize_textarea_field( $_POST['social_media'] ?? '' );
        $affiliate_experience = sanitize_text_field( $_POST['affiliate_experience'] ?? '' );
        $other_programs = sanitize_textarea_field( $_POST['other_programs'] ?? '' );
        $promotion_methods = sanitize_textarea_field( $_POST['promotion_methods'] ?? 'Will be provided after registration' );
        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? '' );
        $paypal_email = sanitize_email( $_POST['paypal_email'] ?? '' );
        $bank_details = sanitize_textarea_field( $_POST['bank_details'] ?? '' );
        $marketing_emails = isset( $_POST['marketing_emails'] ) ? 1 : 0;

        // Validation
        $errors = array();

        if ( empty( $username ) ) {
            $errors[] = __('Username is required.', 'affiliate-bloom');
        } elseif ( username_exists( $username ) ) {
            $errors[] = __('Username already exists.', 'affiliate-bloom');
        } elseif ( ! validate_username( $username ) ) {
            $errors[] = __('Invalid username format.', 'affiliate-bloom');
        }

        if ( empty( $email ) ) {
            $errors[] = __('Email is required.', 'affiliate-bloom');
        } elseif ( ! is_email( $email ) ) {
            $errors[] = __('Invalid email format.', 'affiliate-bloom');
        } elseif ( email_exists( $email ) ) {
            // Check if user already has affiliate status
            $user = get_user_by('email', $email);
            $existing_status = get_user_meta($user->ID, 'affiliate_status', true);

            if ($existing_status === 'approved') {
                $errors[] = __('This email is already registered as an approved affiliate.', 'affiliate-bloom');
            } elseif ($existing_status === 'pending') {
                $errors[] = __('An application with this email is already pending review.', 'affiliate-bloom');
            } else {
                $errors[] = __('Email already exists.', 'affiliate-bloom');
            }
        }

        if ( empty( $password ) ) {
            $errors[] = __('Password is required.', 'affiliate-bloom');
        } elseif ( strlen( $password ) < 6 ) {
            $errors[] = __('Password must be at least 6 characters long.', 'affiliate-bloom');
        }

        if ( $password !== $confirm_password ) {
            $errors[] = __('Passwords do not match.', 'affiliate-bloom');
        }

        if ( empty( $first_name ) ) {
            $errors[] = __('First name is required.', 'affiliate-bloom');
        }

        if ( empty( $last_name ) ) {
            $errors[] = __('Last name is required.', 'affiliate-bloom');
        }

        if ( ! $terms ) {
            $errors[] = __('You must accept the terms and conditions.', 'affiliate-bloom');
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
            'display_name' => $first_name . ' ' . $last_name,
            'role'       => 'subscriber',
        );

        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array(
                'message' => $user_id->get_error_message()
            ));
        }

        // Add user meta for affiliate data
        update_user_meta( $user_id, 'affiliate_phone', $phone );
        update_user_meta( $user_id, 'affiliate_website_url', $website );
        update_user_meta( $user_id, 'affiliate_payment_method', $payment_method );
        update_user_meta( $user_id, 'affiliate_paypal_email', $paypal_email );
        update_user_meta( $user_id, 'affiliate_status', 'approved' );
        update_user_meta( $user_id, 'registration_date', current_time( 'mysql' ) );
        update_user_meta( $user_id, 'is_affiliate_partner', true );

        // Prepare affiliate application data
        $application_data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'address' => '', // Can be added to form if needed
            'website_url' => $website,
            'monthly_visitors' => $monthly_visitors,
            'niche' => $niche,
            'social_media' => $social_media,
            'affiliate_experience' => $affiliate_experience,
            'other_programs' => $other_programs,
            'promotion_methods' => $promotion_methods,
            'payment_method' => $payment_method,
            'paypal_email' => $paypal_email,
            'bank_details' => $bank_details,
            'marketing_emails' => $marketing_emails,
            'application_date' => current_time('mysql'),
            'status' => 'approved',
            'reviewed_date' => current_time('mysql'),
            'reviewed_by' => 1
        );

        // Save to affiliate applications table
        $application_id = $this->save_affiliate_application($application_data);

        if ($application_id) {
            // Link application to user
            update_user_meta($user_id, 'affiliate_application_id', $application_id);

            // Send notification emails
            $this->send_registration_emails( $user_id, $user_data, $application_data, $application_id );

            // Auto-login user (optional)
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id );

            wp_send_json_success( array(
                'message' => __('Registration successful! Your affiliate account has been approved. You can start promoting products immediately.', 'affiliate-bloom'),
                'redirect_url' => home_url(),
                'application_id' => $application_id
            ));
        } else {
            wp_send_json_error( array(
                'message' => __('Registration successful but failed to save affiliate application. Please contact support.', 'affiliate-bloom')
            ));
        }
    }

    private function save_affiliate_application($data) {
        global $wpdb;

        // Ensure the table exists
        $this->maybe_create_application_table();

        $result = $wpdb->insert(
            $wpdb->prefix . 'affiliate_bloom_applications',
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    private function maybe_create_application_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affiliate_bloom_applications';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20),
            address text,
            website_url varchar(255) NOT NULL,
            monthly_visitors varchar(50),
            niche varchar(100),
            social_media text,
            affiliate_experience varchar(50),
            other_programs text,
            promotion_methods text NOT NULL,
            payment_method varchar(50),
            paypal_email varchar(255),
            bank_details text,
            marketing_emails tinyint(1) DEFAULT 0,
            application_date datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'pending',
            admin_notes text,
            reviewed_by int(11),
            reviewed_date datetime,
            PRIMARY KEY (id),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function send_registration_emails( $user_id, $user_data, $application_data, $application_id ) {
        // Send confirmation email to user
        $user_email = $user_data['user_email'];
        $user_name = $user_data['first_name'] . ' ' . $user_data['last_name'];

        $subject = __('Welcome! Your Affiliate Application Has Been Received', 'affiliate-bloom');
        $message = sprintf(
            __('Dear %s,

Welcome to our affiliate program!

Your account has been created successfully and your affiliate application (ID: %d) has been submitted for review.

Application Details:
- Name: %s
- Email: %s
- Website: %s
- Username: %s

Your application is currently pending review. We will get back to you within 2-3 business days with the approval status.

You can log in to your account using:
Username: %s
Password: [The password you created]

Dashboard: %s

If you have any questions, please contact our support team.

Best regards,
The Affiliate Team', 'affiliate-bloom'),
            $user_data['first_name'],
            $application_id,
            $user_name,
            $user_email,
            $application_data['website_url'],
            $user_data['user_login'],
            $user_data['user_login'],
            home_url('/affiliate-dashboard/')
        );

        wp_mail( $user_email, $subject, $message );

        // Send notification to admin
        $admin_email = get_option( 'admin_email' );
        $admin_subject = __('New Affiliate Registration & Application', 'affiliate-bloom');
        $admin_message = sprintf(
            __('A new affiliate has registered and submitted an application.

User Details:
- Name: %s
- Email: %s
- Username: %s
- User ID: %d

Application Details:
- Application ID: %d
- Website: %s
- Experience Level: %s
- Monthly Visitors: %s
- Niche: %s

Please review and approve their application in the admin panel.

Admin Panel: %s', 'affiliate-bloom'),
            $user_name,
            $user_email,
            $user_data['user_login'],
            $user_id,
            $application_id,
            $application_data['website_url'],
            $application_data['affiliate_experience'],
            $application_data['monthly_visitors'],
            $application_data['niche'],
            admin_url('admin.php?page=affiliate-bloom-applications')
        );

        wp_mail( $admin_email, $admin_subject, $admin_message );
    }

    // Utility method to get application by user ID
    public static function get_user_application($user_id) {
        global $wpdb;

        $application_id = get_user_meta($user_id, 'affiliate_application_id', true);
        if (!$application_id) {
            return false;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}affiliate_bloom_applications WHERE id = %d",
                $application_id
            ),
            ARRAY_A
        );
    }

    // Method to update application status
    public static function update_application_status($application_id, $status, $admin_notes = '', $reviewed_by = null) {
        global $wpdb;

        if (!$reviewed_by) {
            $reviewed_by = get_current_user_id();
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'affiliate_bloom_applications',
            array(
                'status' => $status,
                'admin_notes' => $admin_notes,
                'reviewed_by' => $reviewed_by,
                'reviewed_date' => current_time('mysql')
            ),
            array('id' => $application_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );

        // Update user meta status as well
        $application = $wpdb->get_row(
            $wpdb->prepare("SELECT email FROM {$wpdb->prefix}affiliate_bloom_applications WHERE id = %d", $application_id)
        );

        if ($application) {
            $user = get_user_by('email', $application->email);
            if ($user) {
                update_user_meta($user->ID, 'affiliate_status', $status);

                // Send status update email
                self::send_status_update_email($user->ID, $status, $admin_notes);
            }
        }

        return $result !== false;
    }

    private static function send_status_update_email($user_id, $status, $admin_notes = '') {
        $user = get_userdata($user_id);
        if (!$user) return;

        $subject = '';
        $message = '';

        switch ($status) {
            case 'approved':
                $subject = __('Congratulations! Your Affiliate Application Has Been Approved', 'affiliate-bloom');
                $message = sprintf(
                    __('Dear %s,

Great news! Your affiliate application has been approved.

You can now start promoting our products and earning commissions. Please log in to your dashboard to get started:
%s

%s

Welcome to our affiliate family!

Best regards,
The Affiliate Team', 'affiliate-bloom'),
                    $user->first_name,
                    home_url('/affiliate-dashboard/'),
                    $admin_notes ? "Additional Notes: " . $admin_notes : ''
                );
                break;

            case 'rejected':
                $subject = __('Update on Your Affiliate Application', 'affiliate-bloom');
                $message = sprintf(
                    __('Dear %s,

Thank you for your interest in our affiliate program.

Unfortunately, we cannot approve your application at this time.

%s

If you have any questions or would like to discuss this further, please contact our support team.

Best regards,
The Affiliate Team', 'affiliate-bloom'),
                    $user->first_name,
                    $admin_notes ? "Reason: " . $admin_notes : ''
                );
                break;
        }

        if ($subject && $message) {
            wp_mail($user->user_email, $subject, $message);
        }
    }
}