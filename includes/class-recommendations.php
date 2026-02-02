<?php
/**
 * Enhanced Recommendations Class
 *
 * Provides context-aware, actionable code recommendations
 * with theme/plugin-specific guidance.
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

class ITX_Cheetah_Recommendations {

    /**
     * Database instance
     */
    private $database;

    /**
     * Known theme patterns
     */
    private $theme_patterns = array();

    /**
     * Known plugin patterns
     */
    private $plugin_patterns = array();

    /**
     * Constructor
     */
    public function __construct($database) {
        $this->database = $database;
        $this->init_patterns();
    }

    /**
     * Initialize known patterns for themes and plugins
     */
    private function init_patterns() {
        // Theme patterns that cause DOM bloat
        $this->theme_patterns = array(
            'astra' => array(
                'name' => 'Astra',
                'detector' => 'ast-',
                'issues' => array(
                    'wrapper_divs' => array(
                        'pattern' => '<div class="ast-container">',
                        'description' => 'Astra uses multiple container wrappers',
                        'fix' => 'Go to Customize > Layout > Container to simplify structure'
                    )
                )
            ),
            'divi' => array(
                'name' => 'Divi',
                'detector' => 'et_pb_',
                'issues' => array(
                    'module_wrappers' => array(
                        'pattern' => '<div class="et_pb_module',
                        'description' => 'Divi modules create nested wrapper structures',
                        'fix' => 'Use Divi\'s "Reduce DOM Size" option in Theme Options'
                    )
                )
            ),
            'elementor' => array(
                'name' => 'Elementor',
                'detector' => 'elementor-',
                'issues' => array(
                    'section_wrappers' => array(
                        'pattern' => '<div class="elementor-section',
                        'description' => 'Elementor sections create 5+ nested divs',
                        'fix' => 'Enable Elementor\'s "Optimized DOM Output" in Settings > Experiments'
                    ),
                    'column_wrappers' => array(
                        'pattern' => '<div class="elementor-column-wrap',
                        'description' => 'Column wrappers add unnecessary depth',
                        'fix' => 'Update to Elementor 3.0+ which reduces column wrappers'
                    )
                )
            ),
            'wpbakery' => array(
                'name' => 'WPBakery',
                'detector' => 'vc_',
                'issues' => array(
                    'row_wrappers' => array(
                        'pattern' => '<div class="vc_row',
                        'description' => 'WPBakery rows use excessive wrapper divs',
                        'fix' => 'Consider migrating to a lighter page builder'
                    )
                )
            ),
            'generatepress' => array(
                'name' => 'GeneratePress',
                'detector' => 'generate-',
                'issues' => array()
            ),
            'flavflavor' => array(
                'name' => 'flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor',
                'detector' => 'flavor-',
                'issues' => array()
            )
        );

        // Plugin patterns that cause DOM bloat
        $this->plugin_patterns = array(
            'woocommerce' => array(
                'name' => 'WooCommerce',
                'detector' => 'woocommerce',
                'issues' => array(
                    'product_loops' => array(
                        'pattern' => '<ul class="products',
                        'description' => 'Product grids create deep DOM structures',
                        'fix' => 'Limit products per page and use AJAX pagination'
                    )
                )
            ),
            'yoast' => array(
                'name' => 'Yoast SEO',
                'detector' => 'yoast',
                'issues' => array(
                    'schema_output' => array(
                        'pattern' => 'yoast-schema',
                        'description' => 'Schema markup adds hidden elements',
                        'fix' => 'This is necessary for SEO - keep it'
                    )
                )
            ),
            'wpforms' => array(
                'name' => 'WPForms',
                'detector' => 'wpforms',
                'issues' => array(
                    'form_wrappers' => array(
                        'pattern' => '<div class="wpforms-container',
                        'description' => 'Form containers use multiple wrapper divs',
                        'fix' => 'Use minimal form styling in WPForms settings'
                    )
                )
            ),
            'contact-form-7' => array(
                'name' => 'Contact Form 7',
                'detector' => 'wpcf7',
                'issues' => array(
                    'form_structure' => array(
                        'pattern' => '<div class="wpcf7',
                        'description' => 'CF7 adds wrapper divs for each form',
                        'fix' => 'Use a lighter form plugin or custom HTML forms'
                    )
                )
            ),
            'slider-revolution' => array(
                'name' => 'Slider Revolution',
                'detector' => 'rev_slider',
                'issues' => array(
                    'slider_layers' => array(
                        'pattern' => 'rs-layer',
                        'description' => 'Each slider layer adds multiple DOM nodes',
                        'fix' => 'Reduce slider complexity or use CSS-only alternatives'
                    )
                )
            )
        );
    }

    /**
     * Generate actionable recommendations from scan data
     */
    public function generate_recommendations($scan_data) {
        $recommendations = array();
        $html = isset($scan_data['html']) ? $scan_data['html'] : '';
        $analysis = isset($scan_data['analysis']) ? $scan_data['analysis'] : array();

        // Detect active theme/plugins
        $detected_theme = $this->detect_theme($html);
        $detected_plugins = $this->detect_plugins($html);

        // Generate priority-based recommendations
        $recommendations['high_priority'] = $this->get_high_priority_fixes($analysis, $html, $detected_theme, $detected_plugins);
        $recommendations['medium_priority'] = $this->get_medium_priority_fixes($analysis, $html);
        $recommendations['low_priority'] = $this->get_low_priority_fixes($analysis, $html);

        // Add theme-specific recommendations
        if ($detected_theme) {
            $recommendations['theme_specific'] = $this->get_theme_recommendations($detected_theme, $html);
        }

        // Add plugin-specific recommendations
        if (!empty($detected_plugins)) {
            $recommendations['plugin_specific'] = $this->get_plugin_recommendations($detected_plugins, $html);
        }

        // Calculate estimated impact
        $recommendations['impact_summary'] = $this->calculate_impact_summary($recommendations);

        return $recommendations;
    }

    /**
     * Detect active theme from HTML
     */
    private function detect_theme($html) {
        foreach ($this->theme_patterns as $key => $theme) {
            if (strpos($html, $theme['detector']) !== false) {
                return array_merge(array('key' => $key), $theme);
            }
        }

        // Try to detect from body class
        if (preg_match('/class="[^"]*theme-([a-zA-Z0-9_-]+)/', $html, $matches)) {
            return array(
                'key' => $matches[1],
                'name' => ucfirst($matches[1]),
                'detector' => $matches[1],
                'issues' => array()
            );
        }

        return null;
    }

    /**
     * Detect active plugins from HTML
     */
    private function detect_plugins($html) {
        $detected = array();

        foreach ($this->plugin_patterns as $key => $plugin) {
            if (strpos($html, $plugin['detector']) !== false) {
                $detected[$key] = $plugin;
            }
        }

        return $detected;
    }

    /**
     * Get high priority fixes (35%+ DOM reduction potential)
     */
    private function get_high_priority_fixes($analysis, $html, $theme, $plugins) {
        $fixes = array();

        $total_nodes = isset($analysis['total_nodes']) ? $analysis['total_nodes'] : 1;
        $element_counts = isset($analysis['element_counts']) ? $analysis['element_counts'] : array();

        // Get div count from HTML or from element_counts
        $div_count = !empty($html) ? $this->count_elements($html, 'div') : 0;
        if ($div_count === 0 && isset($element_counts['div'])) {
            $div_count = $element_counts['div'];
        }

        if ($div_count > 0 && ($div_count / $total_nodes) > 0.35) {
            $fixes[] = array(
                'id' => 'excessive_divs',
                'severity' => 'critical',
                'title' => 'Excessive Div Elements',
                'description' => sprintf(
                    'Found %s div elements (%s%% of all elements). This significantly impacts rendering performance.',
                    number_format($div_count),
                    round(($div_count / $total_nodes) * 100, 1)
                ),
                'estimated_reduction' => round($div_count * 0.3),
                'time_to_fix' => '30-45 minutes',
                'code_example' => $this->get_div_reduction_example($html),
                'steps' => array(
                    'Identify wrapper divs that can be removed',
                    'Replace nested divs with semantic HTML elements',
                    'Use CSS Grid/Flexbox instead of wrapper divs for layout',
                    'Remove Bootstrap/grid wrapper divs where possible'
                )
            );
        }

        // Check for deep nesting
        $max_depth = isset($analysis['max_depth']) ? $analysis['max_depth'] : 0;
        if ($max_depth > 15) {
            $fixes[] = array(
                'id' => 'deep_nesting',
                'severity' => 'critical',
                'title' => 'Excessive DOM Depth',
                'description' => sprintf(
                    'DOM tree has %d levels deep (recommended: ≤15). Deep nesting severely impacts browser rendering.',
                    $max_depth
                ),
                'estimated_reduction' => round(($max_depth - 15) * 10),
                'time_to_fix' => '45-60 minutes',
                'code_example' => $this->get_flattening_example(),
                'steps' => array(
                    'Identify the deepest nested elements using browser DevTools',
                    'Flatten the DOM structure by removing unnecessary containers',
                    'Consider component restructuring for deeply nested areas',
                    'Use CSS positioning instead of nested wrapper elements'
                )
            );
        }

        // Check for page builder bloat
        if ($theme && isset($theme['key'])) {
            $builder_nodes = $this->count_builder_nodes($html, $theme);
            if ($builder_nodes > 200) {
                $fixes[] = array(
                    'id' => 'builder_bloat',
                    'severity' => 'critical',
                    'title' => sprintf('%s DOM Bloat', $theme['name']),
                    'description' => sprintf(
                        '%s is adding approximately %d wrapper elements to your page.',
                        $theme['name'],
                        $builder_nodes
                    ),
                    'estimated_reduction' => round($builder_nodes * 0.4),
                    'time_to_fix' => '20-30 minutes',
                    'code_example' => $this->get_builder_optimization_example($theme),
                    'steps' => $this->get_builder_optimization_steps($theme)
                );
            }
        }

        return $fixes;
    }

    /**
     * Get medium priority fixes (15-35% DOM reduction potential)
     */
    private function get_medium_priority_fixes($analysis, $html) {
        $fixes = array();
        $element_counts = isset($analysis['element_counts']) ? $analysis['element_counts'] : array();

        // Check for excessive span elements
        $span_count = !empty($html) ? $this->count_elements($html, 'span') : 0;
        if ($span_count === 0 && isset($element_counts['span'])) {
            $span_count = $element_counts['span'];
        }
        if ($span_count > 100) {
            $fixes[] = array(
                'id' => 'excessive_spans',
                'severity' => 'warning',
                'title' => 'Excessive Span Elements',
                'description' => sprintf(
                    'Found %d span elements. Many can be replaced with CSS styling.',
                    $span_count
                ),
                'estimated_reduction' => round($span_count * 0.5),
                'time_to_fix' => '20-30 minutes',
                'code_example' => $this->get_span_reduction_example(),
                'steps' => array(
                    'Replace decorative spans with CSS pseudo-elements',
                    'Remove spans used only for styling - apply styles to parent',
                    'Combine adjacent inline elements where possible'
                )
            );
        }

        // Check for SVG optimization
        $svg_count = !empty($html) ? $this->count_elements($html, 'svg') : 0;
        if ($svg_count === 0 && isset($element_counts['svg'])) {
            $svg_count = $element_counts['svg'];
        }
        if ($svg_count > 10) {
            $svg_nodes = !empty($html) ? $this->estimate_svg_nodes($html) : $svg_count * 5;
            $fixes[] = array(
                'id' => 'svg_optimization',
                'severity' => 'warning',
                'title' => 'Unoptimized SVG Usage',
                'description' => sprintf(
                    'Found %d inline SVGs adding approximately %d DOM nodes.',
                    $svg_count,
                    $svg_nodes
                ),
                'estimated_reduction' => round($svg_nodes * 0.6),
                'time_to_fix' => '15-20 minutes',
                'code_example' => $this->get_svg_optimization_example(),
                'steps' => array(
                    'Use SVG sprite sheets instead of inline SVGs',
                    'Optimize SVGs with SVGO to remove unnecessary elements',
                    'Consider using icon fonts for simple icons',
                    'Use <img> tags for decorative SVGs'
                )
            );
        }

        // Check for form optimization
        $form_count = !empty($html) ? $this->count_elements($html, 'form') : 0;
        if ($form_count === 0 && isset($element_counts['form'])) {
            $form_count = $element_counts['form'];
        }
        if ($form_count > 0) {
            $form_nodes = !empty($html) ? $this->estimate_form_nodes($html) : $form_count * 20;
            if ($form_nodes > 50) {
                $fixes[] = array(
                    'id' => 'form_optimization',
                    'severity' => 'warning',
                    'title' => 'Form Structure Optimization',
                    'description' => sprintf(
                        'Found %d form(s) with approximately %d DOM nodes. Forms often have excessive wrapper elements.',
                        $form_count,
                        $form_nodes
                    ),
                    'estimated_reduction' => round($form_nodes * 0.3),
                    'time_to_fix' => '15-20 minutes',
                    'code_example' => $this->get_form_optimization_example(),
                    'steps' => array(
                        'Remove unnecessary wrapper divs around form fields',
                        'Use CSS Grid for form layout instead of wrapper elements',
                        'Consider lighter form plugins or native HTML forms'
                    )
                );
            }
        }

        return $fixes;
    }

    /**
     * Get low priority fixes (<15% DOM reduction potential)
     */
    private function get_low_priority_fixes($analysis, $html) {
        $fixes = array();
        $element_counts = isset($analysis['element_counts']) ? $analysis['element_counts'] : array();

        // Check for excessive list items
        $li_count = !empty($html) ? $this->count_elements($html, 'li') : 0;
        if ($li_count === 0 && isset($element_counts['li'])) {
            $li_count = $element_counts['li'];
        }
        if ($li_count > 50) {
            $fixes[] = array(
                'id' => 'list_optimization',
                'severity' => 'info',
                'title' => 'List Element Optimization',
                'description' => sprintf(
                    'Found %d list items. Consider pagination or lazy loading for long lists.',
                    $li_count
                ),
                'estimated_reduction' => round($li_count * 0.3),
                'time_to_fix' => '10-15 minutes',
                'code_example' => null,
                'steps' => array(
                    'Implement pagination for long lists',
                    'Use virtual scrolling for very long lists',
                    'Lazy load list items as user scrolls'
                )
            );
        }

        // Check for table optimization
        $table_count = !empty($html) ? $this->count_elements($html, 'table') : 0;
        if ($table_count === 0 && isset($element_counts['table'])) {
            $table_count = $element_counts['table'];
        }
        if ($table_count > 0) {
            $td_count = !empty($html) ? $this->count_elements($html, 'td') : (isset($element_counts['td']) ? $element_counts['td'] : 0);
            $th_count = !empty($html) ? $this->count_elements($html, 'th') : (isset($element_counts['th']) ? $element_counts['th'] : 0);
            $table_cells = $td_count + $th_count;
            if ($table_cells > 100) {
                $fixes[] = array(
                    'id' => 'table_optimization',
                    'severity' => 'info',
                    'title' => 'Table Structure Optimization',
                    'description' => sprintf(
                        'Found %d table cells. Large tables significantly increase DOM size.',
                        $table_cells
                    ),
                    'estimated_reduction' => round($table_cells * 0.2),
                    'time_to_fix' => '15-20 minutes',
                    'code_example' => null,
                    'steps' => array(
                        'Implement table pagination',
                        'Use virtual scrolling for large data tables',
                        'Consider using CSS Grid instead of tables for layouts'
                    )
                );
            }
        }

        // Check for iframe usage
        $iframe_count = !empty($html) ? $this->count_elements($html, 'iframe') : 0;
        if ($iframe_count === 0 && isset($element_counts['iframe'])) {
            $iframe_count = $element_counts['iframe'];
        }
        if ($iframe_count > 2) {
            $fixes[] = array(
                'id' => 'iframe_optimization',
                'severity' => 'info',
                'title' => 'Iframe Usage',
                'description' => sprintf(
                    'Found %d iframes. Each iframe loads additional DOM content.',
                    $iframe_count
                ),
                'estimated_reduction' => 0,
                'time_to_fix' => '10-15 minutes',
                'code_example' => null,
                'steps' => array(
                    'Lazy load iframes using loading="lazy" attribute',
                    'Use facade patterns for embeds (YouTube, maps)',
                    'Load iframes only on user interaction'
                )
            );
        }

        return $fixes;
    }

    /**
     * Get theme-specific recommendations
     */
    private function get_theme_recommendations($theme, $html) {
        $recommendations = array();

        if (!isset($theme['issues']) || empty($theme['issues'])) {
            return $recommendations;
        }

        foreach ($theme['issues'] as $issue_key => $issue) {
            if (strpos($html, $issue['pattern']) !== false) {
                $recommendations[] = array(
                    'id' => $theme['key'] . '_' . $issue_key,
                    'theme' => $theme['name'],
                    'title' => ucwords(str_replace('_', ' ', $issue_key)),
                    'description' => $issue['description'],
                    'fix' => $issue['fix'],
                    'documentation_url' => $this->get_theme_docs_url($theme['key'])
                );
            }
        }

        return $recommendations;
    }

    /**
     * Get plugin-specific recommendations
     */
    private function get_plugin_recommendations($plugins, $html) {
        $recommendations = array();

        foreach ($plugins as $plugin_key => $plugin) {
            if (!isset($plugin['issues']) || empty($plugin['issues'])) {
                continue;
            }

            foreach ($plugin['issues'] as $issue_key => $issue) {
                if (strpos($html, $issue['pattern']) !== false) {
                    $recommendations[] = array(
                        'id' => $plugin_key . '_' . $issue_key,
                        'plugin' => $plugin['name'],
                        'title' => ucwords(str_replace('_', ' ', $issue_key)),
                        'description' => $issue['description'],
                        'fix' => $issue['fix']
                    );
                }
            }
        }

        return $recommendations;
    }

    /**
     * Calculate impact summary
     */
    private function calculate_impact_summary($recommendations) {
        $total_reduction = 0;
        $total_time = 0;

        foreach (array('high_priority', 'medium_priority', 'low_priority') as $priority) {
            if (!isset($recommendations[$priority])) {
                continue;
            }

            foreach ($recommendations[$priority] as $fix) {
                $total_reduction += isset($fix['estimated_reduction']) ? $fix['estimated_reduction'] : 0;

                // Parse time estimate (e.g., "30-45 minutes")
                if (isset($fix['time_to_fix'])) {
                    preg_match('/(\d+)/', $fix['time_to_fix'], $matches);
                    if (isset($matches[1])) {
                        $total_time += intval($matches[1]);
                    }
                }
            }
        }

        return array(
            'estimated_node_reduction' => $total_reduction,
            'estimated_time_minutes' => $total_time,
            'estimated_performance_improvement' => $this->estimate_performance_improvement($total_reduction)
        );
    }

    /**
     * Estimate performance improvement based on node reduction
     */
    private function estimate_performance_improvement($node_reduction) {
        // Rough estimates based on typical improvements
        $lcp_improvement = min(30, round($node_reduction / 50));
        $fid_improvement = min(25, round($node_reduction / 60));
        $cls_improvement = min(20, round($node_reduction / 80));

        return array(
            'lcp' => $lcp_improvement . '%',
            'fid' => $fid_improvement . '%',
            'cls' => $cls_improvement . '%'
        );
    }

    /**
     * Count specific elements in HTML
     */
    private function count_elements($html, $tag) {
        return substr_count(strtolower($html), '<' . $tag);
    }

    /**
     * Count builder-specific nodes
     */
    private function count_builder_nodes($html, $theme) {
        $count = 0;
        $detector = $theme['detector'];

        // Count elements with builder-specific classes
        preg_match_all('/class="[^"]*' . preg_quote($detector, '/') . '[^"]*"/', $html, $matches);
        $count = count($matches[0]);

        return $count;
    }

    /**
     * Estimate SVG node count
     */
    private function estimate_svg_nodes($html) {
        $svg_count = $this->count_elements($html, 'svg');
        $path_count = $this->count_elements($html, 'path');
        $g_count = $this->count_elements($html, '<g');

        return $svg_count + $path_count + $g_count;
    }

    /**
     * Estimate form node count
     */
    private function estimate_form_nodes($html) {
        $form_count = $this->count_elements($html, 'form');
        $input_count = $this->count_elements($html, 'input');
        $label_count = $this->count_elements($html, 'label');
        $wrapper_estimate = $form_count * 10; // Assume 10 wrapper divs per form

        return $input_count + $label_count + $wrapper_estimate;
    }

    /**
     * Get div reduction code example
     */
    private function get_div_reduction_example($html) {
        return array(
            'before' => '<!-- Common pattern causing bloat -->
<div class="container">
  <div class="row">
    <div class="col-12">
      <div class="wrapper">
        <div class="inner">
          <div class="content">
            <p>Your content here</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>',
            'after' => '<!-- Optimized structure -->
<main class="container">
  <p>Your content here</p>
</main>',
            'css_changes' => '/* Use CSS Grid instead of wrapper divs */
.container {
  display: grid;
  grid-template-columns: 1fr;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}'
        );
    }

    /**
     * Get flattening code example
     */
    private function get_flattening_example() {
        return array(
            'before' => '<!-- Deeply nested structure -->
<div class="level-1">
  <div class="level-2">
    <div class="level-3">
      <div class="level-4">
        <div class="level-5">
          <article>Content</article>
        </div>
      </div>
    </div>
  </div>
</div>',
            'after' => '<!-- Flattened structure -->
<article class="content-article">
  Content
</article>',
            'css_changes' => '/* Apply all needed styles directly */
.content-article {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
  background: #fff;
}'
        );
    }

    /**
     * Get builder optimization example
     */
    private function get_builder_optimization_example($theme) {
        $examples = array(
            'elementor' => array(
                'title' => 'Elementor DOM Optimization',
                'settings_path' => 'Elementor > Settings > Experiments',
                'code' => '// Add to functions.php to reduce Elementor wrappers
add_action(\'elementor/frontend/after_enqueue_scripts\', function() {
    // Enable optimized DOM output
    if (class_exists(\'\\Elementor\\Plugin\')) {
        add_filter(\'elementor/frontend/container/should_render_wrapper\', \'__return_false\');
    }
});'
            ),
            'divi' => array(
                'title' => 'Divi DOM Optimization',
                'settings_path' => 'Divi > Theme Options > Performance',
                'code' => '// Add to functions.php for Divi optimization
add_filter(\'et_builder_inner_content_class\', function($classes) {
    return array_filter($classes, function($class) {
        return !in_array($class, [\'et_pb_row\', \'et_pb_column\']);
    });
});'
            )
        );

        return isset($examples[$theme['key']]) ? $examples[$theme['key']] : null;
    }

    /**
     * Get builder optimization steps
     */
    private function get_builder_optimization_steps($theme) {
        $steps = array(
            'elementor' => array(
                'Go to Elementor > Settings > Experiments',
                'Enable "Optimized DOM Output" experiment',
                'Enable "Inline Font Icons" to reduce DOM',
                'Use Containers instead of Sections (Elementor 3.6+)',
                'Clear Elementor cache after changes'
            ),
            'divi' => array(
                'Go to Divi > Theme Options > Performance',
                'Enable "Dynamic CSS" option',
                'Enable "Static CSS File Generation"',
                'Consider using Divi\'s "Wireframe Mode" for simpler layouts',
                'Remove unused Divi modules from pages'
            ),
            'wpbakery' => array(
                'Consider migrating to a lighter page builder',
                'Use custom CSS classes instead of nested rows/columns',
                'Minimize use of inner rows',
                'Remove unused WPBakery elements'
            )
        );

        return isset($steps[$theme['key']]) ? $steps[$theme['key']] : array(
            'Review your page builder settings for optimization options',
            'Reduce nested sections and columns',
            'Use simpler layouts where possible',
            'Consider custom code for complex layouts'
        );
    }

    /**
     * Get span reduction example
     */
    private function get_span_reduction_example() {
        return array(
            'before' => '<!-- Excessive spans -->
<nav>
  <a href="#">
    <span class="icon"></span>
    <span class="text">Home</span>
    <span class="arrow"></span>
  </a>
</nav>',
            'after' => '<!-- Use CSS pseudo-elements -->
<nav>
  <a href="#" class="nav-link">Home</a>
</nav>',
            'css_changes' => '.nav-link {
  display: flex;
  align-items: center;
}
.nav-link::before {
  content: "";
  /* icon styles */
}
.nav-link::after {
  content: "→";
}'
        );
    }

    /**
     * Get SVG optimization example
     */
    private function get_svg_optimization_example() {
        return array(
            'before' => '<!-- Inline SVG on every usage -->
<svg class="icon" viewBox="0 0 24 24">
  <path d="M12 0C5.373 0 0 5.373..."/>
</svg>
<svg class="icon" viewBox="0 0 24 24">
  <path d="M12 0C5.373 0 0 5.373..."/>
</svg>',
            'after' => '<!-- SVG Sprite (define once) -->
<svg style="display:none">
  <symbol id="icon-home" viewBox="0 0 24 24">
    <path d="M12 0C5.373 0 0 5.373..."/>
  </symbol>
</svg>

<!-- Use references -->
<svg class="icon"><use href="#icon-home"/></svg>
<svg class="icon"><use href="#icon-home"/></svg>',
            'css_changes' => '.icon {
  width: 24px;
  height: 24px;
  fill: currentColor;
}'
        );
    }

    /**
     * Get form optimization example
     */
    private function get_form_optimization_example() {
        return array(
            'before' => '<!-- Form with excessive wrappers -->
<form>
  <div class="form-group">
    <div class="form-field">
      <div class="input-wrapper">
        <label>Name</label>
        <input type="text">
      </div>
    </div>
  </div>
</form>',
            'after' => '<!-- Optimized form structure -->
<form class="contact-form">
  <label>
    Name
    <input type="text" name="name">
  </label>
</form>',
            'css_changes' => '.contact-form {
  display: grid;
  gap: 1rem;
}
.contact-form label {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}'
        );
    }

    /**
     * Get theme documentation URL
     */
    private function get_theme_docs_url($theme_key) {
        $docs = array(
            'elementor' => 'https://developers.elementor.com/docs/optimizing-performance/',
            'divi' => 'https://www.elegantthemes.com/documentation/divi/performance/',
            'astra' => 'https://developer.theme.dev/docs/optimize-astra-theme/',
            'generatepress' => 'https://developer.theme.dev/docs/generatepress-performance/'
        );

        return isset($docs[$theme_key]) ? $docs[$theme_key] : null;
    }

    /**
     * Get DOM tree structure for visual explorer
     */
    public function get_dom_tree($html, $max_depth = 5) {
        if (empty($html)) {
            return array();
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return array();
        }

        return $this->build_tree_node($body, 0, $max_depth);
    }

    /**
     * Build tree node recursively
     */
    private function build_tree_node($node, $depth, $max_depth) {
        if ($depth >= $max_depth || !$node instanceof DOMElement) {
            return null;
        }

        $child_count = 0;
        $children = array();

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $child_count++;
                $child_data = $this->build_tree_node($child, $depth + 1, $max_depth);
                if ($child_data) {
                    $children[] = $child_data;
                }
            }
        }

        // Get classes
        $classes = $node->getAttribute('class');
        $id = $node->getAttribute('id');

        return array(
            'tag' => strtoupper($node->tagName),
            'id' => $id,
            'classes' => $classes,
            'child_count' => $child_count,
            'children' => $children,
            'depth' => $depth,
            'has_issues' => $this->node_has_issues($node, $child_count, $depth)
        );
    }

    /**
     * Check if node has potential issues
     */
    private function node_has_issues($node, $child_count, $depth) {
        // Check for common issues
        if ($node->tagName === 'div' && $child_count === 1) {
            return 'single-child-wrapper';
        }

        if ($depth > 10) {
            return 'deep-nesting';
        }

        if ($child_count > 50) {
            return 'too-many-children';
        }

        return false;
    }
}
