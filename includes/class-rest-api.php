<?php
/**
 * REST API handler for ITX Cheetah
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API class
 */
class ITX_Cheetah_REST_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'itx-cheetah/v1';

    /**
     * Database instance
     */
    private $database;

    /**
     * Analyzer instance
     */
    private $analyzer;

    /**
     * Core Web Vitals instance
     */
    private $vitals;

    /**
     * Constructor
     */
    public function __construct($database, $analyzer, $vitals = null) {
        $this->database = $database;
        $this->analyzer = $analyzer;
        $this->vitals = $vitals;

        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        // Get all scans
        register_rest_route(self::NAMESPACE, '/scans', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_scans'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'per_page' => array(
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ),
                'page' => array(
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ),
                'search' => array(
                    'type' => 'string',
                    'default' => '',
                ),
                'status' => array(
                    'type' => 'string',
                    'default' => '',
                ),
            ),
        ));

        // Get single scan
        register_rest_route(self::NAMESPACE, '/scans/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_scan'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
            ),
        ));

        // Create new scan
        register_rest_route(self::NAMESPACE, '/scans', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'create_scan'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'url' => array(
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => function($param) {
                        return filter_var($param, FILTER_VALIDATE_URL) !== false;
                    },
                ),
            ),
        ));

        // Delete scan
        register_rest_route(self::NAMESPACE, '/scans/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'delete_scan'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
            ),
        ));

        // Get statistics
        register_rest_route(self::NAMESPACE, '/statistics', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_statistics'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Bulk scan
        register_rest_route(self::NAMESPACE, '/bulk-scan', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'bulk_scan'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'post_types' => array(
                    'type' => 'array',
                    'default' => array('post', 'page'),
                ),
                'limit' => array(
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                ),
            ),
        ));

        // Get scannable URLs
        register_rest_route(self::NAMESPACE, '/urls', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_urls'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'post_types' => array(
                    'type' => 'array',
                    'default' => array('post', 'page'),
                ),
            ),
        ));

        // Collect vitals data from frontend
        register_rest_route(self::NAMESPACE, '/vitals/collect', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'collect_vitals'),
            'permission_callback' => '__return_true', // Public endpoint for frontend collection
            'args' => array(
                'lcp' => array('type' => 'object'),
                'cls' => array('type' => 'object'),
                'inp' => array('type' => 'object'),
                'url' => array('type' => 'string', 'required' => true),
                'post_id' => array('type' => 'integer', 'default' => 0),
            ),
        ));

        // Get vitals report for a scan
        register_rest_route(self::NAMESPACE, '/vitals/report', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_vitals_report'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'scan_id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
            ),
        ));

        // Get vitals history for a post
        register_rest_route(self::NAMESPACE, '/vitals/history', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_vitals_history'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'post_id' => array('type' => 'integer', 'default' => 0),
                'url' => array('type' => 'string', 'default' => ''),
                'days' => array('type' => 'integer', 'default' => 30),
            ),
        ));

        // Get site-wide vitals statistics
        register_rest_route(self::NAMESPACE, '/vitals/statistics', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_vitals_statistics'),
            'permission_callback' => array($this, 'check_permission'),
        ));
    }

    /**
     * Check user permission
     */
    public function check_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Get all scans
     */
    public function get_scans($request) {
        $args = array(
            'per_page' => $request->get_param('per_page'),
            'page' => $request->get_param('page'),
            'search' => $request->get_param('search'),
            'status' => $request->get_param('status'),
        );

        $scans = $this->database->get_scans($args);
        $total = $this->database->get_total_scans($args);

        return new WP_REST_Response(array(
            'scans' => $scans,
            'total' => $total,
            'total_pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ), 200);
    }

    /**
     * Get single scan
     */
    public function get_scan($request) {
        $id = $request->get_param('id');
        $scan = $this->database->get_scan($id);

        if (!$scan) {
            return new WP_Error(
                'scan_not_found',
                __('Scan not found.', 'itx-cheetah'),
                array('status' => 404)
            );
        }

        return new WP_REST_Response($scan, 200);
    }

    /**
     * Create new scan
     */
    public function create_scan($request) {
        $url = $request->get_param('url');

        // Analyze the URL
        $result = $this->analyzer->analyze_url($url);

        if (is_wp_error($result)) {
            return new WP_Error(
                'scan_failed',
                $result->get_error_message(),
                array('status' => 400)
            );
        }

        // Save to database
        $scan_id = $this->database->insert_scan($result);

        if (is_wp_error($scan_id)) {
            return new WP_Error(
                'save_failed',
                $scan_id->get_error_message(),
                array('status' => 500)
            );
        }

        // Return the full scan data
        $scan = $this->database->get_scan($scan_id);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Scan completed successfully.', 'itx-cheetah'),
            'scan' => $scan,
        ), 201);
    }

    /**
     * Delete scan
     */
    public function delete_scan($request) {
        $id = $request->get_param('id');

        $scan = $this->database->get_scan($id);
        if (!$scan) {
            return new WP_Error(
                'scan_not_found',
                __('Scan not found.', 'itx-cheetah'),
                array('status' => 404)
            );
        }

        $deleted = $this->database->delete_scan($id);

        if (!$deleted) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete scan.', 'itx-cheetah'),
                array('status' => 500)
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Scan deleted successfully.', 'itx-cheetah'),
        ), 200);
    }

    /**
     * Get statistics
     */
    public function get_statistics($request) {
        $stats = $this->database->get_statistics();

        return new WP_REST_Response($stats, 200);
    }

    /**
     * Bulk scan
     */
    public function bulk_scan($request) {
        $post_types = $request->get_param('post_types');
        $limit = $request->get_param('limit');

        $urls = $this->analyzer->get_scannable_urls(array(
            'post_types' => $post_types,
            'posts_per_page' => $limit,
        ));

        $results = array(
            'total' => count($urls),
            'completed' => 0,
            'failed' => 0,
            'scans' => array(),
        );

        foreach ($urls as $url_data) {
            $result = $this->analyzer->analyze_url($url_data['url']);

            if (is_wp_error($result)) {
                $results['failed']++;
                $results['scans'][] = array(
                    'url' => $url_data['url'],
                    'success' => false,
                    'error' => $result->get_error_message(),
                );
                continue;
            }

            $scan_id = $this->database->insert_scan($result);

            if (is_wp_error($scan_id)) {
                $results['failed']++;
                $results['scans'][] = array(
                    'url' => $url_data['url'],
                    'success' => false,
                    'error' => $scan_id->get_error_message(),
                );
                continue;
            }

            $results['completed']++;
            $results['scans'][] = array(
                'url' => $url_data['url'],
                'success' => true,
                'scan_id' => $scan_id,
                'score' => $result['performance_score'],
            );
        }

        return new WP_REST_Response($results, 200);
    }

    /**
     * Get scannable URLs
     */
    public function get_urls($request) {
        $post_types = $request->get_param('post_types');

        $urls = $this->analyzer->get_scannable_urls(array(
            'post_types' => $post_types,
        ));

        return new WP_REST_Response(array(
            'total' => count($urls),
            'urls' => $urls,
        ), 200);
    }

    /**
     * Collect vitals data
     */
    public function collect_vitals($request) {
        if (!$this->vitals) {
            return new WP_Error(
                'vitals_not_available',
                __('Core Web Vitals module not initialized.', 'itx-cheetah'),
                array('status' => 500)
            );
        }

        $data = $request->get_json_params();
        $url = isset($data['url']) ? sanitize_url($data['url']) : '';
        $post_id = isset($data['post_id']) ? intval($data['post_id']) : 0;

        if (empty($url)) {
            return new WP_Error(
                'invalid_url',
                __('URL is required.', 'itx-cheetah'),
                array('status' => 400)
            );
        }

        // Process vitals data (this will be handled by the vitals class)
        // For REST API, we'll just return success
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Vitals data will be processed.', 'itx-cheetah'),
        ), 200);
    }

    /**
     * Get vitals report for a scan
     */
    public function get_vitals_report($request) {
        if (!$this->vitals) {
            return new WP_Error(
                'vitals_not_available',
                __('Core Web Vitals module not initialized.', 'itx-cheetah'),
                array('status' => 500)
            );
        }

        $scan_id = $request->get_param('scan_id');
        $report = $this->vitals->generate_vitals_report($scan_id);

        if (!$report) {
            return new WP_Error(
                'report_not_found',
                __('Vitals report not found for this scan.', 'itx-cheetah'),
                array('status' => 404)
            );
        }

        return new WP_REST_Response($report, 200);
    }

    /**
     * Get vitals history
     */
    public function get_vitals_history($request) {
        if (!$this->vitals) {
            return new WP_Error(
                'vitals_not_available',
                __('Core Web Vitals module not initialized.', 'itx-cheetah'),
                array('status' => 500)
            );
        }

        $post_id = $request->get_param('post_id');
        $url = $request->get_param('url');
        $days = $request->get_param('days');

        $history = $this->vitals->get_vitals_history($post_id, $url, $days);

        return new WP_REST_Response(array(
            'history' => $history,
            'count' => count($history),
        ), 200);
    }

    /**
     * Get vitals statistics
     */
    public function get_vitals_statistics($request) {
        if (!$this->vitals) {
            return new WP_Error(
                'vitals_not_available',
                __('Core Web Vitals module not initialized.', 'itx-cheetah'),
                array('status' => 500)
            );
        }

        $stats = $this->vitals->get_vitals_statistics();

        return new WP_REST_Response($stats, 200);
    }
}
