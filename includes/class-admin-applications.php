<?php
namespace AffiliateBloom;

if (!defined('ABSPATH')) {
    exit;
}

class AdminApplications {

    public static function init() {
        $instance = new self();
        return $instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_affiliate_bloom_get_leaderboard', array($this, 'ajax_get_leaderboard'));
        add_action('wp_ajax_affiliate_bloom_get_affiliate_details', array($this, 'ajax_get_affiliate_details'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Affiliate Bloom', 'affiliate-bloom'),
            __('Affiliate Bloom', 'affiliate-bloom'),
            'manage_options',
            'affiliate-bloom',
            array($this, 'admin_page'),
            'dashicons-groups',
            30
        );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_affiliate-bloom') {
            return;
        }

        wp_enqueue_style(
            'affiliate-bloom-admin',
            AFFILIATE_BLOOM_ROOT_DIR_URL . 'assets/css/admin.css',
            array(),
            AFFILIATE_BLOOM_VERSION
        );

        wp_enqueue_script(
            'affiliate-bloom-admin',
            AFFILIATE_BLOOM_ROOT_DIR_URL . 'assets/js/admin.js',
            array('jquery'),
            AFFILIATE_BLOOM_VERSION,
            true
        );

        wp_localize_script('affiliate-bloom-admin', 'affiliateBloomAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affiliate_bloom_admin_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'affiliate-bloom'),
                'error' => __('An error occurred. Please try again.', 'affiliate-bloom'),
            )
        ));
    }

    public function register_settings() {
        register_setting('affiliate_bloom_settings', 'affiliate_bloom_frontend_base_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ));
        register_setting('affiliate_bloom_settings', 'affiliate_bloom_shortlink_base_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ));
    }

    public function admin_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        $tabs = array(
            'dashboard' => array('label' => __('Dashboard', 'affiliate-bloom'), 'icon' => 'dashicons-dashboard'),
            'affiliates' => array('label' => __('Affiliates', 'affiliate-bloom'), 'icon' => 'dashicons-groups'),
            'teams' => array('label' => __('Teams', 'affiliate-bloom'), 'icon' => 'dashicons-networking'),
            'leaderboard' => array('label' => __('Leaderboard', 'affiliate-bloom'), 'icon' => 'dashicons-awards'),
            'settings' => array('label' => __('Settings', 'affiliate-bloom'), 'icon' => 'dashicons-admin-settings'),
        );
        ?>
        <div class="wrap affiliate-bloom-admin">
            <div class="affiliate-bloom-header">
                <div class="header-left">
                    <h1>
                        <span class="dashicons dashicons-groups"></span>
                        <?php _e('Affiliate Bloom', 'affiliate-bloom'); ?>
                    </h1>
                    <span class="version-badge">v<?php echo AFFILIATE_BLOOM_VERSION; ?></span>
                </div>
            </div>

            <nav class="nav-tab-wrapper affiliate-bloom-tabs">
                <?php foreach ($tabs as $tab_key => $tab_data): ?>
                    <a href="<?php echo admin_url('admin.php?page=affiliate-bloom&tab=' . $tab_key); ?>"
                       class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons <?php echo $tab_data['icon']; ?>"></span>
                        <?php echo esc_html($tab_data['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="affiliate-bloom-tab-content">
                <?php
                switch ($current_tab) {
                    case 'dashboard':
                        $this->render_dashboard_tab();
                        break;
                    case 'affiliates':
                        $this->render_affiliates_tab();
                        break;
                    case 'teams':
                        $this->render_teams_tab();
                        break;
                    case 'leaderboard':
                        $this->render_leaderboard_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>

        <?php $this->render_modal(); ?>
        <?php
    }

    // =========================================================================
    // TAB RENDERERS
    // =========================================================================

    private function render_dashboard_tab() {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="affiliate-bloom-dashboard">
            <!-- Main Stats -->
            <div class="stats-grid main-stats">
                <div class="stat-card gradient-blue">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['total_affiliates']); ?></div>
                        <div class="stat-label"><?php _e('Total Affiliates', 'affiliate-bloom'); ?></div>
                    </div>
                </div>

                <div class="stat-card gradient-green">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-networking"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['total_team_members']); ?></div>
                        <div class="stat-label"><?php _e('Team Relationships', 'affiliate-bloom'); ?></div>
                    </div>
                </div>

                <div class="stat-card gradient-purple">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">৳<?php echo number_format($stats['total_commissions'], 0); ?></div>
                        <div class="stat-label"><?php _e('Total Commissions', 'affiliate-bloom'); ?></div>
                    </div>
                </div>

                <div class="stat-card gradient-orange">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">৳<?php echo number_format($stats['total_mlm_commissions'], 0); ?></div>
                        <div class="stat-label"><?php _e('MLM Commissions', 'affiliate-bloom'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Secondary Stats -->
            <div class="stats-grid secondary-stats">
                <div class="stat-card-mini">
                    <span class="dashicons dashicons-visibility"></span>
                    <div class="stat-mini-content">
                        <span class="stat-mini-number"><?php echo number_format($stats['total_clicks']); ?></span>
                        <span class="stat-mini-label"><?php _e('Total Clicks', 'affiliate-bloom'); ?></span>
                    </div>
                </div>

                <div class="stat-card-mini">
                    <span class="dashicons dashicons-cart"></span>
                    <div class="stat-mini-content">
                        <span class="stat-mini-number"><?php echo number_format($stats['total_conversions']); ?></span>
                        <span class="stat-mini-label"><?php _e('Conversions', 'affiliate-bloom'); ?></span>
                    </div>
                </div>

                <div class="stat-card-mini">
                    <span class="dashicons dashicons-chart-line"></span>
                    <div class="stat-mini-content">
                        <span class="stat-mini-number"><?php echo $stats['conversion_rate']; ?>%</span>
                        <span class="stat-mini-label"><?php _e('Conversion Rate', 'affiliate-bloom'); ?></span>
                    </div>
                </div>

                <div class="stat-card-mini">
                    <span class="dashicons dashicons-clock"></span>
                    <div class="stat-mini-content">
                        <span class="stat-mini-number">৳<?php echo number_format($stats['pending_commissions'], 0); ?></span>
                        <span class="stat-mini-label"><?php _e('Pending', 'affiliate-bloom'); ?></span>
                    </div>
                </div>

                <div class="stat-card-mini">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <div class="stat-mini-content">
                        <span class="stat-mini-number">৳<?php echo number_format($stats['approved_commissions'], 0); ?></span>
                        <span class="stat-mini-label"><?php _e('Approved', 'affiliate-bloom'); ?></span>
                    </div>
                </div>

                <div class="stat-card-mini">
                    <span class="dashicons dashicons-admin-links"></span>
                    <div class="stat-mini-content">
                        <span class="stat-mini-number"><?php echo number_format($stats['total_links']); ?></span>
                        <span class="stat-mini-label"><?php _e('Affiliate Links', 'affiliate-bloom'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Dashboard Widgets -->
            <div class="dashboard-widgets">
                <div class="widget-card">
                    <div class="widget-header">
                        <h3><span class="dashicons dashicons-awards"></span> <?php _e('Top Performers', 'affiliate-bloom'); ?></h3>
                        <a href="<?php echo admin_url('admin.php?page=affiliate-bloom&tab=leaderboard'); ?>" class="widget-link"><?php _e('View All', 'affiliate-bloom'); ?> →</a>
                    </div>
                    <div class="widget-body">
                        <?php $this->render_top_affiliates(); ?>
                    </div>
                </div>

                <div class="widget-card">
                    <div class="widget-header">
                        <h3><span class="dashicons dashicons-location"></span> <?php _e('Top Divisions', 'affiliate-bloom'); ?></h3>
                    </div>
                    <div class="widget-body">
                        <?php $this->render_top_divisions(); ?>
                    </div>
                </div>

                <div class="widget-card">
                    <div class="widget-header">
                        <h3><span class="dashicons dashicons-chart-pie"></span> <?php _e('MLM Level Distribution', 'affiliate-bloom'); ?></h3>
                    </div>
                    <div class="widget-body">
                        <?php $this->render_level_distribution(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_affiliates_tab() {
        $affiliates = $this->get_approved_affiliates();
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total = count($affiliates);
        $total_pages = ceil($total / $per_page);
        $affiliates = array_slice($affiliates, ($current_page - 1) * $per_page, $per_page);
        ?>
        <div class="affiliate-bloom-affiliates">
            <div class="table-header">
                <div class="table-title">
                    <h2><?php _e('All Affiliates', 'affiliate-bloom'); ?></h2>
                    <span class="count-badge"><?php echo $total; ?> <?php _e('total', 'affiliate-bloom'); ?></span>
                </div>
                <div class="table-actions">
                    <input type="text" id="affiliate-search" placeholder="<?php _e('Search affiliates...', 'affiliate-bloom'); ?>" class="search-input">
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped affiliate-table">
                <thead>
                    <tr>
                        <th class="column-id"><?php _e('ID', 'affiliate-bloom'); ?></th>
                        <th class="column-name"><?php _e('Name', 'affiliate-bloom'); ?></th>
                        <th class="column-email"><?php _e('Email', 'affiliate-bloom'); ?></th>
                        <th class="column-phone"><?php _e('Phone', 'affiliate-bloom'); ?></th>
                        <th class="column-zilla"><?php _e('Zilla', 'affiliate-bloom'); ?></th>
                        <th class="column-code"><?php _e('Code', 'affiliate-bloom'); ?></th>
                        <th class="column-team"><?php _e('Team', 'affiliate-bloom'); ?></th>
                        <th class="column-clicks"><?php _e('Clicks', 'affiliate-bloom'); ?></th>
                        <th class="column-earnings"><?php _e('Earnings', 'affiliate-bloom'); ?></th>
                        <th class="column-actions"><?php _e('Actions', 'affiliate-bloom'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($affiliates)): ?>
                        <tr>
                            <td colspan="10" class="no-items">
                                <div class="empty-state">
                                    <span class="dashicons dashicons-groups"></span>
                                    <p><?php _e('No affiliates found.', 'affiliate-bloom'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($affiliates as $affiliate): ?>
                            <tr>
                                <td><span class="id-badge">#<?php echo esc_html($affiliate->ID); ?></span></td>
                                <td>
                                    <div class="user-info">
                                        <strong><?php echo esc_html($affiliate->display_name); ?></strong>
                                    </div>
                                </td>
                                <td><a href="mailto:<?php echo esc_attr($affiliate->user_email); ?>"><?php echo esc_html($affiliate->user_email); ?></a></td>
                                <td><?php echo esc_html($affiliate->phone_number ?: '-'); ?></td>
                                <td><span class="location-badge"><?php echo esc_html($affiliate->zilla ?: '-'); ?></span></td>
                                <td><code class="affiliate-code"><?php echo esc_html($affiliate->affiliate_code ?: '-'); ?></code></td>
                                <td><span class="team-badge"><?php echo intval($affiliate->team_size); ?></span></td>
                                <td><?php echo intval($affiliate->total_clicks); ?></td>
                                <td><strong>৳<?php echo number_format($affiliate->total_earnings ?: 0, 0); ?></strong></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="button button-small view-affiliate" data-id="<?php echo $affiliate->ID; ?>" title="<?php _e('View Details', 'affiliate-bloom'); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </button>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $affiliate->ID); ?>" class="button button-small" title="<?php _e('Edit User', 'affiliate-bloom'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'current' => $current_page,
                            'total' => $total_pages,
                            'prev_text' => '← ' . __('Previous', 'affiliate-bloom'),
                            'next_text' => __('Next', 'affiliate-bloom') . ' →',
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_teams_tab() {
        $affiliates = $this->get_affiliates_with_teams();
        ?>
        <div class="affiliate-bloom-teams">
            <div class="table-header">
                <div class="table-title">
                    <h2><?php _e('Team Structure', 'affiliate-bloom'); ?></h2>
                    <span class="count-badge"><?php echo count($affiliates); ?> <?php _e('affiliates', 'affiliate-bloom'); ?></span>
                </div>
                <div class="table-actions">
                    <input type="text" id="team-search" placeholder="<?php _e('Search teams...', 'affiliate-bloom'); ?>" class="search-input">
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped team-table">
                <thead>
                    <tr>
                        <th class="column-id"><?php _e('ID', 'affiliate-bloom'); ?></th>
                        <th class="column-name"><?php _e('Affiliate', 'affiliate-bloom'); ?></th>
                        <th class="column-sponsor"><?php _e('Sponsor', 'affiliate-bloom'); ?></th>
                        <th class="column-level"><?php _e('L1', 'affiliate-bloom'); ?></th>
                        <th class="column-level"><?php _e('L2', 'affiliate-bloom'); ?></th>
                        <th class="column-level"><?php _e('L3', 'affiliate-bloom'); ?></th>
                        <th class="column-level"><?php _e('L4+', 'affiliate-bloom'); ?></th>
                        <th class="column-total"><?php _e('Total', 'affiliate-bloom'); ?></th>
                        <th class="column-earnings"><?php _e('MLM Earnings', 'affiliate-bloom'); ?></th>
                        <th class="column-actions"><?php _e('Actions', 'affiliate-bloom'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($affiliates)): ?>
                        <tr>
                            <td colspan="10" class="no-items">
                                <div class="empty-state">
                                    <span class="dashicons dashicons-networking"></span>
                                    <p><?php _e('No team data found.', 'affiliate-bloom'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($affiliates as $affiliate): ?>
                            <?php
                            $mlm = MLMCommission::init();
                            $team_counts = $mlm->get_downline_count_by_level($affiliate->ID);
                            $sponsor_id = $mlm->get_user_sponsor($affiliate->ID);
                            $sponsor = $sponsor_id ? get_user_by('ID', $sponsor_id) : null;
                            $mlm_earnings = $mlm->get_user_mlm_earnings($affiliate->ID);
                            $level4_plus = array_sum(array_slice($team_counts, 3, null, true));
                            ?>
                            <tr>
                                <td><span class="id-badge">#<?php echo esc_html($affiliate->ID); ?></span></td>
                                <td><strong><?php echo esc_html($affiliate->display_name); ?></strong></td>
                                <td>
                                    <?php if ($sponsor): ?>
                                        <span class="sponsor-badge"><?php echo esc_html($sponsor->display_name); ?></span>
                                    <?php else: ?>
                                        <span class="no-sponsor">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="level-badge l1"><?php echo intval($team_counts[1] ?? 0); ?></span></td>
                                <td><span class="level-badge l2"><?php echo intval($team_counts[2] ?? 0); ?></span></td>
                                <td><span class="level-badge l3"><?php echo intval($team_counts[3] ?? 0); ?></span></td>
                                <td><span class="level-badge l4"><?php echo intval($level4_plus); ?></span></td>
                                <td><span class="total-badge"><?php echo array_sum($team_counts); ?></span></td>
                                <td><strong>৳<?php echo number_format($mlm_earnings['total'] ?? 0, 0); ?></strong></td>
                                <td>
                                    <button class="button button-small view-affiliate" data-id="<?php echo $affiliate->ID; ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_leaderboard_tab() {
        $leaderboard = Leaderboard::init();
        $divisions = $leaderboard->get_divisions();

        $filter_division = isset($_GET['division']) ? sanitize_text_field($_GET['division']) : '';
        $filter_zilla = isset($_GET['zilla']) ? sanitize_text_field($_GET['zilla']) : '';
        $order_by = isset($_GET['order_by']) ? sanitize_text_field($_GET['order_by']) : 'team_purchased_value';

        $data = $leaderboard->get_leaderboard(array(
            'division' => $filter_division,
            'zilla' => $filter_zilla,
            'order_by' => $order_by,
            'limit' => 100
        ));
        ?>
        <div class="affiliate-bloom-leaderboard">
            <div class="table-header">
                <div class="table-title">
                    <h2><?php _e('Leaderboard Rankings', 'affiliate-bloom'); ?></h2>
                    <span class="count-badge"><?php echo $data['total']; ?> <?php _e('participants', 'affiliate-bloom'); ?></span>
                </div>
            </div>

            <div class="leaderboard-filters">
                <form method="get" class="filter-form">
                    <input type="hidden" name="page" value="affiliate-bloom">
                    <input type="hidden" name="tab" value="leaderboard">

                    <div class="filter-group">
                        <label><?php _e('Division', 'affiliate-bloom'); ?></label>
                        <select name="division" id="filter-division">
                            <option value=""><?php _e('All Divisions', 'affiliate-bloom'); ?></option>
                            <?php foreach ($divisions as $division): ?>
                                <option value="<?php echo esc_attr($division); ?>" <?php selected($filter_division, $division); ?>>
                                    <?php echo esc_html($division); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><?php _e('Zilla', 'affiliate-bloom'); ?></label>
                        <select name="zilla" id="filter-zilla">
                            <option value=""><?php _e('All Zillas', 'affiliate-bloom'); ?></option>
                            <?php if ($filter_division): ?>
                                <?php foreach ($leaderboard->get_districts_by_division($filter_division) as $district): ?>
                                    <option value="<?php echo esc_attr($district); ?>" <?php selected($filter_zilla, $district); ?>>
                                        <?php echo esc_html($district); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><?php _e('Sort By', 'affiliate-bloom'); ?></label>
                        <select name="order_by" id="filter-order">
                            <option value="team_purchased_value" <?php selected($order_by, 'team_purchased_value'); ?>><?php _e('Team Value', 'affiliate-bloom'); ?></option>
                            <option value="team_size" <?php selected($order_by, 'team_size'); ?>><?php _e('Team Size', 'affiliate-bloom'); ?></option>
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="button button-primary"><?php _e('Apply Filters', 'affiliate-bloom'); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=affiliate-bloom&tab=leaderboard'); ?>" class="button"><?php _e('Reset', 'affiliate-bloom'); ?></a>
                    </div>
                </form>
            </div>

            <?php if (!empty($data['leaderboard'])): ?>
                <!-- Top 3 Podium -->
                <div class="leaderboard-podium">
                    <?php
                    $top3 = array_slice($data['leaderboard'], 0, 3);
                    $positions = array(2, 1, 3); // Display order: 2nd, 1st, 3rd
                    foreach ($positions as $pos):
                        $entry = isset($top3[$pos - 1]) ? $top3[$pos - 1] : null;
                        if (!$entry) continue;
                    ?>
                        <div class="podium-item position-<?php echo $pos; ?>">
                            <div class="podium-medal">
                                <?php if ($pos === 1): ?>🥇<?php elseif ($pos === 2): ?>🥈<?php else: ?>🥉<?php endif; ?>
                            </div>
                            <div class="podium-name"><?php echo esc_html($entry['full_name']); ?></div>
                            <div class="podium-stats">
                                <span class="podium-team"><?php echo $entry['team_size']; ?> <?php _e('members', 'affiliate-bloom'); ?></span>
                                <span class="podium-value">৳<?php echo number_format($entry['team_purchased_value'], 0); ?></span>
                            </div>
                            <div class="podium-location"><?php echo esc_html($entry['zilla'] ?: '-'); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped leaderboard-table">
                <thead>
                    <tr>
                        <th class="column-position"><?php _e('Rank', 'affiliate-bloom'); ?></th>
                        <th class="column-id"><?php _e('ID', 'affiliate-bloom'); ?></th>
                        <th class="column-name"><?php _e('Name', 'affiliate-bloom'); ?></th>
                        <th class="column-zilla"><?php _e('Zilla', 'affiliate-bloom'); ?></th>
                        <th class="column-division"><?php _e('Division', 'affiliate-bloom'); ?></th>
                        <th class="column-team"><?php _e('Team Size', 'affiliate-bloom'); ?></th>
                        <th class="column-value"><?php _e('Team Value', 'affiliate-bloom'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['leaderboard'])): ?>
                        <tr>
                            <td colspan="7" class="no-items">
                                <div class="empty-state">
                                    <span class="dashicons dashicons-awards"></span>
                                    <p><?php _e('No leaderboard data found.', 'affiliate-bloom'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['leaderboard'] as $entry): ?>
                            <tr class="<?php echo $entry['position'] <= 3 ? 'top-rank rank-' . $entry['position'] : ''; ?>">
                                <td>
                                    <span class="rank-badge rank-<?php echo $entry['position']; ?>">
                                        <?php if ($entry['position'] === 1): ?>
                                            🥇
                                        <?php elseif ($entry['position'] === 2): ?>
                                            🥈
                                        <?php elseif ($entry['position'] === 3): ?>
                                            🥉
                                        <?php else: ?>
                                            #<?php echo $entry['position']; ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><span class="id-badge">#<?php echo esc_html($entry['user_id']); ?></span></td>
                                <td><strong><?php echo esc_html($entry['full_name']); ?></strong></td>
                                <td><span class="location-badge"><?php echo esc_html($entry['zilla'] ?: '-'); ?></span></td>
                                <td><?php echo esc_html($entry['division'] ?: '-'); ?></td>
                                <td><span class="team-badge"><?php echo intval($entry['team_size']); ?></span></td>
                                <td><strong class="value-highlight">৳<?php echo number_format($entry['team_purchased_value'], 0); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_settings_tab() {
        ?>
        <div class="affiliate-bloom-settings">
            <div class="settings-section">
                <h2><?php _e('General Settings', 'affiliate-bloom'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('affiliate_bloom_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="affiliate_bloom_frontend_base_url"><?php _e('Frontend Base URL', 'affiliate-bloom'); ?></label>
                            </th>
                            <td>
                                <input type="url"
                                       class="regular-text"
                                       id="affiliate_bloom_frontend_base_url"
                                       name="affiliate_bloom_frontend_base_url"
                                       value="<?php echo esc_attr(get_option('affiliate_bloom_frontend_base_url', '')); ?>"
                                       placeholder="https://example.com/">
                                <p class="description"><?php _e('Base URL for referral links. Leave blank to use site URL.', 'affiliate-bloom'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="affiliate_bloom_shortlink_base_url"><?php _e('Shortlink Base URL', 'affiliate-bloom'); ?></label>
                            </th>
                            <td>
                                <input type="url"
                                       class="regular-text"
                                       id="affiliate_bloom_shortlink_base_url"
                                       name="affiliate_bloom_shortlink_base_url"
                                       value="<?php echo esc_attr(get_option('affiliate_bloom_shortlink_base_url', '')); ?>"
                                       placeholder="https://short.link/">
                                <p class="description"><?php _e('Base URL for short affiliate links.', 'affiliate-bloom'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(); ?>
                </form>
            </div>

            <div class="settings-section">
                <h2><?php _e('MLM Commission Structure', 'affiliate-bloom'); ?></h2>
                <div class="commission-grid">
                    <?php
                    $mlm = MLMCommission::init();
                    $rates = $mlm->get_commission_rates();
                    foreach ($rates as $level => $rate):
                    ?>
                        <div class="commission-card">
                            <div class="commission-level">Level <?php echo $level; ?></div>
                            <div class="commission-rate"><?php echo $rate; ?>%</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // HELPER RENDERERS
    // =========================================================================

    private function render_top_affiliates() {
        $leaderboard = Leaderboard::init();
        $data = $leaderboard->get_leaderboard(array('limit' => 5));

        if (empty($data['leaderboard'])) {
            echo '<div class="empty-state-small"><p>' . __('No data yet', 'affiliate-bloom') . '</p></div>';
            return;
        }
        ?>
        <div class="top-list">
            <?php foreach ($data['leaderboard'] as $entry): ?>
                <div class="top-list-item">
                    <span class="top-rank"><?php echo $entry['position'] <= 3 ? ($entry['position'] === 1 ? '🥇' : ($entry['position'] === 2 ? '🥈' : '🥉')) : '#' . $entry['position']; ?></span>
                    <div class="top-info">
                        <strong><?php echo esc_html($entry['full_name']); ?></strong>
                        <small><?php echo esc_html($entry['zilla'] ?: '-'); ?></small>
                    </div>
                    <span class="top-value">৳<?php echo number_format($entry['team_purchased_value'], 0); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_top_divisions() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT um.meta_value as zilla, COUNT(*) as count
             FROM {$wpdb->usermeta} um
             INNER JOIN {$wpdb->usermeta} um2 ON um.user_id = um2.user_id AND um2.meta_key = 'affiliate_status' AND um2.meta_value = 'approved'
             WHERE um.meta_key = 'zilla' AND um.meta_value != ''
             GROUP BY um.meta_value
             ORDER BY count DESC
             LIMIT 5"
        );

        if (empty($results)) {
            echo '<div class="empty-state-small"><p>' . __('No data yet', 'affiliate-bloom') . '</p></div>';
            return;
        }

        $leaderboard = Leaderboard::init();
        ?>
        <div class="top-list">
            <?php foreach ($results as $index => $row): ?>
                <div class="top-list-item">
                    <span class="top-rank">#<?php echo $index + 1; ?></span>
                    <div class="top-info">
                        <strong><?php echo esc_html($row->zilla); ?></strong>
                        <small><?php echo esc_html($leaderboard->get_division_for_district($row->zilla) ?: '-'); ?></small>
                    </div>
                    <span class="top-value"><?php echo $row->count; ?> <?php _e('affiliates', 'affiliate-bloom'); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_level_distribution() {
        global $wpdb;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_hierarchy");

        if (!$total) {
            echo '<div class="empty-state-small"><p>' . __('No team data yet', 'affiliate-bloom') . '</p></div>';
            return;
        }

        // Since we only track direct sponsor, we need to calculate levels differently
        // For now, show Level 1 distribution
        $level1_count = $wpdb->get_var("SELECT COUNT(DISTINCT sponsor_id) FROM {$wpdb->prefix}affiliate_bloom_hierarchy");
        ?>
        <div class="level-bars">
            <div class="level-bar-item">
                <div class="level-bar-label">
                    <span><?php _e('Total Relationships', 'affiliate-bloom'); ?></span>
                    <span><?php echo $total; ?></span>
                </div>
                <div class="level-bar">
                    <div class="level-bar-fill" style="width: 100%; background: linear-gradient(90deg, #667eea, #764ba2);"></div>
                </div>
            </div>
            <div class="level-bar-item">
                <div class="level-bar-label">
                    <span><?php _e('Active Sponsors', 'affiliate-bloom'); ?></span>
                    <span><?php echo $level1_count; ?></span>
                </div>
                <div class="level-bar">
                    <div class="level-bar-fill" style="width: <?php echo min(100, ($level1_count / max(1, $total)) * 100); ?>%; background: linear-gradient(90deg, #11998e, #38ef7d);"></div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_modal() {
        ?>
        <div id="affiliate-bloom-modal" class="affiliate-bloom-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title"><?php _e('Details', 'affiliate-bloom'); ?></h2>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body" id="modal-body"></div>
                <div class="modal-footer" id="modal-footer">
                    <button class="button modal-close"><?php _e('Close', 'affiliate-bloom'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // DATA METHODS
    // =========================================================================

    private function get_dashboard_stats() {
        global $wpdb;

        $stats = array();

        // Total affiliates
        $stats['total_affiliates'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'affiliate_status' AND meta_value = 'approved'"
        ) ?: 0;

        // Total clicks
        $stats['total_clicks'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks"
        ) ?: 0;

        // Total conversions
        $stats['total_conversions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_conversions"
        ) ?: 0;

        // Conversion rate
        $stats['conversion_rate'] = $stats['total_clicks'] > 0
            ? round(($stats['total_conversions'] / $stats['total_clicks']) * 100, 1)
            : 0;

        // Total commissions
        $stats['total_commissions'] = $wpdb->get_var(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions"
        ) ?: 0;

        // Pending commissions
        $stats['pending_commissions'] = $wpdb->get_var(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE status = 'pending'"
        ) ?: 0;

        // Approved commissions
        $stats['approved_commissions'] = $wpdb->get_var(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions WHERE status = 'approved'"
        ) ?: 0;

        // Total team members
        $stats['total_team_members'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_hierarchy"
        ) ?: 0;

        // Total MLM commissions
        $stats['total_mlm_commissions'] = $wpdb->get_var(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_mlm_commissions"
        ) ?: 0;

        // Total affiliate links
        $stats['total_links'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'affiliate_link' AND post_status = 'publish'"
        ) ?: 0;

        return $stats;
    }

    private function get_approved_affiliates() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT u.*,
                    um2.meta_value as affiliate_code,
                    um3.meta_value as phone_number,
                    um4.meta_value as zilla,
                    COALESCE(clicks.total_clicks, 0) as total_clicks,
                    COALESCE(conversions.total_earnings, 0) as total_earnings,
                    COALESCE(team.team_size, 0) as team_size
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'affiliate_status' AND um1.meta_value = 'approved'
             LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'affiliate_code'
             LEFT JOIN {$wpdb->usermeta} um3 ON u.ID = um3.user_id AND um3.meta_key = 'phone_number'
             LEFT JOIN {$wpdb->usermeta} um4 ON u.ID = um4.user_id AND um4.meta_key = 'zilla'
             LEFT JOIN (
                 SELECT user_id, COUNT(*) as total_clicks
                 FROM {$wpdb->prefix}affiliate_bloom_clicks
                 GROUP BY user_id
             ) clicks ON u.ID = clicks.user_id
             LEFT JOIN (
                 SELECT user_id, SUM(commission_amount) as total_earnings
                 FROM {$wpdb->prefix}affiliate_bloom_conversions
                 GROUP BY user_id
             ) conversions ON u.ID = conversions.user_id
             LEFT JOIN (
                 SELECT sponsor_id, COUNT(*) as team_size
                 FROM {$wpdb->prefix}affiliate_bloom_hierarchy
                 GROUP BY sponsor_id
             ) team ON u.ID = team.sponsor_id
             ORDER BY u.display_name"
        ) ?: array();
    }

    private function get_affiliates_with_teams() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT u.*
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'affiliate_status' AND um.meta_value = 'approved'
             ORDER BY u.display_name"
        ) ?: array();
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    public function ajax_get_affiliate_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $user_id = intval($_POST['user_id']);
        $user = get_user_by('ID', $user_id);

        if (!$user) {
            wp_send_json_error('User not found');
        }

        $mlm = MLMCommission::init();
        $team_counts = $mlm->get_downline_count_by_level($user_id);
        $mlm_earnings = $mlm->get_user_mlm_earnings($user_id);
        $sponsor_id = $mlm->get_user_sponsor($user_id);
        $sponsor = $sponsor_id ? get_user_by('ID', $sponsor_id) : null;

        ob_start();
        ?>
        <div class="affiliate-details-modal">
            <div class="detail-grid">
                <div class="detail-card">
                    <h4><?php _e('User Information', 'affiliate-bloom'); ?></h4>
                    <div class="detail-row"><span><?php _e('ID:', 'affiliate-bloom'); ?></span> <strong>#<?php echo esc_html($user_id); ?></strong></div>
                    <div class="detail-row"><span><?php _e('Name:', 'affiliate-bloom'); ?></span> <strong><?php echo esc_html($user->display_name); ?></strong></div>
                    <div class="detail-row"><span><?php _e('Email:', 'affiliate-bloom'); ?></span> <?php echo esc_html($user->user_email); ?></div>
                    <div class="detail-row"><span><?php _e('Phone:', 'affiliate-bloom'); ?></span> <?php echo esc_html(get_user_meta($user_id, 'phone_number', true) ?: '-'); ?></div>
                    <div class="detail-row"><span><?php _e('Zilla:', 'affiliate-bloom'); ?></span> <?php echo esc_html(get_user_meta($user_id, 'zilla', true) ?: '-'); ?></div>
                    <div class="detail-row"><span><?php _e('Code:', 'affiliate-bloom'); ?></span> <code><?php echo esc_html(get_user_meta($user_id, 'affiliate_code', true) ?: '-'); ?></code></div>
                    <div class="detail-row"><span><?php _e('Sponsor:', 'affiliate-bloom'); ?></span> <?php echo $sponsor ? esc_html($sponsor->display_name) : '-'; ?></div>
                </div>

                <div class="detail-card">
                    <h4><?php _e('Team Statistics', 'affiliate-bloom'); ?></h4>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <?php if (($team_counts[$i] ?? 0) > 0): ?>
                            <div class="detail-row">
                                <span><?php printf(__('Level %d:', 'affiliate-bloom'), $i); ?></span>
                                <strong><?php echo intval($team_counts[$i]); ?></strong>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <div class="detail-row total">
                        <span><?php _e('Total:', 'affiliate-bloom'); ?></span>
                        <strong><?php echo array_sum($team_counts); ?></strong>
                    </div>
                </div>

                <div class="detail-card">
                    <h4><?php _e('MLM Earnings', 'affiliate-bloom'); ?></h4>
                    <div class="detail-row"><span><?php _e('Total:', 'affiliate-bloom'); ?></span> <strong>৳<?php echo number_format($mlm_earnings['total'] ?? 0, 2); ?></strong></div>
                    <div class="detail-row"><span><?php _e('Pending:', 'affiliate-bloom'); ?></span> ৳<?php echo number_format($mlm_earnings['pending'] ?? 0, 2); ?></div>
                    <div class="detail-row"><span><?php _e('Approved:', 'affiliate-bloom'); ?></span> ৳<?php echo number_format($mlm_earnings['approved'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'title' => sprintf(__('Affiliate: %s', 'affiliate-bloom'), $user->display_name)
        ));
    }

    public function ajax_get_leaderboard() {
        if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $leaderboard = Leaderboard::init();
        $data = $leaderboard->get_leaderboard(array(
            'division' => sanitize_text_field($_POST['division'] ?? ''),
            'zilla' => sanitize_text_field($_POST['zilla'] ?? ''),
            'order_by' => sanitize_text_field($_POST['order_by'] ?? 'team_purchased_value'),
            'limit' => intval($_POST['limit'] ?? 50)
        ));

        wp_send_json_success($data);
    }
}
