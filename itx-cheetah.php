<?php
/**
 * Plugin Name: ITX Cheetah
 * Plugin URI: https://example.com/itx-cheetah
 * Description: A comprehensive DOM analyzer and optimization plugin for WordPress. Monitor, analyze, and optimize your page DOM size for better performance and Core Web Vitals.
 * Version: 1.0.0
 * Author: ITX
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: itx-cheetah
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ITX_CHEETAH_VERSION', '1.0.0');
define('ITX_CHEETAH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ITX_CHEETAH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ITX_CHEETAH_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class ITX_Cheetah {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Database handler
     */
    public $database;

    /**
     * Analyzer handler
     */
    public $analyzer;

    /**
     * Admin handler
     */
    public $admin;

    /**
     * REST API handler
     */
    public $rest_api;

    /**
     * Cron handler
     */
    public $cron;

    /**
     * Reports handler
     */
    public $reports;

    /**
     * Notifications handler
     */
    public $notifications;

    /**
     * Metabox handler
     */
    public $metabox;

    /**
     * Core Web Vitals handler
     */
    public $vitals;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-database.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-analyzer.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-cron.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-reports.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-notifications.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-metabox.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-recommendations.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-core-web-vitals.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-admin.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-rest-api.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        $this->database = new ITX_Cheetah_Database();
        $this->analyzer = new ITX_Cheetah_Analyzer();
        $this->cron = new ITX_Cheetah_Cron($this->database, $this->analyzer);
        $this->reports = new ITX_Cheetah_Reports($this->database);
        $this->notifications = new ITX_Cheetah_Notifications($this->database);
        $this->vitals = new ITX_Cheetah_Core_Web_Vitals($this->database, $this->analyzer);
        $this->rest_api = new ITX_Cheetah_REST_API($this->database, $this->analyzer, $this->vitals);

        if (is_admin()) {
            $this->admin = new ITX_Cheetah_Admin($this->database, $this->analyzer, $this->cron);
            $this->metabox = new ITX_Cheetah_Metabox($this->database, $this->analyzer);

            // Check for database upgrades
            $this->maybe_upgrade_database();
        }
    }

    /**
     * Check and upgrade database if needed
     */
    private function maybe_upgrade_database() {
        $current_version = get_option('itx_cheetah_db_version', '0');

        if (version_compare($current_version, ITX_CHEETAH_VERSION, '<')) {
            $this->database->create_tables();
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('itx-cheetah', false, dirname(ITX_CHEETAH_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-database.php';
        $database = new ITX_Cheetah_Database();
        $database->create_tables();

        // Set default options
        $default_options = array(
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

        if (!get_option('itx_cheetah_settings')) {
            add_option('itx_cheetah_settings', $default_options);
        }

        // Create vitals table
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-core-web-vitals.php';
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-analyzer.php';
        $analyzer = new ITX_Cheetah_Analyzer();
        $vitals = new ITX_Cheetah_Core_Web_Vitals($database, $analyzer);
        $vitals->create_table();

        // Clear rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled hooks if any
        wp_clear_scheduled_hook('itx_cheetah_scheduled_scan');
        wp_clear_scheduled_hook('itx_cheetah_cleanup');
        flush_rewrite_rules();
    }
}

/**
 * Returns the main instance of the plugin
 */
function itx_cheetah() {
    return ITX_Cheetah::get_instance();
}

// Initialize plugin
itx_cheetah();
