<?php
/**
 * Scan comparison page template
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <span class="dashicons dashicons-chart-area"></span>
        <?php esc_html_e('Compare Scans', 'itx-cheetah'); ?>
    </h1>

    <?php if (empty($scans_to_compare)) : ?>
        <!-- Selection Interface -->
        <div class="itx-card">
            <h2><?php esc_html_e('Select Scans to Compare', 'itx-cheetah'); ?></h2>
            <p class="description">
                <?php esc_html_e('Select 2-5 scans from the same URL to compare changes over time, or compare different pages.', 'itx-cheetah'); ?>
            </p>

            <form method="get" id="itx-compare-form">
                <input type="hidden" name="page" value="itx-cheetah-compare">

                <!-- URL Filter -->
                <div class="itx-filter-row">
                    <label for="itx-url-filter"><?php esc_html_e('Filter by URL:', 'itx-cheetah'); ?></label>
                    <select id="itx-url-filter" name="url_filter" style="min-width: 300px;">
                        <option value=""><?php esc_html_e('All URLs', 'itx-cheetah'); ?></option>
                        <?php foreach ($unique_urls as $url) : ?>
                            <option value="<?php echo esc_attr($url); ?>" <?php selected($url_filter, $url); ?>>
                                <?php echo esc_html(wp_trim_words($url, 8, '...')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e('Filter', 'itx-cheetah'); ?></button>
                </div>

                <!-- Scan Selection -->
                <div class="itx-table-scroll" style="max-height: 400px; margin-top: 20px;">
                    <table class="itx-table itx-table-hover">
                        <thead>
                            <tr>
                                <th class="itx-col-check">
                                    <span class="screen-reader-text"><?php esc_html_e('Select', 'itx-cheetah'); ?></span>
                                </th>
                                <th><?php esc_html_e('URL', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Nodes', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Depth', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Score', 'itx-cheetah'); ?></th>
                                <th><?php esc_html_e('Date', 'itx-cheetah'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_scans as $scan) : ?>
                                <tr>
                                    <td class="itx-col-check">
                                        <input type="checkbox"
                                               name="scan_ids[]"
                                               value="<?php echo esc_attr($scan['id']); ?>"
                                               class="itx-compare-checkbox">
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($scan['url']); ?>" target="_blank">
                                            <?php echo esc_html(wp_trim_words($scan['url'], 6, '...')); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html(number_format_i18n($scan['total_nodes'])); ?></td>
                                    <td><?php echo esc_html($scan['max_depth']); ?></td>
                                    <td>
                                        <?php echo ITX_Cheetah_Admin::get_status_badge($scan['performance_score']); ?>
                                        <?php echo esc_html($scan['performance_score']); ?>
                                    </td>
                                    <td><?php echo esc_html(ITX_Cheetah_Admin::time_ago($scan['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="itx-compare-actions" style="margin-top: 20px;">
                    <button type="submit" class="button button-primary" id="itx-compare-button" disabled>
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php esc_html_e('Compare Selected', 'itx-cheetah'); ?>
                    </button>
                    <span class="itx-compare-hint">
                        <?php esc_html_e('Select 2-5 scans to compare', 'itx-cheetah'); ?>
                    </span>
                </div>
            </form>
        </div>

    <?php else : ?>
        <!-- Comparison Results -->
        <div class="itx-card">
            <div class="itx-card-header">
                <h2><?php esc_html_e('Comparison Results', 'itx-cheetah'); ?></h2>
                <div class="itx-export-buttons">
                    <?php
                    $scan_ids = array_column($scans_to_compare, 'id');
                    ?>
                    <a href="<?php echo esc_url(ITX_Cheetah_Reports::get_export_url('csv', array('export_type' => 'comparison', 'scan_ids' => $scan_ids))); ?>" class="button button-small">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php esc_html_e('Export CSV', 'itx-cheetah'); ?>
                    </a>
                    <a href="<?php echo esc_url(ITX_Cheetah_Reports::get_export_url('json', array('export_type' => 'comparison', 'scan_ids' => $scan_ids))); ?>" class="button button-small">
                        <span class="dashicons dashicons-media-code"></span>
                        <?php esc_html_e('Export JSON', 'itx-cheetah'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-compare')); ?>" class="button button-small">
                        <?php esc_html_e('New Comparison', 'itx-cheetah'); ?>
                    </a>
                </div>
            </div>

            <!-- Comparison Chart -->
            <div class="itx-comparison-chart">
                <canvas id="itx-comparison-chart" height="300"></canvas>
            </div>
        </div>

        <!-- Comparison Table -->
        <div class="itx-card">
            <h2><?php esc_html_e('Detailed Comparison', 'itx-cheetah'); ?></h2>

            <table class="itx-table itx-comparison-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Metric', 'itx-cheetah'); ?></th>
                        <?php foreach ($scans_to_compare as $index => $scan) : ?>
                            <th>
                                <?php
                                printf(
                                    esc_html__('Scan #%d', 'itx-cheetah'),
                                    $index + 1
                                );
                                ?>
                                <br>
                                <small><?php echo esc_html(date_i18n('M j, Y', strtotime($scan['created_at']))); ?></small>
                            </th>
                        <?php endforeach; ?>
                        <th><?php esc_html_e('Change', 'itx-cheetah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('URL', 'itx-cheetah'); ?></strong></td>
                        <?php foreach ($scans_to_compare as $scan) : ?>
                            <td>
                                <a href="<?php echo esc_url($scan['url']); ?>" target="_blank" title="<?php echo esc_attr($scan['url']); ?>">
                                    <?php echo esc_html(wp_trim_words($scan['url'], 4, '...')); ?>
                                </a>
                            </td>
                        <?php endforeach; ?>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Total Nodes', 'itx-cheetah'); ?></strong></td>
                        <?php foreach ($scans_to_compare as $scan) : ?>
                            <td><?php echo esc_html(number_format_i18n($scan['total_nodes'])); ?></td>
                        <?php endforeach; ?>
                        <td>
                            <?php
                            $first = reset($scans_to_compare);
                            $last = end($scans_to_compare);
                            $node_change = $last['total_nodes'] - $first['total_nodes'];
                            $change_class = $node_change > 0 ? 'negative' : ($node_change < 0 ? 'positive' : '');
                            $change_icon = $node_change > 0 ? '↑' : ($node_change < 0 ? '↓' : '→');
                            ?>
                            <span class="itx-change <?php echo esc_attr($change_class); ?>">
                                <?php echo esc_html($change_icon . ' ' . number_format_i18n(abs($node_change))); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Max Depth', 'itx-cheetah'); ?></strong></td>
                        <?php foreach ($scans_to_compare as $scan) : ?>
                            <td><?php echo esc_html($scan['max_depth']); ?></td>
                        <?php endforeach; ?>
                        <td>
                            <?php
                            $depth_change = $last['max_depth'] - $first['max_depth'];
                            $change_class = $depth_change > 0 ? 'negative' : ($depth_change < 0 ? 'positive' : '');
                            $change_icon = $depth_change > 0 ? '↑' : ($depth_change < 0 ? '↓' : '→');
                            ?>
                            <span class="itx-change <?php echo esc_attr($change_class); ?>">
                                <?php echo esc_html($change_icon . ' ' . abs($depth_change)); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Performance Score', 'itx-cheetah'); ?></strong></td>
                        <?php foreach ($scans_to_compare as $scan) : ?>
                            <td>
                                <?php echo ITX_Cheetah_Admin::get_status_badge($scan['performance_score']); ?>
                                <?php echo esc_html($scan['performance_score']); ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <?php
                            $score_change = $last['performance_score'] - $first['performance_score'];
                            $change_class = $score_change > 0 ? 'positive' : ($score_change < 0 ? 'negative' : '');
                            $change_icon = $score_change > 0 ? '↑' : ($score_change < 0 ? '↓' : '→');
                            ?>
                            <span class="itx-change <?php echo esc_attr($change_class); ?>">
                                <?php echo esc_html($change_icon . ' ' . abs($score_change)); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Scan Time', 'itx-cheetah'); ?></strong></td>
                        <?php foreach ($scans_to_compare as $scan) : ?>
                            <td><?php echo esc_html($scan['scan_time'] . 's'); ?></td>
                        <?php endforeach; ?>
                        <td>-</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Individual Scan Links -->
        <div class="itx-card">
            <h2><?php esc_html_e('View Individual Reports', 'itx-cheetah'); ?></h2>
            <div class="itx-scan-links">
                <?php foreach ($scans_to_compare as $index => $scan) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $scan['id'])); ?>" class="button">
                        <?php printf(esc_html__('Scan #%d - %s', 'itx-cheetah'), $index + 1, date_i18n('M j, Y', strtotime($scan['created_at']))); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('itx-comparison-chart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo wp_json_encode(array_map(function($s) {
                            return date_i18n('M j', strtotime($s['created_at']));
                        }, $scans_to_compare)); ?>,
                        datasets: [
                            {
                                label: '<?php esc_html_e('Total Nodes', 'itx-cheetah'); ?>',
                                data: <?php echo wp_json_encode(array_column($scans_to_compare, 'total_nodes')); ?>,
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                yAxisID: 'y',
                                tension: 0.3
                            },
                            {
                                label: '<?php esc_html_e('Performance Score', 'itx-cheetah'); ?>',
                                data: <?php echo wp_json_encode(array_column($scans_to_compare, 'performance_score')); ?>,
                                borderColor: '#22c55e',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                yAxisID: 'y1',
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false,
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
        });
        </script>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.itx-compare-checkbox');
    const compareButton = document.getElementById('itx-compare-button');

    function updateCompareButton() {
        const checked = document.querySelectorAll('.itx-compare-checkbox:checked').length;
        compareButton.disabled = checked < 2 || checked > 5;
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateCompareButton);
    });
});
</script>
