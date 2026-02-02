<?php
/**
 * Actionable Fixes Template
 *
 * Shows detailed, context-aware recommendations with code examples
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get enhanced recommendations
$analyzer = itx_cheetah()->analyzer;
$enhanced_recs = $analyzer->get_enhanced_recommendations($scan);

$status_label = $scan['performance_score'] >= 80 ? 'good' : ($scan['performance_score'] >= 50 ? 'warning' : 'critical');
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $scan['id'])); ?>" class="itx-back-link">
            <span class="dashicons dashicons-arrow-left-alt"></span>
        </a>
        <span class="dashicons dashicons-hammer"></span>
        <?php esc_html_e('Actionable Fixes', 'itx-cheetah'); ?>
    </h1>

    <?php
    // Check if there are any recommendations
    $has_recommendations = !empty($enhanced_recs['high_priority']) ||
                          !empty($enhanced_recs['medium_priority']) ||
                          !empty($enhanced_recs['low_priority']) ||
                          !empty($enhanced_recs['theme_specific']) ||
                          !empty($enhanced_recs['plugin_specific']);

    if (!$has_recommendations) :
    ?>
    <!-- No Recommendations Available -->
    <div class="itx-card">
        <div class="itx-empty-state">
            <span class="dashicons dashicons-yes-alt" style="color: var(--itx-success);"></span>
            <h3><?php esc_html_e('Great job! Your page is well optimized.', 'itx-cheetah'); ?></h3>
            <p><?php esc_html_e('No major issues were detected that require actionable fixes. Your DOM structure appears to be within recommended guidelines.', 'itx-cheetah'); ?></p>
            <p class="description">
                <?php
                printf(
                    esc_html__('Current score: %d | Total nodes: %s | Max depth: %d', 'itx-cheetah'),
                    $scan['performance_score'],
                    number_format($scan['total_nodes']),
                    $scan['max_depth']
                );
                ?>
            </p>
            <div style="margin-top: 20px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $scan['id'])); ?>" class="button button-primary">
                    <?php esc_html_e('View Full Report', 'itx-cheetah'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-optimizations')); ?>" class="button">
                    <?php esc_html_e('View Best Practices', 'itx-cheetah'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php else : ?>

    <!-- Impact Summary -->
    <?php if (!empty($enhanced_recs['impact_summary'])) :
        $impact = $enhanced_recs['impact_summary'];
    ?>
    <div class="itx-info-card">
        <h3>
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e('Estimated Impact Summary', 'itx-cheetah'); ?>
        </h3>
        <div class="itx-impact-grid">
            <div class="itx-impact-item">
                <span class="itx-impact-value"><?php echo esc_html(number_format($impact['estimated_node_reduction'])); ?></span>
                <span class="itx-impact-label"><?php esc_html_e('Nodes to Remove', 'itx-cheetah'); ?></span>
            </div>
            <div class="itx-impact-item">
                <span class="itx-impact-value"><?php echo esc_html($impact['estimated_time_minutes']); ?> <?php esc_html_e('min', 'itx-cheetah'); ?></span>
                <span class="itx-impact-label"><?php esc_html_e('Estimated Time', 'itx-cheetah'); ?></span>
            </div>
            <?php if (!empty($impact['estimated_performance_improvement'])) : ?>
            <div class="itx-impact-item">
                <span class="itx-impact-value itx-text-success">+<?php echo esc_html($impact['estimated_performance_improvement']['lcp']); ?></span>
                <span class="itx-impact-label"><?php esc_html_e('LCP Improvement', 'itx-cheetah'); ?></span>
            </div>
            <div class="itx-impact-item">
                <span class="itx-impact-value itx-text-success">+<?php echo esc_html($impact['estimated_performance_improvement']['fid']); ?></span>
                <span class="itx-impact-label"><?php esc_html_e('FID Improvement', 'itx-cheetah'); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- High Priority Fixes -->
    <?php if (!empty($enhanced_recs['high_priority'])) : ?>
    <div class="itx-card itx-fixes-section">
        <div class="itx-card-header">
            <h2>
                <span class="dashicons dashicons-warning" style="color: var(--itx-danger);"></span>
                <?php esc_html_e('High Impact Fixes', 'itx-cheetah'); ?>
            </h2>
            <span class="itx-badge itx-badge-critical"><?php esc_html_e('35%+ DOM Reduction', 'itx-cheetah'); ?></span>
        </div>

        <div class="itx-accordion" data-multiple="true">
            <?php foreach ($enhanced_recs['high_priority'] as $index => $fix) : ?>
            <div class="itx-accordion-item <?php echo $index === 0 ? 'active' : ''; ?>">
                <div class="itx-accordion-header">
                    <div class="itx-accordion-title">
                        <span class="dashicons dashicons-warning" style="color: var(--itx-danger);"></span>
                        <?php echo esc_html($fix['title']); ?>
                    </div>
                    <div class="itx-fix-meta">
                        <span class="itx-fix-reduction">-<?php echo esc_html(number_format($fix['estimated_reduction'])); ?> <?php esc_html_e('nodes', 'itx-cheetah'); ?></span>
                        <span class="itx-fix-time"><?php echo esc_html($fix['time_to_fix']); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 itx-accordion-toggle"></span>
                    </div>
                </div>
                <div class="itx-accordion-content">
                    <p class="itx-fix-description"><?php echo esc_html($fix['description']); ?></p>

                    <!-- Steps to Fix -->
                    <div class="itx-fix-steps">
                        <h4><?php esc_html_e('Steps to Fix:', 'itx-cheetah'); ?></h4>
                        <ol>
                            <?php foreach ($fix['steps'] as $step) : ?>
                            <li><?php echo esc_html($step); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>

                    <!-- Code Example -->
                    <?php if (!empty($fix['code_example'])) : ?>
                    <div class="itx-code-example">
                        <h4><?php esc_html_e('Code Example:', 'itx-cheetah'); ?></h4>

                        <div class="itx-code-comparison">
                            <div class="itx-code-before">
                                <span class="itx-code-label itx-label-before"><?php esc_html_e('Before', 'itx-cheetah'); ?></span>
                                <pre><code><?php echo esc_html($fix['code_example']['before']); ?></code></pre>
                            </div>

                            <div class="itx-code-arrow">
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </div>

                            <div class="itx-code-after">
                                <span class="itx-code-label itx-label-after"><?php esc_html_e('After', 'itx-cheetah'); ?></span>
                                <pre><code><?php echo esc_html($fix['code_example']['after']); ?></code></pre>
                            </div>
                        </div>

                        <?php if (!empty($fix['code_example']['css_changes'])) : ?>
                        <div class="itx-css-changes">
                            <span class="itx-code-label"><?php esc_html_e('CSS Changes', 'itx-cheetah'); ?></span>
                            <pre><code><?php echo esc_html($fix['code_example']['css_changes']); ?></code></pre>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Medium Priority Fixes -->
    <?php if (!empty($enhanced_recs['medium_priority'])) : ?>
    <div class="itx-card itx-fixes-section">
        <div class="itx-card-header">
            <h2>
                <span class="dashicons dashicons-flag" style="color: var(--itx-warning);"></span>
                <?php esc_html_e('Medium Impact Fixes', 'itx-cheetah'); ?>
            </h2>
            <span class="itx-badge itx-badge-warning"><?php esc_html_e('15-35% DOM Reduction', 'itx-cheetah'); ?></span>
        </div>

        <div class="itx-accordion" data-multiple="true">
            <?php foreach ($enhanced_recs['medium_priority'] as $fix) : ?>
            <div class="itx-accordion-item">
                <div class="itx-accordion-header">
                    <div class="itx-accordion-title">
                        <span class="dashicons dashicons-flag" style="color: var(--itx-warning);"></span>
                        <?php echo esc_html($fix['title']); ?>
                    </div>
                    <div class="itx-fix-meta">
                        <span class="itx-fix-reduction">-<?php echo esc_html(number_format($fix['estimated_reduction'])); ?> <?php esc_html_e('nodes', 'itx-cheetah'); ?></span>
                        <span class="itx-fix-time"><?php echo esc_html($fix['time_to_fix']); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 itx-accordion-toggle"></span>
                    </div>
                </div>
                <div class="itx-accordion-content">
                    <p class="itx-fix-description"><?php echo esc_html($fix['description']); ?></p>

                    <div class="itx-fix-steps">
                        <h4><?php esc_html_e('Steps to Fix:', 'itx-cheetah'); ?></h4>
                        <ol>
                            <?php foreach ($fix['steps'] as $step) : ?>
                            <li><?php echo esc_html($step); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>

                    <?php if (!empty($fix['code_example'])) : ?>
                    <div class="itx-code-example">
                        <h4><?php esc_html_e('Code Example:', 'itx-cheetah'); ?></h4>
                        <div class="itx-code-comparison">
                            <div class="itx-code-before">
                                <span class="itx-code-label itx-label-before"><?php esc_html_e('Before', 'itx-cheetah'); ?></span>
                                <pre><code><?php echo esc_html($fix['code_example']['before']); ?></code></pre>
                            </div>
                            <div class="itx-code-arrow">
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </div>
                            <div class="itx-code-after">
                                <span class="itx-code-label itx-label-after"><?php esc_html_e('After', 'itx-cheetah'); ?></span>
                                <pre><code><?php echo esc_html($fix['code_example']['after']); ?></code></pre>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Low Priority Fixes -->
    <?php if (!empty($enhanced_recs['low_priority'])) : ?>
    <div class="itx-card itx-fixes-section">
        <div class="itx-card-header">
            <h2>
                <span class="dashicons dashicons-info" style="color: var(--itx-primary);"></span>
                <?php esc_html_e('Low Impact Fixes', 'itx-cheetah'); ?>
            </h2>
            <span class="itx-badge itx-badge-good"><?php esc_html_e('<15% DOM Reduction', 'itx-cheetah'); ?></span>
        </div>

        <div class="itx-accordion" data-multiple="true">
            <?php foreach ($enhanced_recs['low_priority'] as $fix) : ?>
            <div class="itx-accordion-item">
                <div class="itx-accordion-header">
                    <div class="itx-accordion-title">
                        <span class="dashicons dashicons-info" style="color: var(--itx-primary);"></span>
                        <?php echo esc_html($fix['title']); ?>
                    </div>
                    <div class="itx-fix-meta">
                        <?php if (!empty($fix['estimated_reduction'])) : ?>
                        <span class="itx-fix-reduction">-<?php echo esc_html(number_format($fix['estimated_reduction'])); ?> <?php esc_html_e('nodes', 'itx-cheetah'); ?></span>
                        <?php endif; ?>
                        <span class="itx-fix-time"><?php echo esc_html($fix['time_to_fix']); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 itx-accordion-toggle"></span>
                    </div>
                </div>
                <div class="itx-accordion-content">
                    <p class="itx-fix-description"><?php echo esc_html($fix['description']); ?></p>

                    <div class="itx-fix-steps">
                        <h4><?php esc_html_e('Steps to Fix:', 'itx-cheetah'); ?></h4>
                        <ol>
                            <?php foreach ($fix['steps'] as $step) : ?>
                            <li><?php echo esc_html($step); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Theme-Specific Recommendations -->
    <?php if (!empty($enhanced_recs['theme_specific'])) : ?>
    <div class="itx-card itx-fixes-section">
        <div class="itx-card-header">
            <h2>
                <span class="dashicons dashicons-admin-appearance"></span>
                <?php esc_html_e('Theme-Specific Optimizations', 'itx-cheetah'); ?>
            </h2>
        </div>

        <?php foreach ($enhanced_recs['theme_specific'] as $rec) : ?>
        <div class="itx-theme-rec">
            <div class="itx-theme-rec-header">
                <span class="itx-theme-name"><?php echo esc_html($rec['theme']); ?></span>
                <span class="itx-theme-issue"><?php echo esc_html($rec['title']); ?></span>
            </div>
            <p><?php echo esc_html($rec['description']); ?></p>
            <div class="itx-theme-fix">
                <strong><?php esc_html_e('Fix:', 'itx-cheetah'); ?></strong>
                <?php echo esc_html($rec['fix']); ?>
            </div>
            <?php if (!empty($rec['documentation_url'])) : ?>
            <a href="<?php echo esc_url($rec['documentation_url']); ?>" target="_blank" class="itx-docs-link">
                <span class="dashicons dashicons-external"></span>
                <?php esc_html_e('View Documentation', 'itx-cheetah'); ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Plugin-Specific Recommendations -->
    <?php if (!empty($enhanced_recs['plugin_specific'])) : ?>
    <div class="itx-card itx-fixes-section">
        <div class="itx-card-header">
            <h2>
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php esc_html_e('Plugin-Specific Optimizations', 'itx-cheetah'); ?>
            </h2>
        </div>

        <?php foreach ($enhanced_recs['plugin_specific'] as $rec) : ?>
        <div class="itx-plugin-rec">
            <div class="itx-plugin-rec-header">
                <span class="itx-plugin-name"><?php echo esc_html($rec['plugin']); ?></span>
                <span class="itx-plugin-issue"><?php echo esc_html($rec['title']); ?></span>
            </div>
            <p><?php echo esc_html($rec['description']); ?></p>
            <div class="itx-plugin-fix">
                <strong><?php esc_html_e('Fix:', 'itx-cheetah'); ?></strong>
                <?php echo esc_html($rec['fix']); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="itx-report-actions">
        <a href="<?php echo esc_url(admin_url('admin.php?page=itx-cheetah-report&scan_id=' . $scan['id'])); ?>" class="button">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e('Back to Report', 'itx-cheetah'); ?>
        </a>

        <button type="button" class="button button-primary" onclick="window.print();">
            <span class="dashicons dashicons-printer"></span>
            <?php esc_html_e('Print Action Plan', 'itx-cheetah'); ?>
        </button>
    </div>

    <?php endif; // End has_recommendations check ?>
</div>

<style>
/* Impact Grid */
.itx-impact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.itx-impact-item {
    text-align: center;
    padding: 15px;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 8px;
}

