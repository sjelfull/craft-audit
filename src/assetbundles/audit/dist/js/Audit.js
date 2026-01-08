/**
 * Audit plugin for Craft CMS
 *
 * Audit JS
 *
 * @author    Superbig
 * @copyright Copyright (c) 2017 Superbig
 * @link      https://superbig.co
 * @package   Audit
 * @since     1.0.0
 */
(function (window, Craft, $) {
    var AuditDatabaseUpdater = {
        init: function ($updater) {
            var self = this;
            self.$updater = $updater;
            self.$start = self.$updater.find('.js-start');

            self.bindEvents();
        },

        bindEvents: function () {
            var self = this;
            self.$start.on('click', function (e) {
                e.preventDefault();
                self.start();
            });
        },

        start: function () {
            var self = this;

            self.$start.prop('disabled', true);

            Craft.sendActionRequest('POST', 'audit/geo/start-update')
                .then(function (response) {
                    if (response.data.success) {
                        Craft.cp.displaySuccess('Queued database update');
                        setTimeout(function() {
                            self.$start.prop('disabled', false);
                            self.$start.text('Update database');
                        }, 3000);
                    }
                })
                .catch(function (error) {
                    debugger
                    Craft.cp.displayError(error.response?.data?.message || 'An error occurred');
                    self.$start.prop('disabled', false);
                });
        }
    };

    var $updater = $('[data-audit-updater]');
    if ($updater.length) {
        AuditDatabaseUpdater.init($updater);
    }
})(window, Craft, jQuery);
