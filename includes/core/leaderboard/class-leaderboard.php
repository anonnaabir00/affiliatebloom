<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Leaderboard {

    private static $instance = null;

    /**
     * Bangladesh divisions with their districts (zillas)
     */
    private $divisions = array(
        'Barishal' => array(
            'Barguna', 'Barishal', 'Bhola', 'Jhalokati', 'Patuakhali', 'Pirojpur'
        ),
        'Chattogram' => array(
            'Bandarban', 'Brahmanbaria', 'Chandpur', 'Chattogram', 'Comilla',
            'Cox\'s Bazar', 'Feni', 'Khagrachhari', 'Lakshmipur', 'Noakhali', 'Rangamati'
        ),
        'Dhaka' => array(
            'Dhaka', 'Faridpur', 'Gazipur', 'Gopalganj', 'Kishoreganj', 'Madaripur',
            'Manikganj', 'Munshiganj', 'Narayanganj', 'Narsingdi', 'Rajbari',
            'Shariatpur', 'Tangail'
        ),
        'Khulna' => array(
            'Bagerhat', 'Chuadanga', 'Jessore', 'Jhenaidah', 'Khulna', 'Kushtia',
            'Magura', 'Meherpur', 'Narail', 'Satkhira'
        ),
        'Mymensingh' => array(
            'Jamalpur', 'Mymensingh', 'Netrokona', 'Sherpur'
        ),
        'Rajshahi' => array(
            'Bogra', 'Chapainawabganj', 'Joypurhat', 'Naogaon', 'Natore', 'Nawabganj',
            'Pabna', 'Rajshahi', 'Sirajganj'
        ),
        'Rangpur' => array(
            'Dinajpur', 'Gaibandha', 'Kurigram', 'Lalmonirhat', 'Nilphamari',
            'Panchagarh', 'Rangpur', 'Thakurgaon'
        ),
        'Sylhet' => array(
            'Habiganj', 'Moulvibazar', 'Sunamganj', 'Sylhet'
        )
    );

    public static function init() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get all divisions
     */
    public function get_divisions() {
        return array_keys( $this->divisions );
    }

    /**
     * Get districts by division
     */
    public function get_districts_by_division( $division ) {
        return isset( $this->divisions[ $division ] ) ? $this->divisions[ $division ] : array();
    }

    /**
     * Get all districts
     */
    public function get_all_districts() {
        $all_districts = array();
        foreach ( $this->divisions as $division => $districts ) {
            foreach ( $districts as $district ) {
                $all_districts[ $district ] = $division;
            }
        }
        return $all_districts;
    }

    /**
     * Get division for a district
     */
    public function get_division_for_district( $district ) {
        foreach ( $this->divisions as $division => $districts ) {
            if ( in_array( $district, $districts, true ) ) {
                return $division;
            }
        }
        return null;
    }

    /**
     * Get leaderboard data with filters
     *
     * @param array $args {
     *     Optional. Arguments for filtering the leaderboard.
     *
     *     @type string $division     Filter by division name.
     *     @type string $zilla        Filter by zilla/district name.
     *     @type int    $limit        Number of results to return. Default 50.
     *     @type int    $offset       Offset for pagination. Default 0.
     *     @type string $order_by     Field to order by: 'team_size', 'team_purchased_value'. Default 'team_purchased_value'.
     *     @type string $order        Order direction: 'ASC' or 'DESC'. Default 'DESC'.
     *     @type string $start_date   Filter orders from this date (YYYY-MM-DD).
     *     @type string $end_date     Filter orders up to this date (YYYY-MM-DD).
     * }
     * @return array Leaderboard data with rankings.
     */
    public function get_leaderboard( $args = array() ) {
        $defaults = array(
            'division'   => '',
            'zilla'      => '',
            'limit'      => 50,
            'offset'     => 0,
            'order_by'   => 'team_purchased_value',
            'order'      => 'DESC',
            'start_date' => '',
            'end_date'   => ''
        );

        $args = wp_parse_args( $args, $defaults );

        // Get all approved affiliates
        $affiliates = $this->get_filtered_affiliates( $args['division'], $args['zilla'] );

        if ( empty( $affiliates ) ) {
            return array(
                'leaderboard' => array(),
                'total'       => 0,
                'limit'       => $args['limit'],
                'offset'      => $args['offset'],
                'filters'     => array(
                    'division' => $args['division'],
                    'zilla'    => $args['zilla']
                )
            );
        }

        // Calculate metrics for each affiliate
        $leaderboard_data = array();
        $mlm = MLMCommission::init();

        $start_date = $this->normalize_date( $args['start_date'], false );
        $end_date = $this->normalize_date( $args['end_date'], true );

        foreach ( $affiliates as $affiliate ) {
            $user_id = $affiliate->ID;
            $user = get_user_by( 'ID', $user_id );

            if ( ! $user ) {
                continue;
            }

            // Get team size
            $team_size = $this->get_team_size( $user_id );

            // Get team purchased value
            $team_purchased_value = $this->get_team_purchased_value( $user_id, $start_date, $end_date );

            // Get user's full name
            $full_name = $this->get_user_full_name( $user );

            $leaderboard_data[] = array(
                'user_id'              => $user_id,
                'full_name'            => $full_name,
                'team_size'            => $team_size,
                'team_purchased_value' => $team_purchased_value,
                'zilla'                => get_user_meta( $user_id, 'zilla', true ),
                'division'             => $this->get_division_for_district( get_user_meta( $user_id, 'zilla', true ) )
            );
        }

        // Sort by the specified field
        $order_by = $args['order_by'];
        $order = strtoupper( $args['order'] ) === 'ASC' ? SORT_ASC : SORT_DESC;

        usort( $leaderboard_data, function( $a, $b ) use ( $order_by, $order ) {
            $val_a = isset( $a[ $order_by ] ) ? $a[ $order_by ] : 0;
            $val_b = isset( $b[ $order_by ] ) ? $b[ $order_by ] : 0;

            if ( $val_a == $val_b ) {
                return 0;
            }

            if ( $order === SORT_ASC ) {
                return $val_a < $val_b ? -1 : 1;
            } else {
                return $val_a > $val_b ? -1 : 1;
            }
        } );

        // Add position/rank
        $position = 1;
        foreach ( $leaderboard_data as &$entry ) {
            $entry['position'] = $position;
            $position++;
        }

        $total = count( $leaderboard_data );

        // Apply pagination
        $leaderboard_data = array_slice( $leaderboard_data, $args['offset'], $args['limit'] );

        return array(
            'leaderboard' => $leaderboard_data,
            'total'       => $total,
            'limit'       => $args['limit'],
            'offset'      => $args['offset'],
            'filters'     => array(
                'division' => $args['division'],
                'zilla'    => $args['zilla']
            )
        );
    }

    /**
     * Get filtered affiliates based on division and zilla
     */
    private function get_filtered_affiliates( $division = '', $zilla = '' ) {
        $meta_query = array(
            'relation' => 'AND',
            array(
                'key'     => 'affiliate_status',
                'value'   => 'approved',
                'compare' => '='
            )
        );

        // Filter by zilla if provided
        if ( ! empty( $zilla ) ) {
            $meta_query[] = array(
                'key'     => 'zilla',
                'value'   => $zilla,
                'compare' => '='
            );
        } elseif ( ! empty( $division ) ) {
            // Filter by division (get all districts in the division)
            $districts = $this->get_districts_by_division( $division );
            if ( ! empty( $districts ) ) {
                $meta_query[] = array(
                    'key'     => 'zilla',
                    'value'   => $districts,
                    'compare' => 'IN'
                );
            }
        }

        $args = array(
            'meta_query' => $meta_query,
            'fields'     => 'all'
        );

        $user_query = new \WP_User_Query( $args );

        return $user_query->get_results();
    }

    /**
     * Get team size for a user (Level 1 / direct referrals only)
     */
    private function get_team_size( $user_id ) {
        global $wpdb;

        // Query directly for level 1 members only (direct referrals)
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_hierarchy WHERE sponsor_id = %d",
            $user_id
        ) );

        return intval( $count );
    }

    /**
     * Get total purchased value of user's Level 1 team members only
     */
    private function get_team_purchased_value( $user_id, $start_date = null, $end_date = null ) {
        global $wpdb;

        // Get only level 1 members (direct referrals) from hierarchy table
        $direct_members = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}affiliate_bloom_hierarchy WHERE sponsor_id = %d",
            $user_id
        ) );

        if ( empty( $direct_members ) ) {
            return 0.0;
        }

        $total_value = 0.0;

        foreach ( $direct_members as $member_id ) {
            $member_value = $this->get_user_purchased_value( $member_id, $start_date, $end_date );
            $total_value += $member_value;
        }

        return $total_value;
    }

    /**
     * Get purchased value for a single user
     */
    private function get_user_purchased_value( $user_id, $start_date = null, $end_date = null ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return 0.0;
        }

        $date_query = null;
        if ( $start_date || $end_date ) {
            $date_query = array();
            if ( $start_date ) {
                $date_query['after'] = $start_date;
            }
            if ( $end_date ) {
                $date_query['before'] = $end_date;
            }
            $date_query['inclusive'] = true;
        }

        $paid_statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
        $paid_statuses = apply_filters( 'affiliate_bloom_leaderboard_order_statuses', $paid_statuses );

        $orders = wc_get_orders( array(
            'customer_id'  => $user_id,
            'status'       => $paid_statuses,
            'limit'        => -1,
            'return'       => 'ids',
            'date_created' => $date_query
        ) );

        $total = 0.0;
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $total += floatval( $order->get_total() );
            }
        }

        return $total;
    }

    /**
     * Get user full name
     */
    private function get_user_full_name( $user ) {
        $first_name = $user->first_name;
        $last_name = $user->last_name;

        if ( ! empty( $first_name ) || ! empty( $last_name ) ) {
            return trim( $first_name . ' ' . $last_name );
        }

        return $user->display_name;
    }

    /**
     * Normalize date for queries
     */
    private function normalize_date( $date, $end_of_day = false ) {
        if ( empty( $date ) ) {
            return null;
        }

        $timestamp = strtotime( $date );
        if ( ! $timestamp ) {
            return null;
        }

        $formatted = wp_date( 'Y-m-d', $timestamp );
        return $formatted . ( $end_of_day ? ' 23:59:59' : ' 00:00:00' );
    }

    /**
     * Get user's position in leaderboard
     */
    public function get_user_position( $user_id, $args = array() ) {
        $args['limit'] = PHP_INT_MAX;
        $args['offset'] = 0;

        $leaderboard = $this->get_leaderboard( $args );

        foreach ( $leaderboard['leaderboard'] as $entry ) {
            if ( $entry['user_id'] == $user_id ) {
                return $entry['position'];
            }
        }

        return null;
    }
}
