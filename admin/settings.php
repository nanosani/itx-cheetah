<?php
/**
 * Settings page template
 *
 * @package ITX_Cheetah
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get next scheduled scan
$next_scan = wp_next_scheduled('itx_cheetah_scheduled_scan');
?>

<div class="wrap itx-cheetah-wrap">
    <h1 class="itx-page-title">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e('Settings', 'itx-cheetah'); ?>
    </h1>

    <?php if (isset($_GET['saved'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully.', 'itx-cheetah'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="itx-settings-form">
        <?php wp_nonce_field('itx_cheetah_settings'); ?>

        <div class="itx-card">
            <h2><?php esc_html_e('Performance Thresholds', 'itx-cheetah'); ?></h2>
            <p class="description">
                <?php esc_html_e('Configure the thresholds used to calculate performance scores. These are based on Google\'s recommendations for optimal DOM size.', 'itx-cheetah'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="node_threshold_good"><?php esc_html_e('Node Count - Good', 'itx-cheetah'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="node_threshold_good"
                               name="node_threshold_good"
                               value="<?php echo esc_attr($settings['node_threshold_good']); ?>"
                               class="small-text"
                               min="100"
                               max="5000">
                        <p class="description">
                            <?php esc_html_e('Pages with fewer nodes than this are considered "Good". Default: 1000', 'itx-cheetah'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="node_threshold_warning"><?php esc_html_e('Node Count - Warning', 'itx-cheetah'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="node_threshold_warning"
                               name="node_threshold_warning"
                               value="<?php echo esc_attr($settings['node_threshold_warning']); ?>"
                               class="small-text"
                               min="500"
                               max="10000">
                        <p class="description">
                            <?php esc_html_e('Pages with more nodes than this are considered "Critical". Default: 1500', 'itx-cheetah'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="depth_threshold_good"><?php esc_html_e('DOM Depth - Good', 'itx-cheetah'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="depth_threshold_good"
                               name="depth_threshold_good"
                               value="<?php echo esc_attr($settings['depth_threshold_good']); ?>"
                               class="small-text"
                               min="5"
                               max="50">
                        <p class="description">
                            <?php esc_html_e('Pages with depth less than this are considered "Good". Default: 20', 'itx-cheetah'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="depth_threshold_warning"><?php esc_html_e('DOM Depth - Warning', 'itx-cheetah'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="depth_threshold_warning"
                               name="depth_threshold_warning"
                               value="<?php echo esc_attr($settings['depth_threshold_warning']); ?>"
                               class="small-text"
                               min="10"
                               max="100">
                        <p class="description">
                            <?php esc_html_e('Pages with depth more than this are considered "Critical". Default: 32', 'itx-cheetah'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="itx-card">
            <h2><?php esc_html_e('Scheduled Scans', 'itx-cheetah'); ?></h2>
            <p class="description">
                <?php esc_html_e('Configure automatic scanning to keep your DOM metrics up to date.', 'itx-cheetah'); ?>
            </p>

            <?php if ($next_scan) : ?>
                <div class="itx-schedule-status">
                    <span class="dashicons dashicons-clock"></span>
                    <?php
                    printf(
                        esc_html__('Next scheduled scan: %s', 'itx-cheetah'),
                        '<strong>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scan)) . '</strong>'
                    );
                    ?>
                </div>
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="auto_scan_frequency"><?php esc_html_e('Scan Frequency', 'itx-cheetah'); ?></label>
                    </th>
                    <td>
                        <select id="auto_scan_frequency" name="auto_scan_frequency">
                            <option value="disabled" <?php selected($settings['auto_scan_frequency'] ?? 'disabled', 'disabled'); ?>>
                                <?php esc_html_e('Disabled', 'itx-cheetah'); ?>
                            </option>
                            <option value="hourly" <?php selected($settings['auto_scan_frequency'] ?? '', 'hourly'); ?>>
                                <?php esc_html_e('Hourly', 'itx-cheetah'); ?>
                            </option>
                            <option value="twice_daily" <?php selected($settings['auto_scan_frequency'] ?? '', 'twice_daily'); ?>>
                                <?php esc_html_e('Twice Daily', 'itx-cheetah'); ?>
                            </option>
                            <option value="daily" <?php selected($settings['auto_scan_frequency'] ?? '', 'daily'); ?>>
                                <?php esc_html_e('Daily', 'itx-cheetah'); ?>
                            </option>
                            <option value="weekly" <?php selected($settings['auto_scan_frequency'] ?? '', 'weekly'); ?>>
                                <?php esc_html_e('Weekly', 'itx-cheetah'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('How often to automatically scan your site.', 'itx-cheetah'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="auto_scan_batch_size"><?php esc_html_e('Batch Size', 'itx-cheetah'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="auto_scan_batch_size"
                               name="auto_scan_batch_size"
                               value="<?php echo esc_attr($settings['auto_scan_batch_size'] ?? 10); ?>"
                               class="small-text"
                               min="1"
                               max="50">
                        <p class="description">
                            <?php esc_html_e('Number of pages to scan per scheduled run. Lower values reduce server load.', 'itx-cheetah'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php esc_html_e('Post Types to Scan', 'itx-cheetah'); ?>
                    </th>
                    <td>
                        <?php
                        $post_types = get_post_types(array('public' => true), 'objects');
                        $selected_types = $settings['auto_scan_post_types'] ?? array('post', 'page');

                        foreach ($post_types as $post_type) :
                        ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox"
                                       name="auto_scan_post_types[]"
                                       value="<?php echo esc_attr($post_type->name); ?>"
                                       <?php checked(in_array($post_type->name, $selected_types)); ?>>
                                <?php echo esc_html($post_type->labels->name); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">
                            <?php esc_html_e('Which post types to include in scheduled scans.', 'itx-cheetah'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="itx-card">
            <h2><?php esc_html_e('Email Notifications', 'itx-cheetah'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Enable Notifications', 'itx-cheetah'); ?>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="email_notifications"
                                   value="1"
                                   <?php checked($settings['email_notifications'] ?? false); ?>>
                            <?php esc_html_e('Send email notifications when critical issues are found', 'itx-cheetah'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="notification_email"><?php esc_html_e('Notification Email', 'itx-cheetah'); ?></label>
                    </th>
                    <td>
                        <input type="email"
                               id="notification_email"
                               name="notification_email"
                               value="<?php echo esc_attr($settings['notification_email'] ?? get_option('admin_email')); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Email address to receive notifications. Defaults to admin email.', 'itx-cheetah'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="itx-card">
            <h2><?php esc_html_e('Data Management', 'itx-cheetah'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="data_retention_days"><?php esc_html_e('Data Retention', 'itx-cheetah'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="data_retention_days"
                               name="data_retention_days"
                               value="<?php echo esc_attr($settings['data_retention_days']); ?>"
                               class="small-text"
                               min="7"
                               max="365">
                        <?php esc_html_e('days', 'itx-cheetah'); ?>
                        <p class="description">
                            <?php esc_html_e('Scans older than this will be automatically deleted. Default: 90 days', 'itx-cheetah'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="itx-card">
            <h2><?php esc_html_e('Export All Data', 'itx-cheetah'); ?></h2>
            <p class="description">
                <?php esc_html_e('Download all your scan data for backup or analysis.', 'itx-cheetah'); ?>
            </p>

            <div class="itx-export-buttons">
                <a href="<?php echo esc_url(ITX_Cheetah_Reports::get_export_url('csv', array('export_type' => 'all'))); ?>" class="button">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php esc_html_e('Export All as CSV', 'itx-cheetah'); ?>
                </a>
                <a href="<?php echo esc_url(ITX_Cheetah_Reports::get_export_url('json', array('export_type' => 'all'))); ?>" class="button">
                    <span class="dashicons dashicons-media-code"></span>
                    <?php esc_html_e('Export All as JSON', 'itx-cheetah'); ?>
                </a>
            </div>
        </div>

        <div class="itx-card">
            <h2><?php esc_html_e('About Google Recommendations', 'itx-cheetah'); ?></h2>

            <div class="itx-info-box">
                <p><?php esc_html_e('According to Google\'s Lighthouse performance audit:', 'itx-cheetah'); ?></p>
                <ul>
                    <li><strong><?php esc_html_e('Total DOM Nodes:', 'itx-cheetah'); ?></strong> <?php esc_html_e('Should be less than 1,500 for optimal performance', 'itx-cheetah'); ?></li>
                    <li><strong><?php esc_html_e('Maximum DOM Depth:', 'itx-cheetah'); ?></strong> <?php esc_html_e('Should be less than 32 levels deep', 'itx-cheetah'); ?></li>
                    <li><strong><?php esc_html_e('Maximum Children:', 'itx-cheetah'); ?></strong> <?php esc_html_e('No parent element should have more than 60 child elements', 'itx-cheetah'); ?></li>
                </ul>
                <p>
                    <a href="https://developer.chrome.com/docs/lighthouse/performance/dom-size/" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Learn more about DOM size optimization', 'itx-cheetah'); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                </p>
            </div>
        </div>

        <p class="submit">
            <button type="submit" name="itx_cheetah_save_settings" class="button button-primary">
                <?php esc_html_e('Save Settings', 'itx-cheetah'); ?>
            </button>
        </p>
    </form>
</div>
