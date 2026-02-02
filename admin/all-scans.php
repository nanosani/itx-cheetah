<?php
/**
 * All scans list template
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e('All Scans', 'itx-cheetah'); ?>
    </h1>

    <?php if (isset($_GET['deleted'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $count = intval($_GET['deleted']);
                printf(
                    esc_html(_n('%d scan deleted.', '%d scans deleted.', $count, 'itx-cheetah')),
                    $count
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="itx-filters">
        <form method="get" class="itx-filter-form">
            <input type="hidden" name="page" value="itx-cheetah-scans">

            <div class="itx-search-box">
                <input type="search"
                       name="s"
                       value="<?php echo esc_attr($search); ?>"
                       placeholder="<?php esc_attr_e('Search by URL...', 'itx-cheetah'); ?>">
                <button type="submit" class="button">
                    <span class="dashicons dashicons-search"></span>
                </button>
            </div>

            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'itx-cheetah'); ?></option>
                <option value="completed" <?php selected($status, 'completed'); ?>><?php esc_html_e('Completed', 'itx-cheetah'); ?></option>
                <option value="processing" <?php selected($status, 'processing'); ?>><?php esc_html_e('Processing', 'itx-cheetah'); ?></option>
                <option value="failed" <?php selected($status, 'failed'); ?>><?php esc_html_e('Failed', 'itx-cheetah'); ?></option>
            </select>

            <button type="submit" class="button"><?php esc_html_e('Filter', 'itx-cheetah'); ?></button>

            <?php if ($search || $status) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-scans')); ?>" class="button">
                    <?php esc_html_e('Clear', 'itx-cheetah'); ?>
                </a>
            <?php endif; ?>
        </form>

        <div class="itx-filter-info">
            <?php
            printf(
                esc_html(_n('Showing %d scan', 'Showing %d scans', $total_scans, 'itx-cheetah')),
                $total_scans
            );
            ?>
        </div>
    </div>

    <?php if (empty($scans)) : ?>
        <div class="itx-card">
            <div class="itx-empty-state">
                <span class="dashicons dashicons-search"></span>
                <?php if ($search || $status) : ?>
                    <p><?php esc_html_e('No scans match your filters.', 'itx-cheetah'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-scans')); ?>" class="button">
                        <?php esc_html_e('Clear Filters', 'itx-cheetah'); ?>
                    </a>
                <?php else : ?>
                    <p><?php esc_html_e('No scans yet. Go to the Dashboard to scan your first page!', 'itx-cheetah'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah')); ?>" class="button button-primary">
                        <?php esc_html_e('Go to Dashboard', 'itx-cheetah'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else : ?>
        <form method="post" id="itx-scans-form">
            <?php wp_nonce_field('bulk_action_scans'); ?>
            <input type="hidden" name="action" value="bulk_delete">

            <div class="itx-bulk-actions">
                <label>
                    <input type="checkbox" id="itx-select-all">
                    <?php esc_html_e('Select All', 'itx-cheetah'); ?>
                </label>

                <button type="submit" class="button" id="itx-bulk-delete" disabled>
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Delete Selected', 'itx-cheetah'); ?>
                </button>
            </div>

            <div class="itx-card">
                <table class="itx-table itx-table-hover">
                    <thead>
                        <tr>
                            <th class="itx-col-check">
                                <span class="screen-reader-text"><?php esc_html_e('Select', 'itx-cheetah'); ?></span>
                            </th>
                            <th class="itx-col-url"><?php esc_html_e('URL', 'itx-cheetah'); ?></th>
                            <th class="itx-col-nodes"><?php esc_html_e('Nodes', 'itx-cheetah'); ?></th>
                            <th class="itx-col-depth"><?php esc_html_e('Depth', 'itx-cheetah'); ?></th>
                            <th class="itx-col-score"><?php esc_html_e('Score', 'itx-cheetah'); ?></th>
                            <th class="itx-col-status"><?php esc_html_e('Status', 'itx-cheetah'); ?></th>
                            <th class="itx-col-date"><?php esc_html_e('Date', 'itx-cheetah'); ?></th>
                            <th class="itx-col-actions"><?php esc_html_e('Actions', 'itx-cheetah'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scans as $scan) : ?>
                            <tr>
                                <td class="itx-col-check">
                                    <input type="checkbox" name="scan_ids[]" value="<?php echo esc_attr($scan['id']); ?>" class="itx-scan-checkbox">
                                </td>
                                <td class="itx-col-url">
                                    <a href="<?php echo esc_url($scan['url']); ?>" target="_blank" class="itx-url-link" title="<?php echo esc_attr($scan['url']); ?>">
                                        <?php echo esc_html(wp_trim_words($scan['url'], 8, '...')); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                    <?php if ($scan['post_id']) : ?>
                                        <span class="itx-post-type">
                                            <?php echo esc_html(get_post_type($scan['post_id'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="itx-col-nodes">
                                    <span class="itx-node-count <?php echo $scan['total_nodes'] > 1500 ? 'critical' : ($scan['total_nodes'] > 1000 ? 'warning' : 'good'); ?>">
                                        <?php echo esc_html(ITX_Cheetah_Admin::format_number($scan['total_nodes'])); ?>
                                    </span>
                                </td>
                                <td class="itx-col-depth">
                                    <span class="itx-depth-count <?php echo $scan['max_depth'] > 32 ? 'critical' : ($scan['max_depth'] > 20 ? 'warning' : 'good'); ?>">
                                        <?php echo esc_html($scan['max_depth']); ?>
                                    </span>
                                </td>
                                <td class="itx-col-score">
                                    <?php echo ITX_Cheetah_Admin::get_status_badge($scan['performance_score']); ?>
                                    <span class="itx-score-number"><?php echo esc_html($scan['performance_score']); ?></span>
                                </td>
                                <td class="itx-col-status">
                                    <span class="itx-status itx-status-<?php echo esc_attr($scan['status']); ?>">
                                        <?php echo esc_html(ucfirst($scan['status'])); ?>
                                    </span>
                                </td>
                                <td class="itx-col-date" title="<?php echo esc_attr($scan['created_at']); ?>">
                                    <?php echo esc_html(ITX_Cheetah_Admin::time_ago($scan['created_at'])); ?>
                                </td>
                                <td class="itx-col-actions">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $scan['id'])); ?>" class="button button-small" title="<?php esc_attr_e('View Report', 'itx-cheetah'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=itx-cheetah-scans&action=delete&scan_id=' . $scan['id']), 'delete_scan_' . $scan['id'])); ?>" class="button button-small itx-button-danger" title="<?php esc_attr_e('Delete', 'itx-cheetah'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this scan?', 'itx-cheetah'); ?>');">
                                        <span class="dashicons dashicons-trash"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Pagination -->
        <?php if ($total_pages > 1) : ?>
            <div class="itx-pagination">
                <?php
                $base_url = admin_url('admin.php?page=itx-cheetah-scans');
                if ($search) {
                    $base_url = add_query_arg('s', $search, $base_url);
                }
                if ($status) {
                    $base_url = add_query_arg('status', $status, $base_url);
                }

                // Previous
                if ($current_page > 1) :
                ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $base_url)); ?>" class="button">
                        &laquo; <?php esc_html_e('Previous', 'itx-cheetah'); ?>
                    </a>
                <?php else : ?>
                    <span class="button disabled">&laquo; <?php esc_html_e('Previous', 'itx-cheetah'); ?></span>
                <?php endif; ?>

                <span class="itx-pagination-info">
                    <?php
                    printf(
                        esc_html__('Page %1$d of %2$d', 'itx-cheetah'),
                        $current_page,
                        $total_pages
                    );
                    ?>
                </span>

                <?php
                // Next
                if ($current_page < $total_pages) :
                ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $base_url)); ?>" class="button">
                        <?php esc_html_e('Next', 'itx-cheetah'); ?> &raquo;
                    </a>
                <?php else : ?>
                    <span class="button disabled"><?php esc_html_e('Next', 'itx-cheetah'); ?> &raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('itx-select-all');
    const checkboxes = document.querySelectorAll('.itx-scan-checkbox');
    const bulkDelete = document.getElementById('itx-bulk-delete');
    const form = document.getElementById('itx-scans-form');

    function updateBulkButton() {
        const checked = document.querySelectorAll('.itx-scan-checkbox:checked');
        bulkDelete.disabled = checked.length === 0;
    }

    selectAll?.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkButton();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkButton);
    });

    form?.addEventListener('submit', function(e) {
        const checked = document.querySelectorAll('.itx-scan-checkbox:checked');
        if (checked.length === 0) {
            e.preventDefault();
            return false;
        }
        if (!confirm(itxCheetah.strings.confirmBulkDelete)) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
