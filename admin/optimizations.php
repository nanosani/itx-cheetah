<?php
/**
 * Optimizations page template
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <span class="dashicons dashicons-performance"></span>
        <?php esc_html_e('Optimization Guide', 'itx-cheetah'); ?>
    </h1>

    <div class="itx-row">
        <div class="itx-col-8">
            <!-- Overview Stats -->
            <div class="itx-card">
                <h2><?php esc_html_e('Your Site Overview', 'itx-cheetah'); ?></h2>

                <?php if ($stats['total_scans'] > 0) : ?>
                    <div class="itx-overview-stats">
                        <div class="itx-overview-stat">
                            <div class="itx-overview-circle <?php echo $stats['avg_score'] >= 80 ? 'good' : ($stats['avg_score'] >= 50 ? 'warning' : 'critical'); ?>">
                                <span class="itx-overview-value"><?php echo esc_html($stats['avg_score']); ?></span>
                            </div>
                            <span class="itx-overview-label"><?php esc_html_e('Avg. Score', 'itx-cheetah'); ?></span>
                        </div>
                        <div class="itx-overview-details">
                            <p>
                                <?php
                                printf(
                                    esc_html__('Based on %d scans, your site has an average DOM performance score of %d out of 100.', 'itx-cheetah'),
                                    $stats['total_scans'],
                                    $stats['avg_score']
                                );
                                ?>
                            </p>
                            <ul>
                                <li><span class="itx-badge itx-badge-good"><?php echo esc_html($stats['good_count']); ?></span> <?php esc_html_e('pages performing well', 'itx-cheetah'); ?></li>
                                <li><span class="itx-badge itx-badge-warning"><?php echo esc_html($stats['warning_count']); ?></span> <?php esc_html_e('pages need improvement', 'itx-cheetah'); ?></li>
                                <li><span class="itx-badge itx-badge-critical"><?php echo esc_html($stats['critical_count']); ?></span> <?php esc_html_e('pages need urgent attention', 'itx-cheetah'); ?></li>
                            </ul>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="itx-empty-state">
                        <span class="dashicons dashicons-search"></span>
                        <p><?php esc_html_e('No scans yet. Scan some pages to get optimization recommendations.', 'itx-cheetah'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah')); ?>" class="button button-primary">
                            <?php esc_html_e('Start Scanning', 'itx-cheetah'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Common Issues -->
            <?php if (!empty($common_issues)) : ?>
            <div class="itx-card">
                <h2><?php esc_html_e('Common Issues on Your Site', 'itx-cheetah'); ?></h2>

                <div class="itx-issues-list">
                    <?php foreach ($common_issues as $issue) : ?>
                        <div class="itx-issue itx-issue-<?php echo esc_attr($issue['severity']); ?>">
                            <div class="itx-issue-header">
                                <span class="itx-issue-icon">
                                    <?php if ($issue['severity'] === 'critical') : ?>
                                        <span class="dashicons dashicons-warning"></span>
                                    <?php elseif ($issue['severity'] === 'warning') : ?>
                                        <span class="dashicons dashicons-flag"></span>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-info"></span>
                                    <?php endif; ?>
                                </span>
                                <h3><?php echo esc_html($issue['title']); ?></h3>
                                <span class="itx-issue-count">
                                    <?php
                                    printf(
                                        esc_html(_n('%d page affected', '%d pages affected', $issue['count'], 'itx-cheetah')),
                                        $issue['count']
                                    );
                                    ?>
                                </span>
                            </div>
                            <p><?php echo esc_html($issue['description']); ?></p>
                            <div class="itx-issue-solution">
                                <strong><?php esc_html_e('How to fix:', 'itx-cheetah'); ?></strong>
                                <ul>
                                    <?php foreach ($issue['solutions'] as $solution) : ?>
                                        <li><?php echo esc_html($solution); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Optimization Tips -->
            <div class="itx-card">
                <h2><?php esc_html_e('DOM Optimization Best Practices', 'itx-cheetah'); ?></h2>

                <div class="itx-tips-accordion">
                    <div class="itx-tip-item">
                        <button class="itx-tip-header" type="button">
                            <span class="dashicons dashicons-editor-code"></span>
                            <span><?php esc_html_e('Reduce DOM Node Count', 'itx-cheetah'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="itx-tip-content">
                            <p><?php esc_html_e('A large DOM can increase memory usage, cause longer style calculations, and produce costly layout reflows.', 'itx-cheetah'); ?></p>
                            <h4><?php esc_html_e('Recommendations:', 'itx-cheetah'); ?></h4>
                            <ul>
                                <li><?php esc_html_e('Use CSS pseudo-elements (::before, ::after) instead of extra elements for decorative content', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Avoid using page builders that generate excessive wrapper elements', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Implement lazy loading for content below the fold', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Use virtual scrolling for long lists', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Remove hidden elements that aren\'t needed', 'itx-cheetah'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <div class="itx-tip-item">
                        <button class="itx-tip-header" type="button">
                            <span class="dashicons dashicons-networking"></span>
                            <span><?php esc_html_e('Flatten DOM Depth', 'itx-cheetah'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="itx-tip-content">
                            <p><?php esc_html_e('Deep DOM trees can cause performance issues with selector matching and layout calculations.', 'itx-cheetah'); ?></p>
                            <h4><?php esc_html_e('Recommendations:', 'itx-cheetah'); ?></h4>
                            <ul>
                                <li><?php esc_html_e('Use CSS Grid and Flexbox to reduce the need for nested containers', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Avoid deeply nested component structures', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Review theme and page builder output for unnecessary nesting', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Use semantic HTML elements appropriately', 'itx-cheetah'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <div class="itx-tip-item">
                        <button class="itx-tip-header" type="button">
                            <span class="dashicons dashicons-admin-plugins"></span>
                            <span><?php esc_html_e('Optimize Plugin Usage', 'itx-cheetah'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="itx-tip-content">
                            <p><?php esc_html_e('Plugins can add significant DOM complexity to your pages.', 'itx-cheetah'); ?></p>
                            <h4><?php esc_html_e('Recommendations:', 'itx-cheetah'); ?></h4>
                            <ul>
                                <li><?php esc_html_e('Audit plugins for DOM impact by scanning before and after activation', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Choose lightweight alternatives for heavy plugins', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Disable plugin features you don\'t need', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Consider custom solutions for simple functionality', 'itx-cheetah'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <div class="itx-tip-item">
                        <button class="itx-tip-header" type="button">
                            <span class="dashicons dashicons-format-image"></span>
                            <span><?php esc_html_e('Optimize Images & Media', 'itx-cheetah'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="itx-tip-content">
                            <p><?php esc_html_e('Images and media embeds can add considerable DOM complexity.', 'itx-cheetah'); ?></p>
                            <h4><?php esc_html_e('Recommendations:', 'itx-cheetah'); ?></h4>
                            <ul>
                                <li><?php esc_html_e('Implement native lazy loading with loading="lazy"', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Use SVG sprites instead of inline SVGs', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Consider facade patterns for video embeds', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Limit the number of images per page', 'itx-cheetah'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <div class="itx-tip-item">
                        <button class="itx-tip-header" type="button">
                            <span class="dashicons dashicons-editor-table"></span>
                            <span><?php esc_html_e('Optimize Tables & Lists', 'itx-cheetah'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="itx-tip-content">
                            <p><?php esc_html_e('Large tables and lists significantly increase DOM node count.', 'itx-cheetah'); ?></p>
                            <h4><?php esc_html_e('Recommendations:', 'itx-cheetah'); ?></h4>
                            <ul>
                                <li><?php esc_html_e('Implement pagination for large data sets', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Use virtual scrolling for long lists', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Load table data dynamically via AJAX', 'itx-cheetah'); ?></li>
                                <li><?php esc_html_e('Consider accordion patterns for collapsible content', 'itx-cheetah'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="itx-col-4">
            <!-- Google Recommendations -->
            <div class="itx-card">
                <h2><?php esc_html_e('Google Guidelines', 'itx-cheetah'); ?></h2>
                <div class="itx-guidelines">
                    <div class="itx-guideline">
                        <span class="itx-guideline-value">&lt; 1,500</span>
                        <span class="itx-guideline-label"><?php esc_html_e('Total DOM Nodes', 'itx-cheetah'); ?></span>
                    </div>
                    <div class="itx-guideline">
                        <span class="itx-guideline-value">&lt; 32</span>
                        <span class="itx-guideline-label"><?php esc_html_e('Maximum Depth', 'itx-cheetah'); ?></span>
                    </div>
                    <div class="itx-guideline">
                        <span class="itx-guideline-value">&lt; 60</span>
                        <span class="itx-guideline-label"><?php esc_html_e('Max Children per Node', 'itx-cheetah'); ?></span>
                    </div>
                </div>
                <p class="description">
                    <?php esc_html_e('These are Google Lighthouse recommendations for optimal DOM performance.', 'itx-cheetah'); ?>
                </p>
                <a href="https://developer.chrome.com/docs/lighthouse/performance/dom-size/" target="_blank" rel="noopener" class="button">
                    <?php esc_html_e('Learn More', 'itx-cheetah'); ?>
                    <span class="dashicons dashicons-external"></span>
                </a>
            </div>

            <!-- Critical Pages -->
            <?php if (!empty($critical_pages)) : ?>
            <div class="itx-card itx-alert-card">
                <h2>
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Priority Pages', 'itx-cheetah'); ?>
                </h2>
                <p class="description"><?php esc_html_e('These pages need optimization the most:', 'itx-cheetah'); ?></p>
                <ul class="itx-priority-list">
                    <?php foreach (array_slice($critical_pages, 0, 10) as $page) : ?>
                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $page['id'])); ?>">
                                <?php echo esc_html(wp_trim_words($page['url'], 4, '...')); ?>
                            </a>
                            <div class="itx-priority-meta">
                                <span class="itx-badge itx-badge-critical"><?php echo esc_html($page['performance_score']); ?></span>
                                <span><?php echo esc_html(number_format_i18n($page['total_nodes'])); ?> <?php esc_html_e('nodes', 'itx-cheetah'); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Tools -->
            <div class="itx-card">
                <h2><?php esc_html_e('Useful Tools', 'itx-cheetah'); ?></h2>
                <ul class="itx-tools-list">
                    <li>
                        <a href="https://pagespeed.web.dev/" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-performance"></span>
                            <?php esc_html_e('PageSpeed Insights', 'itx-cheetah'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://search.google.com/search-console" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-admin-site-alt3"></span>
                            <?php esc_html_e('Google Search Console', 'itx-cheetah'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://web.dev/measure/" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-chart-line"></span>
                            <?php esc_html_e('Web.dev Measure', 'itx-cheetah'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
/* Overview Stats */
.itx-overview-stats {
    display: flex;
    align-items: center;
    gap: 30px;
}

