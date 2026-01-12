<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MLMTeamAPI {

    private $namespace = 'affiliate-bloom/v1';
    private $jwt_auth;

    public static function init() {
        $instance = new self();
        return $instance;
    }

    public function __construct() {
        $this->jwt_auth = new JWTAuth();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // Get team statistics
        register_rest_route( $this->namespace, '/team/stats', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_team_stats' ),
            'permission_callback' => array( $this, 'check_permission' )
        ) );

        // Get team members
        register_rest_route( $this->namespace, '/team/members', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_team_members' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args' => array(
                'level' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'description' => 'Filter by specific level (1-9)'
                ),
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                    'sanitize_callback' => 'absint'
                ),
                'offset' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint'
                )
            )
        ) );

        // Get MLM earnings
        register_rest_route( $this->namespace, '/team/earnings', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_mlm_earnings' ),
            'permission_callback' => array( $this, 'check_permission' )
        ) );

        // Get MLM commission history
        register_rest_route( $this->namespace, '/team/commissions', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_commission_history' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args' => array(
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                    'sanitize_callback' => 'absint'
                ),
                'offset' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint'
                )
            )
        ) );

        // Get upline chain
        register_rest_route( $this->namespace, '/team/upline', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_upline' ),
            'permission_callback' => array( $this, 'check_permission' )
        ) );

        // Get commission rates
        register_rest_route( $this->namespace, '/team/commission-rates', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_commission_rates' ),
            'permission_callback' => '__return_true'
        ) );

        // Get direct referrals
        register_rest_route( $this->namespace, '/team/direct-referrals', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_direct_referrals' ),
            'permission_callback' => array( $this, 'check_permission' )
        ) );
    }

    /**
     * Check JWT permission
     */
    public function check_permission( $request ) {
        return $this->jwt_auth->authenticate_request( $request );
    }

    /**
     * Get current user from token
     */
    private function get_current_user_from_token( $request ) {
        $user = $this->jwt_auth->get_current_user_from_token( $request );
        return $user ? $user->ID : false;
    }

    /**
     * Get team statistics
     */
    public function get_team_stats( $request ) {
        $user_id = $this->get_current_user_from_token( $request );

        if ( ! $user_id ) {
            return new \WP_Error(
                'invalid_user',
                __( 'Could not identify user.', 'affiliate-bloom' ),
                array( 'status' => 401 )
            );
        }

        $mlm = MLMCommission::init();
        $stats = $mlm->get_team_stats( $user_id );

        return rest_ensure_response( array(
            'success' => true,
            'data' => $stats
        ) );
    }

    /**
     * Get team members
     */
    public function get_team_members( $request ) {
        $user_id = $this->get_current_user_from_token( $request );

        if ( ! $user_id ) {
            return new \WP_Error(
                'invalid_user',
                __( 'Could not identify user.', 'affiliate-bloom' ),
                array( 'status' => 401 )
            );
        }

        $level = $request->get_param( 'level' );
        $limit = $request->get_param( 'limit' ) ?: 50;
        $offset = $request->get_param( 'offset' ) ?: 0;

        $mlm = MLMCommission::init();
        $members = $mlm->get_team_members_detailed( $user_id, $level, $limit, $offset );

        return rest_ensure_response( array(
            'success' => true,
            'data' => $members
        ) );
    }

    /**
     * Get MLM earnings summary
     */
    public function get_mlm_earnings( $request ) {
        $user_id = $this->get_current_user_from_token( $request );

        if ( ! $user_id ) {
            return new \WP_Error(
                'invalid_user',
                __( 'Could not identify user.', 'affiliate-bloom' ),
                array( 'status' => 401 )
            );
        }

        $mlm = MLMCommission::init();
        $earnings = $mlm->get_user_mlm_earnings( $user_id );

        return rest_ensure_response( array(
            'success' => true,
            'data' => $earnings
        ) );
    }

    /**
     * Get commission history
     */
    public function get_commission_history( $request ) {
        $user_id = $this->get_current_user_from_token( $request );

        if ( ! $user_id ) {
            return new \WP_Error(
                'invalid_user',
                __( 'Could not identify user.', 'affiliate-bloom' ),
                array( 'status' => 401 )
            );
        }

        $limit = $request->get_param( 'limit' ) ?: 50;
        $offset = $request->get_param( 'offset' ) ?: 0;

        $mlm = MLMCommission::init();
        $history = $mlm->get_user_mlm_commission_history( $user_id, $limit, $offset );

        return rest_ensure_response( array(
            'success' => true,
            'data' => $history
        ) );
    }

    /**
     * Get upline chain
     */
    public function get_upline( $request ) {
        $user_id = $this->get_current_user_from_token( $request );

        if ( ! $user_id ) {
            return new \WP_Error(
                'invalid_user',
                __( 'Could not identify user.', 'affiliate-bloom' ),
                array( 'status' => 401 )
            );
        }

        $mlm = MLMCommission::init();
        $upline_ids = $mlm->get_upline_chain( $user_id );

        // Enrich with user data
        $upline = array();
        foreach ( $upline_ids as $level => $sponsor_id ) {
            $user = get_user_by( 'ID', $sponsor_id );
            if ( $user ) {
                $upline[] = array(
                    'level' => $level,
                    'user_id' => $sponsor_id,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'commission_rate' => $mlm->get_level_commission_rate( $level )
                );
            }
        }

        return rest_ensure_response( array(
            'success' => true,
            'data' => $upline
        ) );
    }

    /**
     * Get commission rates
     */
    public function get_commission_rates( $request ) {
        $mlm = MLMCommission::init();
        $rates = $mlm->get_commission_rates();

        $formatted_rates = array();
        foreach ( $rates as $level => $rate ) {
            $formatted_rates[] = array(
                'level' => $level,
                'rate' => $rate,
                'description' => sprintf( 'Level %d - %s%%', $level, $rate )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'data' => array(
                'rates' => $formatted_rates,
                'total_levels' => count( $rates ),
                'total_percentage' => array_sum( $rates )
            )
        ) );
    }

    /**
     * Get direct referrals
     */
    public function get_direct_referrals( $request ) {
        $user_id = $this->get_current_user_from_token( $request );

        if ( ! $user_id ) {
            return new \WP_Error(
                'invalid_user',
                __( 'Could not identify user.', 'affiliate-bloom' ),
                array( 'status' => 401 )
            );
        }

        $mlm = MLMCommission::init();
        $direct = $mlm->get_direct_downline( $user_id );

        // Enrich with user data
        $referrals = array();
        foreach ( $direct as $member ) {
            $user = get_user_by( 'ID', $member['user_id'] );
            if ( $user ) {
                $referrals[] = array(
                    'user_id' => $member['user_id'],
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'joined_date' => $member['created_date'],
                    'affiliate_status' => get_user_meta( $member['user_id'], 'affiliate_status', true )
                );
            }
        }

        return rest_ensure_response( array(
            'success' => true,
            'data' => array(
                'referrals' => $referrals,
                'total' => count( $referrals )
            )
        ) );
    }
}