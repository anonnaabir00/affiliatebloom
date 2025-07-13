<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Actions {

    public static function init() {
        $instance = new self();
        return $instance;
    }

    public function __construct() {
        add_action('wp_login', array($this, 'handle_user_login'), 10, 2);
        add_action('wp_loaded', array($this, 'check_login_bonus'));
    }

    public function handle_user_login($user_login, $user) {
        $this->add_login_bonus($user->ID);
    }

    public function check_login_bonus() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $this->add_login_bonus($user_id);
        }
    }

    private function add_login_bonus($user_id) {
        $today = date('Y-m-d');
        $last_bonus_date = get_user_meta($user_id, 'last_login_bonus_date', true);

        if ($last_bonus_date === $today) {
            return;
        }

        $current_balance = get_user_meta($user_id, 'affiliate_balance', true);
        $current_balance = $current_balance ? floatval($current_balance) : 0;

        $bonus_amount = 5.00;
        $new_balance = $current_balance + $bonus_amount;

        update_user_meta($user_id, 'affiliate_balance', $new_balance);
        update_user_meta($user_id, 'last_login_bonus_date', $today);

        $this->log_bonus_transaction($user_id, $bonus_amount, 'login_bonus');
    }

    private function log_bonus_transaction($user_id, $amount, $type = "login_bonus") {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            $transaction_history = array();
        }

        $description = 'Daily login bonus';
        if ($type !== 'login_bonus') {
            $description = 'Manual bonus';
        }

        $transaction = array(
            'id' => uniqid('txn_'),
            'type' => $type,
            'amount' => $amount,
            'status' => 'completed',
            'description' => $description,
            'created_date' => current_time('mysql'),
            'date' => date('Y-m-d'),
            'timestamp' => current_time('timestamp')
        );

        $transaction_history[] = $transaction;
        update_user_meta($user_id, 'affiliate_transaction_history', $transaction_history);

        return $transaction['id'];
    }

    public function get_user_login_bonuses($user_id) {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            return 0;
        }

        $total = 0;
        foreach ($transaction_history as $transaction) {
            if ($transaction['type'] === 'login_bonus' && $transaction['status'] === 'completed') {
                $total += floatval($transaction['amount']);
            }
        }

        return $total;
    }

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

        usort($transaction_history, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $transaction_history;
    }

    public function can_get_login_bonus($user_id) {
        $today = date('Y-m-d');
        $last_bonus_date = get_user_meta($user_id, 'last_login_bonus_date', true);

        return $last_bonus_date !== $today;
    }

    public function manual_add_login_bonus($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        $current_balance = get_user_meta($user_id, 'affiliate_balance', true);
        $current_balance = $current_balance ? floatval($current_balance) : 0;

        $bonus_amount = 5.00;
        $new_balance = $current_balance + $bonus_amount;

        update_user_meta($user_id, 'affiliate_balance', $new_balance);
        $this->log_bonus_transaction($user_id, $bonus_amount, 'manual_bonus');

        return true;
    }

    public function get_user_balance($user_id) {
        $balance = get_user_meta($user_id, 'affiliate_balance', true);
        return $balance ? floatval($balance) : 0;
    }

    public function update_user_balance($user_id, $amount, $type = 'adjustment', $description = '') {
        $current_balance = get_user_meta($user_id, 'affiliate_balance', true);
        $current_balance = $current_balance ? floatval($current_balance) : 0;

        $new_balance = $current_balance + $amount;

        if ($new_balance < 0) {
            return false;
        }

        update_user_meta($user_id, 'affiliate_balance', $new_balance);
        $this->log_balance_transaction($user_id, $amount, $type, $description);

        return true;
    }

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
            'description' => $description,
            'created_date' => current_time('mysql'),
            'date' => date('Y-m-d'),
            'timestamp' => current_time('timestamp')
        );

        $transaction_history[] = $transaction;
        update_user_meta($user_id, 'affiliate_transaction_history', $transaction_history);

        return $transaction['id'];
    }
}