<?php
namespace AffiliateBloom;
use AffiliateBloom\BonusAfterLogin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Actions {

    public static function init() {
        $self = new self();
        BonusAfterLogin::init();
    }

    public function __construct() {
        // Add other general actions here (non-login bonus related)
        //add_action('init', array($this, 'init_general_actions'));
    }

    /**
     * Initialize general actions
     */
    public function init_general_actions() {
        // Add other action hooks here
    }

    /**
     * Get user's current balance
     */
    public function get_user_balance($user_id) {
        $balance = get_user_meta($user_id, 'affiliate_balance', true);
        return $balance ? floatval($balance) : 0;
    }

    /**
     * Update user balance with transaction logging
     */
    public function update_user_balance($user_id, $amount, $type = 'adjustment', $description = '') {
        $current_balance = get_user_meta($user_id, 'affiliate_balance', true);
        $current_balance = $current_balance ? floatval($current_balance) : 0;

        $new_balance = $current_balance + $amount;

        // Prevent negative balance
        if ($new_balance < 0) {
            return false;
        }

        update_user_meta($user_id, 'affiliate_balance', $new_balance);
        $this->log_balance_transaction($user_id, $amount, $type, $description);

        return true;
    }

    /**
     * Log balance transaction to user's transaction history
     */
    private function log_balance_transaction($user_id, $amount, $type, $description = '') {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            $transaction_history = array();
        }

        $transaction = array(
            'id' => uniqid('txn_'),
            'type' => $type,
            'amount' => $amount,
            'status' => 'completed',
            'description' => $description ?: ucfirst(str_replace('_', ' ', $type)),
            'created_date' => current_time('mysql'),
            'date' => date('Y-m-d'),
            'timestamp' => current_time('timestamp')
        );

        $transaction_history[] = $transaction;
        update_user_meta($user_id, 'affiliate_transaction_history', $transaction_history);

        return $transaction['id'];
    }

    /**
     * Get user transaction history
     */
    public function get_user_transaction_history($user_id, $type = '') {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            return array();
        }

        if (!empty($type)) {
            $transaction_history = array_filter($transaction_history, function($transaction) use ($type) {
                return $transaction['type'] === $type;
            });
        }

        // Sort by timestamp (newest first)
        usort($transaction_history, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $transaction_history;
    }

    /**
     * Add commission earnings
     */
    public function add_commission($user_id, $amount, $description = 'Affiliate commission') {
        return $this->update_user_balance($user_id, $amount, 'commission', $description);
    }

    /**
     * Deduct amount from user balance (for withdrawals, etc.)
     */
    public function deduct_balance($user_id, $amount, $type = 'withdrawal', $description = '') {
        return $this->update_user_balance($user_id, -$amount, $type, $description);
    }

    /**
     * Get user's total earnings by type
     */
    public function get_user_earnings_by_type($user_id, $type) {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            return 0;
        }

        $total = 0;
        foreach ($transaction_history as $transaction) {
            if ($transaction['type'] === $type && $transaction['status'] === 'completed') {
                $total += floatval($transaction['amount']);
            }
        }

        return $total;
    }

    /**
     * Get user's earning statistics
     */
    public function get_user_earning_stats($user_id) {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            return array(
                'total_commissions' => 0,
                'total_bonuses' => 0,
                'total_withdrawals' => 0,
                'current_balance' => 0,
                'transaction_count' => 0
            );
        }

        $commissions = 0;
        $bonuses = 0;
        $withdrawals = 0;

        foreach ($transaction_history as $transaction) {
            if ($transaction['status'] !== 'completed') {
                continue;
            }

            $amount = floatval($transaction['amount']);

            switch ($transaction['type']) {
                case 'commission':
                    $commissions += $amount;
                    break;
                case 'login_bonus':
                case 'welcome_bonus':
                case 'manual_bonus':
                    $bonuses += $amount;
                    break;
                case 'withdrawal':
                    $withdrawals += abs($amount);
                    break;
            }
        }

        return array(
            'total_commissions' => $commissions,
            'total_bonuses' => $bonuses,
            'total_withdrawals' => $withdrawals,
            'current_balance' => $this->get_user_balance($user_id),
            'transaction_count' => count($transaction_history)
        );
    }

    /**
     * Process withdrawal request
     */
    public function process_withdrawal($user_id, $amount, $method = 'paypal', $details = '') {
        $current_balance = $this->get_user_balance($user_id);

        if ($current_balance < $amount) {
            return false; // Insufficient balance
        }

        // Deduct from balance
        $success = $this->deduct_balance($user_id, $amount, 'withdrawal', "Withdrawal via {$method}: {$details}");

        if ($success) {
            // Log withdrawal request (you might want to create a separate withdrawals table)
            $this->log_withdrawal_request($user_id, $amount, $method, $details);
        }

        return $success;
    }

    /**
     * Log withdrawal request
     */
    private function log_withdrawal_request($user_id, $amount, $method, $details) {
        // This could be expanded to use a separate database table for withdrawal requests
        $withdrawal_requests = get_user_meta($user_id, 'affiliate_withdrawal_requests', true);
        if (!is_array($withdrawal_requests)) {
            $withdrawal_requests = array();
        }

        $request = array(
            'id' => uniqid('wdr_'),
            'amount' => $amount,
            'method' => $method,
            'details' => $details,
            'status' => 'pending',
            'request_date' => current_time('mysql'),
            'processed_date' => null
        );

        $withdrawal_requests[] = $request;
        update_user_meta($user_id, 'affiliate_withdrawal_requests', $withdrawal_requests);

        // Optional: Send notification to admin
        do_action('affiliate_bloom_withdrawal_requested', $user_id, $amount, $method, $details);

        return $request['id'];
    }

    /**
     * Get user withdrawal requests
     */
    public function get_user_withdrawal_requests($user_id) {
        $withdrawal_requests = get_user_meta($user_id, 'affiliate_withdrawal_requests', true);
        if (!is_array($withdrawal_requests)) {
            return array();
        }

        // Sort by request date (newest first)
        usort($withdrawal_requests, function($a, $b) {
            return strtotime($b['request_date']) - strtotime($a['request_date']);
        });

        return $withdrawal_requests;
    }
}