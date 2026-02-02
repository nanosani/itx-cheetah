<?php
/**
 * Database handler for ITX Cheetah
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database class for managing scan data
 */
class ITX_Cheetah_Database {

    /**
     * Table name for scans
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'itx_cheetah_scans';
    }

    /**
     * Get table name
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned DEFAULT 0,
            url varchar(2083) NOT NULL,
            total_nodes int(11) unsigned DEFAULT 0,
            max_depth int(11) unsigned DEFAULT 0,
            element_counts longtext,
            node_distribution longtext,
            large_nodes longtext,
            recommendations longtext,
            html_snapshot longtext,
            performance_score int(3) unsigned DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            scan_time float DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY performance_score (performance_score)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Check if we need to add html_snapshot column (for upgrades)
        $this->maybe_add_html_snapshot_column();

        // Create vitals table
        $this->create_vitals_table();

        // Store database version
        update_option('itx_cheetah_db_version', ITX_CHEETAH_VERSION);
    }

    /**
     * Create vitals table
     */
    private function create_vitals_table() {
        if (!class_exists('ITX_Cheetah_Core_Web_Vitals')) {
            require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-core-web-vitals.php';
        }

        // We need database and analyzer instances, but we'll create a minimal version
        global $wpdb;
        $vitals_table = $wpdb->prefix . 'itx_cheetah_vitals';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$vitals_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_id bigint(20) unsigned DEFAULT 0,
            url varchar(500) NOT NULL,
            post_id bigint(20) unsigned DEFAULT 0,
            lcp_score float DEFAULT NULL,
            lcp_element varchar(255) DEFAULT NULL,
            lcp_element_type varchar(50) DEFAULT NULL,
            lcp_element_size varchar(50) DEFAULT NULL,
            lcp_load_time float DEFAULT NULL,
            lcp_ttfb_impact float DEFAULT NULL,
            lcp_resource_load_time float DEFAULT NULL,
            render_blocking_resources_count int(11) DEFAULT 0,
            cls_score float DEFAULT NULL,
            cls_shifts_count int(11) DEFAULT 0,
            cls_elements longtext,
            missing_dimensions_count int(11) DEFAULT 0,
            dynamic_content_insertions int(11) DEFAULT 0,
            font_loading_shifts int(11) DEFAULT 0,
            inp_score float DEFAULT NULL,
            fid_score float DEFAULT NULL,
            long_tasks_count int(11) DEFAULT 0,
            long_tasks_total_time float DEFAULT NULL,
            js_execution_time float DEFAULT NULL,
            event_listeners_count int(11) DEFAULT 0,
            main_thread_blocking_time float DEFAULT NULL,
            input_delay_max float DEFAULT NULL,
            input_delay_avg float DEFAULT NULL,
            raw_metrics longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scan_id (scan_id),
            KEY post_id (post_id),
            KEY url (url(191))
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Add html_snapshot column if it doesn't exist (for database upgrades)
     */
    private function maybe_add_html_snapshot_column() {
        global $wpdb;

        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'html_snapshot'",
                DB_NAME,
                $this->table_name
            )
        );

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN html_snapshot longtext AFTER recommendations");
        }
    }

    /**
     * Drop tables on uninstall
     */
    public function drop_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        
        // Drop vitals table
        $vitals_table = $wpdb->prefix . 'itx_cheetah_vitals';
        $wpdb->query("DROP TABLE IF EXISTS {$vitals_table}");
        
        delete_option('itx_cheetah_db_version');
    }

    /**
     * Insert a new scan
     */
    public function insert_scan($data) {
        global $wpdb;

        $defaults = array(
            'post_id' => 0,
            'url' => '',
            'total_nodes' => 0,
            'max_depth' => 0,
            'element_counts' => '',
            'node_distribution' => '',
            'large_nodes' => '',
            'recommendations' => '',
            'html_snapshot' => '',
            'performance_score' => 0,
            'status' => 'pending',
            'scan_time' => 0,
        );

        $data = wp_parse_args($data, $defaults);

        // Serialize arrays
        if (is_array($data['element_counts'])) {
            $data['element_counts'] = maybe_serialize($data['element_counts']);
        }
        if (is_array($data['node_distribution'])) {
            $data['node_distribution'] = maybe_serialize($data['node_distribution']);
        }
        if (is_array($data['large_nodes'])) {
            $data['large_nodes'] = maybe_serialize($data['large_nodes']);
        }
        if (is_array($data['recommendations'])) {
            $data['recommendations'] = maybe_serialize($data['recommendations']);
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'post_id' => intval($data['post_id']),
                'url' => sanitize_url($data['url']),
                'total_nodes' => intval($data['total_nodes']),
                'max_depth' => intval($data['max_depth']),
                'element_counts' => $data['element_counts'],
                'node_distribution' => $data['node_distribution'],
                'large_nodes' => $data['large_nodes'],
                'recommendations' => $data['recommendations'],
                'html_snapshot' => $data['html_snapshot'],
                'performance_score' => intval($data['performance_score']),
                'status' => sanitize_text_field($data['status']),
                'scan_time' => floatval($data['scan_time']),
            ),
            array('%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f')
        );

        if ($result === false) {
            return new WP_Error('db_insert_error', $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a scan
     */
    public function update_scan($id, $data) {
        global $wpdb;

        // Serialize arrays if present
        if (isset($data['element_counts']) && is_array($data['element_counts'])) {
            $data['element_counts'] = maybe_serialize($data['element_counts']);
        }
        if (isset($data['node_distribution']) && is_array($data['node_distribution'])) {
            $data['node_distribution'] = maybe_serialize($data['node_distribution']);
        }
        if (isset($data['large_nodes']) && is_array($data['large_nodes'])) {
            $data['large_nodes'] = maybe_serialize($data['large_nodes']);
        }
        if (isset($data['recommendations']) && is_array($data['recommendations'])) {
            $data['recommendations'] = maybe_serialize($data['recommendations']);
        }

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_update_error', $wpdb->last_error);
        }

        return true;
    }

    /**
     * Get a single scan by ID
     */
    public function get_scan($id) {
        global $wpdb;

        $scan = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );

        if ($scan) {
            $scan = $this->unserialize_scan($scan);
        }

        return $scan;
    }

    /**
     * Get scan by URL
     */
    public function get_scan_by_url($url) {
        global $wpdb;

        $scan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE url = %s ORDER BY created_at DESC LIMIT 1",
                $url
            ),
            ARRAY_A
        );

        if ($scan) {
            $scan = $this->unserialize_scan($scan);
        }

        return $scan;
    }

    /**
     * Get scans with pagination
     */
    public function get_scans($args = array()) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'status' => '',
            'search' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $where[] = 'url LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $where_clause = implode(' AND ', $where);

        // Validate orderby
        $allowed_orderby = array('id', 'url', 'total_nodes', 'max_depth', 'performance_score', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($args['page'] - 1) * $args['per_page'];

        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        $scans = $wpdb->get_results(
            $wpdb->prepare($query, $values),
            ARRAY_A
        );

        // Unserialize data
        foreach ($scans as &$scan) {
            $scan = $this->unserialize_scan($scan);
        }

        return $scans;
    }

    /**
     * Get total count of scans
     */
    public function get_total_scans($args = array()) {
        global $wpdb;

        $where = array('1=1');
        $values = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $where[] = 'url LIKE %s';
            $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($values)) {
            $count = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}", $values)
            );
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}");
        }

        return intval($count);
    }

    /**
     * Delete a scan
     */
    public function delete_scan($id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete multiple scans
     */
    public function delete_scans($ids) {
        global $wpdb;

        if (empty($ids)) {
            return false;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $result = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})", $ids)
        );

        return $result !== false;
    }

    /**
     * Get statistics
     */
    public function get_statistics() {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_scans,
                AVG(total_nodes) as avg_nodes,
                MAX(total_nodes) as max_nodes,
                MIN(total_nodes) as min_nodes,
                AVG(max_depth) as avg_depth,
                MAX(max_depth) as max_depth_value,
                AVG(performance_score) as avg_score,
                SUM(CASE WHEN performance_score >= 80 THEN 1 ELSE 0 END) as good_count,
                SUM(CASE WHEN performance_score >= 50 AND performance_score < 80 THEN 1 ELSE 0 END) as warning_count,
                SUM(CASE WHEN performance_score < 50 THEN 1 ELSE 0 END) as critical_count
            FROM {$this->table_name}
            WHERE status = 'completed'",
            ARRAY_A
        );

        return array(
            'total_scans' => intval($stats['total_scans']),
            'avg_nodes' => round(floatval($stats['avg_nodes'])),
            'max_nodes' => intval($stats['max_nodes']),
            'min_nodes' => intval($stats['min_nodes']),
            'avg_depth' => round(floatval($stats['avg_depth']), 1),
            'max_depth' => intval($stats['max_depth_value']),
            'avg_score' => round(floatval($stats['avg_score'])),
            'good_count' => intval($stats['good_count']),
            'warning_count' => intval($stats['warning_count']),
            'critical_count' => intval($stats['critical_count']),
        );
    }

    /**
     * Get recent scans
     */
    public function get_recent_scans($limit = 10) {
        global $wpdb;

        $scans = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE status = 'completed' ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        foreach ($scans as &$scan) {
            $scan = $this->unserialize_scan($scan);
        }

        return $scans;
    }

    /**
     * Get critical pages (low performance score)
     */
    public function get_critical_pages($limit = 10) {
        global $wpdb;

        $scans = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE status = 'completed' AND performance_score < 50
                ORDER BY performance_score ASC, total_nodes DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        foreach ($scans as &$scan) {
            $scan = $this->unserialize_scan($scan);
        }

        return $scans;
    }

    /**
     * Unserialize scan data
     */
    private function unserialize_scan($scan) {
        if (!is_array($scan)) {
            return $scan;
        }

        $scan['element_counts'] = maybe_unserialize($scan['element_counts']);
        $scan['node_distribution'] = maybe_unserialize($scan['node_distribution']);
        $scan['large_nodes'] = maybe_unserialize($scan['large_nodes']);
        $scan['recommendations'] = maybe_unserialize($scan['recommendations']);

        return $scan;
    }

    /**
     * Clean old scans based on retention period
     */
    public function cleanup_old_scans($days = 90) {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $result;
    }
}
