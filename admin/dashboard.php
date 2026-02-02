<?php
/**
 * Dashboard template
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get historical data for chart
$historical_data = itx_cheetah()->reports->get_historical_data(30);
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <span class="dashicons dashicons-performance"></span>
        <?php esc_html_e('ITX Cheetah Dashboard', 'itx-cheetah'); ?>
    </h1>

    <!-- Quick Scan Section -->
    <div class="itx-card itx-scan-card">
        <h2><?php esc_html_e('Quick Scan', 'itx-cheetah'); ?></h2>
        <p class="description"><?php esc_html_e('Enter a URL from your site to analyze its DOM structure.', 'itx-cheetah'); ?></p>

        <div class="itx-scan-form">
            <input type="url"
                   id="itx-scan-url"
                   class="itx-scan-input"
                   placeholder="<?php esc_attr_e('https://yoursite.com/page-to-scan', 'itx-cheetah'); ?>"
                   value="<?php echo esc_url(home_url('/')); ?>">

            <button type="button" id="itx-scan-button" class="button button-primary button-hero">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e('Scan Now', 'itx-cheetah'); ?>
            </button>
        </div>

        <div id="itx-scan-progress" class="itx-scan-progress" style="display: none;">
            <div class="itx-spinner"></div>
            <span class="itx-scan-status"><?php esc_html_e('Scanning...', 'itx-cheetah'); ?></span>
        </div>

        <div id="itx-scan-result" class="itx-scan-result" style="display: none;"></div>
    </div>

    <!-- Statistics Cards -->
    <div class="itx-stats-grid">
        <div class="itx-stat-card">
            <div class="itx-stat-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="itx-stat-content">
                <span class="itx-stat-value"><?php echo esc_html(ITX_Cheetah_Admin::format_number($stats['total_scans'])); ?></span>
                <span class="itx-stat-label"><?php esc_html_e('Total Scans', 'itx-cheetah'); ?></span>
            </div>
        </div>

        <div class="itx-stat-card">
            <div class="itx-stat-icon">
                <span class="dashicons dashicons-editor-code"></span>
            </div>
            <div class="itx-stat-content">
                <span class="itx-stat-value"><?php echo esc_html(ITX_Cheetah_Admin::format_number($stats['avg_nodes'])); ?></span>
                <span class="itx-stat-label"><?php esc_html_e('Avg. DOM Nodes', 'itx-cheetah'); ?></span>
            </div>
        </div>

        <div class="itx-stat-card">
            <div class="itx-stat-icon">
                <span class="dashicons dashicons-arrow-up-alt"></span>
            </div>
            <div class="itx-stat-content">
                <span class="itx-stat-value"><?php echo esc_html(ITX_Cheetah_Admin::format_number($stats['max_nodes'])); ?></span>
                <span class="itx-stat-label"><?php esc_html_e('Max DOM Nodes', 'itx-cheetah'); ?></span>
            </div>
        </div>

        <div class="itx-stat-card">
            <div class="itx-stat-icon">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <div class="itx-stat-content">
                <span class="itx-stat-value"><?php echo esc_html($stats['avg_depth']); ?></span>
                <span class="itx-stat-label"><?php esc_html_e('Avg. Depth', 'itx-cheetah'); ?></span>
            </div>
        </div>
    </div>

    <!-- Historical Chart -->
    <?php if (!empty($historical_data) && count($historical_data) > 1) : ?>
    <div class="itx-card">
        <div class="itx-card-header">
            <h2><?php esc_html_e('DOM Size Trend (Last 30 Days)', 'itx-cheetah'); ?></h2>
        </div>
        <div class="itx-historical-chart">
            <canvas id="itx-historical-chart" height="250"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Status Overview -->
    <div class="itx-row">
        <div class="itx-col-8">
            <!-- Recent Scans -->
            <div class="itx-card">
                <div class="itx-card-header">
                    <h2><?php esc_html_e('Recent Scans', 'itx-cheetah'); ?></h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-scans')); ?>" class="button">
                        <?php esc_html_e('View All', 'itx-cheetah'); ?>
                    </a>
                </div>

                <?php if (empty($recent_scans)) : ?>
                    <div class="itx-empty-state">
                        <span class="dashicons dashicons-search"></span>
                        <p><?php esc_html_e('No scans yet. Use the Quick Scan above to analyze your first page!', 'itx-cheetah'); ?></p>
                    </div>
                <?php else : ?>
                    <table class="itx-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('URL', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Nodes', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Depth', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Score', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Date', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Actions', 'itx-cheetah'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_scans as $scan) : ?>
                                <tr>
                                    <td class="itx-url-cell">
                                        <a href="<?php echo esc_url($scan['url']); ?>" target="_blank" title="<?php echo esc_attr($scan['url']); ?>">
                                            <?php echo esc_html(wp_trim_words($scan['url'], 5, '...')); ?>
                                            <span class="dashicons dashicons-external"></span>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html(ITX_Cheetah_Admin::format_number($scan['total_nodes'])); ?></td>
                                    <td><?php echo esc_html($scan['max_depth']); ?></td>
                                    <td>
                                        <?php echo ITX_Cheetah_Admin::get_status_badge($scan['performance_score']); ?>
                                        <span class="itx-score"><?php echo esc_html($scan['performance_score']); ?></span>
                                    </td>
                                    <td title="<?php echo esc_attr($scan['created_at']); ?>">
                                        <?php echo esc_html(ITX_Cheetah_Admin::time_ago($scan['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $scan['id'])); ?>" class="button button-small">
                                            <?php esc_html_e('View', 'itx-cheetah'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="itx-col-4">
            <!-- Status Distribution -->
            <div class="itx-card">
                <h2><?php esc_html_e('Status Distribution', 'itx-cheetah'); ?></h2>

                <?php if ($stats['total_scans'] > 0) : ?>
                    <div class="itx-chart-container">
                        <canvas id="itx-status-chart"></canvas>
                    </div>

                    <div class="itx-legend">
                        <div class="itx-legend-item">
                            <span class="itx-legend-color good"></span>
                            <span class="itx-legend-label"><?php esc_html_e('Good', 'itx-cheetah'); ?></span>
                            <span class="itx-legend-value"><?php echo esc_html($stats['good_count']); ?></span>
                        </div>
                        <div class="itx-legend-item">
                            <span class="itx-legend-color warning"></span>
                            <span class="itx-legend-label"><?php esc_html_e('Warning', 'itx-cheetah'); ?></span>
                            <span class="itx-legend-value"><?php echo esc_html($stats['warning_count']); ?></span>
                        </div>
                        <div class="itx-legend-item">
                            <span class="itx-legend-color critical"></span>
                            <span class="itx-legend-label"><?php esc_html_e('Critical', 'itx-cheetah'); ?></span>
                            <span class="itx-legend-value"><?php echo esc_html($stats['critical_count']); ?></span>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="itx-empty-state small">
                        <p><?php esc_html_e('No data available yet.', 'itx-cheetah'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="itx-card">
                <h2><?php esc_html_e('Quick Actions', 'itx-cheetah'); ?></h2>
                <div class="itx-quick-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-bulk')); ?>" class="itx-quick-action">
                        <span class="dashicons dashicons-update"></span>
                        <span><?php esc_html_e('Bulk Scan', 'itx-cheetah'); ?></span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-compare')); ?>" class="itx-quick-action">
                        <span class="dashicons dashicons-chart-area"></span>
                        <span><?php esc_html_e('Compare Scans', 'itx-cheetah'); ?></span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-optimizations')); ?>" class="itx-quick-action">
                        <span class="dashicons dashicons-performance"></span>
                        <span><?php esc_html_e('Optimizations', 'itx-cheetah'); ?></span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-settings')); ?>" class="itx-quick-action">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <span><?php esc_html_e('Settings', 'itx-cheetah'); ?></span>
                    </a>
                </div>
            </div>

            <!-- Critical Pages Alert -->
            <?php if (!empty($critical_pages)) : ?>
                <div class="itx-card itx-alert-card">
                    <h2>
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Pages Needing Attention', 'itx-cheetah'); ?>
                    </h2>
                    <ul class="itx-alert-list">
                        <?php foreach ($critical_pages as $page) : ?>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $page['id'])); ?>">
                                    <?php echo esc_html(wp_trim_words($page['url'], 3, '...')); ?>
                                </a>
                                <span class="itx-alert-meta">
                                    <?php echo esc_html(ITX_Cheetah_Admin::format_number($page['total_nodes'])); ?> <?php esc_html_e('nodes', 'itx-cheetah'); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($stats['total_scans'] > 0) : ?>
    // Status distribution chart
    const statusCtx = document.getElementById('itx-status-chart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['<?php esc_html_e('Good', 'itx-cheetah'); ?>', '<?php esc_html_e('Warning', 'itx-cheetah'); ?>', '<?php esc_html_e('Critical', 'itx-cheetah'); ?>'],
                datasets: [{
                    data: [<?php echo intval($stats['good_count']); ?>, <?php echo intval($stats['warning_count']); ?>, <?php echo intval($stats['critical_count']); ?>],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                cutout: '70%'
            }
        });
    }
    <?php endif; ?>

    <?php if (!empty($historical_data) && count($historical_data) > 1) : ?>
    // Historical chart
    const histCtx = document.getElementById('itx-historical-chart');
    if (histCtx) {
        new Chart(histCtx, {
            type: 'line',
            data: {
                labels: <?php echo wp_json_encode(array_map(function($d) {
                    return date_i18n('M j', strtotime($d['scan_date']));
                }, $historical_data)); ?>,
                datasets: [
                    {
                        label: '<?php esc_html_e('Avg. Nodes', 'itx-cheetah'); ?>',
                        data: <?php echo wp_json_encode(array_map(function($d) {
                            return round($d['avg_nodes']);
                        }, $historical_data)); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: '<?php esc_html_e('Avg. Score', 'itx-cheetah'); ?>',
                        data: <?php echo wp_json_encode(array_map(function($d) {
                            return round($d['avg_score']);
                        }, $historical_data)); ?>,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: '<?php esc_html_e('Nodes', 'itx-cheetah'); ?>'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        min: 0,
                        max: 100,
                        title: {
                            display: true,
                            text: '<?php esc_html_e('Score', 'itx-cheetah'); ?>'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });
    }
    <?php endif; ?>
});
</script>
