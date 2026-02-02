<?php
/**
 * Core Web Vitals Detailed Report
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

$scan_id = isset($_GET['scan_id']) ? intval($_GET['scan_id']) : 0;

if (!$scan_id) {
    wp_die(__('Invalid scan ID.', 'itx-cheetah'));
}

$vitals = itx_cheetah()->vitals;
$report = $vitals ? $vitals->generate_vitals_report($scan_id) : null;

if (!$report) {
    wp_die(__('Vitals report not found.', 'itx-cheetah'));
}

$scan = itx_cheetah()->database->get_scan($scan_id);
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <span class="dashicons dashicons-performance"></span>
        <?php esc_html_e('Core Web Vitals Report', 'itx-cheetah'); ?>
    </h1>

    <div class="itx-breadcrumb">
        <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah')); ?>">
            <?php esc_html_e('Dashboard', 'itx-cheetah'); ?>
        </a> &gt;
        <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-vitals')); ?>">
            <?php esc_html_e('Core Web Vitals', 'itx-cheetah'); ?>
        </a> &gt;
        <?php esc_html_e('Report', 'itx-cheetah'); ?>
    </div>

    <!-- Overall Score -->
    <div class="itx-card">
        <h2><?php esc_html_e('Overall Score', 'itx-cheetah'); ?></h2>
        <div class="itx-score-display">
            <div class="itx-score-circle-large">
                <?php
                $overall_score = $report['overall_score'];
                $score_class = $overall_score >= 80 ? 'good' : ($overall_score >= 50 ? 'warning' : 'critical');
                ?>
                <div class="itx-score-value-large itx-score-<?php echo esc_attr($score_class); ?>">
                    <?php echo esc_html($overall_score); ?>
                </div>
            </div>
            <div class="itx-score-breakdown">
                <p><strong><?php esc_html_e('URL:', 'itx-cheetah'); ?></strong> 
                    <a href="<?php echo esc_url($scan['url'] ?? ''); ?>" target="_blank">
                        <?php echo esc_html($scan['url'] ?? ''); ?>
                    </a>
                </p>
                <p><strong><?php esc_html_e('Scan Date:', 'itx-cheetah'); ?></strong> 
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($scan['created_at'] ?? 'now'))); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- LCP Section -->
    <div class="itx-card">
        <h2>
            <?php esc_html_e('Largest Contentful Paint (LCP)', 'itx-cheetah'); ?>
            <span class="itx-badge itx-badge-<?php echo esc_attr($report['lcp']['status']); ?>">
                <?php echo esc_html(ucfirst(str_replace('-', ' ', $report['lcp']['status']))); ?>
            </span>
        </h2>

        <div class="itx-metric-details">
            <div class="itx-metric-item">
                <strong><?php esc_html_e('Score:', 'itx-cheetah'); ?></strong>
                <span class="itx-metric-value-large">
                    <?php echo esc_html(number_format($report['lcp']['score'], 2)); ?>s
                </span>
            </div>
            <div class="itx-metric-item">
                <strong><?php esc_html_e('LCP Element:', 'itx-cheetah'); ?></strong>
                <?php echo esc_html($report['lcp']['element']); ?> 
                (<?php echo esc_html($report['lcp']['element_type']); ?>)
            </div>
            <div class="itx-metric-item">
                <strong><?php esc_html_e('Load Time:', 'itx-cheetah'); ?></strong>
                <?php echo esc_html(number_format($report['lcp']['load_time'], 2)); ?>s
            </div>
            <?php if ($report['lcp']['ttfb_impact']): ?>
            <div class="itx-metric-item">
                <strong><?php esc_html_e('TTFB Impact:', 'itx-cheetah'); ?></strong>
                <?php echo esc_html(number_format($report['lcp']['ttfb_impact'], 1)); ?>%
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($report['lcp']['issues'])): ?>
        <div class="itx-issues-section">
            <h3><?php esc_html_e('Issues Detected', 'itx-cheetah'); ?></h3>
            <ul>
                <?php foreach ($report['lcp']['issues'] as $issue): ?>
                <li class="itx-issue itx-issue-<?php echo esc_attr($issue['severity']); ?>">
                    <?php echo esc_html($issue['message']); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($report['lcp']['recommendations'])): ?>
        <div class="itx-recommendations-section">
            <h3><?php esc_html_e('Recommendations', 'itx-cheetah'); ?></h3>
            <ul>
                <?php foreach ($report['lcp']['recommendations'] as $rec): ?>
                <li><?php echo esc_html($rec); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- CLS Section -->
    <div class="itx-card">
        <h2>
            <?php esc_html_e('Cumulative Layout Shift (CLS)', 'itx-cheetah'); ?>
            <span class="itx-badge itx-badge-<?php echo esc_attr($report['cls']['status']); ?>">
                <?php echo esc_html(ucfirst(str_replace('-', ' ', $report['cls']['status']))); ?>
            </span>
        </h2>

        <div class="itx-metric-details">
            <div class="itx-metric-item">
                <strong><?php esc_html_e('Score:', 'itx-cheetah'); ?></strong>
                <span class="itx-metric-value-large">
                    <?php echo esc_html(number_format($report['cls']['score'], 3)); ?>
                </span>
            </div>
            <div class="itx-metric-item">
                <strong><?php esc_html_e('Layout Shifts:', 'itx-cheetah'); ?></strong>
                <?php echo esc_html($report['cls']['shifts_count']); ?>
            </div>
        </div>

        <?php if (!empty($report['cls']['issues'])): ?>
        <div class="itx-issues-section">
            <h3><?php esc_html_e('Issues Detected', 'itx-cheetah'); ?></h3>
            <ul>
                <?php foreach ($report['cls']['issues'] as $issue): ?>
                <li class="itx-issue itx-issue-<?php echo esc_attr($issue['severity']); ?>">
                    <?php echo esc_html($issue['message']); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($report['cls']['recommendations'])): ?>
        <div class="itx-recommendations-section">
            <h3><?php esc_html_e('Recommendations', 'itx-cheetah'); ?></h3>
            <ul>
                <?php foreach ($report['cls']['recommendations'] as $rec): ?>
                <li><?php echo esc_html($rec); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- INP Section -->
    <div class="itx-card">
        <h2>
            <?php esc_html_e('Interaction to Next Paint (INP)', 'itx-cheetah'); ?>
            <span class="itx-badge itx-badge-<?php echo esc_attr($report['inp']['status']); ?>">
                <?php echo esc_html(ucfirst(str_replace('-', ' ', $report['inp']['status']))); ?>
            </span>
        </h2>

        <div class="itx-metric-details">
            <div class="itx-metric-item">
                <strong><?php esc_html_e('INP Score:', 'itx-cheetah'); ?></strong>
                <span class="itx-metric-value-large">
                    <?php echo esc_html(number_format($report['inp']['score'], 0)); ?>ms
                </span>
            </div>
            <?php if ($report['inp']['fid_score']): ?>
            <div class="itx-metric-item">
                <strong><?php esc_html_e('FID Score:', 'itx-cheetah'); ?></strong>
                <?php echo esc_html(number_format($report['inp']['fid_score'], 0)); ?>ms
            </div>
            <?php endif; ?>
            <div class="itx-metric-item">
                <strong><?php esc_html_e('Long Tasks:', 'itx-cheetah'); ?></strong>
                <?php echo esc_html($report['inp']['long_tasks']); ?>
            </div>
        </div>

        <?php if (!empty($report['inp']['issues'])): ?>
        <div class="itx-issues-section">
            <h3><?php esc_html_e('Issues Detected', 'itx-cheetah'); ?></h3>
            <ul>
                <?php foreach ($report['inp']['issues'] as $issue): ?>
                <li class="itx-issue itx-issue-<?php echo esc_attr($issue['severity']); ?>">
                    <?php echo esc_html($issue['message']); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($report['inp']['recommendations'])): ?>
        <div class="itx-recommendations-section">
            <h3><?php esc_html_e('Recommendations', 'itx-cheetah'); ?></h3>
            <ul>
                <?php foreach ($report['inp']['recommendations'] as $rec): ?>
                <li><?php echo esc_html($rec); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="itx-card">
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-vitals')); ?>" class="button">
                <?php esc_html_e('Back to Vitals Dashboard', 'itx-cheetah'); ?>
            </a>
            <?php if ($scan): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $scan_id)); ?>" class="button">
                <?php esc_html_e('View DOM Analysis Report', 'itx-cheetah'); ?>
            </a>
            <?php endif; ?>
        </p>
    </div>
</div>
