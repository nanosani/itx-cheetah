<?php
/**
 * DOM Analyzer for ITX Cheetah
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DOM analysis class
 */
class ITX_Cheetah_Analyzer {

    /**
     * Performance thresholds
     */
    private $thresholds;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('itx_cheetah_settings', array());

        $this->thresholds = array(
            'nodes_good' => isset($settings['node_threshold_good']) ? $settings['node_threshold_good'] : 1000,
            'nodes_warning' => isset($settings['node_threshold_warning']) ? $settings['node_threshold_warning'] : 1500,
            'depth_good' => isset($settings['depth_threshold_good']) ? $settings['depth_threshold_good'] : 20,
            'depth_warning' => isset($settings['depth_threshold_warning']) ? $settings['depth_threshold_warning'] : 32,
        );
    }

    /**
     * Analyze a URL
     */
    public function analyze_url($url) {
        $start_time = microtime(true);

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid URL provided.', 'itx-cheetah'));
        }

        // Check if URL is from this site
        $site_url = get_site_url();
        if (strpos($url, $site_url) !== 0) {
            return new WP_Error('external_url', __('Only URLs from this WordPress site can be scanned.', 'itx-cheetah'));
        }

        // Fetch the page HTML
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'ITX-Cheetah-Scanner/1.0',
        ));

        if (is_wp_error($response)) {
            return new WP_Error('fetch_error', $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP error %d when fetching URL.', 'itx-cheetah'), $status_code));
        }

        $html = wp_remote_retrieve_body($response);

        if (empty($html)) {
            return new WP_Error('empty_response', __('Empty response received from URL.', 'itx-cheetah'));
        }

        // Analyze the HTML
        $analysis = $this->analyze_html($html);

        if (is_wp_error($analysis)) {
            return $analysis;
        }

        $scan_time = microtime(true) - $start_time;

        // Calculate performance score
        $performance_score = $this->calculate_performance_score(
            $analysis['total_nodes'],
            $analysis['max_depth']
        );

        // Generate recommendations
        $recommendations = $this->generate_recommendations($analysis);

        // Try to get post ID from URL
        $post_id = url_to_postid($url);

        return array(
            'post_id' => $post_id,
            'url' => $url,
            'total_nodes' => $analysis['total_nodes'],
            'max_depth' => $analysis['max_depth'],
            'element_counts' => $analysis['element_counts'],
            'node_distribution' => $analysis['node_distribution'],
            'large_nodes' => $analysis['large_nodes'],
            'recommendations' => $recommendations,
            'performance_score' => $performance_score,
            'status' => 'completed',
            'scan_time' => round($scan_time, 3),
            'html_snapshot' => $this->get_html_snapshot($html),
        );
    }

    /**
     * Get a compressed HTML snapshot for enhanced recommendations
     */
    private function get_html_snapshot($html) {
        // Store only the body content to reduce size
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $body = $matches[1];
        } else {
            $body = $html;
        }

        // Truncate if too large (max 500KB)
        if (strlen($body) > 500000) {
            $body = substr($body, 0, 500000);
        }

        return base64_encode(gzcompress($body, 9));
    }

    /**
     * Decompress HTML snapshot
     */
    public function decompress_html_snapshot($snapshot) {
        if (empty($snapshot)) {
            return '';
        }

        $decoded = base64_decode($snapshot);
        if ($decoded === false) {
            return '';
        }

        $decompressed = @gzuncompress($decoded);
        return $decompressed !== false ? $decompressed : '';
    }

    /**
     * Get enhanced recommendations using the recommendations class
     */
    public function get_enhanced_recommendations($scan_data) {
        require_once ITX_CHEETAH_PLUGIN_DIR . 'includes/class-recommendations.php';

        $database = new ITX_Cheetah_Database();
        $recommendations_engine = new ITX_Cheetah_Recommendations($database);

        // Decompress HTML if available
        $html = '';
        if (isset($scan_data['html_snapshot'])) {
            $html = $this->decompress_html_snapshot($scan_data['html_snapshot']);
        }

        return $recommendations_engine->generate_recommendations(array(
            'html' => $html,
            'analysis' => array(
                'total_nodes' => isset($scan_data['total_nodes']) ? $scan_data['total_nodes'] : 0,
                'max_depth' => isset($scan_data['max_depth']) ? $scan_data['max_depth'] : 0,
                'element_counts' => isset($scan_data['element_counts']) ? $scan_data['element_counts'] : array(),
                'large_nodes' => isset($scan_data['large_nodes']) ? $scan_data['large_nodes'] : array(),
            )
        ));
    }

    /**
     * Analyze HTML content
     */
    public function analyze_html($html) {
        // Suppress HTML parsing errors
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();

        $total_nodes = 0;
        $max_depth = 0;
        $element_counts = array();
        $node_distribution = array();
        $large_nodes = array();

        // Analyze the DOM tree
        $this->traverse_dom(
            $dom->documentElement,
            1,
            $total_nodes,
            $max_depth,
            $element_counts,
            $node_distribution,
            $large_nodes
        );

        // Sort element counts by value (descending)
        arsort($element_counts);

        // Sort node distribution by depth
        ksort($node_distribution);

        // Sort large nodes by child count (descending)
        usort($large_nodes, function($a, $b) {
            return $b['children'] - $a['children'];
        });

        // Limit large nodes to top 20
        $large_nodes = array_slice($large_nodes, 0, 20);

        return array(
            'total_nodes' => $total_nodes,
            'max_depth' => $max_depth,
            'element_counts' => $element_counts,
            'node_distribution' => $node_distribution,
            'large_nodes' => $large_nodes,
        );
    }

    /**
     * Traverse DOM tree recursively
     */
    private function traverse_dom(
        $node,
        $depth,
        &$total_nodes,
        &$max_depth,
        &$element_counts,
        &$node_distribution,
        &$large_nodes
    ) {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        $total_nodes++;

        // Track max depth
        if ($depth > $max_depth) {
            $max_depth = $depth;
        }

        // Count element types
        $tag_name = strtolower($node->nodeName);
        if (!isset($element_counts[$tag_name])) {
            $element_counts[$tag_name] = 0;
        }
        $element_counts[$tag_name]++;

        // Track distribution by depth ranges
        $depth_range = $this->get_depth_range($depth);
        if (!isset($node_distribution[$depth_range])) {
            $node_distribution[$depth_range] = 0;
        }
        $node_distribution[$depth_range]++;

        // Check for large nodes (elements with many children)
        $child_count = 0;
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $child_count++;
            }
        }

        if ($child_count > 50) {
            $large_nodes[] = array(
                'tag' => $tag_name,
                'id' => $node->getAttribute('id'),
                'class' => $node->getAttribute('class'),
                'children' => $child_count,
                'depth' => $depth,
            );
        }

        // Recursively traverse children
        foreach ($node->childNodes as $child) {
            $this->traverse_dom(
                $child,
                $depth + 1,
                $total_nodes,
                $max_depth,
                $element_counts,
                $node_distribution,
                $large_nodes
            );
        }
    }

    /**
     * Get depth range label
     */
    private function get_depth_range($depth) {
        if ($depth <= 5) {
            return '1-5';
        } elseif ($depth <= 10) {
            return '6-10';
        } elseif ($depth <= 15) {
            return '11-15';
        } elseif ($depth <= 20) {
            return '16-20';
        } elseif ($depth <= 25) {
            return '21-25';
        } elseif ($depth <= 30) {
            return '26-30';
        } else {
            return '31+';
        }
    }

    /**
     * Calculate performance score (0-100)
     */
    public function calculate_performance_score($total_nodes, $max_depth) {
        // Node score (0-50 points)
        $node_score = 50;
        if ($total_nodes > $this->thresholds['nodes_warning']) {
            // Critical: exponential penalty
            $excess = $total_nodes - $this->thresholds['nodes_warning'];
            $node_score = max(0, 50 - ($excess / 50));
        } elseif ($total_nodes > $this->thresholds['nodes_good']) {
            // Warning: linear penalty
            $range = $this->thresholds['nodes_warning'] - $this->thresholds['nodes_good'];
            $excess = $total_nodes - $this->thresholds['nodes_good'];
            $node_score = 50 - (($excess / $range) * 25);
        }

        // Depth score (0-50 points)
        $depth_score = 50;
        if ($max_depth > $this->thresholds['depth_warning']) {
            // Critical: exponential penalty
            $excess = $max_depth - $this->thresholds['depth_warning'];
            $depth_score = max(0, 50 - ($excess * 5));
        } elseif ($max_depth > $this->thresholds['depth_good']) {
            // Warning: linear penalty
            $range = $this->thresholds['depth_warning'] - $this->thresholds['depth_good'];
            $excess = $max_depth - $this->thresholds['depth_good'];
            $depth_score = 50 - (($excess / $range) * 25);
        }

        return round($node_score + $depth_score);
    }

    /**
     * Get status label based on score
     */
    public function get_status_label($score) {
        if ($score >= 80) {
            return 'good';
        } elseif ($score >= 50) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Get status color
     */
    public function get_status_color($score) {
        if ($score >= 80) {
            return '#22c55e'; // Green
        } elseif ($score >= 50) {
            return '#f59e0b'; // Yellow/Orange
        } else {
            return '#ef4444'; // Red
        }
    }

    /**
     * Generate recommendations based on analysis
     */
    public function generate_recommendations($analysis) {
        $recommendations = array();

        // Node count recommendations
        if ($analysis['total_nodes'] > $this->thresholds['nodes_warning']) {
            $recommendations[] = array(
                'severity' => 'critical',
                'title' => __('Excessive DOM Size', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page has %d DOM nodes, which exceeds the recommended maximum of %d. This can significantly impact performance.', 'itx-cheetah'),
                    $analysis['total_nodes'],
                    $this->thresholds['nodes_warning']
                ),
                'suggestions' => array(
                    __('Consider lazy loading content below the fold', 'itx-cheetah'),
                    __('Remove unnecessary wrapper elements', 'itx-cheetah'),
                    __('Use virtual scrolling for long lists', 'itx-cheetah'),
                    __('Review plugins that may add excessive markup', 'itx-cheetah'),
                ),
            );
        } elseif ($analysis['total_nodes'] > $this->thresholds['nodes_good']) {
            $recommendations[] = array(
                'severity' => 'warning',
                'title' => __('High DOM Node Count', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page has %d DOM nodes. Consider reducing this below %d for optimal performance.', 'itx-cheetah'),
                    $analysis['total_nodes'],
                    $this->thresholds['nodes_good']
                ),
                'suggestions' => array(
                    __('Audit your page structure for unnecessary elements', 'itx-cheetah'),
                    __('Consider component-based loading for complex sections', 'itx-cheetah'),
                ),
            );
        }

        // Depth recommendations
        if ($analysis['max_depth'] > $this->thresholds['depth_warning']) {
            $recommendations[] = array(
                'severity' => 'critical',
                'title' => __('Excessive DOM Depth', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your DOM tree has a maximum depth of %d levels, exceeding the recommended %d levels.', 'itx-cheetah'),
                    $analysis['max_depth'],
                    $this->thresholds['depth_warning']
                ),
                'suggestions' => array(
                    __('Flatten your HTML structure where possible', 'itx-cheetah'),
                    __('Review page builder output for excessive nesting', 'itx-cheetah'),
                    __('Use CSS Grid or Flexbox instead of nested containers', 'itx-cheetah'),
                ),
            );
        } elseif ($analysis['max_depth'] > $this->thresholds['depth_good']) {
            $recommendations[] = array(
                'severity' => 'warning',
                'title' => __('Deep DOM Nesting', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your DOM tree has a depth of %d levels. Consider reducing this below %d.', 'itx-cheetah'),
                    $analysis['max_depth'],
                    $this->thresholds['depth_good']
                ),
                'suggestions' => array(
                    __('Review nested container elements', 'itx-cheetah'),
                    __('Simplify component hierarchies', 'itx-cheetah'),
                ),
            );
        }

        // Large nodes recommendations
        if (!empty($analysis['large_nodes'])) {
            $largest = $analysis['large_nodes'][0];
            $recommendations[] = array(
                'severity' => 'warning',
                'title' => __('Elements with Many Children', 'itx-cheetah'),
                'description' => sprintf(
                    __('Found %d elements with more than 50 direct children. The largest has %d children.', 'itx-cheetah'),
                    count($analysis['large_nodes']),
                    $largest['children']
                ),
                'suggestions' => array(
                    __('Consider pagination for long lists', 'itx-cheetah'),
                    __('Implement infinite scroll or load-more patterns', 'itx-cheetah'),
                    __('Use content virtualization for large data sets', 'itx-cheetah'),
                ),
            );
        }

        // Element-specific recommendations
        if (isset($analysis['element_counts']['div']) && $analysis['element_counts']['div'] > 100) {
            $recommendations[] = array(
                'severity' => 'info',
                'title' => __('High Div Count', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page contains %d div elements. This might indicate wrapper div bloat.', 'itx-cheetah'),
                    $analysis['element_counts']['div']
                ),
                'suggestions' => array(
                    __('Use semantic HTML elements where appropriate', 'itx-cheetah'),
                    __('Remove unnecessary wrapper divs', 'itx-cheetah'),
                    __('Review CSS to reduce the need for structural markup', 'itx-cheetah'),
                ),
            );
        }

        // Check for excessive spans
        if (isset($analysis['element_counts']['span']) && $analysis['element_counts']['span'] > 50) {
            $recommendations[] = array(
                'severity' => 'info',
                'title' => __('High Span Count', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page contains %d span elements. Consider if all are necessary.', 'itx-cheetah'),
                    $analysis['element_counts']['span']
                ),
                'suggestions' => array(
                    __('Review inline styling elements', 'itx-cheetah'),
                    __('Consider using CSS classes instead of wrapper spans', 'itx-cheetah'),
                ),
            );
        }

        // Check for excessive images
        if (isset($analysis['element_counts']['img']) && $analysis['element_counts']['img'] > 30) {
            $recommendations[] = array(
                'severity' => 'info',
                'title' => __('Many Images Detected', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page has %d images. This can impact initial load time and DOM complexity.', 'itx-cheetah'),
                    $analysis['element_counts']['img']
                ),
                'suggestions' => array(
                    __('Implement lazy loading for images below the fold', 'itx-cheetah'),
                    __('Consider using CSS sprites for icons', 'itx-cheetah'),
                    __('Use responsive images with srcset', 'itx-cheetah'),
                ),
            );
        }

        // Check for excessive forms/inputs
        $form_elements = 0;
        foreach (array('input', 'select', 'textarea', 'button') as $el) {
            if (isset($analysis['element_counts'][$el])) {
                $form_elements += $analysis['element_counts'][$el];
            }
        }
        if ($form_elements > 50) {
            $recommendations[] = array(
                'severity' => 'info',
                'title' => __('Complex Forms Detected', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page has %d form elements. Complex forms can slow down rendering.', 'itx-cheetah'),
                    $form_elements
                ),
                'suggestions' => array(
                    __('Consider splitting long forms into multiple steps', 'itx-cheetah'),
                    __('Lazy load form sections that are not immediately visible', 'itx-cheetah'),
                    __('Remove hidden or unused form fields', 'itx-cheetah'),
                ),
            );
        }

        // Check for excessive SVG elements
        if (isset($analysis['element_counts']['svg']) && $analysis['element_counts']['svg'] > 20) {
            $recommendations[] = array(
                'severity' => 'info',
                'title' => __('Many SVG Elements', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page has %d SVG elements. Inline SVGs add to DOM complexity.', 'itx-cheetah'),
                    $analysis['element_counts']['svg']
                ),
                'suggestions' => array(
                    __('Consider using an SVG sprite sheet', 'itx-cheetah'),
                    __('Use icon fonts for simple icons', 'itx-cheetah'),
                    __('Reference external SVG files where possible', 'itx-cheetah'),
                ),
            );
        }

        // Check for table-based layouts
        if (isset($analysis['element_counts']['table']) && $analysis['element_counts']['table'] > 5) {
            $recommendations[] = array(
                'severity' => 'info',
                'title' => __('Multiple Tables Detected', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page has %d table elements. Ensure tables are used for tabular data only.', 'itx-cheetah'),
                    $analysis['element_counts']['table']
                ),
                'suggestions' => array(
                    __('Use CSS Grid or Flexbox for layouts instead of tables', 'itx-cheetah'),
                    __('Consider responsive table alternatives for mobile', 'itx-cheetah'),
                ),
            );
        }

        // Check for iframe usage
        if (isset($analysis['element_counts']['iframe']) && $analysis['element_counts']['iframe'] > 0) {
            $recommendations[] = array(
                'severity' => 'info',
                'title' => __('Iframe Elements Detected', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page has %d iframe(s). Iframes can significantly impact performance.', 'itx-cheetah'),
                    $analysis['element_counts']['iframe']
                ),
                'suggestions' => array(
                    __('Lazy load iframes that are below the fold', 'itx-cheetah'),
                    __('Consider using facade patterns for video embeds', 'itx-cheetah'),
                    __('Remove unnecessary third-party embeds', 'itx-cheetah'),
                ),
            );
        }

        // Check for script tags (potential bloat indicator)
        if (isset($analysis['element_counts']['script']) && $analysis['element_counts']['script'] > 15) {
            $recommendations[] = array(
                'severity' => 'warning',
                'title' => __('Many Script Tags', 'itx-cheetah'),
                'description' => sprintf(
                    __('Your page has %d script tags. Too many scripts can delay page interactivity.', 'itx-cheetah'),
                    $analysis['element_counts']['script']
                ),
                'suggestions' => array(
                    __('Combine and minify JavaScript files', 'itx-cheetah'),
                    __('Defer non-critical scripts', 'itx-cheetah'),
                    __('Review and remove unused JavaScript', 'itx-cheetah'),
                    __('Consider using a JavaScript bundler', 'itx-cheetah'),
                ),
            );
        }

        // Check for deep nesting in specific ranges
        if (isset($analysis['node_distribution']['31+']) && $analysis['node_distribution']['31+'] > 10) {
            $recommendations[] = array(
                'severity' => 'warning',
                'title' => __('Deeply Nested Elements', 'itx-cheetah'),
                'description' => sprintf(
                    __('You have %d elements nested more than 31 levels deep. This severely impacts rendering performance.', 'itx-cheetah'),
                    $analysis['node_distribution']['31+']
                ),
                'suggestions' => array(
                    __('Review page builder or theme structure for excessive nesting', 'itx-cheetah'),
                    __('Flatten nested sections using CSS Grid', 'itx-cheetah'),
                    __('Consider custom CSS solutions instead of nested containers', 'itx-cheetah'),
                ),
            );
        }

        // Good score message
        if (empty($recommendations)) {
            $recommendations[] = array(
                'severity' => 'success',
                'title' => __('Good DOM Structure', 'itx-cheetah'),
                'description' => __('Your page has a healthy DOM structure that should perform well.', 'itx-cheetah'),
                'suggestions' => array(
                    __('Continue monitoring as you add content', 'itx-cheetah'),
                    __('Consider setting up scheduled scans', 'itx-cheetah'),
                ),
            );
        }

        return $recommendations;
    }

    /**
     * Get all scannable URLs from WordPress
     */
    public function get_scannable_urls($args = array()) {
        $defaults = array(
            'post_types' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );

        $args = wp_parse_args($args, $defaults);

        $urls = array();

        // Get posts/pages
        $query_args = array(
            'post_type' => $args['post_types'],
            'post_status' => $args['post_status'],
            'posts_per_page' => $args['posts_per_page'],
            'fields' => 'ids',
        );

        $posts = get_posts($query_args);

        foreach ($posts as $post_id) {
            $urls[] = array(
                'id' => $post_id,
                'url' => get_permalink($post_id),
                'title' => get_the_title($post_id),
                'type' => get_post_type($post_id),
            );
        }

        // Add homepage
        $urls[] = array(
            'id' => 0,
            'url' => home_url('/'),
            'title' => __('Homepage', 'itx-cheetah'),
            'type' => 'home',
        );

        return $urls;
    }
}
