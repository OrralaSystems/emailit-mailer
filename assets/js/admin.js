/**
 * EmailIT Mailer - Admin JavaScript
 * 
 * @package EmailIT_Mailer
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * EmailIT Admin Module
     */
    var EmailITAdmin = {

        /**
         * Initialize the module
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            // Test email button
            $('#emailit_test_button').on('click', this.handleTestEmail.bind(this));

            // Clear logs button
            $('#emailit_clear_logs').on('click', this.handleClearLogs.bind(this));

            // Toggle API key visibility
            $('#emailit-toggle-api-key').on('click', this.toggleApiKey.bind(this));

            // Toggle enabled/disabled
            $('#emailit_enabled').on('change', this.handleEnabledToggle.bind(this));

            // View response modal
            $('.emailit-view-response').on('click', this.showResponseModal.bind(this));

            // Close modal
            $('.emailit-modal-close').on('click', this.closeModal.bind(this));
            $(document).on('click', '.emailit-modal', function (e) {
                if ($(e.target).hasClass('emailit-modal')) {
                    EmailITAdmin.closeModal();
                }
            });

            // Close modal on escape key
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    EmailITAdmin.closeModal();
                }
            });
        },

        /**
         * Handle enabled toggle change
         */
        handleEnabledToggle: function (e) {
            var $checkbox = $(e.currentTarget);
            var $label = $('#emailit_enabled_label');
            var isEnabled = $checkbox.is(':checked');

            if (isEnabled) {
                $label.text('Activo').removeClass('disabled').addClass('enabled');
            } else {
                $label.text('Inactivo').removeClass('enabled').addClass('disabled');
            }
        },

        /**
         * Handle test email button click
         */
        handleTestEmail: function (e) {
            e.preventDefault();

            var $button = $('#emailit_test_button');
            var $result = $('#emailit_test_result');
            var testEmail = $('#emailit_test_email').val();

            // Validate email
            if (!testEmail || !this.isValidEmail(testEmail)) {
                $result.removeClass('success loading').addClass('error')
                    .text(emailitAdmin.strings.enterEmail);
                return;
            }

            // Show loading state
            $button.prop('disabled', true);
            $result.removeClass('success error').addClass('loading')
                .text(emailitAdmin.strings.testing);

            // Make AJAX request
            $.ajax({
                url: emailitAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'emailit_test_connection',
                    nonce: emailitAdmin.nonce,
                    email: testEmail
                },
                success: function (response) {
                    $button.prop('disabled', false);

                    if (response.success) {
                        $result.removeClass('loading error').addClass('success')
                            .text(response.data.message);
                    } else {
                        $result.removeClass('loading success').addClass('error')
                            .text(emailitAdmin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function (xhr, status, error) {
                    $button.prop('disabled', false);
                    $result.removeClass('loading success').addClass('error')
                        .text(emailitAdmin.strings.error + ' ' + error);
                }
            });
        },

        /**
         * Handle clear logs button click
         */
        handleClearLogs: function (e) {
            e.preventDefault();

            // Confirm action
            if (!confirm(emailitAdmin.strings.confirmClear)) {
                return;
            }

            var $button = $('#emailit_clear_logs');
            var originalText = $button.text();

            // Show loading state
            $button.prop('disabled', true).text(emailitAdmin.strings.clearing);

            // Make AJAX request
            $.ajax({
                url: emailitAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'emailit_clear_logs',
                    nonce: emailitAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Show success message and reload page
                        alert(emailitAdmin.strings.cleared);
                        location.reload();
                    } else {
                        $button.prop('disabled', false).text(originalText);
                        alert(emailitAdmin.strings.clearError + ' ' + response.data.message);
                    }
                },
                error: function (xhr, status, error) {
                    $button.prop('disabled', false).text(originalText);
                    alert(emailitAdmin.strings.clearError + ' ' + error);
                }
            });
        },

        /**
         * Toggle API key visibility
         */
        toggleApiKey: function (e) {
            e.preventDefault();

            var $input = $('#emailit_api_key');
            var $button = $(e.currentTarget);

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $button.text('Ocultar');
            } else {
                $input.attr('type', 'password');
                $button.text('Mostrar');
            }
        },

        /**
         * Show response modal
         */
        showResponseModal: function (e) {
            e.preventDefault();

            var response = $(e.currentTarget).data('response');

            // Try to format as JSON if possible
            try {
                var parsed = JSON.parse(response);
                response = JSON.stringify(parsed, null, 2);
            } catch (err) {
                // Not JSON, use as-is
            }

            $('#emailit-modal-response').text(response);
            $('#emailit-response-modal').fadeIn(200);
        },

        /**
         * Close modal
         */
        closeModal: function () {
            $('.emailit-modal').fadeOut(200);
        },

        /**
         * Validate email format
         */
        isValidEmail: function (email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        EmailITAdmin.init();
    });

})(jQuery);
