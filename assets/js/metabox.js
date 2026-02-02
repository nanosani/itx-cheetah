/**
 * ITX Cheetah Meta Box JavaScript
 */

(function($) {
    'use strict';

    const MetaboxScanner = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '#itx-metabox-scan', this.handleScan.bind(this));
        },

        handleScan: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const postId = $button.data('post-id');

            if (!postId) {
                alert(itxCheetahMetabox.strings.saveFirst);
                return;
            }

            this.showProgress();

            $.ajax({
                url: itxCheetahMetabox.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'itx_cheetah_scan_post',
                    nonce: itxCheetahMetabox.nonce,
                    post_id: postId
                },
                success: function(response) {
                    MetaboxScanner.hideProgress();

                    if (response.success) {
                        $('#itx-metabox-results').html(response.data.html);
                        // Update button text
                        $('#itx-metabox-scan').html('<span class="dashicons dashicons-search"></span> Rescan');
                    } else {
                        alert(response.data.message || itxCheetahMetabox.strings.scanError);
                    }
                },
                error: function() {
                    MetaboxScanner.hideProgress();
                    alert(itxCheetahMetabox.strings.scanError);
                }
            });
        },

        showProgress: function() {
            $('#itx-metabox-scan').prop('disabled', true);
            $('#itx-metabox-progress').show();
        },

        hideProgress: function() {
            $('#itx-metabox-scan').prop('disabled', false);
            $('#itx-metabox-progress').hide();
        }
    };

    $(document).ready(function() {
        MetaboxScanner.init();
    });

})(jQuery);
