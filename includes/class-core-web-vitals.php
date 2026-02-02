<?php
/**
 * Core Web Vitals Audit Module
 *
 * Analyzes, tracks, and reports on LCP, CLS, and INP/FID metrics
 * with actionable insights for WordPress sites.
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core Web Vitals class
 */
class ITX_Cheetah_Core_Web_Vitals {

    /**
     * Database instance
     */
    private $database;

    /**
     * Analyzer instance
     */
    private $analyzer;

    /**
     * Table name for vitals
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct($database, $analyzer) {
        global $wpdb;
        $this->database = $database;
        $this->analyzer = $analyzer;
        $this->table_name = $wpdb->prefix . 'itx_cheetah_vitals';

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_vitals_collector'));
        add_action('wp_ajax_itx_cheetah_collect_vitals', array($this, 'handle_vitals_collection'));
        add_action('wp_ajax_nopriv_itx_cheetah_collect_vitals', array($this, 'handle_vitals_collection'));
        add_action('itx_cheetah_after_scan', array($this, 'run_vitals_audit'), 10, 2);
    }

    /**
     * Enqueue vitals collector script
     */
    public function enqueue_vitals_collector() {
        // Only collect on frontend
        if (is_admin()) {
            return;
        }

        // Get current URL - use a more reliable method that works on all page types
        $current_url = '';
        
        // Try WordPress functions first for better accuracy
        if (is_singular()) {
            $current_url = get_permalink();
        } elseif (is_home() || is_front_page()) {
            $current_url = home_url('/');
        } elseif (is_category()) {
            $current_url = get_category_link(get_queried_object_id());
        } elseif (is_tag()) {
            $current_url = get_tag_link(get_queried_object_id());
        } elseif (is_tax()) {
            $term = get_queried_object();
            $current_url = get_term_link($term);
            if (is_wp_error($current_url)) {
                $current_url = '';
            }
        } elseif (is_archive()) {
            // For post type archives
            $post_type = get_query_var('post_type');
            if ($post_type) {
                $current_url = get_post_type_archive_link($post_type);
            } else {
                $current_url = get_author_posts_url(get_queried_object_id());
            }
            if (empty($current_url) || is_wp_error($current_url)) {
                $current_url = '';
            }
        } elseif (is_search()) {
            $current_url = home_url('/?s=' . urlencode(get_search_query()));
        }
        
        // Fallback to server variables if WordPress functions didn't work
        // This ensures we always have a URL, even for custom routes
        if (empty($current_url)) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
            $current_url = $protocol . '://' . $host . $uri;
        }
        
        // Clean up the URL and remove query strings that might interfere
        $current_url = esc_url_raw($current_url);

        wp_enqueue_script(
            'itx-cheetah-vitals-collector',
            ITX_CHEETAH_PLUGIN_URL . 'assets/js/vitals-collector.js',
            array(),
            ITX_CHEETAH_VERSION,
            true
        );

