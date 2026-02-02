<?php
/**
 * Admin interface for ITX Cheetah
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class ITX_Cheetah_Admin {

    /**
     * Database instance
     */
    private $database;

    /**
     * Analyzer instance
     */
    private $analyzer;

    /**
     * Cron instance
     */
    private $cron;

    /**
     * Constructor
     */
    public function __construct($database, $analyzer, $cron = null) {
        $this->database = $database;
        $this->analyzer = $analyzer;
        $this->cron = $cron;

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'handle_actions'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('ITX Cheetah', 'itx-cheetah'),
            __('ITX Cheetah', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah',
            array($this, 'render_dashboard'),
            'dashicons-performance',
            80
        );

        // Dashboard submenu
        add_submenu_page(
            'itx-cheetah',
            __('Dashboard', 'itx-cheetah'),
            __('Dashboard', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah',
            array($this, 'render_dashboard')
        );

        // All Scans submenu
        add_submenu_page(
            'itx-cheetah',
            __('All Scans', 'itx-cheetah'),
            __('All Scans', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah-scans',
            array($this, 'render_all_scans')
        );

        // Bulk Scan submenu
        add_submenu_page(
            'itx-cheetah',
            __('Bulk Scan', 'itx-cheetah'),
            __('Bulk Scan', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah-bulk',
            array($this, 'render_bulk_scan')
        );

        // Compare Scans submenu
        add_submenu_page(
            'itx-cheetah',
            __('Compare', 'itx-cheetah'),
            __('Compare', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah-compare',
            array($this, 'render_compare')
        );

        // Optimizations submenu
        add_submenu_page(
            'itx-cheetah',
            __('Optimizations', 'itx-cheetah'),
            __('Optimizations', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah-optimizations',
            array($this, 'render_optimizations')
        );

        // Scan Report submenu (hidden)
        add_submenu_page(
            null,
            __('Scan Report', 'itx-cheetah'),
            __('Scan Report', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah-report',
            array($this, 'render_report')
        );

        // Actionable Fixes submenu (hidden)
        add_submenu_page(
            null,
            __('Actionable Fixes', 'itx-cheetah'),
            __('Actionable Fixes', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah-fixes',
            array($this, 'render_actionable_fixes')
        );

        // Core Web Vitals submenu
        add_submenu_page(
            'itx-cheetah',
            __('Core Web Vitals', 'itx-cheetah'),
            __('Core Web Vitals', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah-vitals',
            array($this, 'render_vitals')
        );

        // Vitals Report submenu (hidden)
        add_submenu_page(
            null,
            __('Vitals Report', 'itx-cheetah'),
            __('Vitals Report', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah-vitals-report',
            array($this, 'render_vitals_report')
        );

        // Settings submenu
        add_submenu_page(
            'itx-cheetah',
            __('Settings', 'itx-cheetah'),
            __('Settings', 'itx-cheetah'),
            'manage_options',
            'itx-cheetah-settings',
            array($this, 'render_settings')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'itx-cheetah') === false) {
            return;
        }

        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Admin CSS
        wp_enqueue_style(
            'itx-cheetah-admin',
            ITX_CHEETAH_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ITX_CHEETAH_VERSION
        );

        // Admin JS
        wp_enqueue_script(
            'itx-cheetah-admin',
            ITX_CHEETAH_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'chartjs'),
            ITX_CHEETAH_VERSION,
            true
        );

        // Localize script
        wp_localize_script('itx-cheetah-admin', 'itxCheetah', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('itx-cheetah/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => array(
                'scanning' => __('Scanning...', 'itx-cheetah'),
                'scanComplete' => __('Scan complete!', 'itx-cheetah'),
                'scanError' => __('Scan failed', 'itx-cheetah'),
                'confirmDelete' => __('Are you sure you want to delete this scan?', 'itx-cheetah'),
                'confirmBulkDelete' => __('Are you sure you want to delete the selected scans?', 'itx-cheetah'),
                'noUrlSelected' => __('Please enter a URL to scan.', 'itx-cheetah'),
                'bulkScanStarted' => __('Bulk scan started...', 'itx-cheetah'),
                'bulkScanComplete' => __('Bulk scan complete!', 'itx-cheetah'),
                'bulkScanStopped' => __('Bulk scan stopped.', 'itx-cheetah'),
                'preparingUrls' => __('Preparing URLs to scan...', 'itx-cheetah'),
            ),
        ));
    }

    /**
     * Handle admin actions
     */
    public function handle_actions() {
        // Handle single delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['scan_id'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_scan_' . $_GET['scan_id'])) {
                wp_die(__('Security check failed.', 'itx-cheetah'));
            }

            $this->database->delete_scan(intval($_GET['scan_id']));

            wp_redirect(admin_url('admin.php?page=itx-cheetah-scans&deleted=1'));
            exit;
        }

        // Handle bulk delete
        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['scan_ids'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk_action_scans')) {
                wp_die(__('Security check failed.', 'itx-cheetah'));
            }

            $ids = array_map('intval', $_POST['scan_ids']);
            $this->database->delete_scans($ids);

            wp_redirect(admin_url('admin.php?page=itx-cheetah-scans&deleted=' . count($ids)));
            exit;
        }

        // Handle settings save
        if (isset($_POST['itx_cheetah_save_settings'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'itx_cheetah_settings')) {
                wp_die(__('Security check failed.', 'itx-cheetah'));
            }

            $settings = array(
                'node_threshold_good' => intval($_POST['node_threshold_good']),
                'node_threshold_warning' => intval($_POST['node_threshold_warning']),
                'depth_threshold_good' => intval($_POST['depth_threshold_good']),
                'depth_threshold_warning' => intval($_POST['depth_threshold_warning']),
                'data_retention_days' => intval($_POST['data_retention_days']),
                'auto_scan_frequency' => sanitize_text_field($_POST['auto_scan_frequency'] ?? 'disabled'),
                'auto_scan_batch_size' => intval($_POST['auto_scan_batch_size'] ?? 10),
                'auto_scan_post_types' => isset($_POST['auto_scan_post_types']) ? array_map('sanitize_text_field', $_POST['auto_scan_post_types']) : array('post', 'page'),
                'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                'notification_email' => sanitize_email($_POST['notification_email'] ?? get_option('admin_email')),
            );

            update_option('itx_cheetah_settings', $settings);

            // Update cron schedule if frequency changed
            if ($this->cron) {
                $this->cron->schedule_scans($settings['auto_scan_frequency']);
            }

            wp_redirect(admin_url('admin.php?page=itx-cheetah-settings&saved=1'));
            exit;
        }
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $stats = $this->database->get_statistics();
        $recent_scans = $this->database->get_recent_scans(10);
        $critical_pages = $this->database->get_critical_pages(5);

        include ITX_CHEETAH_PLUGIN_DIR . 'admin/dashboard.php';
    }

    /**
     * Render all scans page
     */
    public function render_all_scans() {
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        $args = array(
            'per_page' => $per_page,
            'page' => $current_page,
            'search' => $search,
            'status' => $status,
        );

        $scans = $this->database->get_scans($args);
        $total_scans = $this->database->get_total_scans($args);
        $total_pages = ceil($total_scans / $per_page);

        include ITX_CHEETAH_PLUGIN_DIR . 'admin/all-scans.php';
    }

    /**
     * Render bulk scan page
     */
    public function render_bulk_scan() {
        $stats = $this->database->get_statistics();
        $critical_pages = $this->database->get_critical_pages(5);

        include ITX_CHEETAH_PLUGIN_DIR . 'admin/bulk-scan.php';
    }

    /**
     * Render compare page
     */
    public function render_compare() {
        // Get scans to compare if IDs provided
        $scans_to_compare = array();
        if (isset($_GET['scan_ids']) && is_array($_GET['scan_ids'])) {
            foreach ($_GET['scan_ids'] as $id) {
                $scan = $this->database->get_scan(intval($id));
                if ($scan) {
                    $scans_to_compare[] = $scan;
                }
            }
        }

        // Get all scans for selection
        $available_scans = $this->database->get_scans(array(
            'per_page' => 100,
            'page' => 1,
            'status' => 'completed',
        ));

        // Get unique URLs for filtering
        $unique_urls = array_unique(array_column($available_scans, 'url'));

        // URL filter
        $url_filter = isset($_GET['url_filter']) ? sanitize_url($_GET['url_filter']) : '';
        if ($url_filter) {
            $available_scans = array_filter($available_scans, function($scan) use ($url_filter) {
                return $scan['url'] === $url_filter;
            });
        }

        include ITX_CHEETAH_PLUGIN_DIR . 'admin/compare.php';
    }

    /**
     * Render single scan report
     */
    public function render_report() {
        $scan_id = isset($_GET['scan_id']) ? intval($_GET['scan_id']) : 0;

        if (!$scan_id) {
            wp_die(__('Invalid scan ID.', 'itx-cheetah'));
        }

        $scan = $this->database->get_scan($scan_id);

        if (!$scan) {
            wp_die(__('Scan not found.', 'itx-cheetah'));
        }

        include ITX_CHEETAH_PLUGIN_DIR . 'admin/report.php';
    }

    /**
     * Render actionable fixes page
     */
    public function render_actionable_fixes() {
        $scan_id = isset($_GET['scan_id']) ? intval($_GET['scan_id']) : 0;

        if (!$scan_id) {
            wp_die(__('Invalid scan ID.', 'itx-cheetah'));
        }

        $scan = $this->database->get_scan($scan_id);

        if (!$scan) {
            wp_die(__('Scan not found.', 'itx-cheetah'));
        }

        include ITX_CHEETAH_PLUGIN_DIR . 'admin/actionable-fixes.php';
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        $settings = get_option('itx_cheetah_settings', array());

        $defaults = array(
            'node_threshold_good' => 1000,
            'node_threshold_warning' => 1500,
            'depth_threshold_good' => 20,
            'depth_threshold_warning' => 32,
            'data_retention_days' => 90,
            'auto_scan_frequency' => 'disabled',
            'auto_scan_batch_size' => 10,
            'auto_scan_post_types' => array('post', 'page'),
            'email_notifications' => 0,
            'notification_email' => get_option('admin_email'),
        );

        $settings = wp_parse_args($settings, $defaults);

        include ITX_CHEETAH_PLUGIN_DIR . 'admin/settings.php';
    }

    /**
     * Render vitals page
     */
    public function render_vitals() {
        include ITX_CHEETAH_PLUGIN_DIR . 'admin/vitals.php';
    }

    /**
     * Render vitals report page
     */
    public function render_vitals_report() {
        include ITX_CHEETAH_PLUGIN_DIR . 'admin/vitals-report.php';
    }

    /**
     * Render optimizations page
     */
    public function render_optimizations() {
        $stats = $this->database->get_statistics();
        $critical_pages = $this->database->get_critical_pages(10);

        // Analyze common issues across all scans
        $common_issues = $this->analyze_common_issues();

        include ITX_CHEETAH_PLUGIN_DIR . 'admin/optimizations.php';
    }

    /**
     * Analyze common issues across scans
     */
    private function analyze_common_issues() {
        $issues = array();
        $scans = $this->database->get_scans(array(
            'per_page' => 100,
            'status' => 'completed',
        ));

        if (empty($scans)) {
            return $issues;
        }

        $high_node_count = 0;
        $deep_nesting_count = 0;
        $high_div_count = 0;
        $many_scripts_count = 0;

        $settings = get_option('itx_cheetah_settings', array());
        $node_threshold = isset($settings['node_threshold_warning']) ? $settings['node_threshold_warning'] : 1500;
        $depth_threshold = isset($settings['depth_threshold_warning']) ? $settings['depth_threshold_warning'] : 32;

        foreach ($scans as $scan) {
            if ($scan['total_nodes'] > $node_threshold) {
                $high_node_count++;
            }
            if ($scan['max_depth'] > $depth_threshold) {
                $deep_nesting_count++;
            }

            $element_counts = $scan['element_counts'];
            if (is_array($element_counts)) {
                if (isset($element_counts['div']) && $element_counts['div'] > 100) {
                    $high_div_count++;
                }
                if (isset($element_counts['script']) && $element_counts['script'] > 15) {
                    $many_scripts_count++;
                }
            }
        }

        if ($high_node_count > 0) {
            $issues[] = array(
                'severity' => 'critical',
                'title' => __('Excessive DOM Size', 'itx-cheetah'),
                'description' => __('Pages with too many DOM nodes can cause slow rendering and increased memory usage.', 'itx-cheetah'),
                'count' => $high_node_count,
                'solutions' => array(
                    __('Implement lazy loading for content below the fold', 'itx-cheetah'),
                    __('Remove unnecessary wrapper elements', 'itx-cheetah'),
                    __('Use virtual scrolling for long lists', 'itx-cheetah'),
                ),
            );
        }

        if ($deep_nesting_count > 0) {
            $issues[] = array(
                'severity' => 'critical',
                'title' => __('Deep DOM Nesting', 'itx-cheetah'),
                'description' => __('Deeply nested elements slow down CSS selector matching and layout calculations.', 'itx-cheetah'),
                'count' => $deep_nesting_count,
                'solutions' => array(
                    __('Use CSS Grid and Flexbox to flatten your layout', 'itx-cheetah'),
                    __('Review page builder output for excessive nesting', 'itx-cheetah'),
                    __('Simplify component hierarchies', 'itx-cheetah'),
                ),
            );
        }

        if ($high_div_count > 0) {
            $issues[] = array(
                'severity' => 'warning',
                'title' => __('Div Bloat', 'itx-cheetah'),
                'description' => __('Excessive div elements often indicate unnecessary wrapper elements.', 'itx-cheetah'),
                'count' => $high_div_count,
                'solutions' => array(
                    __('Use semantic HTML elements (section, article, nav, etc.)', 'itx-cheetah'),
                    __('Remove decorative wrapper divs and use CSS pseudo-elements', 'itx-cheetah'),
                    __('Audit your theme and plugins for div overuse', 'itx-cheetah'),
                ),
            );
        }

        if ($many_scripts_count > 0) {
            $issues[] = array(
                'severity' => 'warning',
                'title' => __('Many Inline Scripts', 'itx-cheetah'),
                'description' => __('Too many script tags can delay page interactivity and block rendering.', 'itx-cheetah'),
                'count' => $many_scripts_count,
                'solutions' => array(
                    __('Combine and minify JavaScript files', 'itx-cheetah'),
                    __('Defer non-critical scripts', 'itx-cheetah'),
                    __('Remove unused JavaScript from plugins', 'itx-cheetah'),
                ),
            );
        }

        return $issues;
    }

    /**
     * Get status badge HTML
     */
    public static function get_status_badge($score) {
        if ($score >= 80) {
            $class = 'good';
            $label = __('Good', 'itx-cheetah');
        } elseif ($score >= 50) {
            $class = 'warning';
            $label = __('Warning', 'itx-cheetah');
        } else {
            $class = 'critical';
            $label = __('Critical', 'itx-cheetah');
        }

        return sprintf(
            '<span class="itx-badge itx-badge-%s">%s</span>',
            esc_attr($class),
            esc_html($label)
        );
    }

    /**
     * Format number with thousand separator
     */
    public static function format_number($number) {
        return number_format_i18n($number);
    }

    /**
     * Get time ago string
     */
    public static function time_ago($datetime) {
        return human_time_diff(strtotime($datetime), current_time('timestamp')) . ' ' . __('ago', 'itx-cheetah');
    }
}
