/**
 * ITX Cheetah Admin JavaScript
 */

(function($) {
    'use strict';

    /**
     * Scanner module
     */
    const Scanner = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#itx-scan-button').on('click', this.startScan.bind(this));
            $('#itx-scan-url').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    Scanner.startScan();
                }
            });
        },

        startScan: function() {
            const url = $('#itx-scan-url').val().trim();

            if (!url) {
                this.showResult(itxCheetah.strings.noUrlSelected, 'error');
                return;
            }

            // Validate URL
            try {
                new URL(url);
            } catch (e) {
                this.showResult('Please enter a valid URL.', 'error');
                return;
            }

            this.showProgress();

            $.ajax({
                url: itxCheetah.restUrl + 'scans',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': itxCheetah.nonce
                },
                data: {
                    url: url
                },
                success: function(response) {
                    Scanner.hideProgress();
                    if (response.success) {
                        Scanner.showResult(Scanner.formatScanResult(response.scan), 'success');
                    } else {
                        Scanner.showResult(response.message || itxCheetah.strings.scanError, 'error');
                    }
                },
                error: function(xhr) {
                    Scanner.hideProgress();
                    const message = xhr.responseJSON?.message || itxCheetah.strings.scanError;
                    Scanner.showResult(message, 'error');
                }
            });
        },

        showProgress: function() {
            $('#itx-scan-button').prop('disabled', true);
            $('#itx-scan-progress').show();
            $('#itx-scan-result').hide();
        },

        hideProgress: function() {
            $('#itx-scan-button').prop('disabled', false);
            $('#itx-scan-progress').hide();
        },

        showResult: function(content, type) {
            const $result = $('#itx-scan-result');
            $result.removeClass('success error').addClass(type);
            $result.html(content).show();
        },

        formatScanResult: function(scan) {
            const statusClass = scan.performance_score >= 80 ? 'good' :
                               (scan.performance_score >= 50 ? 'warning' : 'critical');

            let html = '<div class="itx-scan-success">';
            html += '<strong>' + itxCheetah.strings.scanComplete + '</strong>';
            html += '<div style="margin-top: 10px; display: flex; gap: 20px; flex-wrap: wrap;">';
            html += '<span><strong>Score:</strong> <span class="itx-badge itx-badge-' + statusClass + '">' + scan.performance_score + '</span></span>';
            html += '<span><strong>Nodes:</strong> ' + this.formatNumber(scan.total_nodes) + '</span>';
            html += '<span><strong>Depth:</strong> ' + scan.max_depth + '</span>';
            html += '</div>';
            html += '<div style="margin-top: 10px;">';
            html += '<a href="' + this.getReportUrl(scan.id) + '" class="button button-small" style="color: #fff; border-color: rgba(255,255,255,0.5);">View Full Report</a>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },

        getReportUrl: function(scanId) {
            return window.location.origin + '/wp-admin/admin.php?page=itx-cheetah-report&scan_id=' + scanId;
        }
    };

    /**
     * Bulk Scanner module
     */
    const BulkScanner = {
        isRunning: false,
        shouldStop: false,
        urlQueue: [],
        results: [],
        totalUrls: 0,
        completedCount: 0,
        failedCount: 0,
        scoreSum: 0,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#itx-start-bulk-scan').on('click', this.startBulkScan.bind(this));
            $('#itx-stop-bulk-scan').on('click', this.stopBulkScan.bind(this));
            $('#itx-export-results-csv').on('click', this.exportResults.bind(this));
        },

        startBulkScan: function() {
            if (this.isRunning) return;

            // Get selected post types
            const postTypes = [];
            $('input[name="post_types[]"]:checked').each(function() {
                postTypes.push($(this).val());
            });

            if (postTypes.length === 0) {
                alert('Please select at least one post type to scan.');
                return;
            }

            const batchSize = parseInt($('#itx-batch-size').val()) || 10;
            const includeHomepage = $('input[name="include_homepage"]').is(':checked');

            // Reset state
            this.isRunning = true;
            this.shouldStop = false;
            this.urlQueue = [];
            this.results = [];
            this.completedCount = 0;
            this.failedCount = 0;
            this.scoreSum = 0;

            // Update UI
            $('#itx-start-bulk-scan').hide();
            $('#itx-stop-bulk-scan').show();
            $('#itx-bulk-progress').show();
            $('#itx-bulk-results').show();
            $('#itx-results-body').empty();
            $('#itx-current-scan').show();
            this.updateProgress(0, 0);
            $('#itx-current-url').text(itxCheetah.strings.preparingUrls || 'Preparing URLs to scan...');

            // Fetch URLs to scan
            $.ajax({
                url: itxCheetah.restUrl + 'urls',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': itxCheetah.nonce
                },
                data: {
                    post_types: postTypes
                },
                success: function(response) {
                    BulkScanner.urlQueue = response.urls.slice(0, batchSize);

                    if (includeHomepage) {
                        // Check if homepage is already in the list
                        const homeUrl = window.location.origin + '/';
                        const hasHome = BulkScanner.urlQueue.some(u => u.url === homeUrl);
                        if (!hasHome) {
                            BulkScanner.urlQueue.unshift({
                                id: 0,
                                url: homeUrl,
                                title: 'Homepage',
                                type: 'home'
                            });
                        }
                    }

                    BulkScanner.totalUrls = BulkScanner.urlQueue.length;
                    BulkScanner.updateProgress(0, BulkScanner.totalUrls);
                    BulkScanner.processNext();
                },
                error: function() {
                    BulkScanner.stopBulkScan();
                    alert('Failed to fetch URLs to scan.');
                }
            });
        },

        processNext: function() {
            if (this.shouldStop || this.urlQueue.length === 0) {
                this.finishBulkScan();
                return;
            }

            const urlData = this.urlQueue.shift();
            $('#itx-current-url').text(urlData.url);

            $.ajax({
                url: itxCheetah.restUrl + 'scans',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': itxCheetah.nonce
                },
                data: {
                    url: urlData.url
                },
                success: function(response) {
                    if (response.success) {
                        BulkScanner.completedCount++;
                        BulkScanner.scoreSum += response.scan.performance_score;
                        BulkScanner.addResult(urlData, response.scan, true);
                    } else {
                        BulkScanner.failedCount++;
                        BulkScanner.addResult(urlData, null, false, response.message);
                    }
                    BulkScanner.updateProgress(BulkScanner.completedCount + BulkScanner.failedCount, BulkScanner.totalUrls);
                    BulkScanner.processNext();
                },
                error: function(xhr) {
                    BulkScanner.failedCount++;
                    const message = xhr.responseJSON?.message || 'Unknown error';
                    BulkScanner.addResult(urlData, null, false, message);
                    BulkScanner.updateProgress(BulkScanner.completedCount + BulkScanner.failedCount, BulkScanner.totalUrls);
                    BulkScanner.processNext();
                }
            });
        },

        addResult: function(urlData, scan, success, error) {
            const result = {
                url: urlData.url,
                title: urlData.title,
                success: success,
                scan: scan,
                error: error
            };

            this.results.push(result);

            // Add to table
            let row = '<tr>';
            row += '<td><a href="' + urlData.url + '" target="_blank">' + this.truncateUrl(urlData.url) + '</a></td>';

            if (success && scan) {
                const statusClass = scan.performance_score >= 80 ? 'good' :
                                   (scan.performance_score >= 50 ? 'warning' : 'critical');
                row += '<td><span class="itx-status itx-status-completed">Success</span></td>';
                row += '<td>' + Scanner.formatNumber(scan.total_nodes) + '</td>';
                row += '<td>' + scan.max_depth + '</td>';
                row += '<td><span class="itx-badge itx-badge-' + statusClass + '">' + scan.performance_score + '</span></td>';
                row += '<td><a href="' + Scanner.getReportUrl(scan.id) + '" class="button button-small">View</a></td>';
            } else {
                row += '<td><span class="itx-status itx-status-failed">Failed</span></td>';
                row += '<td colspan="3"><span class="itx-muted">' + (error || 'Unknown error') + '</span></td>';
                row += '<td>-</td>';
            }

            row += '</tr>';
            $('#itx-results-body').prepend(row);
        },

        updateProgress: function(current, total) {
            const percent = total > 0 ? Math.round((current / total) * 100) : 0;

            $('#itx-progress-current').text(current);
            $('#itx-progress-total').text(total);
            $('#itx-progress-percent').text(percent);
            $('#itx-progress-fill').css('width', percent + '%');

            $('#itx-stat-completed').text(this.completedCount);
            $('#itx-stat-failed').text(this.failedCount);

            if (this.completedCount > 0) {
                const avgScore = Math.round(this.scoreSum / this.completedCount);
                $('#itx-stat-avg-score').text(avgScore);
            }
        },

        stopBulkScan: function() {
            this.shouldStop = true;
            $('#itx-current-url').text(itxCheetah.strings.bulkScanStopped || 'Bulk scan stopped.');
        },

        finishBulkScan: function() {
            this.isRunning = false;
            $('#itx-start-bulk-scan').show();
            $('#itx-stop-bulk-scan').hide();
            $('#itx-current-scan').hide();

            if (!this.shouldStop) {
                $('#itx-current-url').text(itxCheetah.strings.bulkScanComplete || 'Bulk scan complete!');
            }
        },

        truncateUrl: function(url) {
            if (url.length > 50) {
                return url.substring(0, 47) + '...';
            }
            return url;
        },

        exportResults: function() {
            if (this.results.length === 0) {
                alert('No results to export.');
                return;
            }

            let csv = 'URL,Status,Total Nodes,Max Depth,Score,Error\n';

            this.results.forEach(function(result) {
                csv += '"' + result.url + '",';
                csv += result.success ? 'Success,' : 'Failed,';
                csv += result.scan ? result.scan.total_nodes + ',' : ',';
                csv += result.scan ? result.scan.max_depth + ',' : ',';
                csv += result.scan ? result.scan.performance_score + ',' : ',';
                csv += result.error ? '"' + result.error + '"' : '';
                csv += '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'itx-cheetah-bulk-scan-' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };

    /**
     * Bulk Actions module
     */
    const BulkActions = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#itx-select-all').on('change', this.toggleAll);
            $('.itx-scan-checkbox').on('change', this.updateBulkButton);
            $('#itx-scans-form').on('submit', this.confirmBulkDelete);
        },

        toggleAll: function() {
            const checked = $(this).is(':checked');
            $('.itx-scan-checkbox').prop('checked', checked);
            BulkActions.updateBulkButton();
        },

        updateBulkButton: function() {
            const checkedCount = $('.itx-scan-checkbox:checked').length;
            $('#itx-bulk-delete').prop('disabled', checkedCount === 0);
        },

        confirmBulkDelete: function(e) {
            const checkedCount = $('.itx-scan-checkbox:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                return false;
            }
            if (!confirm(itxCheetah.strings.confirmBulkDelete)) {
                e.preventDefault();
                return false;
            }
        }
    };

    /**
     * Compare module
     */
    const Compare = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('.itx-compare-checkbox').on('change', this.updateCompareButton);
        },

        updateCompareButton: function() {
            const checkedCount = $('.itx-compare-checkbox:checked').length;
            $('#itx-compare-button').prop('disabled', checkedCount < 2 || checkedCount > 5);
        }
    };

    /**
     * Accordion module
     */
    const Accordion = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('.itx-accordion-header').on('click', this.toggle);
        },

        toggle: function() {
            const $item = $(this).closest('.itx-accordion-item');
            const $accordion = $item.closest('.itx-accordion');
            const allowMultiple = $accordion.data('multiple') !== false;

            if (!allowMultiple) {
                // Close other items
                $accordion.find('.itx-accordion-item').not($item).removeClass('active');
            }

            $item.toggleClass('active');
        }
    };

    /**
     * Dashboard Charts module
     */
    const DashboardCharts = {
        init: function() {
            this.initTrendChart();
            this.initDistributionChart();
        },

        initTrendChart: function() {
            const $canvas = $('#itx-trend-chart');
            if (!$canvas.length || typeof Chart === 'undefined') return;

            const ctx = $canvas[0].getContext('2d');
            const chartData = $canvas.data('chart');

            if (!chartData || !chartData.labels) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Avg DOM Nodes',
                        data: chartData.nodes,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Avg Score',
                        data: chartData.scores,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'DOM Nodes'
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
                                text: 'Score'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        initDistributionChart: function() {
            const $canvas = $('#itx-distribution-chart');
            if (!$canvas.length || typeof Chart === 'undefined') return;

            const ctx = $canvas[0].getContext('2d');
            const chartData = $canvas.data('chart');

            if (!chartData) return;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Good', 'Warning', 'Critical'],
                    datasets: [{
                        data: [chartData.good || 0, chartData.warning || 0, chartData.critical || 0],
                        backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        Scanner.init();
        BulkScanner.init();
        BulkActions.init();
        Compare.init();
        Accordion.init();
        DashboardCharts.init();

        // Auto-dismiss notices
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut();
        }, 5000);
    });

})(jQuery);
