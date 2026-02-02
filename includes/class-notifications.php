<?php
/**
 * Notifications handler for ITX Cheetah
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Notifications class for email and admin alerts
 */
class ITX_Cheetah_Notifications {

    /**
     * Database instance
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct($database) {
        $this->database = $database;

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));

        // Hook into scan completion
        add_action('itx_cheetah_scan_complete', array($this, 'handle_scan_complete'), 10, 2);
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Only show on ITX Cheetah pages or dashboard
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // Show critical pages alert on dashboard
        if ($screen->id === 'dashboard' || strpos($screen->id, 'itx-cheetah') !== false) {
            $this->show_critical_pages_notice();
        }
    }

    /**
     * Show notice about critical pages
     */
    private function show_critical_pages_notice() {
        $settings = get_option('itx_cheetah_settings', array());

        // Check if user has dismissed this notice recently
        $dismissed = get_transient('itx_cheetah_notice_dismissed');
        if ($dismissed) {
            return;
        }

        $critical_pages = $this->database->get_critical_pages(5);

        if (empty($critical_pages)) {
            return;
        }

        $count = count($critical_pages);
        ?>
        <div class="notice notice-warning is-dismissible itx-cheetah-notice">
            <p>
                <strong><?php esc_html_e('ITX Cheetah:', 'itx-cheetah'); ?></strong>
                <?php
                printf(
                    esc_html(_n(
                        '%d page on your site has critical DOM performance issues.',
                        '%d pages on your site have critical DOM performance issues.',
                        $count,
                        'itx-cheetah'
                    )),
                    $count
                );
                ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah')); ?>">
                    <?php esc_html_e('View Details', 'itx-cheetah'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle scan completion
     */
    public function handle_scan_complete($scan_id, $scan_data) {
        $settings = get_option('itx_cheetah_settings', array());

        // Check if notifications are enabled
        if (empty($settings['email_notifications'])) {
            return;
        }

        // Only notify for critical scans
        if ($scan_data['performance_score'] >= 50) {
            return;
        }

        $this->send_critical_scan_email($scan_data);
    }

    /**
     * Send email for critical scan
     */
    public function send_critical_scan_email($scan_data) {
        $settings = get_option('itx_cheetah_settings', array());
        $to = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');

        $subject = sprintf(
            __('[%s] ITX Cheetah: Critical DOM issue detected', 'itx-cheetah'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __("A page on your site has been scanned and found to have critical DOM performance issues.\n\nURL: %s\nTotal Nodes: %d\nMax Depth: %d\nPerformance Score: %d/100\n\nRecommendations:\n", 'itx-cheetah'),
            $scan_data['url'],
            $scan_data['total_nodes'],
            $scan_data['max_depth'],
            $scan_data['performance_score']
        );

        if (!empty($scan_data['recommendations'])) {
            foreach ($scan_data['recommendations'] as $rec) {
                if ($rec['severity'] === 'critical' || $rec['severity'] === 'warning') {
                    $message .= "\n- " . $rec['title'] . "\n";
                    $message .= "  " . $rec['description'] . "\n";
                }
            }
        }

        $message .= sprintf(
            __("\n\nView full report: %s", 'itx-cheetah'),
            admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $scan_data['id'])
        );

        wp_mail($to, $subject, $message);
    }

    /**
     * Send weekly digest email
     */
    public function send_weekly_digest() {
        $settings = get_option('itx_cheetah_settings', array());

        if (empty($settings['email_notifications'])) {
            return;
        }

        $to = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
        $stats = $this->database->get_statistics();
        $critical_pages = $this->database->get_critical_pages(10);

        if ($stats['total_scans'] === 0) {
            return;
        }

        $subject = sprintf(
            __('[%s] ITX Cheetah Weekly Report', 'itx-cheetah'),
            get_bloginfo('name')
        );

        $message = __("Here's your weekly DOM performance report:\n\n", 'itx-cheetah');

        $message .= sprintf(__("Statistics:\n", 'itx-cheetah'));
        $message .= sprintf(__("- Total Scans: %d\n", 'itx-cheetah'), $stats['total_scans']);
        $message .= sprintf(__("- Average Nodes: %d\n", 'itx-cheetah'), $stats['avg_nodes']);
        $message .= sprintf(__("- Average Score: %d/100\n", 'itx-cheetah'), $stats['avg_score']);
        $message .= sprintf(__("- Good Pages: %d\n", 'itx-cheetah'), $stats['good_count']);
        $message .= sprintf(__("- Warning Pages: %d\n", 'itx-cheetah'), $stats['warning_count']);
        $message .= sprintf(__("- Critical Pages: %d\n", 'itx-cheetah'), $stats['critical_count']);

        if (!empty($critical_pages)) {
            $message .= __("\nPages Needing Attention:\n", 'itx-cheetah');
            foreach ($critical_pages as $page) {
                $message .= sprintf(
                    "- %s (Score: %d, Nodes: %d)\n",
                    $page['url'],
                    $page['performance_score'],
                    $page['total_nodes']
                );
            }
        }

        $message .= sprintf(
            __("\n\nView full dashboard: %s", 'itx-cheetah'),
            admin_url('admin.php?page=itx-cheetah')
        );

        wp_mail($to, $subject, $message);
    }

    /**
     * Get notification count for admin bar
     */
    public function get_notification_count() {
        $critical = $this->database->get_critical_pages(100);
        return count($critical);
    }
}
