<?php
/**
 * Bulk scan page template
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <span class="dashicons dashicons-update"></span>
        <?php esc_html_e('Bulk Scan', 'itx-cheetah'); ?>
    </h1>

    <div class="itx-row">
        <div class="itx-col-8">
            <!-- Scan Configuration -->
            <div class="itx-card">
                <h2><?php esc_html_e('Scan Configuration', 'itx-cheetah'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Select which pages to scan and start the bulk scanning process.', 'itx-cheetah'); ?>
                </p>

                <form id="itx-bulk-scan-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Post Types', 'itx-cheetah'); ?>
                            </th>
                            <td>
                                <?php
                                $post_types = get_post_types(array('public' => true), 'objects');
                                foreach ($post_types as $post_type) :
                                ?>
                                    <label style="display: inline-block; margin-right: 15px; margin-bottom: 5px;">
                                        <input type="checkbox"
                                               name="post_types[]"
                                               value="<?php echo esc_attr($post_type->name); ?>"
                                               <?php checked(in_array($post_type->name, array('post', 'page'))); ?>
                                               class="itx-post-type-checkbox">
                                        <?php echo esc_html($post_type->labels->name); ?>
                                        <span class="itx-count">(<?php echo esc_html(wp_count_posts($post_type->name)->publish); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="itx-batch-size"><?php esc_html_e('Batch Size', 'itx-cheetah'); ?></label>
                            </th>
                            <td>
                                <select id="itx-batch-size" name="batch_size">
                                    <option value="5">5 <?php esc_html_e('pages', 'itx-cheetah'); ?></option>
                                    <option value="10" selected>10 <?php esc_html_e('pages', 'itx-cheetah'); ?></option>
                                    <option value="20">20 <?php esc_html_e('pages', 'itx-cheetah'); ?></option>
                                    <option value="50">50 <?php esc_html_e('pages', 'itx-cheetah'); ?></option>
                                    <option value="100">100 <?php esc_html_e('pages', 'itx-cheetah'); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Number of pages to scan in each batch. Smaller batches are more reliable but take longer.', 'itx-cheetah'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Include Homepage', 'itx-cheetah'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="include_homepage" value="1" checked>
                                    <?php esc_html_e('Include the homepage in the scan', 'itx-cheetah'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <div class="itx-bulk-actions-bar">
                        <button type="button" id="itx-start-bulk-scan" class="button button-primary button-hero">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e('Start Bulk Scan', 'itx-cheetah'); ?>
                        </button>

                        <button type="button" id="itx-stop-bulk-scan" class="button button-secondary" style="display: none;">
                            <span class="dashicons dashicons-no"></span>
                            <?php esc_html_e('Stop Scan', 'itx-cheetah'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Progress Section -->
            <div id="itx-bulk-progress" class="itx-card" style="display: none;">
                <h2><?php esc_html_e('Scan Progress', 'itx-cheetah'); ?></h2>

                <div class="itx-progress-wrapper">
                    <div class="itx-progress-bar-container">
                        <div class="itx-progress-bar-fill" id="itx-progress-fill" style="width: 0%;"></div>
                    </div>
                    <div class="itx-progress-text">
                        <span id="itx-progress-current">0</span> / <span id="itx-progress-total">0</span>
                        (<span id="itx-progress-percent">0</span>%)
                    </div>
                </div>

                <div class="itx-progress-stats">
                    <div class="itx-progress-stat">
                        <span class="itx-progress-stat-value" id="itx-stat-completed">0</span>
                        <span class="itx-progress-stat-label"><?php esc_html_e('Completed', 'itx-cheetah'); ?></span>
                    </div>
                    <div class="itx-progress-stat">
                        <span class="itx-progress-stat-value" id="itx-stat-failed">0</span>
                        <span class="itx-progress-stat-label"><?php esc_html_e('Failed', 'itx-cheetah'); ?></span>
                    </div>
                    <div class="itx-progress-stat">
                        <span class="itx-progress-stat-value" id="itx-stat-avg-score">-</span>
                        <span class="itx-progress-stat-label"><?php esc_html_e('Avg. Score', 'itx-cheetah'); ?></span>
                    </div>
                </div>

                <div id="itx-current-scan" class="itx-current-scan">
                    <span class="itx-spinner"></span>
                    <span id="itx-current-url"><?php esc_html_e('Preparing...', 'itx-cheetah'); ?></span>
                </div>
            </div>

            <!-- Results Section -->
            <div id="itx-bulk-results" class="itx-card" style="display: none;">
                <div class="itx-card-header">
                    <h2><?php esc_html_e('Scan Results', 'itx-cheetah'); ?></h2>
                    <div class="itx-result-actions">
                        <button type="button" id="itx-export-results-csv" class="button button-small">
                            <span class="dashicons dashicons-media-spreadsheet"></span>
                            <?php esc_html_e('Export CSV', 'itx-cheetah'); ?>
                        </button>
                    </div>
                </div>

                <div class="itx-table-scroll" style="max-height: 400px;">
                    <table class="itx-table itx-table-compact">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('URL', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Status', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Nodes', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Depth', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Score', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Action', 'itx-cheetah'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="itx-results-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="itx-col-4">
            <!-- Quick Stats -->
            <div class="itx-card">
                <h2><?php esc_html_e('Site Overview', 'itx-cheetah'); ?></h2>

                <div class="itx-mini-stats">
                    <?php
                    $total_posts = 0;
                    $post_types = get_post_types(array('public' => true), 'objects');
                    foreach ($post_types as $pt) {
                        $total_posts += wp_count_posts($pt->name)->publish;
                    }
                    ?>
                    <div class="itx-mini-stat">
                        <span class="itx-mini-stat-value"><?php echo esc_html(number_format_i18n($total_posts)); ?></span>
                        <span class="itx-mini-stat-label"><?php esc_html_e('Total Published Pages', 'itx-cheetah'); ?></span>
                    </div>

                    <div class="itx-mini-stat">
                        <span class="itx-mini-stat-value"><?php echo esc_html(number_format_i18n($stats['total_scans'])); ?></span>
                        <span class="itx-mini-stat-label"><?php esc_html_e('Pages Already Scanned', 'itx-cheetah'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="itx-card">
                <h2><?php esc_html_e('Tips', 'itx-cheetah'); ?></h2>
                <ul class="itx-tips-list">
                    <li>
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php esc_html_e('Start with a small batch size to test.', 'itx-cheetah'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php esc_html_e('Scanning runs in the background - you can navigate away.', 'itx-cheetah'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php esc_html_e('Consider scheduling automated scans for regular monitoring.', 'itx-cheetah'); ?>
                    </li>
                </ul>
            </div>

            <!-- Recent Critical -->
            <?php if (!empty($critical_pages)) : ?>
                <div class="itx-card itx-alert-card">
                    <h2>
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Recent Critical Pages', 'itx-cheetah'); ?>
                    </h2>
                    <ul class="itx-alert-list">
                        <?php foreach (array_slice($critical_pages, 0, 5) as $page) : ?>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $page['id'])); ?>">
                                    <?php echo esc_html(wp_trim_words($page['url'], 3, '...')); ?>
                                </a>
                                <span class="itx-alert-meta">
                                    <?php echo esc_html($page['performance_score']); ?>%
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
