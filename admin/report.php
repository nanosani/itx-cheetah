<?php
/**
 * Single scan report template
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

$status_label = $scan['performance_score'] >= 80 ? 'good' : ($scan['performance_score'] >= 50 ? 'warning' : 'critical');
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-scans')); ?>" class="itx-back-link">
            <span class="dashicons dashicons-arrow-left-alt"></span>
        </a>
        <?php esc_html_e('Scan Report', 'itx-cheetah'); ?>
    </h1>

    <!-- Report Header -->
    <div class="itx-report-header itx-card">
        <div class="itx-report-url">
            <h2>
                <a href="<?php echo esc_url($scan['url']); ?>" target="_blank">
                    <?php echo esc_html($scan['url']); ?>
                    <span class="dashicons dashicons-external"></span>
                </a>
            </h2>
            <p class="itx-report-meta">
                <?php
                printf(
                    esc_html__('Scanned %s | Scan time: %ss', 'itx-cheetah'),
                    esc_html(ITX_Cheetah_Admin::time_ago($scan['created_at'])),
                    esc_html($scan['scan_time'])
                );
                ?>
            </p>
        </div>

        <div class="itx-report-score itx-score-<?php echo esc_attr($status_label); ?>">
            <div class="itx-score-circle">
                <span class="itx-score-value"><?php echo esc_html($scan['performance_score']); ?></span>
            </div>
            <span class="itx-score-label"><?php esc_html_e('Performance Score', 'itx-cheetah'); ?></span>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="itx-metrics-grid">
        <div class="itx-metric-card">
            <span class="itx-metric-value"><?php echo esc_html(ITX_Cheetah_Admin::format_number($scan['total_nodes'])); ?></span>
            <span class="itx-metric-label"><?php esc_html_e('Total DOM Nodes', 'itx-cheetah'); ?></span>
            <span class="itx-metric-threshold">
                <?php
                $settings = get_option('itx_cheetah_settings', array());
                $node_warning = isset($settings['node_threshold_warning']) ? $settings['node_threshold_warning'] : 1500;
                printf(esc_html__('Recommended: < %s', 'itx-cheetah'), ITX_Cheetah_Admin::format_number($node_warning));
                ?>
            </span>
        </div>

        <div class="itx-metric-card">
            <span class="itx-metric-value"><?php echo esc_html($scan['max_depth']); ?></span>
            <span class="itx-metric-label"><?php esc_html_e('Maximum Depth', 'itx-cheetah'); ?></span>
            <span class="itx-metric-threshold">
                <?php
                $depth_warning = isset($settings['depth_threshold_warning']) ? $settings['depth_threshold_warning'] : 32;
                printf(esc_html__('Recommended: < %s', 'itx-cheetah'), $depth_warning);
                ?>
            </span>
        </div>

        <div class="itx-metric-card">
            <span class="itx-metric-value"><?php echo esc_html(count($scan['element_counts'])); ?></span>
            <span class="itx-metric-label"><?php esc_html_e('Element Types', 'itx-cheetah'); ?></span>
        </div>

        <div class="itx-metric-card">
            <span class="itx-metric-value"><?php echo esc_html(count($scan['large_nodes'])); ?></span>
            <span class="itx-metric-label"><?php esc_html_e('Large Nodes', 'itx-cheetah'); ?></span>
            <span class="itx-metric-threshold"><?php esc_html_e('Elements with >50 children', 'itx-cheetah'); ?></span>
        </div>
    </div>

    <div class="itx-row">
        <!-- Element Distribution -->
        <div class="itx-col-6">
            <div class="itx-card">
                <h2><?php esc_html_e('Element Distribution', 'itx-cheetah'); ?></h2>

                <?php if (!empty($scan['element_counts'])) : ?>
                    <div class="itx-chart-container" style="height: 300px;">
                        <canvas id="itx-elements-chart"></canvas>
                    </div>

                    <div class="itx-table-scroll" style="max-height: 300px;">
                        <table class="itx-table itx-table-compact">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Element', 'itx-cheetah'); ?></th>
                                    <th><?php esc_html_e('Count', 'itx-cheetah'); ?></th>
                                    <th><?php esc_html_e('Percentage', 'itx-cheetah'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total = $scan['total_nodes'];
                                foreach ($scan['element_counts'] as $element => $count) :
                                    $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td><code>&lt;<?php echo esc_html($element); ?>&gt;</code></td>
                                        <td><?php echo esc_html(ITX_Cheetah_Admin::format_number($count)); ?></td>
                                        <td>
                                            <div class="itx-progress-bar">
                                                <div class="itx-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                            </div>
                                            <span><?php echo esc_html($percentage); ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <p class="itx-empty"><?php esc_html_e('No element data available.', 'itx-cheetah'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Depth Distribution -->
        <div class="itx-col-6">
            <div class="itx-card">
                <h2><?php esc_html_e('Node Distribution by Depth', 'itx-cheetah'); ?></h2>

                <?php if (!empty($scan['node_distribution'])) : ?>
                    <div class="itx-chart-container" style="height: 300px;">
                        <canvas id="itx-depth-chart"></canvas>
                    </div>

                    <table class="itx-table itx-table-compact">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Depth Level', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Nodes', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Percentage', 'itx-cheetah'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($scan['node_distribution'] as $range => $count) :
                                $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><?php echo esc_html($range); ?></td>
                                    <td><?php echo esc_html(ITX_Cheetah_Admin::format_number($count)); ?></td>
                                    <td><?php echo esc_html($percentage); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="itx-empty"><?php esc_html_e('No depth data available.', 'itx-cheetah'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Large Nodes -->
    <?php if (!empty($scan['large_nodes'])) : ?>
        <div class="itx-card">
            <h2><?php esc_html_e('Large Nodes (Elements with >50 Children)', 'itx-cheetah'); ?></h2>

            <table class="itx-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Element', 'itx-cheetah'); ?></th>
                        <th><?php esc_html_e('ID', 'itx-cheetah'); ?></th>
                        <th><?php esc_html_e('Class', 'itx-cheetah'); ?></th>
                        <th><?php esc_html_e('Children', 'itx-cheetah'); ?></th>
                        <th><?php esc_html_e('Depth', 'itx-cheetah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scan['large_nodes'] as $node) : ?>
                        <tr>
                            <td><code>&lt;<?php echo esc_html($node['tag']); ?>&gt;</code></td>
                            <td>
                                <?php if (!empty($node['id'])) : ?>
                                    <code>#<?php echo esc_html($node['id']); ?></code>
                                <?php else : ?>
                                    <span class="itx-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($node['class'])) : ?>
                                    <code class="itx-class-list">.<?php echo esc_html(str_replace(' ', ' .', $node['class'])); ?></code>
                                <?php else : ?>
                                    <span class="itx-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="itx-text-warning"><?php echo esc_html($node['children']); ?></strong>
                            </td>
                            <td><?php echo esc_html($node['depth']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Recommendations -->
    <?php if (!empty($scan['recommendations'])) : ?>
        <div class="itx-card">
            <div class="itx-card-header">
                <h2><?php esc_html_e('Recommendations', 'itx-cheetah'); ?></h2>
                <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-fixes&scan_id=' . $scan['id'])); ?>" class="button button-primary">
                    <span class="dashicons dashicons-hammer"></span>
                    <?php esc_html_e('View Actionable Fixes', 'itx-cheetah'); ?>
                </a>
            </div>

            <div class="itx-recommendations">
                <?php foreach ($scan['recommendations'] as $rec) : ?>
                    <div class="itx-recommendation itx-recommendation-<?php echo esc_attr($rec['severity']); ?>">
                        <div class="itx-recommendation-header">
                            <span class="itx-recommendation-icon">
                                <?php
                                switch ($rec['severity']) {
                                    case 'critical':
                                        echo '<span class="dashicons dashicons-warning"></span>';
                                        break;
                                    case 'warning':
                                        echo '<span class="dashicons dashicons-flag"></span>';
                                        break;
                                    case 'success':
                                        echo '<span class="dashicons dashicons-yes-alt"></span>';
                                        break;
                                    default:
                                        echo '<span class="dashicons dashicons-info"></span>';
                                }
                                ?>
                            </span>
                            <h3><?php echo esc_html($rec['title']); ?></h3>
                        </div>
                        <p><?php echo esc_html($rec['description']); ?></p>

                        <?php if (!empty($rec['suggestions'])) : ?>
                            <ul class="itx-suggestions">
                                <?php foreach ($rec['suggestions'] as $suggestion) : ?>
                                    <li><?php echo esc_html($suggestion); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="itx-report-actions">
        <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-scans')); ?>" class="button">
            <?php esc_html_e('Back to All Scans', 'itx-cheetah'); ?>
        </a>

        <button type="button" class="button" id="itx-rescan-button" data-url="<?php echo esc_attr($scan['url']); ?>">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Rescan Page', 'itx-cheetah'); ?>
        </button>

        <a href="<?php echo esc_url(ITX_Cheetah_Reports::get_export_url('csv', array('export_type' => 'single', 'scan_id' => $scan['id']))); ?>" class="button">
            <span class="dashicons dashicons-media-spreadsheet"></span>
            <?php esc_html_e('Export CSV', 'itx-cheetah'); ?>
        </a>

        <a href="<?php echo esc_url(ITX_Cheetah_Reports::get_export_url('json', array('export_type' => 'single', 'scan_id' => $scan['id']))); ?>" class="button">
            <span class="dashicons dashicons-media-code"></span>
            <?php esc_html_e('Export JSON', 'itx-cheetah'); ?>
        </a>

        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=itx-cheetah-scans&action=delete&scan_id=' . $scan['id']), 'delete_scan_' . $scan['id'])); ?>" class="button itx-button-danger" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this scan?', 'itx-cheetah'); ?>');">
            <span class="dashicons dashicons-trash"></span>
            <?php esc_html_e('Delete Scan', 'itx-cheetah'); ?>
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Element distribution chart
    <?php
    $top_elements = array_slice($scan['element_counts'], 0, 10, true);
    $element_labels = array_keys($top_elements);
    $element_values = array_values($top_elements);
    ?>

    const elementsCtx = document.getElementById('itx-elements-chart');
    if (elementsCtx) {
        new Chart(elementsCtx, {
            type: 'bar',
            data: {
                labels: <?php echo wp_json_encode($element_labels); ?>,
                datasets: [{
                    label: '<?php esc_html_e('Count', 'itx-cheetah'); ?>',
                    data: <?php echo wp_json_encode($element_values); ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Depth distribution chart
    <?php
    $depth_labels = array_keys($scan['node_distribution']);
    $depth_values = array_values($scan['node_distribution']);
    ?>

    const depthCtx = document.getElementById('itx-depth-chart');
    if (depthCtx) {
        new Chart(depthCtx, {
            type: 'bar',
            data: {
                labels: <?php echo wp_json_encode($depth_labels); ?>,
                datasets: [{
                    label: '<?php esc_html_e('Nodes', 'itx-cheetah'); ?>',
                    data: <?php echo wp_json_encode($depth_values); ?>,
                    backgroundColor: '#8b5cf6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Rescan button
    document.getElementById('itx-rescan-button')?.addEventListener('click', function() {
        const url = this.dataset.url;
        const scanInput = document.getElementById('itx-scan-url');
        if (scanInput) {
            scanInput.value = url;
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
        document.getElementById('itx-scan-button')?.click();
    });
});
</script>