.itx-overview-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.itx-overview-circle.good {
    background: rgba(34, 197, 94, 0.1);
    border: 4px solid var(--itx-success);
}

.itx-overview-circle.warning {
    background: rgba(245, 158, 11, 0.1);
    border: 4px solid var(--itx-warning);
}

.itx-overview-circle.critical {
    background: rgba(239, 68, 68, 0.1);
    border: 4px solid var(--itx-danger);
}

.itx-overview-value {
    font-size: 32px;
    font-weight: 700;
}

.itx-overview-label {
    display: block;
    text-align: center;
    font-size: 12px;
    color: var(--itx-gray-500);
    margin-top: 8px;
}

.itx-overview-details {
    flex: 1;
}

.itx-overview-details ul {
    margin: 15px 0 0;
    padding: 0;
    list-style: none;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.itx-overview-details li {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Issues List */
.itx-issues-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.itx-issue {
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid;
}

.itx-issue-critical {
    background: rgba(239, 68, 68, 0.05);
    border-color: var(--itx-danger);
}

.itx-issue-warning {
    background: rgba(245, 158, 11, 0.05);
    border-color: var(--itx-warning);
}

.itx-issue-info {
    background: rgba(59, 130, 246, 0.05);
    border-color: var(--itx-primary);
}

.itx-issue-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.itx-issue-header h3 {
    margin: 0;
    flex: 1;
    font-size: 15px;
}

.itx-issue-count {
    font-size: 12px;
    padding: 3px 10px;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 20px;
    color: var(--itx-gray-600);
}

.itx-issue-icon .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.itx-issue-critical .itx-issue-icon {
    color: var(--itx-danger);
}

.itx-issue-warning .itx-issue-icon {
    color: var(--itx-warning);
}

.itx-issue-info .itx-issue-icon {
    color: var(--itx-primary);
}

.itx-issue p {
    margin: 0 0 15px;
    color: var(--itx-gray-600);
}

.itx-issue-solution {
    background: rgba(255, 255, 255, 0.5);
    padding: 12px 15px;
    border-radius: 6px;
}

.itx-issue-solution strong {
    display: block;
    margin-bottom: 8px;
    color: var(--itx-gray-700);
}

.itx-issue-solution ul {
    margin: 0;
    padding-left: 20px;
}

.itx-issue-solution li {
    margin-bottom: 5px;
    color: var(--itx-gray-600);
}

/* Tips Accordion */
.itx-tips-accordion {
    border: 1px solid var(--itx-gray-200);
    border-radius: 8px;
    overflow: hidden;
}

.itx-tip-item {
    border-bottom: 1px solid var(--itx-gray-200);
}

.itx-tip-item:last-child {
    border-bottom: none;
}

.itx-tip-header {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 15px 20px;
    background: var(--itx-gray-50);
    border: none;
    cursor: pointer;
    text-align: left;
    font-size: 14px;
    font-weight: 600;
    color: var(--itx-gray-800);
    transition: background 0.2s;
}

.itx-tip-header:hover {
    background: var(--itx-gray-100);
}

.itx-tip-header .dashicons:first-child {
    color: var(--itx-primary);
}

.itx-tip-header span:nth-child(2) {
    flex: 1;
}

.itx-tip-header .dashicons:last-child {
    transition: transform 0.3s;
}

.itx-tip-item.active .itx-tip-header .dashicons:last-child {
    transform: rotate(180deg);
}

.itx-tip-content {
    display: none;
    padding: 20px;
    background: #fff;
}

.itx-tip-item.active .itx-tip-content {
    display: block;
}

.itx-tip-content p {
    margin: 0 0 15px;
    color: var(--itx-gray-600);
}

.itx-tip-content h4 {
    margin: 0 0 10px;
    font-size: 13px;
    color: var(--itx-gray-700);
}

.itx-tip-content ul {
    margin: 0;
    padding-left: 20px;
}

.itx-tip-content li {
    margin-bottom: 8px;
    color: var(--itx-gray-600);
}

/* Guidelines */
.itx-guidelines {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 15px;
}

.itx-guideline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 15px;
    background: var(--itx-gray-50);
    border-radius: 8px;
}

.itx-guideline-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--itx-success);
}

