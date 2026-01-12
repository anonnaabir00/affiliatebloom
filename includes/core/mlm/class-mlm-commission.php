<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MLMCommission {

    /**
     * Commission rates for each level (1-9)
     * Level 1: 30%, Level 2: 10%, Level 3: 5%, Level 4: 4%
     * Level 5: 3%, Level 6: 2%, Level 7: 1%, Level 8: 1%, Level 9: 1%
     */
    private $commission_rates = array(
        1 => 30,
        2 => 10,
        3 => 5,
        4 => 4,
        5 => 3,
        6 => 2,
        7 => 1,
        8 => 1,
        9 => 1
    );

    private static $instance = null;

    public static function init() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action( 'wp_ajax_affiliate_bloom_get_team_stats', array( $this, 'ajax_get_team_stats' ) );
        add_action( 'wp_ajax_affiliate_bloom_get_team_members', array( $this, 'ajax_get_team_members' ) );
        add_action( 'wp_ajax_affiliate_bloom_get_mlm_earnings', array( $this, 'ajax_get_mlm_earnings' ) );
    }

    /**
     * Get commission rates
     */
    public function get_commission_rates() {
        return apply_filters( 'affiliate_bloom_mlm_commission_rates', $this->commission_rates );
    }

    /**
     * Get commission rate for a specific level
     */
    public function get_level_commission_rate( $level ) {
        $rates = $this->get_commission_rates();
        return isset( $rates[ $level ] ) ? $rates[ $level ] : 0;
    }

    /**
     * Set sponsor for a user (called during registration)
     */
    public function set_user_sponsor( $user_id, $sponsor_id ) {
        global $wpdb;

        if ( $user_id == $sponsor_id ) {
            return false;
        }

        // Check if user already has a sponsor
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}affiliate_bloom_hierarchy WHERE user_id = %d",
            $user_id
        ) );

        if ( $existing ) {
            return false; // Already has sponsor
        }

        // Insert hierarchy record
        $result = $wpdb->insert(
            $wpdb->prefix . 'affiliate_bloom_hierarchy',
            array(
                'user_id' => $user_id,
                'sponsor_id' => $sponsor_id,
                'level' => 1,
                'created_date' => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%d', '%s' )
        );

        if ( $result ) {
            // Store in user meta as well for quick access
            update_user_meta( $user_id, 'affiliate_sponsor_id', $sponsor_id );

            do_action( 'affiliate_bloom_sponsor_set', $user_id, $sponsor_id );
        }

        return $result !== false;
    }

    /**
     * Get user's direct sponsor
     */
    public function get_user_sponsor( $user_id ) {
        global $wpdb;

        $sponsor_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT sponsor_id FROM {$wpdb->prefix}affiliate_bloom_hierarchy WHERE user_id = %d",
            $user_id
        ) );

        return $sponsor_id ? intval( $sponsor_id ) : false;
    }

    /**
     * Get upline chain (all sponsors up to 9 levels)
     * Returns array with level as key and user_id as value
     */
    public function get_upline_chain( $user_id, $max_levels = 9 ) {
        $upline = array();
        $current_user = $user_id;

        for ( $level = 1; $level <= $max_levels; $level++ ) {
            $sponsor_id = $this->get_user_sponsor( $current_user );

            if ( ! $sponsor_id ) {
                break;
            }

            $upline[ $level ] = $sponsor_id;
            $current_user = $sponsor_id;
        }

        return $upline;
    }

    /**
     * Get direct downline (users directly sponsored by this user)
     */
    public function get_direct_downline( $user_id ) {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, created_date FROM {$wpdb->prefix}affiliate_bloom_hierarchy
             WHERE sponsor_id = %d ORDER BY created_date DESC",
            $user_id
        ), ARRAY_A );

        return $results ?: array();
    }

    /**
     * Get all downline members up to specified levels
     */
    public function get_all_downline( $user_id, $max_levels = 9 ) {
        $downline = array();
        $current_level_users = array( $user_id );

        for ( $level = 1; $level <= $max_levels; $level++ ) {
            $next_level_users = array();

            foreach ( $current_level_users as $current_user_id ) {
                $direct = $this->get_direct_downline( $current_user_id );

                foreach ( $direct as $member ) {
                    $member['level'] = $level;
                    $member['sponsor_id'] = $current_user_id;
                    $downline[] = $member;
                    $next_level_users[] = $member['user_id'];
                }
            }

            if ( empty( $next_level_users ) ) {
                break;
            }

            $current_level_users = $next_level_users;
        }

        return $downline;
    }

    /**
     * Get downline count by level
     */
    public function get_downline_count_by_level( $user_id, $max_levels = 9 ) {
        $counts = array();
        $downline = $this->get_all_downline( $user_id, $max_levels );

        for ( $level = 1; $level <= $max_levels; $level++ ) {
            $counts[ $level ] = 0;
        }

        foreach ( $downline as $member ) {
            if ( isset( $counts[ $member['level'] ] ) ) {
                $counts[ $member['level'] ]++;
            }
        }

        return $counts;
    }

    /**
     * Calculate and distribute MLM commissions for a conversion
     */
    public function process_mlm_commissions( $conversion_id, $source_user_id, $order_amount, $order_id = null ) {
        global $wpdb;

        // Get upline chain
        $upline = $this->get_upline_chain( $source_user_id );

        if ( empty( $upline ) ) {
            return array(); // No upline, no MLM commissions
        }

        $commissions_distributed = array();

        foreach ( $upline as $level => $beneficiary_id ) {
            $commission_rate = $this->get_level_commission_rate( $level );

            if ( $commission_rate <= 0 ) {
                continue;
            }

            $commission_amount = ( $order_amount * $commission_rate ) / 100;

            // Insert MLM commission record
            $result = $wpdb->insert(
                $wpdb->prefix . 'affiliate_bloom_mlm_commissions',
                array(
                    'conversion_id' => $conversion_id,
                    'order_id' => $order_id,
                    'beneficiary_id' => $beneficiary_id,
                    'source_user_id' => $source_user_id,
                    'level' => $level,
                    'commission_rate' => $commission_rate,
                    'commission_amount' => $commission_amount,
                    'status' => 'pending',
                    'created_date' => current_time( 'mysql' )
                ),
                array( '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%s', '%s' )
            );

            if ( $result ) {
                $commission_record = array(
                    'id' => $wpdb->insert_id,
                    'beneficiary_id' => $beneficiary_id,
                    'level' => $level,
                    'rate' => $commission_rate,
                    'amount' => $commission_amount
                );

                $commissions_distributed[] = $commission_record;

                // Update user's pending MLM balance
                $this->update_user_mlm_pending_balance( $beneficiary_id, $commission_amount );

                // Log the transaction
                $this->log_mlm_commission_transaction( $beneficiary_id, $source_user_id, $commission_amount, $level );

                do_action( 'affiliate_bloom_mlm_commission_added', $beneficiary_id, $source_user_id, $commission_amount, $level );
            }
        }

        return $commissions_distributed;
    }

    /**
     * Update user's pending MLM balance
     */
    private function update_user_mlm_pending_balance( $user_id, $amount ) {
        $current = get_user_meta( $user_id, 'affiliate_mlm_pending_balance', true ) ?: 0;
        $new_balance = floatval( $current ) + floatval( $amount );
        update_user_meta( $user_id, 'affiliate_mlm_pending_balance', $new_balance );
    }

    /**
     * Log MLM commission transaction
     */
    private function log_mlm_commission_transaction( $beneficiary_id, $source_user_id, $amount, $level ) {
        $transaction_history = get_user_meta( $beneficiary_id, 'affiliate_transaction_history', true );
        if ( ! is_array( $transaction_history ) ) {
            $transaction_history = array();
        }

        $source_user = get_user_by( 'ID', $source_user_id );
        $source_username = $source_user ? $source_user->user_login : 'Unknown';

        $transaction = array(
            'id' => uniqid( 'mlm_' ),
            'type' => 'mlm_commission',
            'amount' => $amount,
            'status' => 'pending',
            'description' => sprintf( 'Level %d commission from %s', $level, $source_username ),
            'source_user_id' => $source_user_id,
            'level' => $level,
            'created_date' => current_time( 'mysql' ),
            'timestamp' => current_time( 'timestamp' )
        );

        $transaction_history[] = $transaction;
        update_user_meta( $beneficiary_id, 'affiliate_transaction_history', $transaction_history );
    }

    /**
     * Get user's MLM earnings summary
     */
    public function get_user_mlm_earnings( $user_id ) {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT level,
                    COUNT(*) as count,
                    SUM(commission_amount) as total,
                    SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN commission_amount ELSE 0 END) as approved
             FROM {$wpdb->prefix}affiliate_bloom_mlm_commissions
             WHERE beneficiary_id = %d
             GROUP BY level
             ORDER BY level ASC",
            $user_id
        ), ARRAY_A );

        $earnings = array(
            'by_level' => array(),
            'total' => 0,
            'pending' => 0,
            'approved' => 0
        );

        foreach ( $results as $row ) {
            $earnings['by_level'][ $row['level'] ] = array(
                'count' => intval( $row['count'] ),
                'total' => floatval( $row['total'] ),
                'pending' => floatval( $row['pending'] ),
                'approved' => floatval( $row['approved'] )
            );
            $earnings['total'] += floatval( $row['total'] );
            $earnings['pending'] += floatval( $row['pending'] );
            $earnings['approved'] += floatval( $row['approved'] );
        }

        return $earnings;
    }

    /**
     * Get user's MLM commission history
     */
    public function get_user_mlm_commission_history( $user_id, $limit = 50, $offset = 0 ) {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT mc.*, u.user_login as source_username, u.user_email as source_email
             FROM {$wpdb->prefix}affiliate_bloom_mlm_commissions mc
             LEFT JOIN {$wpdb->users} u ON mc.source_user_id = u.ID
             WHERE mc.beneficiary_id = %d
             ORDER BY mc.created_date DESC
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ), ARRAY_A );

        return $results ?: array();
    }

    /**
     * Get team statistics for a user
     */
    public function get_team_stats( $user_id ) {
        $downline_counts = $this->get_downline_count_by_level( $user_id );
        $mlm_earnings = $this->get_user_mlm_earnings( $user_id );
        $direct_downline = $this->get_direct_downline( $user_id );

        $total_team = array_sum( $downline_counts );

        return array(
            'total_team_members' => $total_team,
            'direct_referrals' => count( $direct_downline ),
            'team_by_level' => $downline_counts,
            'mlm_earnings' => $mlm_earnings,
            'commission_rates' => $this->get_commission_rates()
        );
    }

    /**
     * Get detailed team members with user info
     */
    public function get_team_members_detailed( $user_id, $level = null, $limit = 50, $offset = 0, $start_date = null, $end_date = null ) {
        $all_downline = $this->get_all_downline( $user_id );

        if ( $level !== null ) {
            $all_downline = array_filter( $all_downline, function( $member ) use ( $level ) {
                return $member['level'] == $level;
            } );
        }

        // Apply pagination
        $total = count( $all_downline );
        $all_downline = array_slice( $all_downline, $offset, $limit );

        // Enrich with user data
        $members = array();
        $start_date = $this->normalize_date( $start_date, false );
        $end_date = $this->normalize_date( $end_date, true );

        foreach ( $all_downline as $member ) {
            $user = get_user_by( 'ID', $member['user_id'] );
            if ( $user ) {
                $order_stats = $this->get_order_stats_for_user( $member['user_id'], $start_date, $end_date );
                $members[] = array(
                    'user_id' => $member['user_id'],
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'phone_number' => $this->get_user_phone_number( $member['user_id'] ),
                    'referral_earning' => $this->get_referral_earnings_for_user( $member['user_id'], $start_date, $end_date ),
                    'team_size' => $this->get_team_size_for_user( $member['user_id'] ),
                    'purchased_value' => $order_stats['purchased_value'],
                    'cancelled_orders' => $order_stats['cancelled_orders'],
                    'cancelled_value' => $order_stats['cancelled_value'],
                    'level' => $member['level'],
                    'sponsor_id' => $member['sponsor_id'],
                    'joined_date' => $member['created_date'],
                    'affiliate_status' => get_user_meta( $member['user_id'], 'affiliate_status', true )
                );
            }
        }

        return array(
            'members' => $members,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        );
    }

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

    private function get_user_phone_number( $user_id ) {
        $phone = get_user_meta( $user_id, 'phone_number', true );
        if ( empty( $phone ) ) {
            $phone = get_user_meta( $user_id, 'affiliate_phone', true );
        }

        return $phone ? $phone : '';
    }

    private function get_referral_earnings_for_user( $user_id, $start_date = null, $end_date = null ) {
        global $wpdb;

        $sql = "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE user_id = %d";
        $params = array( $user_id );

        if ( ! empty( $start_date ) ) {
            $sql .= " AND conversion_date >= %s";
            $params[] = $start_date;
        }

        if ( ! empty( $end_date ) ) {
            $sql .= " AND conversion_date <= %s";
            $params[] = $end_date;
        }

        $query = $wpdb->prepare( $sql, $params );
        $total = $wpdb->get_var( $query );

        return floatval( $total ?: 0 );
    }

    private function get_team_size_for_user( $user_id ) {
        $downline_counts = $this->get_downline_count_by_level( $user_id );
        return array_sum( $downline_counts );
    }

    private function get_order_stats_for_user( $user_id, $start_date = null, $end_date = null ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return array(
                'purchased_value' => 0.0,
                'cancelled_orders' => 0,
                'cancelled_value' => 0.0
            );
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
        $paid_statuses = apply_filters( 'affiliate_bloom_purchased_order_statuses', $paid_statuses );

        $cancelled_statuses = apply_filters( 'affiliate_bloom_cancelled_order_statuses', array( 'cancelled' ) );

        $purchased_orders = wc_get_orders( array(
            'customer_id' => $user_id,
            'status' => $paid_statuses,
            'limit' => -1,
            'return' => 'ids',
            'date_created' => $date_query
        ) );

        $cancelled_orders = wc_get_orders( array(
            'customer_id' => $user_id,
            'status' => $cancelled_statuses,
            'limit' => -1,
            'return' => 'ids',
            'date_created' => $date_query
        ) );

        $purchased_value = 0.0;
        foreach ( $purchased_orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $purchased_value += floatval( $order->get_total() );
            }
        }

        $cancelled_value = 0.0;
        foreach ( $cancelled_orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $cancelled_value += floatval( $order->get_total() );
            }
        }

        return array(
            'purchased_value' => $purchased_value,
            'cancelled_orders' => count( $cancelled_orders ),
            'cancelled_value' => $cancelled_value
        );
    }

    /**
     * Approve MLM commission (move to user balance)
     */
    public function approve_mlm_commission( $commission_id ) {
        global $wpdb;

        $commission = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliate_bloom_mlm_commissions WHERE id = %d AND status = 'pending'",
            $commission_id
        ), ARRAY_A );

        if ( ! $commission ) {
            return false;
        }

        // Update status
        $wpdb->update(
            $wpdb->prefix . 'affiliate_bloom_mlm_commissions',
            array( 'status' => 'approved' ),
            array( 'id' => $commission_id ),
            array( '%s' ),
            array( '%d' )
        );

        // Add to user's actual balance
        $current_balance = get_user_meta( $commission['beneficiary_id'], 'affiliate_balance', true ) ?: 0;
        $new_balance = floatval( $current_balance ) + floatval( $commission['commission_amount'] );
        update_user_meta( $commission['beneficiary_id'], 'affiliate_balance', $new_balance );

        // Deduct from pending
        $pending = get_user_meta( $commission['beneficiary_id'], 'affiliate_mlm_pending_balance', true ) ?: 0;
        $new_pending = max( 0, floatval( $pending ) - floatval( $commission['commission_amount'] ) );
        update_user_meta( $commission['beneficiary_id'], 'affiliate_mlm_pending_balance', $new_pending );

        // Update transaction status
        $this->update_transaction_status( $commission['beneficiary_id'], $commission['source_user_id'], $commission['level'], 'completed' );

        do_action( 'affiliate_bloom_mlm_commission_approved', $commission_id, $commission );

        return true;
    }

    /**
     * Update transaction status in history
     */
    private function update_transaction_status( $user_id, $source_user_id, $level, $new_status ) {
        $history = get_user_meta( $user_id, 'affiliate_transaction_history', true );
        if ( ! is_array( $history ) ) {
            return;
        }

        foreach ( $history as &$txn ) {
            if ( isset( $txn['type'] ) && $txn['type'] === 'mlm_commission'
                 && isset( $txn['source_user_id'] ) && $txn['source_user_id'] == $source_user_id
                 && isset( $txn['level'] ) && $txn['level'] == $level
                 && $txn['status'] === 'pending' ) {
                $txn['status'] = $new_status;
                break;
            }
        }

        update_user_meta( $user_id, 'affiliate_transaction_history', $history );
    }

    /**
     * AJAX: Get team stats
     */
    public function ajax_get_team_stats() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'User not logged in' );
        }

        $user_id = get_current_user_id();
        $stats = $this->get_team_stats( $user_id );

        wp_send_json_success( array( 'stats' => $stats ) );
    }

    /**
     * AJAX: Get team members
     */
    public function ajax_get_team_members() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'User not logged in' );
        }

        $user_id = get_current_user_id();
        $level = isset( $_POST['level'] ) ? intval( $_POST['level'] ) : null;
        $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 50;
        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : null;
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : null;

        $members = $this->get_team_members_detailed( $user_id, $level, $limit, $offset, $start_date, $end_date );

        wp_send_json_success( array( 'team' => $members ) );
    }

    /**
     * AJAX: Get MLM earnings
     */
    public function ajax_get_mlm_earnings() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'User not logged in' );
        }

        $user_id = get_current_user_id();
        $earnings = $this->get_user_mlm_earnings( $user_id );
        $history = $this->get_user_mlm_commission_history( $user_id );

        wp_send_json_success( array(
            'earnings' => $earnings,
            'history' => $history
        ) );
    }
}
