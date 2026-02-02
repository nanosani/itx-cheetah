<?php
/**
 * Core Web Vitals Dashboard
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

$vitals = itx_cheetah()->vitals;
$stats = $vitals ? $vitals->get_vitals_statistics() : array();
$recent_vitals = $vitals ? $vitals->get_vitals_history(0, '', 7) : array();
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <span class="dashicons dashicons-performance"></span>
        <?php esc_html_e('Core Web Vitals', 'itx-cheetah'); ?>
    </h1>

    <!-- Manual Test Section -->
    <div class="itx-card itx-scan-card">
        <h2><?php esc_html_e('Manual Vitals Test', 'itx-cheetah'); ?></h2>
        <p class="description"><?php esc_html_e('Enter a URL from your site to test its Core Web Vitals metrics. The page will open in a new tab and collect metrics automatically.', 'itx-cheetah'); ?></p>

        <div class="itx-scan-form">
            <input type="url"
                   id="itx-vitals-test-url"
                   class="itx-scan-input"
                   placeholder="<?php esc_attr_e('https://yoursite.com/page-to-test', 'itx-cheetah'); ?>"
                   value="<?php echo esc_url(home_url('/')); ?>">

            <button type="button" id="itx-vitals-test-button" class="button button-primary button-hero">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e('Test URL', 'itx-cheetah'); ?>
            </button>
        </div>

        <div id="itx-vitals-test-progress" class="itx-scan-progress" style="display: none;">
            <div class="itx-spinner"></div>
            <span class="itx-scan-status"><?php esc_html_e('Opening page for testing...', 'itx-cheetah'); ?></span>
        </div>

        <div id="itx-vitals-test-result" class="itx-scan-result" style="display: none;"></div>
    </div>

    <?php if (empty($stats) || $stats['total_audits'] === 0): ?>
        <div class="itx-card">
            <h2><?php esc_html_e('No Data Collected Yet', 'itx-cheetah'); ?></h2>
            <p><?php esc_html_e('No Core Web Vitals data collected yet. Visit your site pages to start collecting metrics.', 'itx-cheetah'); ?></p>
            
            <div class="itx-debug-info" style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3><?php esc_html_e('Troubleshooting', 'itx-cheetah'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Make sure you visit pages on the frontend (not admin area)', 'itx-cheetah'); ?></li>
                    <li><?php esc_html_e('Open browser console (F12) and check for any JavaScript errors', 'itx-cheetah'); ?></li>
                    <li><?php esc_html_e('Look for "ITX Cheetah" messages in the console', 'itx-cheetah'); ?></li>
                    <li><?php esc_html_e('Verify the script is loaded by checking:', 'itx-cheetah'); ?>
                        <code style="display: block; margin-top: 5px; padding: 5px; background: white;">
                            <?php echo esc_html(ITX_CHEETAH_PLUGIN_URL . 'assets/js/vitals-collector.js'); ?>
                        </code>
                    </li>
                    <li><?php esc_html_e('Check Network tab in browser DevTools for AJAX requests to admin-ajax.php', 'itx-cheetah'); ?></li>
                </ol>
                <p>
                    <strong><?php esc_html_e('Test URL:', 'itx-cheetah'); ?></strong> 
                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank">
                        <?php echo esc_url(home_url('/')); ?>
                    </a>
                </p>
            </div>
        </div>
    <?php else: ?>

        <!-- Overall Score Card -->
        <div class="itx-stats-grid">
            <div class="itx-stat-card itx-stat-card-large">
                <h3><?php esc_html_e('Overall Vitals Score', 'itx-cheetah'); ?></h3>
                <div class="itx-score-circle">
                    <?php
                    $overall_score = 0;
                    if ($stats['total_audits'] > 0) {
                        $lcp_score = $stats['avg_lcp'] < 2.5 ? 100 : ($stats['avg_lcp'] < 4.0 ? 50 : 0);
                        $cls_score = $stats['avg_cls'] < 0.1 ? 100 : ($stats['avg_cls'] < 0.25 ? 50 : 0);
                        $inp_score = $stats['avg_inp'] < 200 ? 100 : ($stats['avg_inp'] < 500 ? 50 : 0);
                        $overall_score = round(($lcp_score * 0.4) + ($cls_score * 0.3) + ($inp_score * 0.3));
                    }
                    $score_class = $overall_score >= 80 ? 'good' : ($overall_score >= 50 ? 'warning' : 'critical');
                    ?>
                    <div class="itx-score-value itx-score-<?php echo esc_attr($score_class); ?>">
                        <?php echo esc_html($overall_score); ?>
                    </div>
                </div>
                <p class="itx-score-label"><?php esc_html_e('Weighted Average', 'itx-cheetah'); ?></p>
            </div>

            <!-- LCP Card -->
            <div class="itx-stat-card">
                <h3><?php esc_html_e('LCP', 'itx-cheetah'); ?></h3>
                <div class="itx-metric-value">
                    <?php
                    $lcp_value = $stats['avg_lcp'] ?? 0;
                    $lcp_status = $lcp_value < 2.5 ? 'good' : ($lcp_value < 4.0 ? 'warning' : 'poor');
                    ?>
                    <span class="itx-metric-number itx-metric-<?php echo esc_attr($lcp_status); ?>">
                        <?php echo esc_html(number_format($lcp_value, 2)); ?>s
                    </span>
                </div>
                <p class="itx-metric-label">
                    <?php
                    $lcp_good = $stats['lcp_distribution']['good'] ?? 0;
                    $lcp_total = $stats['total_audits'] ?? 1;
                    echo esc_html(sprintf(__('%d of %d pages are good', 'itx-cheetah'), $lcp_good, $lcp_total));
                    ?>
                </p>
            </div>

            <!-- CLS Card -->
            <div class="itx-stat-card">
                <h3><?php esc_html_e('CLS', 'itx-cheetah'); ?></h3>
                <div class="itx-metric-value">
                    <?php
                    $cls_value = $stats['avg_cls'] ?? 0;
                    $cls_status = $cls_value < 0.1 ? 'good' : ($cls_value < 0.25 ? 'warning' : 'poor');
                    ?>
                    <span class="itx-metric-number itx-metric-<?php echo esc_attr($cls_status); ?>">
                        <?php echo esc_html(number_format($cls_value, 3)); ?>
                    </span>
                </div>
                <p class="itx-metric-label">
                    <?php
                    $cls_good = $stats['cls_distribution']['good'] ?? 0;
                    echo esc_html(sprintf(__('%d of %d pages are good', 'itx-cheetah'), $cls_good, $lcp_total));
                    ?>
                </p>
            </div>

            <!-- INP Card -->
            <div class="itx-stat-card">
                <h3><?php esc_html_e('INP', 'itx-cheetah'); ?></h3>
                <div class="itx-metric-value">
                    <?php
                    $inp_value = $stats['avg_inp'] ?? 0;
                    $inp_status = $inp_value < 200 ? 'good' : ($inp_value < 500 ? 'warning' : 'poor');
                    ?>
                    <span class="itx-metric-number itx-metric-<?php echo esc_attr($inp_status); ?>">
                        <?php echo esc_html(number_format($inp_value, 0)); ?>ms
                    </span>
                </div>
                <p class="itx-metric-label">
                    <?php
                    $inp_good = $stats['inp_distribution']['good'] ?? 0;
                    echo esc_html(sprintf(__('%d of %d pages are good', 'itx-cheetah'), $inp_good, $lcp_total));
                    ?>
                </p>
            </div>
        </div>

        <!-- Distribution Charts -->
        <div class="itx-card">
            <h2><?php esc_html_e('Metric Distribution', 'itx-cheetah'); ?></h2>
            <div class="itx-charts-grid">
                <div class="itx-chart-container">
                    <h3><?php esc_html_e('LCP Distribution', 'itx-cheetah'); ?></h3>
                    <canvas id="lcp-chart"></canvas>
                </div>
                <div class="itx-chart-container">
                    <h3><?php esc_html_e('CLS Distribution', 'itx-cheetah'); ?></h3>
                    <canvas id="cls-chart"></canvas>
                </div>
                <div class="itx-chart-container">
                    <h3><?php esc_html_e('INP Distribution', 'itx-cheetah'); ?></h3>
                    <canvas id="inp-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Vitals -->
        <?php if (!empty($recent_vitals)): ?>
        <div class="itx-card">
            <h2><?php esc_html_e('Recent Vitals Audits', 'itx-cheetah'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('URL', 'itx-cheetah'); ?></th>
                        <th><?php esc_html_e('LCP', 'itx-cheetah'); ?></th>
                        <th><?php esc_html_e('CLS', 'itx-cheetah'); ?></th>
                        <th><?php esc_html_e('INP', 'itx-cheetah'); ?></th>
                        <th><?php esc_html_e('Date', 'itx-cheetah'); ?></th>
                        <th><?php esc_html_e('Actions', 'itx-cheetah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recent_vitals, 0, 10) as $vital): ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($vital['url']); ?>" target="_blank">
                                <?php echo esc_html(wp_parse_url($vital['url'], PHP_URL_PATH) ?: $vital['url']); ?>
                            </a>
                        </td>
                        <td>
                            <?php
                            $lcp = floatval($vital['lcp_score'] ?? 0);
                            $lcp_class = $lcp < 2.5 ? 'good' : ($lcp < 4.0 ? 'warning' : 'poor');
                            ?>
                            <span class="itx-badge itx-badge-<?php echo esc_attr($lcp_class); ?>">
                                <?php echo esc_html(number_format($lcp, 2)); ?>s
                            </span>
                        </td>
                        <td>
                            <?php
                            $cls = floatval($vital['cls_score'] ?? 0);
                            $cls_class = $cls < 0.1 ? 'good' : ($cls < 0.25 ? 'warning' : 'poor');
                            ?>
                            <span class="itx-badge itx-badge-<?php echo esc_attr($cls_class); ?>">
                                <?php echo esc_html(number_format($cls, 3)); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $inp = floatval($vital['inp_score'] ?? 0);
                            $inp_class = $inp < 200 ? 'good' : ($inp < 500 ? 'warning' : 'poor');
                            ?>
                            <span class="itx-badge itx-badge-<?php echo esc_attr($inp_class); ?>">
                                <?php echo esc_html(number_format($inp, 0)); ?>ms
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html(human_time_diff(strtotime($vital['created_at']), current_time('timestamp')) . ' ago'); ?>
                        </td>
                        <td>
                            <?php if ($vital['scan_id']): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-vitals-report&scan_id=' . $vital['scan_id'])); ?>" class="button button-small">
                                <?php esc_html_e('View Report', 'itx-cheetah'); ?>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Manual test functionality
    $('#itx-vitals-test-button').on('click', function() {
        const url = $('#itx-vitals-test-url').val().trim();
        
        if (!url) {
            alert('<?php echo esc_js(__('Please enter a URL to test.', 'itx-cheetah')); ?>');
            return;
        }
        
        // Validate URL
        try {
            new URL(url);
        } catch (e) {
            alert('<?php echo esc_js(__('Please enter a valid URL.', 'itx-cheetah')); ?>');
            return;
        }
        
        // Check if URL is from this site
        const siteUrl = '<?php echo esc_js(home_url()); ?>';
        if (!url.startsWith(siteUrl)) {
            if (!confirm('<?php echo esc_js(__('This URL is not from your site. Continue anyway?', 'itx-cheetah')); ?>')) {
                return;
            }
        }
        
        $('#itx-vitals-test-progress').show();
        $('#itx-vitals-test-result').hide();
        
        // Open URL in new tab - the vitals collector will run automatically
        const testWindow = window.open(url, '_blank');
        
        if (testWindow) {
            $('#itx-vitals-test-result').html(
                '<div class="notice notice-success"><p>' +
                '<?php echo esc_js(__('Page opened in new tab. Metrics will be collected automatically. Check back here in a few seconds to see the results.', 'itx-cheetah')); ?>' +
                '</p></div>'
            ).show();
            
            // Refresh the page after 10 seconds to show new data
            setTimeout(function() {
                location.reload();
            }, 10000);
        } else {
            $('#itx-vitals-test-result').html(
                '<div class="notice notice-error"><p>' +
                '<?php echo esc_js(__('Popup blocked. Please allow popups for this site and try again.', 'itx-cheetah')); ?>' +
                '</p></div>'
            ).show();
        }
        
        $('#itx-vitals-test-progress').hide();
    });
    
    // Allow Enter key to trigger test
    $('#itx-vitals-test-url').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#itx-vitals-test-button').click();
        }
    });

    <?php if (!empty($stats) && $stats['total_audits'] > 0): ?>
    // LCP Chart
    const lcpCtx = document.getElementById('lcp-chart');
    if (lcpCtx && typeof Chart !== 'undefined') {
        new Chart(lcpCtx, {
            type: 'doughnut',
            data: {
                labels: ['Good', 'Needs Improvement', 'Poor'],
                datasets: [{
                    data: [
                        <?php echo intval($stats['lcp_distribution']['good'] ?? 0); ?>,
                        <?php echo intval($stats['lcp_distribution']['needs_improvement'] ?? 0); ?>,
                        <?php echo intval($stats['lcp_distribution']['poor'] ?? 0); ?>
                    ],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    }

    // CLS Chart
    const clsCtx = document.getElementById('cls-chart');
    if (clsCtx && typeof Chart !== 'undefined') {
        new Chart(clsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Good', 'Needs Improvement', 'Poor'],
                datasets: [{
                    data: [
                        <?php echo intval($stats['cls_distribution']['good'] ?? 0); ?>,
                        <?php echo intval($stats['cls_distribution']['needs_improvement'] ?? 0); ?>,
                        <?php echo intval($stats['cls_distribution']['poor'] ?? 0); ?>
                    ],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    }

    // INP Chart
    const inpCtx = document.getElementById('inp-chart');
    if (inpCtx && typeof Chart !== 'undefined') {
        new Chart(inpCtx, {
            type: 'doughnut',
            data: {
                labels: ['Good', 'Needs Improvement', 'Poor'],
                datasets: [{
                    data: [
                        <?php echo intval($stats['inp_distribution']['good'] ?? 0); ?>,
                        <?php echo intval($stats['inp_distribution']['needs_improvement'] ?? 0); ?>,
                        <?php echo intval($stats['inp_distribution']['poor'] ?? 0); ?>
                    ],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    }
    <?php endif; ?>
});
</script>