.itx-impact-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: var(--itx-gray-800);
}

.itx-impact-label {
    display: block;
    font-size: 12px;
    color: var(--itx-gray-500);
    margin-top: 5px;
}

.itx-text-success {
    color: var(--itx-success) !important;
}

/* Fixes Section */
.itx-fixes-section {
    margin-bottom: 25px;
}

.itx-fix-meta {
    display: flex;
    align-items: center;
    gap: 15px;
}

.itx-fix-reduction {
    background: rgba(34, 197, 94, 0.1);
    color: var(--itx-success);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.itx-fix-time {
    color: var(--itx-gray-500);
    font-size: 12px;
}

.itx-fix-description {
    font-size: 15px;
    color: var(--itx-gray-700);
    line-height: 1.6;
    margin-bottom: 20px;
}

.itx-fix-steps {
    background: var(--itx-gray-50);
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
}

.itx-fix-steps h4 {
    margin: 0 0 10px;
    font-size: 14px;
    color: var(--itx-gray-700);
}

.itx-fix-steps ol {
    margin: 0;
    padding-left: 20px;
}

.itx-fix-steps li {
    margin-bottom: 8px;
    color: var(--itx-gray-600);
}

/* Code Examples */
.itx-code-example {
    margin-top: 20px;
}

.itx-code-example h4 {
    margin: 0 0 15px;
    font-size: 14px;
    color: var(--itx-gray-700);
}

.itx-code-comparison {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 15px;
    align-items: start;
}

.itx-code-before,
.itx-code-after,
.itx-css-changes {
    position: relative;
}

.itx-code-label {
    display: inline-block;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    border-radius: 4px 4px 0 0;
}

.itx-label-before {
    background: rgba(239, 68, 68, 0.1);
    color: var(--itx-danger);
}

.itx-label-after {
    background: rgba(34, 197, 94, 0.1);
    color: var(--itx-success);
}

.itx-code-example pre {
    margin: 0;
    background: var(--itx-gray-900);
    border-radius: 0 8px 8px 8px;
    padding: 15px;
    overflow-x: auto;
}

.itx-code-example code {
    background: transparent;
    color: #e5e7eb;
    font-size: 12px;
    line-height: 1.5;
    white-space: pre;
}

.itx-code-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    padding-top: 40px;
}