        wp_localize_script('itx-cheetah-vitals-collector', 'itxCheetahVitals', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('itx_cheetah_vitals'),
            'post_id' => get_the_ID() ?: 0,
            'url' => $current_url ?: home_url('/'),
        ));
    }

    /**
     * Handle vitals data collection from frontend
     */
    public function handle_vitals_collection() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'itx_cheetah_vitals')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'itx-cheetah')));
        }

        // Get data from POST (can be JSON string or array)
        $data_string = isset($_POST['data']) ? $_POST['data'] : '';
        
        if (empty($data_string)) {
            // Try to get from raw input
            $raw_input = file_get_contents('php://input');
            $data = json_decode($raw_input, true);
            if ($data && isset($data['data'])) {
                $vitals_data = $data['data'];
            } else {
                wp_send_json_error(array('message' => __('Invalid data received.', 'itx-cheetah')));
            }
        } else {
            // Decode JSON string
            $vitals_data = json_decode(stripslashes($data_string), true);
            if (!$vitals_data) {
                wp_send_json_error(array('message' => __('Failed to decode data.', 'itx-cheetah')));
            }
        }

        $url = isset($vitals_data['url']) ? sanitize_url($vitals_data['url']) : '';
        $post_id = isset($vitals_data['post_id']) ? intval($vitals_data['post_id']) : 0;

        if (empty($url)) {
            wp_send_json_error(array('message' => __('URL is required.', 'itx-cheetah')));
        }

        // Process and store vitals data
        $processed = $this->process_vitals_data($vitals_data, $url, $post_id);

        if (is_wp_error($processed)) {
            wp_send_json_error(array('message' => $processed->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Vitals data collected successfully.', 'itx-cheetah'),
            'vitals_id' => $processed,
        ));
    }

    /**
     * Process and store vitals data
     */
    private function process_vitals_data($data, $url, $post_id) {
        global $wpdb;

        // Extract LCP data
        $lcp = isset($data['lcp']) ? $data['lcp'] : array();
        $lcp_score = isset($lcp['loadTime']) ? floatval($lcp['loadTime']) / 1000 : 0; // Convert to seconds
        $lcp_element = isset($lcp['element']) ? sanitize_text_field($lcp['element']) : '';
        $lcp_element_type = isset($lcp['elementType']) ? sanitize_text_field($lcp['elementType']) : '';
        $lcp_element_size = isset($lcp['elementSize']) ? sanitize_text_field($lcp['elementSize']) : '';
        $lcp_load_time = $lcp_score;
        $lcp_url = isset($lcp['url']) ? sanitize_url($lcp['url']) : '';

        // Extract CLS data
        $cls = isset($data['cls']) ? $data['cls'] : array();
        $cls_score = isset($cls['score']) ? floatval($cls['score']) : 0;
        $cls_shifts_count = isset($cls['shifts']) && is_array($cls['shifts']) ? count($cls['shifts']) : 0;
        $cls_elements = isset($cls['shifts']) ? maybe_serialize($cls['shifts']) : '';

        // Extract INP/FID data
        $inp = isset($data['inp']) ? $data['inp'] : array();
        $inp_score = isset($inp['score']) ? floatval($inp['score']) : 0;
        $fid_score = isset($inp['fid']) ? floatval($inp['fid']) : 0;
        $long_tasks = isset($inp['longTasks']) ? $inp['longTasks'] : array();
        $long_tasks_count = isset($long_tasks['count']) ? intval($long_tasks['count']) : 0;
        $long_tasks_total_time = isset($long_tasks['total_time']) ? floatval($long_tasks['total_time']) : 0;
        $input_delay = isset($inp['inputDelay']) ? $inp['inputDelay'] : array();
        $input_delay_max = isset($input_delay['max_delay']) ? floatval($input_delay['max_delay']) : 0;
        $input_delay_avg = isset($input_delay['avg_delay']) ? floatval($input_delay['avg_delay']) : 0;

        // Get HTML for server-side analysis
        $html = $this->get_page_html($url);

        // Analyze TTFB impact
        $ttfb_data = $this->analyze_ttfb_impact($lcp_load_time, $html);

        // Detect render-blocking resources
        $render_blocking = $this->detect_render_blocking_resources($html);

        // Detect missing dimensions
        $missing_dimensions = $this->detect_missing_dimensions($html);

        // Detect font loading shifts
        $font_shifts = $this->detect_font_loading_shifts($html);

        // Calculate additional metrics
        $js_execution_time = $this->estimate_js_execution_time($html);
        $event_listeners_count = isset($inp['eventListeners']) ? intval($inp['eventListeners']['total_listeners']) : 0;
        $main_thread_blocking_time = $long_tasks_total_time;

        // Store raw metrics
        $raw_metrics = maybe_serialize($data);

        // Try to find associated scan_id
        $scan_id = $this->find_associated_scan($url, $post_id);

        // Insert vitals data
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'scan_id' => $scan_id,
                'url' => $url,
                'post_id' => $post_id,
                'lcp_score' => $lcp_score,
                'lcp_element' => $lcp_element,
                'lcp_element_type' => $lcp_element_type,
                'lcp_element_size' => $lcp_element_size,
                'lcp_load_time' => $lcp_load_time,
                'lcp_ttfb_impact' => $ttfb_data['ttfb_percentage'],
                'lcp_resource_load_time' => $ttfb_data['resource_load_time'],
                'render_blocking_resources_count' => $render_blocking['total_count'],
                'cls_score' => $cls_score,
                'cls_shifts_count' => $cls_shifts_count,
                'cls_elements' => $cls_elements,
                'missing_dimensions_count' => count($missing_dimensions),
                'dynamic_content_insertions' => 0, // Would need DOM mutation observer
                'font_loading_shifts' => count($font_shifts),
                'inp_score' => $inp_score,
                'fid_score' => $fid_score,
                'long_tasks_count' => $long_tasks_count,
                'long_tasks_total_time' => $long_tasks_total_time,
                'js_execution_time' => $js_execution_time,
                'event_listeners_count' => $event_listeners_count,
                'main_thread_blocking_time' => $main_thread_blocking_time,
                'input_delay_max' => $input_delay_max,
                'input_delay_avg' => $input_delay_avg,
                'raw_metrics' => $raw_metrics,
            ),
            array(
                '%d', '%s', '%d',
                '%f', '%s', '%s', '%s', '%f', '%f', '%f', '%d',
                '%f', '%d', '%s', '%d', '%d', '%d',
                '%f', '%f', '%d', '%f', '%f', '%d', '%f', '%f', '%f',
                '%s',
            )
        );

        if ($result === false) {
            return new WP_Error('db_insert_error', $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Run vitals audit after DOM scan
     */
    public function run_vitals_audit($scan_id, $scan_data) {
        // This would trigger a frontend collection
        // For now, we'll analyze what we can server-side
        $url = isset($scan_data['url']) ? $scan_data['url'] : '';
        $html = $this->get_page_html($url);

        if (empty($html)) {
            return;
        }

        // Perform server-side analysis
        $server_analysis = $this->perform_server_side_analysis($html, $scan_data);

        // Store or update vitals with scan_id
        $this->update_vitals_for_scan($scan_id, $server_analysis);
    }

    /**
     * Perform server-side analysis
     */
    private function perform_server_side_analysis($html, $scan_data) {
        $analysis = array();

        // Analyze render-blocking resources
        $analysis['render_blocking'] = $this->detect_render_blocking_resources($html);

        // Detect missing dimensions
        $analysis['missing_dimensions'] = $this->detect_missing_dimensions($html);

        // Detect font loading issues
        $analysis['font_shifts'] = $this->detect_font_loading_shifts($html);

        // Estimate resource load times
        $analysis['resource_analysis'] = $this->analyze_resource_load_times($html);

        return $analysis;
    }

    /**
     * Get page HTML
     */
    private function get_page_html($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'ITX-Cheetah-Vitals/1.0',
        ));

        if (is_wp_error($response)) {
            return '';
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Analyze TTFB impact on LCP
     */
    private function analyze_ttfb_impact($lcp_time, $html) {
        // Get TTFB from performance timing if available
        // For server-side, we'll estimate based on resource analysis
        $resource_load_time = $this->estimate_resource_load_time($html);
        $estimated_ttfb = $resource_load_time * 0.3; // Rough estimate

        $ttfb_percentage = $lcp_time > 0 ? ($estimated_ttfb / $lcp_time) * 100 : 0;

        return array(
            'ttfb_time' => $estimated_ttfb,
            'ttfb_percentage' => round($ttfb_percentage, 2),
            'impact_level' => $ttfb_percentage > 30 ? 'high' : ($ttfb_percentage > 15 ? 'medium' : 'low'),
            'resource_load_time' => $resource_load_time,
            'recommendation' => $this->get_ttfb_recommendation($ttfb_percentage),
        );
    }

    /**
     * Get TTFB recommendation
     */
    private function get_ttfb_recommendation($ttfb_percentage) {
        if ($ttfb_percentage > 30) {
            return __('Optimize server response time. Consider using a CDN, caching, or upgrading hosting.', 'itx-cheetah');
        } elseif ($ttfb_percentage > 15) {
            return __('Server response time is moderate. Consider optimizing database queries and enabling caching.', 'itx-cheetah');
        }
        return __('Server response time is good.', 'itx-cheetah');
    }

    /**
     * Detect render-blocking resources
     */
    private function detect_render_blocking_resources($html) {
        if (empty($html)) {
            return array('css_count' => 0, 'js_count' => 0, 'total_count' => 0, 'css_resources' => array(), 'js_resources' => array());
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $blocking_css = array();
        $blocking_js = array();

        // Find CSS link tags without media or with blocking media
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            $rel = $link->getAttribute('rel');
            $media = $link->getAttribute('media');
            if ($rel === 'stylesheet' && (empty($media) || $media === 'all' || $media === 'screen')) {
                $href = $link->getAttribute('href');
                if ($href) {
                    $blocking_css[] = $href;
                }
            }
        }

        // Find script tags without async/defer
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $src = $script->getAttribute('src');
            $async = $script->getAttribute('async');
            $defer = $script->getAttribute('defer');
            $type = $script->getAttribute('type');

            // Skip module scripts and non-blocking scripts
            if ($type === 'module' || $async || $defer) {
                continue;
            }

            if ($src) {
                $blocking_js[] = $src;
            } elseif (trim($script->textContent)) {
                $blocking_js[] = 'inline';
            }
        }

        return array(
            'css_count' => count($blocking_css),
            'js_count' => count($blocking_js),
            'total_count' => count($blocking_css) + count($blocking_js),
            'css_resources' => $blocking_css,
            'js_resources' => $blocking_js,
        );
    }

    /**
     * Detect images/videos without width/height attributes
     */
    private function detect_missing_dimensions($html) {
        if (empty($html)) {
            return array();
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $issues = array();

        // Check images
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $width = $img->getAttribute('width');
            $height = $img->getAttribute('height');
            if (empty($width) || empty($height)) {
                $src = $img->getAttribute('src');
                $issues[] = array(
                    'type' => 'image',
                    'src' => $src,
                    'alt' => $img->getAttribute('alt'),
                );
            }
        }

        // Check videos
        $videos = $dom->getElementsByTagName('video');
        foreach ($videos as $video) {
            $width = $video->getAttribute('width');
            $height = $video->getAttribute('height');
            if (empty($width) || empty($height)) {
                $src = $video->getAttribute('src');
                $issues[] = array(
                    'type' => 'video',
                    'src' => $src,
                );
            }
        }

        return $issues;
    }

    /**
     * Detect font loading shifts
     */
    private function detect_font_loading_shifts($html) {
        if (empty($html)) {
            return array();
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $issues = array();

        // Check for web fonts in link tags
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (preg_match('/\.(woff|woff2|ttf|otf)/i', $href)) {
                // Check if font-display is set in CSS (would need to fetch CSS)
                // For now, flag fonts without explicit optimization
                $issues[] = array(
                    'font_url' => $href,
                    'display' => 'not_checked',
                    'recommendation' => __('Use font-display: swap or optional in @font-face declaration', 'itx-cheetah'),
                );
            }
        }

        // Check for @font-face in style tags
        $styles = $dom->getElementsByTagName('style');
        foreach ($styles as $style) {
            $content = $style->textContent;
            if (preg_match('/@font-face[^}]*font-display:\s*(none|block)/i', $content)) {
                $issues[] = array(
                    'font_url' => 'inline',
                    'display' => 'block_or_none',
                    'recommendation' => __('Change font-display to swap or optional', 'itx-cheetah'),
                );
            }
        }

        return $issues;
    }

    /**
     * Estimate JavaScript execution time
     */
    private function estimate_js_execution_time($html) {
        if (empty($html)) {
            return 0;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $script_count = 0;
        $inline_script_size = 0;

        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $script_count++;
            $inline_content = trim($script->textContent);
            if (!empty($inline_content)) {
                $inline_script_size += strlen($inline_content);
            }
        }

        // Rough estimate: 1ms per script + 0.01ms per KB of inline code
        $estimated_time = ($script_count * 1) + ($inline_script_size / 1024 * 0.01);

        return round($estimated_time, 2);
    }

    /**
     * Estimate resource load time
     */
    private function estimate_resource_load_time($html) {
        if (empty($html)) {
            return 0;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $resource_count = 0;

        // Count CSS files
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            if ($link->getAttribute('rel') === 'stylesheet') {
                $resource_count++;
            }
        }

        // Count JS files
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            if ($script->getAttribute('src')) {
                $resource_count++;
            }
        }

        // Rough estimate: 100ms per resource
        return $resource_count * 0.1;
    }

    /**
     * Analyze resource load times
     */
    private function analyze_resource_load_times($html) {
        if (empty($html)) {
            return array();
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $resources = array(
            'css' => array(),
            'js' => array(),
            'images' => array(),
            'fonts' => array(),
        );

        // Get CSS resources
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($link->getAttribute('rel') === 'stylesheet' && $href) {
                $resources['css'][] = $href;
            } elseif (preg_match('/\.(woff|woff2|ttf|otf)/i', $href)) {
                $resources['fonts'][] = $href;
            }
        }

        // Get JS resources
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $src = $script->getAttribute('src');
            if ($src) {
                $resources['js'][] = $src;
            }
        }

        // Get image resources
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if ($src) {
                $resources['images'][] = $src;
            }
        }

        return $resources;
    }

    /**
     * Find associated scan for URL
     */
    private function find_associated_scan($url, $post_id) {
        global $wpdb;
        $scans_table = $this->database->get_table_name();

        $scan = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$scans_table} WHERE url = %s ORDER BY created_at DESC LIMIT 1",
                $url
            )
        );

        return $scan ? intval($scan) : 0;
    }

    /**
     * Update vitals for a scan
     */
    private function update_vitals_for_scan($scan_id, $server_analysis) {
        global $wpdb;

        // Find existing vitals record
        $vitals_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE scan_id = %d ORDER BY created_at DESC LIMIT 1",
                $scan_id
            )
        );

        if ($vitals_id) {
            // Update existing record
            $wpdb->update(
                $this->table_name,
                array(
                    'render_blocking_resources_count' => $server_analysis['render_blocking']['total_count'],
                    'missing_dimensions_count' => count($server_analysis['missing_dimensions']),
                    'font_loading_shifts' => count($server_analysis['font_shifts']),
                ),
                array('id' => $vitals_id),
                array('%d', '%d', '%d'),
                array('%d')
            );
        }
    }

    /**
     * Get vitals data for a scan
     */
    public function get_vitals_data($scan_id) {
        global $wpdb;

        $vitals = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE scan_id = %d ORDER BY created_at DESC LIMIT 1", $scan_id),
            ARRAY_A
        );

        if ($vitals) {
            $vitals['cls_elements'] = maybe_unserialize($vitals['cls_elements']);
            $vitals['raw_metrics'] = maybe_unserialize($vitals['raw_metrics']);
        }

        return $vitals;
    }

    /**
     * Get vitals history for a post/URL
     */
    public function get_vitals_history($post_id = 0, $url = '', $days = 30) {
        global $wpdb;

        $where = array();
        $values = array();

        if ($post_id > 0) {
            $where[] = 'post_id = %d';
            $values[] = $post_id;
        }

        if (!empty($url)) {
            $where[] = 'url = %s';
            $values[] = $url;
        }

        $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
        $values[] = $days;

        $where_clause = implode(' AND ', $where);

        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC";
        $vitals = $wpdb->get_results(
            $wpdb->prepare($query, $values),
            ARRAY_A
        );

        foreach ($vitals as &$vital) {
            $vital['cls_elements'] = maybe_unserialize($vital['cls_elements']);
            $vital['raw_metrics'] = maybe_unserialize($vital['raw_metrics']);
        }

        return $vitals;
    }

    /**
     * Get site-wide vitals statistics
     */
    public function get_vitals_statistics() {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_audits,
                AVG(lcp_score) as avg_lcp,
                AVG(cls_score) as avg_cls,
                AVG(inp_score) as avg_inp,
                AVG(fid_score) as avg_fid,
                SUM(CASE WHEN lcp_score < 2.5 THEN 1 ELSE 0 END) as lcp_good,
                SUM(CASE WHEN lcp_score >= 2.5 AND lcp_score < 4.0 THEN 1 ELSE 0 END) as lcp_needs_improvement,
                SUM(CASE WHEN lcp_score >= 4.0 THEN 1 ELSE 0 END) as lcp_poor,
                SUM(CASE WHEN cls_score < 0.1 THEN 1 ELSE 0 END) as cls_good,
                SUM(CASE WHEN cls_score >= 0.1 AND cls_score < 0.25 THEN 1 ELSE 0 END) as cls_needs_improvement,
                SUM(CASE WHEN cls_score >= 0.25 THEN 1 ELSE 0 END) as cls_poor,
                SUM(CASE WHEN inp_score < 200 THEN 1 ELSE 0 END) as inp_good,
                SUM(CASE WHEN inp_score >= 200 AND inp_score < 500 THEN 1 ELSE 0 END) as inp_needs_improvement,
                SUM(CASE WHEN inp_score >= 500 THEN 1 ELSE 0 END) as inp_poor
            FROM {$this->table_name}",
            ARRAY_A
        );

        return array(
            'total_audits' => intval($stats['total_audits']),
            'avg_lcp' => round(floatval($stats['avg_lcp']), 2),
            'avg_cls' => round(floatval($stats['avg_cls']), 3),
            'avg_inp' => round(floatval($stats['avg_inp']), 2),
            'avg_fid' => round(floatval($stats['avg_fid']), 2),
            'lcp_distribution' => array(
                'good' => intval($stats['lcp_good']),
                'needs_improvement' => intval($stats['lcp_needs_improvement']),
                'poor' => intval($stats['lcp_poor']),
            ),
            'cls_distribution' => array(
                'good' => intval($stats['cls_good']),
                'needs_improvement' => intval($stats['cls_needs_improvement']),
                'poor' => intval($stats['cls_poor']),
            ),
            'inp_distribution' => array(
                'good' => intval($stats['inp_good']),
                'needs_improvement' => intval($stats['inp_needs_improvement']),
                'poor' => intval($stats['inp_poor']),
            ),
        );
    }

    /**
     * Generate vitals report
     */
    public function generate_vitals_report($scan_id) {
        $vitals = $this->get_vitals_data($scan_id);

        if (!$vitals) {
            return null;
        }

        return array(
            'overall_score' => $this->calculate_overall_score($vitals),
            'lcp' => array(
                'score' => $vitals['lcp_score'],
                'status' => $this->get_status($vitals['lcp_score'], 'lcp'),
                'element' => $vitals['lcp_element'],
                'element_type' => $vitals['lcp_element_type'],
                'load_time' => $vitals['lcp_load_time'],
                'ttfb_impact' => $vitals['lcp_ttfb_impact'],
                'issues' => $this->get_lcp_issues($vitals),
                'recommendations' => $this->get_lcp_recommendations($vitals),
            ),
            'cls' => array(
                'score' => $vitals['cls_score'],
                'status' => $this->get_status($vitals['cls_score'], 'cls'),
                'shifts_count' => $vitals['cls_shifts_count'],
                'issues' => $this->get_cls_issues($vitals),
                'recommendations' => $this->get_cls_recommendations($vitals),
            ),
            'inp' => array(
                'score' => $vitals['inp_score'],
                'fid_score' => $vitals['fid_score'],
                'status' => $this->get_status($vitals['inp_score'], 'inp'),
                'long_tasks' => $vitals['long_tasks_count'],
                'issues' => $this->get_inp_issues($vitals),
                'recommendations' => $this->get_inp_recommendations($vitals),
            ),
        );
    }

    /**
     * Calculate overall score
     */
    private function calculate_overall_score($vitals) {
        $lcp_score = $this->get_score_value($vitals['lcp_score'], 'lcp');
        $cls_score = $this->get_score_value($vitals['cls_score'], 'cls');
        $inp_score = $this->get_score_value($vitals['inp_score'], 'inp');

        // Weighted average (LCP 40%, CLS 30%, INP 30%)
        $overall = ($lcp_score * 0.4) + ($cls_score * 0.3) + ($inp_score * 0.3);

        return round($overall);
    }

    /**
     * Get score value (0-100)
     */
    private function get_score_value($value, $metric) {
        switch ($metric) {
            case 'lcp':
                if ($value < 2.5) return 100;
                if ($value < 4.0) return 50;
                return 0;
            case 'cls':
                if ($value < 0.1) return 100;
                if ($value < 0.25) return 50;
                return 0;
            case 'inp':
                if ($value < 200) return 100;
                if ($value < 500) return 50;
                return 0;
        }
        return 0;
    }

    /**
     * Get status label
     */
    private function get_status($value, $metric) {
        switch ($metric) {
            case 'lcp':
                if ($value < 2.5) return 'good';
                if ($value < 4.0) return 'needs-improvement';
                return 'poor';
            case 'cls':
                if ($value < 0.1) return 'good';
                if ($value < 0.25) return 'needs-improvement';
                return 'poor';
            case 'inp':
                if ($value < 200) return 'good';
                if ($value < 500) return 'needs-improvement';
                return 'poor';
        }
        return 'unknown';
    }

    /**
     * Get LCP issues
     */
    private function get_lcp_issues($vitals) {
        $issues = array();

        if ($vitals['lcp_ttfb_impact'] > 30) {
            $issues[] = array(
                'type' => 'ttfb',
                'severity' => 'high',
                'message' => sprintf(__('TTFB accounts for %.1f%% of LCP time', 'itx-cheetah'), $vitals['lcp_ttfb_impact']),
            );
        }

        if ($vitals['render_blocking_resources_count'] > 5) {
            $issues[] = array(
                'type' => 'render_blocking',
                'severity' => 'high',
                'message' => sprintf(__('%d render-blocking resources detected', 'itx-cheetah'), $vitals['render_blocking_resources_count']),
            );
        }

        return $issues;
    }

    /**
     * Get LCP recommendations
     */
    private function get_lcp_recommendations($vitals) {
        $recommendations = array();

        if ($vitals['lcp_ttfb_impact'] > 30) {
            $recommendations[] = __('Optimize server response time. Consider using a CDN, caching, or upgrading hosting.', 'itx-cheetah');
        }

        if ($vitals['render_blocking_resources_count'] > 5) {
            $recommendations[] = __('Defer non-critical CSS and JavaScript. Use async/defer attributes for scripts.', 'itx-cheetah');
        }

        if ($vitals['lcp_element_type'] === 'image') {
            $recommendations[] = __('Preload the LCP image. Add <link rel="preload" as="image" href="..."> in the <head>.', 'itx-cheetah');
        }

        return $recommendations;
    }

    /**
     * Get CLS issues
     */
    private function get_cls_issues($vitals) {
        $issues = array();

        if ($vitals['missing_dimensions_count'] > 0) {
            $issues[] = array(
                'type' => 'missing_dimensions',
                'severity' => 'high',
                'message' => sprintf(__('%d images/videos without width/height attributes', 'itx-cheetah'), $vitals['missing_dimensions_count']),
            );
        }

        if ($vitals['font_loading_shifts'] > 0) {
            $issues[] = array(
                'type' => 'font_shifts',
                'severity' => 'medium',
                'message' => sprintf(__('%d font loading issues detected', 'itx-cheetah'), $vitals['font_loading_shifts']),
            );
        }

        return $issues;
    }

    /**
     * Get CLS recommendations
     */
    private function get_cls_recommendations($vitals) {
        $recommendations = array();

        if ($vitals['missing_dimensions_count'] > 0) {
            $recommendations[] = __('Add width and height attributes to all images and videos.', 'itx-cheetah');
        }

        if ($vitals['font_loading_shifts'] > 0) {
            $recommendations[] = __('Use font-display: swap or optional in @font-face declarations.', 'itx-cheetah');
        }

        if ($vitals['cls_shifts_count'] > 5) {
            $recommendations[] = __('Reserve space for dynamic content (ads, embeds) to prevent layout shifts.', 'itx-cheetah');
        }

        return $recommendations;
    }

    /**
     * Get INP issues
     */
    private function get_inp_issues($vitals) {
        $issues = array();

        if ($vitals['long_tasks_count'] > 5) {
            $issues[] = array(
                'type' => 'long_tasks',
                'severity' => 'high',
                'message' => sprintf(__('%d long tasks detected (>50ms each)', 'itx-cheetah'), $vitals['long_tasks_count']),
            );
        }

        if ($vitals['main_thread_blocking_time'] > 1000) {
            $issues[] = array(
                'type' => 'main_thread_blocking',
                'severity' => 'high',
                'message' => sprintf(__('Main thread blocked for %.0fms', 'itx-cheetah'), $vitals['main_thread_blocking_time']),
            );
        }

        if ($vitals['input_delay_max'] > 100) {
            $issues[] = array(
                'type' => 'input_delay',
                'severity' => 'medium',
                'message' => sprintf(__('Maximum input delay: %.0fms', 'itx-cheetah'), $vitals['input_delay_max']),
            );
        }

        return $issues;
    }

    /**
     * Get INP recommendations
     */
    private function get_inp_recommendations($vitals) {
        $recommendations = array();

        if ($vitals['long_tasks_count'] > 5) {
            $recommendations[] = __('Break up long JavaScript tasks. Use setTimeout or requestIdleCallback to split work.', 'itx-cheetah');
        }

        if ($vitals['main_thread_blocking_time'] > 1000) {
            $recommendations[] = __('Move heavy computation to Web Workers. Defer non-critical JavaScript execution.', 'itx-cheetah');
        }

        if ($vitals['event_listeners_count'] > 100) {
            $recommendations[] = __('Optimize event delegation. Use event delegation instead of individual listeners.', 'itx-cheetah');
        }

        return $recommendations;
    }

    /**
     * Create vitals table
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
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
     * Drop vitals table
     */
    public function drop_table() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
    }
}
