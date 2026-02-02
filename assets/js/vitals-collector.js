/**
 * Core Web Vitals Collector
 * 
 * Collects LCP, CLS, and INP/FID metrics from the browser
 * and sends them to the server for analysis.
 *
 * @package ITX_Cheetah
 */

(function() {
    'use strict';

    console.log('ITX Cheetah: Vitals collector script loaded');

    // Wait for page to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            console.log('ITX Cheetah: DOM loaded, initializing...');
            init();
        });
    } else {
        console.log('ITX Cheetah: Document ready, initializing...');
        init();
    }

    function init() {
        // Only collect if vitals collector is available
        if (typeof itxCheetahVitals === 'undefined') {
            console.warn('ITX Cheetah: Vitals collector not initialized');
            return;
        }

        // Wait for page to be fully loaded before collecting
        if (document.readyState !== 'complete') {
            window.addEventListener('load', function() {
                setTimeout(collectVitals, 2000); // Wait 2 seconds after load
            });
        } else {
            setTimeout(collectVitals, 2000);
        }
    }

    function collectVitals() {
        console.log('ITX Cheetah: Starting vitals collection...');
        
        // Collect all metrics with fallbacks
        Promise.allSettled([
            identifyLCPElement(),
            measureCLS(),
            detectLongTasks(),
            measureInputDelay(),
            analyzeEventListeners()
        ]).then((results) => {
            console.log('ITX Cheetah: Metrics collected', results);
            const lcp = results[0].status === 'fulfilled' ? results[0].value : { element: 'Unknown', elementType: 'unknown', elementSize: 0, loadTime: 0, url: '' };
            const cls = results[1].status === 'fulfilled' ? results[1].value : { score: 0, shifts: [], count: 0 };
            const longTasks = results[2].status === 'fulfilled' ? results[2].value : { count: 0, total_time: 0, tasks: [] };
            const inputDelay = results[3].status === 'fulfilled' ? results[3].value : { delays: [], max_delay: 0, avg_delay: 0 };
            const eventListeners = results[4].status === 'fulfilled' ? results[4].value : { total_listeners: 0, elements_with_listeners: 0, details: [] };

            // Get actual current URL from browser (more reliable)
            const currentUrl = window.location.href;
            
            // Send to server
            sendVitalsData({
                lcp: lcp,
                cls: cls,
                inp: {
                    longTasks: longTasks,
                    inputDelay: inputDelay,
                    eventListeners: eventListeners,
                    score: calculateINPScore(longTasks, inputDelay)
                },
                url: currentUrl || itxCheetahVitals.url,
                post_id: itxCheetahVitals.post_id
            });
        }).catch(error => {
            console.error('ITX Cheetah: Error collecting vitals', error);
        });
    }

    /**
     * Identify LCP element
     */
    function identifyLCPElement() {
        return new Promise((resolve) => {
            let lcpEntry = null;
            let lcpObserver = null;

            // Try to get LCP from Performance API first
            if (window.performance && window.performance.getEntriesByType) {
                try {
                    const lcpEntries = window.performance.getEntriesByType('largest-contentful-paint');
                    if (lcpEntries.length > 0) {
                        lcpEntry = lcpEntries[lcpEntries.length - 1];
                    }
                } catch (e) {
                    // Not supported
                }
            }

            // Also set up observer for future LCP updates
            try {
                if ('PerformanceObserver' in window) {
                    lcpObserver = new PerformanceObserver((list) => {
                        const entries = list.getEntries();
                        if (entries.length > 0) {
                            lcpEntry = entries[entries.length - 1];
                        }
                    });

                    lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
                }
            } catch (e) {
                // PerformanceObserver not supported
            }

            // Resolve after delay
            setTimeout(() => {
                if (lcpObserver) {
                    lcpObserver.disconnect();
                }

                // If still no LCP, try to estimate from navigation timing
                if (!lcpEntry && window.performance && window.performance.timing) {
                    const timing = window.performance.timing;
                    const loadTime = timing.loadEventEnd - timing.navigationStart;
                    lcpEntry = {
                        loadTime: loadTime,
                        renderTime: loadTime
                    };
                }

                const result = {
                    element: lcpEntry?.element?.tagName || 'Unknown',
                    elementType: getElementType(lcpEntry?.element),
                    elementSize: lcpEntry?.size || 0,
                    loadTime: lcpEntry ? (lcpEntry.renderTime || lcpEntry.loadTime || 0) : 0,
                    url: lcpEntry?.url || ''
                };

                resolve(result);
            }, 5000); // Increased delay to 5 seconds
        });
    }

    /**
     * Get element type
     */
    function getElementType(element) {
        if (!element) return 'unknown';
        if (element.tagName === 'IMG') return 'image';
        if (element.tagName === 'VIDEO') return 'video';
        if (element.tagName === 'SVG') return 'svg';
        if (element.nodeType === 3) return 'text';
        if (element.tagName === 'P' || element.tagName === 'H1' || element.tagName === 'H2') return 'text';
        return 'other';
    }

    /**
     * Measure CLS
     */
    function measureCLS() {
        return new Promise((resolve) => {
            let clsValue = 0;
            let clsEntries = [];

            try {
                const observer = new PerformanceObserver((list) => {
                    list.getEntries().forEach((entry) => {
                        // Only count shifts that weren't caused by user input
                        if (!entry.hadRecentInput) {
                            clsValue += entry.value;
                            clsEntries.push({
                                value: entry.value,
                                sources: entry.sources || [],
                                time: entry.startTime
                            });
                        }
                    });
                });

                observer.observe({ type: 'layout-shift', buffered: true });

                // Resolve after delay
                setTimeout(() => {
                    if (observer) {
                        observer.disconnect();
                    }
                    resolve({
                        score: clsValue,
                        shifts: clsEntries,
                        count: clsEntries.length
                    });
                }, 5000);
            } catch (e) {
                // PerformanceObserver not supported
                resolve({
                    score: 0,
                    shifts: [],
                    count: 0
                });
            }
        });
    }

    /**
     * Detect long tasks
     */
    function detectLongTasks() {
        return new Promise((resolve) => {
            let longTasks = [];
            let totalLongTaskTime = 0;
            let observer = null;

            try {
                observer = new PerformanceObserver((list) => {
                    list.getEntries().forEach((entry) => {
                        if (entry.duration > 50) {
                            longTasks.push({
                                duration: entry.duration,
                                startTime: entry.startTime,
                                attribution: entry.attribution || []
                            });
                            totalLongTaskTime += entry.duration;
                        }
                    });
                });

                observer.observe({ entryTypes: ['longtask'] });
            } catch (e) {
                // Long task observer not supported
            }

            // Resolve after delay
            setTimeout(() => {
                if (observer) {
                    observer.disconnect();
                }

                resolve({
                    count: longTasks.length,
                    total_time: totalLongTaskTime,
                    tasks: longTasks
                });
            }, 5000);
        });
    }

    /**
     * Measure input delay
     */
    function measureInputDelay() {
        return new Promise((resolve) => {
            let delays = [];
            let observer = null;

            try {
                observer = new PerformanceObserver((list) => {
                    list.getEntries().forEach((entry) => {
                        if (entry.processingStart && entry.processingEnd) {
                            delays.push({
                                type: entry.name,
                                processingStart: entry.processingStart,
                                processingEnd: entry.processingEnd,
                                duration: entry.processingEnd - entry.processingStart
                            });
                        }
                    });
                });

                observer.observe({ type: 'event', buffered: true });
            } catch (e) {
                // Event observer not fully supported
            }

            // Also listen for user interactions
            const interactionTypes = ['click', 'keydown', 'pointerdown'];
            interactionTypes.forEach(type => {
                document.addEventListener(type, function handler(event) {
                    const delay = performance.now() - event.timeStamp;
                    delays.push({
                        type: type,
                        duration: delay,
                        timestamp: event.timeStamp
                    });
                }, { once: false, passive: true });
            });

            // Resolve after delay
            setTimeout(() => {
                if (observer) {
                    observer.disconnect();
                }

                const maxDelay = delays.length > 0 ? Math.max(...delays.map(d => d.duration)) : 0;
                const avgDelay = delays.length > 0 ?
                    delays.reduce((sum, d) => sum + d.duration, 0) / delays.length : 0;

                resolve({
                    delays: delays,
                    max_delay: maxDelay,
                    avg_delay: avgDelay
                });
            }, 3000);
        });
    }

    /**
     * Analyze event listeners (simplified)
     */
    function analyzeEventListeners() {
        // Note: getEventListeners is only available in Chrome DevTools
        // This is a simplified estimation
        const allElements = document.querySelectorAll('*');
        let estimatedListeners = 0;

        // Estimate based on common patterns
        allElements.forEach(element => {
            // Check for common event attributes
            const hasOnClick = element.hasAttribute('onclick');
            const hasOnChange = element.hasAttribute('onchange');
            const hasOnSubmit = element.hasAttribute('onsubmit');
            
            if (hasOnClick || hasOnChange || hasOnSubmit) {
                estimatedListeners++;
            }
        });

        // Add estimated listeners from common interactive elements
        const interactiveElements = document.querySelectorAll('a, button, input, select, textarea');
        estimatedListeners += interactiveElements.length * 0.5; // Estimate 0.5 listeners per interactive element

        return {
            total_listeners: Math.round(estimatedListeners),
            elements_with_listeners: allElements.length,
            details: []
        };
    }

    /**
     * Calculate INP score
     */
    function calculateINPScore(longTasks, inputDelay) {
        // INP is the worst interaction delay
        // For now, use max input delay or long task impact
        const maxInputDelay = inputDelay.max_delay || 0;
        const longTaskImpact = longTasks.total_time || 0;

        // INP is typically the worst interaction delay
        return Math.max(maxInputDelay, longTaskImpact);
    }

    /**
     * Send vitals data to server
     */
    function sendVitalsData(data) {
        if (typeof itxCheetahVitals === 'undefined') {
            console.warn('ITX Cheetah: Vitals collector not initialized');
            return;
        }

        console.log('ITX Cheetah: Sending vitals data', data);
        console.log('ITX Cheetah: AJAX URL', itxCheetahVitals.ajaxUrl);

        // Use FormData for better compatibility
        const formData = new FormData();
        formData.append('action', 'itx_cheetah_collect_vitals');
        formData.append('nonce', itxCheetahVitals.nonce);
        formData.append('data', JSON.stringify(data));

        fetch(itxCheetahVitals.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('ITX Cheetah: Response status', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                console.log('ITX Cheetah: Vitals data collected successfully', result.data);
            } else {
                console.warn('ITX Cheetah: Failed to collect vitals', result.data);
            }
        })
        .catch(error => {
            console.error('ITX Cheetah: Error sending vitals data', error);
        });
    }

})();
