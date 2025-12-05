<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LoginRestrict {

    public static function init() {
        $self = new self();
        return $self;
    }

    public function __construct() {
        add_action('template_redirect', array($this, 'restrict_page_access'));
        add_action('wp_login', array($this, 'handle_user_login'), 10, 2);
    }

    public function restrict_page_access() {
        // Check if user is not logged in
        if (!is_user_logged_in()) {
            // Get current request URI
            $request_uri = $_SERVER['REQUEST_URI'];

            // Parse the URL to get the path
            $parsed_url = parse_url($request_uri);
            $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

            // Remove trailing slash for consistent comparison
            $path = rtrim($path, '/');

            // Define allowed paths for non-logged-in users
            $allowed_paths = array(
                '/login',
                '/register',
                '/wp-admin/admin-ajax.php', // Allow AJAX requests
            );

            // Check if current path is in allowed paths
            $is_allowed = false;
            foreach ($allowed_paths as $allowed_path) {
                if (strpos($path, $allowed_path) !== false) {
                    $is_allowed = true;
                    break;
                }
            }

            // Also allow if it's the homepage and you want to show login there
            if ($path === '' || $path === '/') {
                // Optionally redirect homepage to login
                // $is_allowed = false;
            }

            // If not allowed, redirect to login page
            if (!$is_allowed) {
                wp_redirect(home_url('/login'));
                exit;
            }
        }
    }

    /**
     * Handle user login actions
     */
    public function handle_user_login($user_login, $user) {
        // Add any custom login handling here
        // For example, redirect to a specific page after login
        // wp_redirect(home_url('/dashboard'));
    }
}