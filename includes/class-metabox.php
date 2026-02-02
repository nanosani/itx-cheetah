<?php
/**
 * Post Meta Box for ITX Cheetah
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta box class for post editor integration
 */
class ITX_Cheetah_Metabox {

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
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_metabox_scripts'));
        add_action('wp_ajax_itx_cheetah_scan_post', array($this, 'ajax_scan_post'));
    }

    /**
     * Add meta box to post editor
     */
    public function add_meta_box() {
        $post_types = get_post_types(array('public' => true), 'names');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'itx-cheetah-metabox',
                __('DOM Analysis', 'itx-cheetah'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Enqueue scripts for meta box
     */
    public function enqueue_metabox_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        wp_enqueue_style(
            'itx-cheetah-metabox',
            ITX_CHEETAH_PLUGIN_URL . 'assets/css/metabox.css',
            array(),
            ITX_CHEETAH_VERSION
        );

        wp_enqueue_script(
            'itx-cheetah-metabox',
            ITX_CHEETAH_PLUGIN_URL . 'assets/js/metabox.js',
            array('jquery'),
            ITX_CHEETAH_VERSION,
            true
        );

        wp_localize_script('itx-cheetah-metabox', 'itxCheetahMetabox', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('itx_cheetah_metabox'),
            'strings' => array(
                'scanning' => __('Scanning...', 'itx-cheetah'),
                'scanComplete' => __('Scan complete!', 'itx-cheetah'),
                'scanError' => __('Scan failed', 'itx-cheetah'),
                'saveFirst' => __('Please save the post first to scan.', 'itx-cheetah'),
            ),
        ));
    }

    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        $post_id = $post->ID;
        $permalink = get_permalink($post_id);

        // Get latest scan for this post
        $scan = null;
        if ($permalink) {
            $scan = $this->database->get_scan_by_url($permalink);
        }

        ?>
        <div class="itx-metabox-content">
            <?php if ($post->post_status === 'publish' && $permalink) : ?>
                <?php if ($scan) : ?>
                    <!-- Show last scan results -->
                    <div class="itx-metabox-results" id="itx-metabox-results">
                        <?php echo $this->render_scan_results($scan); ?>
                    </div>
                <?php else : ?>
                    <div class="itx-metabox-no-scan" id="itx-metabox-results">
                        <p class="description">
                            <?php esc_html_e('This page has not been scanned yet.', 'itx-cheetah'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="itx-metabox-actions">
                    <button type="button"
                            class="button button-primary"
                            id="itx-metabox-scan"
                            data-post-id="<?php echo esc_attr($post_id); ?>">
                        <span class="dashicons dashicons-search"></span>
                        <?php echo $scan ? esc_html__('Rescan', 'itx-cheetah') : esc_html__('Scan Now', 'itx-cheetah'); ?>
                    </button>

                    <?php if ($scan) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $scan['id'])); ?>"
                           class="button"
                           target="_blank">
                            <?php esc_html_e('Full Report', 'itx-cheetah'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="itx-metabox-progress" id="itx-metabox-progress" style="display: none;">
                    <span class="spinner is-active"></span>
                    <span><?php esc_html_e('Scanning...', 'itx-cheetah'); ?></span>
                </div>
            <?php else : ?>
                <p class="description">
                    <?php esc_html_e('Publish this post to enable DOM scanning.', 'itx-cheetah'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render scan results HTML
     */
    private function render_scan_results($scan) {
        $score = $scan['performance_score'];
        $status_class = $score >= 80 ? 'good' : ($score >= 50 ? 'warning' : 'critical');
        $status_label = $score >= 80 ? __('Good', 'itx-cheetah') : ($score >= 50 ? __('Warning', 'itx-cheetah') : __('Critical', 'itx-cheetah'));

        ob_start();
        ?>
        <div class="itx-metabox-score itx-score-<?php echo esc_attr($status_class); ?>">
            <span class="itx-score-value"><?php echo esc_html($score); ?></span>
            <span class="itx-score-label"><?php echo esc_html($status_label); ?></span>
        </div>

        <div class="itx-metabox-metrics">
            <div class="itx-metabox-metric">
                <span class="itx-metric-value"><?php echo esc_html(number_format_i18n($scan['total_nodes'])); ?></span>
                <span class="itx-metric-label"><?php esc_html_e('Nodes', 'itx-cheetah'); ?></span>
            </div>
            <div class="itx-metabox-metric">
                <span class="itx-metric-value"><?php echo esc_html($scan['max_depth']); ?></span>
                <span class="itx-metric-label"><?php esc_html_e('Depth', 'itx-cheetah'); ?></span>
            </div>
        </div>

        <?php if (!empty($scan['recommendations'])) :
            $critical_recs = array_filter($scan['recommendations'], function($r) {
                return $r['severity'] === 'critical' || $r['severity'] === 'warning';
            });
            if (!empty($critical_recs)) :
        ?>
            <div class="itx-metabox-issues">
                <strong><?php esc_html_e('Issues:', 'itx-cheetah'); ?></strong>
                <ul>
                    <?php foreach (array_slice($critical_recs, 0, 3) as $rec) : ?>
                        <li class="itx-issue-<?php echo esc_attr($rec['severity']); ?>">
                            <?php echo esc_html($rec['title']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; endif; ?>

        <p class="itx-metabox-time">
            <?php
            printf(
                esc_html__('Last scanned: %s', 'itx-cheetah'),
                esc_html(human_time_diff(strtotime($scan['created_at']), current_time('timestamp')) . ' ' . __('ago', 'itx-cheetah'))
            );
            ?>
        </p>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for scanning post
     */
    public function ajax_scan_post() {
        check_ajax_referer('itx_cheetah_metabox', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'itx-cheetah')));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'itx-cheetah')));
        }

        $permalink = get_permalink($post_id);

        if (!$permalink) {
            wp_send_json_error(array('message' => __('Could not get post URL.', 'itx-cheetah')));
        }

        // Run the scan
        $result = $this->analyzer->analyze_url($permalink);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Save the scan
        $scan_id = $this->database->insert_scan($result);

        if (is_wp_error($scan_id)) {
            wp_send_json_error(array('message' => $scan_id->get_error_message()));
        }

        // Get the saved scan
        $scan = $this->database->get_scan($scan_id);

        // Trigger action for notifications
        do_action('itx_cheetah_scan_complete', $scan_id, $scan);

        wp_send_json_success(array(
            'html' => $this->render_scan_results($scan),
            'scan_id' => $scan_id,
        ));
    }
}