.itx-code-arrow .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    color: var(--itx-gray-400);
}

.itx-css-changes {
    margin-top: 15px;
}

.itx-css-changes .itx-code-label {
    background: rgba(59, 130, 246, 0.1);
    color: var(--itx-primary);
}

/* Theme/Plugin Recommendations */
.itx-theme-rec,
.itx-plugin-rec {
    padding: 20px;
    border-bottom: 1px solid var(--itx-gray-100);
}

.itx-theme-rec:last-child,
.itx-plugin-rec:last-child {
    border-bottom: none;
}

.itx-theme-rec-header,
.itx-plugin-rec-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.itx-theme-name,
.itx-plugin-name {
    background: var(--itx-primary);
    color: #fff;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.itx-theme-issue,
.itx-plugin-issue {
    font-weight: 600;
    color: var(--itx-gray-800);
}

.itx-theme-fix,
.itx-plugin-fix {
    background: var(--itx-gray-50);
    padding: 10px 15px;
    border-radius: 6px;
    margin-top: 10px;
}

.itx-docs-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
    color: var(--itx-primary);
    text-decoration: none;
}

.itx-docs-link:hover {
    text-decoration: underline;
}

/* Print Styles */
@media print {
    .itx-back-link,
    .itx-report-actions {
        display: none !important;
    }

    .itx-accordion-item {
        break-inside: avoid;
    }

    .itx-accordion-content {
        display: block !important;
    }
}

/* Responsive */
@media (max-width: 782px) {
    .itx-code-comparison {
        grid-template-columns: 1fr;
    }

    .itx-code-arrow {
        display: none;
    }
}
</style>
