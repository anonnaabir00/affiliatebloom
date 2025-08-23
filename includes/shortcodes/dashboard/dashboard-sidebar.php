<?php
namespace AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardSidebar {

    public static function init() {
        $self = new self();
        add_shortcode('dashboard_sidebar', array($self, 'dashboard_sidebar_shortcode'));
        add_action('wp_enqueue_scripts', array($self, 'enqueue_scripts'));
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
    }

    public function dashboard_sidebar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'menu_name' => 'primary', // Default menu location
            'menu_id' => '', // Specific menu ID
            'show_user_info' => true,
            'sidebar_title' => 'Dashboard'
        ), $atts);

        ob_start();
        ?>
        <style>
        .affiliate-dashboard {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            position: relative;
            min-height: 100vh;
        }

        .dashboard-sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .dashboard-sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }

        .sidebar-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .user-info {
            margin-top: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }

        .user-details h4 {
            margin: 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .user-details span {
            font-size: 12px;
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .sidebar-menu > ul > li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #fff;
            color: white;
        }

        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            border-left-color: #fff;
            color: white;
        }

        .menu-icon {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            opacity: 0.8;
            background: currentColor;
            mask-size: contain;
            mask-repeat: no-repeat;
            mask-position: center;
        }

        .icon-dashboard { mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='currentColor' viewBox='0 0 24 24'%3E%3Cpath d='M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z'/%3E%3C/svg%3E"); }
        .icon-analytics { mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='currentColor' viewBox='0 0 24 24'%3E%3Cpath d='M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z'/%3E%3C/svg%3E"); }
        .icon-users { mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='currentColor' viewBox='0 0 24 24'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'/%3E%3C/svg%3E"); }
        .icon-settings { mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='currentColor' viewBox='0 0 24 24'%3E%3Cpath d='M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.82,11.69,4.82,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z'/%3E%3C/svg%3E"); }
        .icon-arrow-down { mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='currentColor' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(0,0,0,0.1);
        }

        .submenu.open {
            max-height: 500px;
        }

        .submenu a {
            padding: 10px 20px 10px 52px;
            font-size: 14px;
            border-left: none;
        }

        .submenu a:hover {
            background: rgba(255,255,255,0.08);
        }

        .has-submenu > a {
            position: relative;
        }

        .has-submenu > a::after {
            content: '';
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background: currentColor;
            mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='currentColor' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            mask-size: contain;
            mask-repeat: no-repeat;
            mask-position: center;
            transition: transform 0.3s ease;
        }

        .has-submenu.open > a::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .sidebar-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .sidebar-toggle.active {
            left: 300px;
        }

        .hamburger {
            width: 24px;
            height: 18px;
            position: relative;
        }

        .hamburger span {
            display: block;
            height: 2px;
            background: white;
            margin: 4px 0;
            transition: 0.3s;
            border-radius: 1px;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .main-content {
            margin-left: 0;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            padding: 80px 20px 20px;
        }

        /* Desktop styles */
        @media (min-width: 768px) {
            .dashboard-sidebar {
                position: relative;
                left: 0;
                height: auto;
                min-height: 100vh;
            }

            .sidebar-toggle {
                display: none;
            }

            .sidebar-overlay {
                display: none;
            }

            .main-content {
                margin-left: 280px;
                padding: 20px;
            }

            .affiliate-dashboard {
                display: flex;
            }
        }

        /* Mobile responsive */
        @media (max-width: 767px) {
            .dashboard-sidebar {
                width: 100%;
                max-width: 320px;
            }

            .sidebar-toggle.active {
                left: 20px;
            }

            .main-content {
                margin-left: 0;
            }
        }
        </style>

        <div class="affiliate-dashboard">
            <button class="sidebar-toggle" id="sidebarToggle">
                <div class="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </button>

            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <nav class="dashboard-sidebar" id="dashboardSidebar">
                <div class="sidebar-header">
                    <h2 class="sidebar-title"><?php echo esc_html($atts['sidebar_title']); ?></h2>

                    <?php if ($atts['show_user_info']): ?>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php
                            $current_user = wp_get_current_user();
                            echo esc_html(strtoupper(substr($current_user->display_name, 0, 1)));
                            ?>
                        </div>
                        <div class="user-details">
                            <h4><?php echo esc_html($current_user->display_name); ?></h4>
                            <span><?php echo esc_html($current_user->user_email); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="sidebar-menu">
                    <?php
                    // Get menu by location or ID
                    $menu_items = null;

                    if (!empty($atts['menu_id'])) {
                        $menu_items = wp_get_nav_menu_items($atts['menu_id']);
                    } else {
                        $locations = get_nav_menu_locations();
                        if (isset($locations[$atts['menu_name']])) {
                            $menu_items = wp_get_nav_menu_items($locations[$atts['menu_name']]);
                        }
                    }

                    if ($menu_items) {
                        echo $this->build_menu_tree($menu_items);
                    } else {
                        // Fallback menu if no WordPress menu is found
                        echo $this->get_fallback_menu();
                    }
                    ?>
                </div>
            </nav>

            <main class="main-content">
                <div class="dashboard-content">
                    <!-- Main content area - can be customized -->
                    <h1>Welcome to your Dashboard</h1>
                    <p>This is the main content area. The sidebar menu will load your WordPress menu items.</p>
                </div>
            </main>
        </div>

        <script>
        jQuery(document).ready(function($) {
            const sidebar = $('#dashboardSidebar');
            const toggle = $('#sidebarToggle');
            const overlay = $('#sidebarOverlay');
            const hamburger = toggle.find('.hamburger');

            // Toggle sidebar
            toggle.on('click', function() {
                sidebar.toggleClass('active');
                overlay.toggleClass('active');
                hamburger.toggleClass('active');
                toggle.toggleClass('active');
            });

            // Close sidebar when clicking overlay
            overlay.on('click', function() {
                sidebar.removeClass('active');
                overlay.removeClass('active');
                hamburger.removeClass('active');
                toggle.removeClass('active');
            });

            // Handle submenu toggles
            $('.has-submenu > a').on('click', function(e) {
                e.preventDefault();
                const parent = $(this).parent();
                const submenu = parent.find('.submenu');

                parent.toggleClass('open');
                submenu.toggleClass('open');

                // Close other submenus
                $('.has-submenu').not(parent).removeClass('open');
                $('.submenu').not(submenu).removeClass('open');
            });

            // Handle window resize
            $(window).on('resize', function() {
                if ($(window).width() >= 768) {
                    sidebar.removeClass('active');
                    overlay.removeClass('active');
                    hamburger.removeClass('active');
                    toggle.removeClass('active');
                }
            });

            // Set active menu item based on current page
            const currentUrl = window.location.href;
            $('.sidebar-menu a').each(function() {
                if ($(this).attr('href') === currentUrl) {
                    $(this).addClass('active');
                    // Open parent submenu if this is a submenu item
                    const parentSubmenu = $(this).closest('.submenu');
                    if (parentSubmenu.length) {
                        parentSubmenu.addClass('open');
                        parentSubmenu.closest('.has-submenu').addClass('open');
                    }
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    private function build_menu_tree($menu_items) {
        $menu_tree = array();
        $submenu_items = array();

        // Separate parent and child items
        foreach ($menu_items as $item) {
            if ($item->menu_item_parent == 0) {
                $menu_tree[$item->ID] = $item;
                $menu_tree[$item->ID]->children = array();
            } else {
                $submenu_items[] = $item;
            }
        }

        // Add children to their parents
        foreach ($submenu_items as $item) {
            if (isset($menu_tree[$item->menu_item_parent])) {
                $menu_tree[$item->menu_item_parent]->children[] = $item;
            }
        }

        // Build HTML
        $html = '<ul>';
        foreach ($menu_tree as $item) {
            $html .= $this->build_menu_item($item);
        }
        $html .= '</ul>';

        return $html;
    }

    private function build_menu_item($item) {
        $has_children = !empty($item->children);
        $classes = $has_children ? 'has-submenu' : '';

        $html = '<li class="' . $classes . '">';
        $html .= '<a href="' . esc_url($item->url) . '">';
        $html .= '<span class="menu-icon ' . $this->get_menu_icon($item->title) . '"></span>';
        $html .= esc_html($item->title);
        $html .= '</a>';

        if ($has_children) {
            $html .= '<ul class="submenu">';
            foreach ($item->children as $child) {
                $html .= '<li><a href="' . esc_url($child->url) . '">' . esc_html($child->title) . '</a></li>';
            }
            $html .= '</ul>';
        }

        $html .= '</li>';
        return $html;
    }

    private function get_menu_icon($title) {
        $title_lower = strtolower($title);

        if (strpos($title_lower, 'dashboard') !== false || strpos($title_lower, 'home') !== false) {
            return 'icon-dashboard';
        } elseif (strpos($title_lower, 'analytics') !== false || strpos($title_lower, 'reports') !== false || strpos($title_lower, 'statistics') !== false) {
            return 'icon-analytics';
        } elseif (strpos($title_lower, 'users') !== false || strpos($title_lower, 'members') !== false || strpos($title_lower, 'profile') !== false) {
            return 'icon-users';
        } elseif (strpos($title_lower, 'settings') !== false || strpos($title_lower, 'configuration') !== false) {
            return 'icon-settings';
        } else {
            return 'icon-dashboard'; // Default icon
        }
    }

    private function get_fallback_menu() {
        return '
        <ul>
            <li><a href="#"><span class="menu-icon icon-dashboard"></span>Dashboard</a></li>
            <li class="has-submenu">
                <a href="#"><span class="menu-icon icon-analytics"></span>Analytics</a>
                <ul class="submenu">
                    <li><a href="#">Overview</a></li>
                    <li><a href="#">Traffic</a></li>
                    <li><a href="#">Conversions</a></li>
                </ul>
            </li>
            <li class="has-submenu">
                <a href="#"><span class="menu-icon icon-users"></span>Affiliates</a>
                <ul class="submenu">
                    <li><a href="#">All Affiliates</a></li>
                    <li><a href="#">Add New</a></li>
                    <li><a href="#">Commissions</a></li>
                </ul>
            </li>
            <li><a href="#"><span class="menu-icon icon-settings"></span>Settings</a></li>
        </ul>';
    }
}

?>

