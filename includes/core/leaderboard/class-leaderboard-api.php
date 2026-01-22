<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LeaderboardAPI {

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
        // Get leaderboard rankings
        register_rest_route( $this->namespace, '/leaderboard', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_leaderboard' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'division' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Filter by division name (e.g., Dhaka, Chattogram)'
                ),
                'zilla' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Filter by zilla/district name'
                ),
                'limit' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 50,
                    'sanitize_callback' => 'absint',
                    'description'       => 'Number of results to return'
                ),
                'offset' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                    'description'       => 'Offset for pagination'
                ),
                'order_by' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'team_purchased_value',
                    'enum'              => array( 'team_size', 'team_purchased_value' ),
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Field to order by'
                ),
                'order' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'DESC',
                    'enum'              => array( 'ASC', 'DESC' ),
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Order direction'
                ),
                'start_date' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Filter orders from this date (YYYY-MM-DD)'
                ),
                'end_date' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Filter orders up to this date (YYYY-MM-DD)'
                )
            )
        ) );

        // Get current user's position in leaderboard
        register_rest_route( $this->namespace, '/leaderboard/my-position', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_my_position' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'division' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Filter by division name'
                ),
                'zilla' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Filter by zilla/district name'
                ),
                'order_by' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'team_purchased_value',
                    'enum'              => array( 'team_size', 'team_purchased_value' ),
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Field to order by'
                )
            )
        ) );

        // Get list of divisions
        register_rest_route( $this->namespace, '/leaderboard/divisions', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_divisions' ),
            'permission_callback' => '__return_true'
        ) );

        // Get list of districts (optionally filtered by division)
        register_rest_route( $this->namespace, '/leaderboard/districts', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_districts' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'division' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Filter districts by division name'
                )
            )
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
     * Get leaderboard rankings
     */
    public function get_leaderboard( $request ) {
        $leaderboard = Leaderboard::init();

        $args = array(
            'division'   => $request->get_param( 'division' ) ?: '',
            'zilla'      => $request->get_param( 'zilla' ) ?: '',
            'limit'      => $request->get_param( 'limit' ) ?: 50,
            'offset'     => $request->get_param( 'offset' ) ?: 0,
            'order_by'   => $request->get_param( 'order_by' ) ?: 'team_purchased_value',
            'order'      => $request->get_param( 'order' ) ?: 'DESC',
            'start_date' => $request->get_param( 'start_date' ) ?: '',
            'end_date'   => $request->get_param( 'end_date' ) ?: ''
        );

        $data = $leaderboard->get_leaderboard( $args );

        return rest_ensure_response( array(
            'success' => true,
            'data'    => $data
        ) );
    }

    /**
     * Get current user's position in leaderboard
     */
    public function get_my_position( $request ) {
        $user_id = $this->get_current_user_from_token( $request );

        if ( ! $user_id ) {
            return new \WP_Error(
                'invalid_user',
                __( 'Could not identify user.', 'affiliate-bloom' ),
                array( 'status' => 401 )
            );
        }

        $leaderboard = Leaderboard::init();

        $args = array(
            'division' => $request->get_param( 'division' ) ?: '',
            'zilla'    => $request->get_param( 'zilla' ) ?: '',
            'order_by' => $request->get_param( 'order_by' ) ?: 'team_purchased_value',
            'order'    => 'DESC'
        );

        // Get full leaderboard to find position
        $args['limit'] = PHP_INT_MAX;
        $args['offset'] = 0;

        $data = $leaderboard->get_leaderboard( $args );

        $user_position = null;
        $user_data = null;

        foreach ( $data['leaderboard'] as $entry ) {
            if ( $entry['user_id'] == $user_id ) {
                $user_position = $entry['position'];
                $user_data = $entry;
                break;
            }
        }

        return rest_ensure_response( array(
            'success'  => true,
            'data'     => array(
                'position'             => $user_position,
                'user_id'              => $user_id,
                'full_name'            => $user_data ? $user_data['full_name'] : null,
                'team_size'            => $user_data ? $user_data['team_size'] : 0,
                'team_purchased_value' => $user_data ? $user_data['team_purchased_value'] : 0,
                'total_participants'   => $data['total'],
                'filters'              => array(
                    'division' => $args['division'],
                    'zilla'    => $args['zilla']
                )
            )
        ) );
    }

    /**
     * Get list of divisions
     */
    public function get_divisions( $request ) {
        $leaderboard = Leaderboard::init();
        $divisions = $leaderboard->get_divisions();

        return rest_ensure_response( array(
            'success' => true,
            'data'    => array(
                'divisions' => $divisions,
                'total'     => count( $divisions )
            )
        ) );
    }

    /**
     * Get list of districts
     */
    public function get_districts( $request ) {
        $leaderboard = Leaderboard::init();
        $division = $request->get_param( 'division' );

        if ( ! empty( $division ) ) {
            $districts = $leaderboard->get_districts_by_division( $division );
            return rest_ensure_response( array(
                'success' => true,
                'data'    => array(
                    'division'  => $division,
                    'districts' => $districts,
                    'total'     => count( $districts )
                )
            ) );
        }

        // Return all districts grouped by division
        $all_data = array();
        foreach ( $leaderboard->get_divisions() as $div ) {
            $all_data[ $div ] = $leaderboard->get_districts_by_division( $div );
        }

        return rest_ensure_response( array(
            'success' => true,
            'data'    => array(
                'districts_by_division' => $all_data,
                'total_divisions'       => count( $all_data )
            )
        ) );
    }
}