.itx-guideline-label {
    font-size: 13px;
    color: var(--itx-gray-600);
}

/* Priority List */
.itx-priority-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.itx-priority-list li {
    padding: 12px 0;
    border-bottom: 1px solid var(--itx-gray-100);
}

.itx-priority-list li:last-child {
    border-bottom: none;
}

.itx-priority-list a {
    display: block;
    color: var(--itx-gray-800);
    text-decoration: none;
    margin-bottom: 5px;
}

.itx-priority-list a:hover {
    color: var(--itx-primary);
}

.itx-priority-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: var(--itx-gray-500);
}

/* Tools List */
.itx-tools-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.itx-tools-list li {
    margin-bottom: 10px;
}

.itx-tools-list a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: var(--itx-gray-50);
    border-radius: 8px;
    text-decoration: none;
    color: var(--itx-gray-700);
    transition: background 0.2s;
}

.itx-tools-list a:hover {
    background: var(--itx-gray-100);
    color: var(--itx-primary);
}

.itx-tools-list .dashicons {
    color: var(--itx-primary);
}

@media (max-width: 782px) {
    .itx-overview-stats {
        flex-direction: column;
        text-align: center;
    }

    .itx-overview-details ul {
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Accordion functionality
    document.querySelectorAll('.itx-tip-header').forEach(function(header) {
        header.addEventListener('click', function() {
            const item = this.closest('.itx-tip-item');
            const isActive = item.classList.contains('active');

            // Close all
            document.querySelectorAll('.itx-tip-item').forEach(function(i) {
                i.classList.remove('active');
            });

            // Open clicked if it wasn't active
            if (!isActive) {
                item.classList.add('active');
            }
        });
    });

    // Open first item by default
    const firstItem = document.querySelector('.itx-tip-item');
    if (firstItem) {
        firstItem.classList.add('active');
    }
});
</script>
