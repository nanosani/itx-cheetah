<?php
/**
 * Cron handler for ITX Cheetah
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron class for scheduled tasks
 */
class ITX_Cheetah_Cron {

    /**
     * Database instance
     */
    private $database;

    /**
     * Analyzer instance
     */
    private $analyzer;

    /**
     * Constructor
     */
    public function __construct($database, $analyzer) {
        $this->database = $database;
        $this->analyzer = $analyzer;

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register custom cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));

        // Register cron hooks
        add_action('itx_cheetah_scheduled_scan', array($this, 'run_scheduled_scan'));
        add_action('itx_cheetah_cleanup', array($this, 'run_cleanup'));

        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('itx_cheetah_cleanup')) {
            wp_schedule_event(time(), 'daily', 'itx_cheetah_cleanup');
        }
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['itx_cheetah_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Once Weekly', 'itx-cheetah'),
        );

        $schedules['itx_cheetah_twice_daily'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('Twice Daily', 'itx-cheetah'),
        );

        return $schedules;
    }

    /**
     * Schedule automatic scans
     */
    public function schedule_scans($frequency) {
        // Clear existing schedule
        $this->unschedule_scans();

        if (empty($frequency) || $frequency === 'disabled') {
            return false;
        }

        // Map frequency to WordPress cron schedule
        $schedule_map = array(
            'hourly' => 'hourly',
            'twice_daily' => 'itx_cheetah_twice_daily',
            'daily' => 'daily',
            'weekly' => 'itx_cheetah_weekly',
        );

        if (!isset($schedule_map[$frequency])) {
            return false;
        }

        $schedule = $schedule_map[$frequency];
        wp_schedule_event(time(), $schedule, 'itx_cheetah_scheduled_scan');

        return true;
    }

    /**
     * Unschedule automatic scans
     */
    public function unschedule_scans() {
        $timestamp = wp_next_scheduled('itx_cheetah_scheduled_scan');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'itx_cheetah_scheduled_scan');
        }
    }

    /**
     * Run scheduled scan
     */
    public function run_scheduled_scan() {
        $settings = get_option('itx_cheetah_settings', array());

        // Get scan settings
        $post_types = isset($settings['auto_scan_post_types']) ? $settings['auto_scan_post_types'] : array('post', 'page');
        $batch_size = isset($settings['auto_scan_batch_size']) ? intval($settings['auto_scan_batch_size']) : 10;

        // Get URLs to scan
        $urls = $this->analyzer->get_scannable_urls(array(
            'post_types' => $post_types,
            'posts_per_page' => $batch_size,
        ));

        $results = array(
            'total' => count($urls),
            'completed' => 0,
            'failed' => 0,
            'timestamp' => current_time('mysql'),
        );

        foreach ($urls as $url_data) {
            $result = $this->analyzer->analyze_url($url_data['url']);

            if (is_wp_error($result)) {
                $results['failed']++;
                continue;
            }

            $scan_id = $this->database->insert_scan($result);

            if (!is_wp_error($scan_id)) {
                $results['completed']++;
            } else {
                $results['failed']++;
            }

            // Small delay to prevent server overload
            usleep(500000); // 0.5 seconds
        }

        // Log results
        $this->log_scan_results($results);

        // Send notification if enabled
        $this->maybe_send_notification($results);

        return $results;
    }

    /**
     * Run cleanup of old scans
     */
    public function run_cleanup() {
        $settings = get_option('itx_cheetah_settings', array());
        $retention_days = isset($settings['data_retention_days']) ? intval($settings['data_retention_days']) : 90;

        $deleted = $this->database->cleanup_old_scans($retention_days);

        // Log cleanup
        if ($deleted > 0) {
            error_log(sprintf(
                '[ITX Cheetah] Cleanup: Deleted %d scans older than %d days',
                $deleted,
                $retention_days
            ));
        }

        return $deleted;
    }

    /**
     * Log scan results
     */
    private function log_scan_results($results) {
        error_log(sprintf(
            '[ITX Cheetah] Scheduled scan completed: %d/%d successful, %d failed',
            $results['completed'],
            $results['total'],
            $results['failed']
        ));
    }

    /**
     * Maybe send email notification
     */
    private function maybe_send_notification($results) {
        $settings = get_option('itx_cheetah_settings', array());

        if (empty($settings['email_notifications'])) {
            return;
        }

        $notify_email = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');

        // Check if there are critical issues
        $critical_scans = $this->database->get_critical_pages(5);

        if (empty($critical_scans)) {
            return; // No critical issues, no need to notify
        }

        $subject = sprintf(
            __('[%s] ITX Cheetah: %d pages need attention', 'itx-cheetah'),
            get_bloginfo('name'),
            count($critical_scans)
        );

        $message = sprintf(
            __("ITX Cheetah has completed a scheduled scan of your website.\n\nResults:\n- Total scanned: %d\n- Completed: %d\n- Failed: %d\n\nThe following pages have critical DOM issues:\n\n", 'itx-cheetah'),
            $results['total'],
            $results['completed'],
            $results['failed']
        );

        foreach ($critical_scans as $scan) {
            $message .= sprintf(
                "- %s\n  Nodes: %d | Depth: %d | Score: %d\n\n",
                $scan['url'],
                $scan['total_nodes'],
                $scan['max_depth'],
                $scan['performance_score']
            );
        }

        $message .= sprintf(
            __("\nView full reports: %s", 'itx-cheetah'),
            admin_url('admin.php?page=itx-cheetah')
        );

        wp_mail($notify_email, $subject, $message);
    }

    /**
     * Get next scheduled scan time
     */
    public function get_next_scheduled_scan() {
        return wp_next_scheduled('itx_cheetah_scheduled_scan');
    }

    /**
     * Check if scheduled scans are enabled
     */
    public function is_scheduled() {
        return (bool) wp_next_scheduled('itx_cheetah_scheduled_scan');
    }

    /**
     * Get current schedule frequency
     */
    public function get_schedule_frequency() {
        $settings = get_option('itx_cheetah_settings', array());
        return isset($settings['auto_scan_frequency']) ? $settings['auto_scan_frequency'] : 'disabled';
    }
}
