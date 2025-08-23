<?php

namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BonusAfterLogin {

    private $bonus_amount = 5.00;

    public static function init() {
        $self = new self();
        return $self;
    }

    public function __construct() {
        add_action('wp_login', array($this, 'handle_user_login'), 10, 2);
        add_action('wp_loaded', array($this, 'check_login_bonus'));
    }

    /**
     * Handle user login event
     */
    public function handle_user_login($user_login, $user) {
        $this->add_login_bonus($user->ID);
    }

    /**
     * Check and add login bonus for already logged-in users
     */
    public function check_login_bonus() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $this->add_login_bonus($user_id);
        }
    }

    /**
     * Add daily login bonus to user
     */
    private function add_login_bonus($user_id) {
        $today = date('Y-m-d');
        $last_bonus_date = get_user_meta($user_id, 'last_login_bonus_date', true);

        // If user already got bonus today, skip
        if ($last_bonus_date === $today) {
            return false;
        }

        $current_balance = get_user_meta($user_id, 'affiliate_balance', true);
        $current_balance = $current_balance ? floatval($current_balance) : 0;

        $new_balance = $current_balance + $this->bonus_amount;

        update_user_meta($user_id, 'affiliate_balance', $new_balance);
        update_user_meta($user_id, 'last_login_bonus_date', $today);

        $transaction_id = $this->log_bonus_transaction($user_id, $this->bonus_amount, 'login_bonus');

        // Optional: Add action hook for other plugins/themes
        do_action('affiliate_bloom_login_bonus_added', $user_id, $this->bonus_amount, $transaction_id);

        return $transaction_id;
    }

    /**
     * Log bonus transaction to user's transaction history
     */
    private function log_bonus_transaction($user_id, $amount, $type = "login_bonus") {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            $transaction_history = array();
        }

        $description = 'Daily login bonus';
        if ($type === 'manual_bonus') {
            $description = 'Manual bonus';
        } elseif ($type === 'welcome_bonus') {
            $description = 'Welcome bonus';
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

    /**
     * Get total login bonuses for a user
     */
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

    /**
     * Get user transaction history (login bonus related)
     */
    public function get_user_transaction_history($user_id, $type = 'login_bonus') {
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
     * Check if user can get login bonus today
     */
    public function can_get_login_bonus($user_id) {
        $today = date('Y-m-d');
        $last_bonus_date = get_user_meta($user_id, 'last_login_bonus_date', true);

        return $last_bonus_date !== $today;
    }

    /**
     * Manually add login bonus (for admin purposes)
     */
    public function manual_add_login_bonus($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }

        $current_balance = get_user_meta($user_id, 'affiliate_balance', true);
        $current_balance = $current_balance ? floatval($current_balance) : 0;

        $new_balance = $current_balance + $this->bonus_amount;

        update_user_meta($user_id, 'affiliate_balance', $new_balance);
        $transaction_id = $this->log_bonus_transaction($user_id, $this->bonus_amount, 'manual_bonus');

        // Optional: Add action hook
        do_action('affiliate_bloom_manual_bonus_added', $user_id, $this->bonus_amount, $transaction_id);

        return $transaction_id;
    }

    /**
     * Get user's current balance
     */
    public function get_user_balance($user_id) {
        $balance = get_user_meta($user_id, 'affiliate_balance', true);
        return $balance ? floatval($balance) : 0;
    }

    /**
     * Set the bonus amount (for customization)
     */
    public function set_bonus_amount($amount) {
        $this->bonus_amount = floatval($amount);
    }

    /**
     * Get the current bonus amount
     */
    public function get_bonus_amount() {
        return $this->bonus_amount;
    }

    /**
     * Get user's last login bonus date
     */
    public function get_last_bonus_date($user_id) {
        return get_user_meta($user_id, 'last_login_bonus_date', true);
    }

    /**
     * Get login bonus statistics for a user
     */
    public function get_user_login_stats($user_id) {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            return array(
                'total_bonuses' => 0,
                'total_amount' => 0,
                'last_bonus_date' => '',
                'streak_days' => 0
            );
        }

        $login_bonuses = array_filter($transaction_history, function($transaction) {
            return $transaction['type'] === 'login_bonus' && $transaction['status'] === 'completed';
        });

        $total_bonuses = count($login_bonuses);
        $total_amount = array_sum(array_column($login_bonuses, 'amount'));
        $last_bonus_date = get_user_meta($user_id, 'last_login_bonus_date', true);

        // Calculate streak (simplified version)
        $streak_days = $this->calculate_login_streak($user_id);

        return array(
            'total_bonuses' => $total_bonuses,
            'total_amount' => $total_amount,
            'last_bonus_date' => $last_bonus_date,
            'streak_days' => $streak_days
        );
    }

    /**
     * Calculate login streak days (simplified)
     */
    private function calculate_login_streak($user_id) {
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (!is_array($transaction_history)) {
            return 0;
        }

        $login_bonuses = array_filter($transaction_history, function($transaction) {
            return $transaction['type'] === 'login_bonus' && $transaction['status'] === 'completed';
        });

        // Sort by date (newest first)
        usort($login_bonuses, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        $streak = 0;
        $current_date = date('Y-m-d');

        foreach ($login_bonuses as $bonus) {
            $bonus_date = $bonus['date'];
            $expected_date = date('Y-m-d', strtotime($current_date . ' -' . $streak . ' days'));

            if ($bonus_date === $expected_date) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Reset user's login bonus data (for testing purposes)
     */
    public function reset_user_login_bonus($user_id) {
        delete_user_meta($user_id, 'last_login_bonus_date');

        // Optional: Remove login bonus transactions from history
        $transaction_history = get_user_meta($user_id, 'affiliate_transaction_history', true);
        if (is_array($transaction_history)) {
            $filtered_history = array_filter($transaction_history, function($transaction) {
                return $transaction['type'] !== 'login_bonus';
            });
            update_user_meta($user_id, 'affiliate_transaction_history', $filtered_history);
        }

        return true;
    }

    /**
     * Add welcome bonus for new users
     */
    public function add_welcome_bonus($user_id, $amount = null) {
        if ($amount === null) {
            $amount = $this->bonus_amount * 2; // Double the regular bonus for welcome
        }

        $current_balance = get_user_meta($user_id, 'affiliate_balance', true);
        $current_balance = $current_balance ? floatval($current_balance) : 0;

        $new_balance = $current_balance + $amount;

        update_user_meta($user_id, 'affiliate_balance', $new_balance);
        $transaction_id = $this->log_bonus_transaction($user_id, $amount, 'welcome_bonus');

        // Optional: Add action hook
        do_action('affiliate_bloom_welcome_bonus_added', $user_id, $amount, $transaction_id);

        return $transaction_id;
    }
}