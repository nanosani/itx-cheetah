<?php
/**
 * Reports handler for ITX Cheetah
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reports class for generating and exporting reports
 */
class ITX_Cheetah_Reports {

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
        add_action('admin_init', array($this, 'handle_export'));
    }

    /**
     * Handle export requests
     */
    public function handle_export() {
        if (!isset($_GET['itx_cheetah_export'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'itx-cheetah'));
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'itx_cheetah_export')) {
            wp_die(__('Security check failed.', 'itx-cheetah'));
        }

        $format = sanitize_text_field($_GET['itx_cheetah_export']);
        $scan_id = isset($_GET['scan_id']) ? intval($_GET['scan_id']) : 0;
        $export_type = isset($_GET['export_type']) ? sanitize_text_field($_GET['export_type']) : 'single';

        switch ($export_type) {
            case 'single':
                $this->export_single_scan($scan_id, $format);
                break;
            case 'all':
                $this->export_all_scans($format);
                break;
            case 'comparison':
                $scan_ids = isset($_GET['scan_ids']) ? array_map('intval', explode(',', $_GET['scan_ids'])) : array();
                $this->export_comparison($scan_ids, $format);
                break;
        }

        exit;
    }

    /**
     * Export single scan
     */
    public function export_single_scan($scan_id, $format) {
        $scan = $this->database->get_scan($scan_id);

        if (!$scan) {
            wp_die(__('Scan not found.', 'itx-cheetah'));
        }

        $filename = 'itx-cheetah-scan-' . $scan_id . '-' . date('Y-m-d');

        switch ($format) {
            case 'csv':
                $this->output_csv($this->format_scan_for_csv($scan), $filename);
                break;
            case 'json':
                $this->output_json($scan, $filename);
                break;
            default:
                wp_die(__('Invalid export format.', 'itx-cheetah'));
        }
    }

    /**
     * Export all scans
     */
    public function export_all_scans($format) {
        $scans = $this->database->get_scans(array(
            'per_page' => 1000,
            'page' => 1,
            'status' => 'completed',
        ));

        if (empty($scans)) {
            wp_die(__('No scans to export.', 'itx-cheetah'));
        }

        $filename = 'itx-cheetah-all-scans-' . date('Y-m-d');

        switch ($format) {
            case 'csv':
                $this->output_csv($this->format_scans_for_csv($scans), $filename);
                break;
            case 'json':
                $this->output_json(array(
                    'exported_at' => current_time('mysql'),
                    'total_scans' => count($scans),
                    'scans' => $scans,
                ), $filename);
                break;
            default:
                wp_die(__('Invalid export format.', 'itx-cheetah'));
        }
    }

    /**
     * Export scan comparison
     */
    public function export_comparison($scan_ids, $format) {
        if (count($scan_ids) < 2) {
            wp_die(__('At least 2 scans required for comparison.', 'itx-cheetah'));
        }

        $scans = array();
        foreach ($scan_ids as $id) {
            $scan = $this->database->get_scan($id);
            if ($scan) {
                $scans[] = $scan;
            }
        }

        if (count($scans) < 2) {
            wp_die(__('Could not find the specified scans.', 'itx-cheetah'));
        }

        $comparison = $this->generate_comparison($scans);
        $filename = 'itx-cheetah-comparison-' . date('Y-m-d');

        switch ($format) {
            case 'csv':
                $this->output_csv($this->format_comparison_for_csv($comparison), $filename);
                break;
            case 'json':
                $this->output_json($comparison, $filename);
                break;
            default:
                wp_die(__('Invalid export format.', 'itx-cheetah'));
        }
    }

    /**
     * Generate comparison data
     */
    public function generate_comparison($scans) {
        $comparison = array(
            'generated_at' => current_time('mysql'),
            'scans' => array(),
            'summary' => array(
                'total_scans' => count($scans),
                'node_change' => 0,
                'depth_change' => 0,
                'score_change' => 0,
            ),
        );

        foreach ($scans as $index => $scan) {
            $comparison['scans'][] = array(
                'id' => $scan['id'],
                'url' => $scan['url'],
                'total_nodes' => $scan['total_nodes'],
                'max_depth' => $scan['max_depth'],
                'performance_score' => $scan['performance_score'],
                'created_at' => $scan['created_at'],
            );

            if ($index > 0) {
                $prev = $scans[$index - 1];
                $comparison['summary']['node_change'] += $scan['total_nodes'] - $prev['total_nodes'];
                $comparison['summary']['depth_change'] += $scan['max_depth'] - $prev['max_depth'];
                $comparison['summary']['score_change'] += $scan['performance_score'] - $prev['performance_score'];
            }
        }

        return $comparison;
    }

    /**
     * Format single scan for CSV
     */
    private function format_scan_for_csv($scan) {
        $rows = array();

        // Header info
        $rows[] = array('ITX Cheetah Scan Report');
        $rows[] = array('Generated', current_time('mysql'));
        $rows[] = array('');

        // Basic info
        $rows[] = array('URL', $scan['url']);
        $rows[] = array('Scan Date', $scan['created_at']);
        $rows[] = array('Scan Time (s)', $scan['scan_time']);
        $rows[] = array('');

        // Metrics
        $rows[] = array('Metrics');
        $rows[] = array('Total Nodes', $scan['total_nodes']);
        $rows[] = array('Max Depth', $scan['max_depth']);
        $rows[] = array('Performance Score', $scan['performance_score']);
        $rows[] = array('');

        // Element counts
        $rows[] = array('Element Distribution');
        $rows[] = array('Element', 'Count', 'Percentage');

        if (!empty($scan['element_counts']) && is_array($scan['element_counts'])) {
            foreach ($scan['element_counts'] as $element => $count) {
                $percentage = $scan['total_nodes'] > 0 ? round(($count / $scan['total_nodes']) * 100, 2) : 0;
                $rows[] = array($element, $count, $percentage . '%');
            }
        }

        $rows[] = array('');

        // Depth distribution
        $rows[] = array('Depth Distribution');
        $rows[] = array('Depth Range', 'Nodes');

        if (!empty($scan['node_distribution']) && is_array($scan['node_distribution'])) {
            foreach ($scan['node_distribution'] as $range => $count) {
                $rows[] = array($range, $count);
            }
        }

        $rows[] = array('');

        // Large nodes
        if (!empty($scan['large_nodes']) && is_array($scan['large_nodes'])) {
            $rows[] = array('Large Nodes (>50 children)');
            $rows[] = array('Element', 'ID', 'Class', 'Children', 'Depth');

            foreach ($scan['large_nodes'] as $node) {
                $rows[] = array(
                    $node['tag'],
                    $node['id'] ?: '-',
                    $node['class'] ?: '-',
                    $node['children'],
                    $node['depth'],
                );
            }
        }

        return $rows;
    }

    /**
     * Format multiple scans for CSV
     */
    private function format_scans_for_csv($scans) {
        $rows = array();

        // Header
        $rows[] = array(
            'ID',
            'URL',
            'Total Nodes',
            'Max Depth',
            'Performance Score',
            'Status',
            'Scan Time (s)',
            'Created At',
        );

        foreach ($scans as $scan) {
            $rows[] = array(
                $scan['id'],
                $scan['url'],
                $scan['total_nodes'],
                $scan['max_depth'],
                $scan['performance_score'],
                $scan['status'],
                $scan['scan_time'],
                $scan['created_at'],
            );
        }

        return $rows;
    }

    /**
     * Format comparison for CSV
     */
    private function format_comparison_for_csv($comparison) {
        $rows = array();

        $rows[] = array('ITX Cheetah Scan Comparison');
        $rows[] = array('Generated', $comparison['generated_at']);
        $rows[] = array('');

        // Summary
        $rows[] = array('Summary');
        $rows[] = array('Total Scans Compared', $comparison['summary']['total_scans']);
        $rows[] = array('Total Node Change', $comparison['summary']['node_change']);
        $rows[] = array('Total Depth Change', $comparison['summary']['depth_change']);
        $rows[] = array('Total Score Change', $comparison['summary']['score_change']);
        $rows[] = array('');

        // Individual scans
        $rows[] = array('Scan Details');
        $rows[] = array('ID', 'URL', 'Total Nodes', 'Max Depth', 'Score', 'Date');

        foreach ($comparison['scans'] as $scan) {
            $rows[] = array(
                $scan['id'],
                $scan['url'],
                $scan['total_nodes'],
                $scan['max_depth'],
                $scan['performance_score'],
                $scan['created_at'],
            );
        }

        return $rows;
    }

    /**
     * Output CSV file
     */
    private function output_csv($rows, $filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
    }

    /**
     * Output JSON file
     */
    private function output_json($data, $filename) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get export URL
     */
    public static function get_export_url($format, $args = array()) {
        $defaults = array(
            'export_type' => 'single',
            'scan_id' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $url = add_query_arg(array(
            'itx_cheetah_export' => $format,
            'export_type' => $args['export_type'],
            '_wpnonce' => wp_create_nonce('itx_cheetah_export'),
        ), admin_url('admin.php'));

        if ($args['scan_id']) {
            $url = add_query_arg('scan_id', $args['scan_id'], $url);
        }

        if (isset($args['scan_ids'])) {
            $url = add_query_arg('scan_ids', implode(',', $args['scan_ids']), $url);
        }

        return $url;
    }

    /**
     * Get site-wide report data
     */
    public function get_site_report() {
        $stats = $this->database->get_statistics();
        $recent_scans = $this->database->get_recent_scans(50);
        $critical_pages = $this->database->get_critical_pages(20);

        // Calculate trends
        $trends = $this->calculate_trends($recent_scans);

        return array(
            'generated_at' => current_time('mysql'),
            'statistics' => $stats,
            'trends' => $trends,
            'critical_pages' => $critical_pages,
            'recent_scans' => $recent_scans,
        );
    }

    /**
     * Calculate trends from recent scans
     */
    private function calculate_trends($scans) {
        if (count($scans) < 2) {
            return array(
                'nodes_trend' => 0,
                'depth_trend' => 0,
                'score_trend' => 0,
            );
        }

        // Compare first half vs second half
        $mid = floor(count($scans) / 2);
        $recent = array_slice($scans, 0, $mid);
        $older = array_slice($scans, $mid);

        $recent_avg_nodes = array_sum(array_column($recent, 'total_nodes')) / count($recent);
        $older_avg_nodes = array_sum(array_column($older, 'total_nodes')) / count($older);

        $recent_avg_depth = array_sum(array_column($recent, 'max_depth')) / count($recent);
        $older_avg_depth = array_sum(array_column($older, 'max_depth')) / count($older);

        $recent_avg_score = array_sum(array_column($recent, 'performance_score')) / count($recent);
        $older_avg_score = array_sum(array_column($older, 'performance_score')) / count($older);

        return array(
            'nodes_trend' => round($recent_avg_nodes - $older_avg_nodes),
            'depth_trend' => round($recent_avg_depth - $older_avg_depth, 1),
            'score_trend' => round($recent_avg_score - $older_avg_score),
            'recent_avg_nodes' => round($recent_avg_nodes),
            'older_avg_nodes' => round($older_avg_nodes),
            'recent_avg_score' => round($recent_avg_score),
            'older_avg_score' => round($older_avg_score),
        );
    }

    /**
     * Get historical data for charts
     */
    public function get_historical_data($days = 30) {
        global $wpdb;
        $table = $this->database->get_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    DATE(created_at) as scan_date,
                    AVG(total_nodes) as avg_nodes,
                    AVG(max_depth) as avg_depth,
                    AVG(performance_score) as avg_score,
                    COUNT(*) as scan_count
                FROM {$table}
                WHERE status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY DATE(created_at)
                ORDER BY scan_date ASC",
                $days
            ),
            ARRAY_A
        );

        return $results;
    }
}
