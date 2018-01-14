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
        steps: {
            download: {
                action: 'audit/geo/download-database',
                text: 'Downloading GeoLite database'
            },
            verify: {
                action: 'audit/geo/unpack-database',
                text: 'Unpacking and verifying database'
            },
        },
        currentStep: -1,
        totalSteps: 0,
        isVisible: false,

        init: function ($updater) {
            var self = this;
            self.$dbWarning = $('[data-db-warning]');
            self.$updater = $updater;
            self.$status = self.$updater.find('.js-status');
            self.$statusText = self.$updater.find('.js-statusText');
            self.$start = self.$updater.find('.js-start');
            self.stepIds = Object.keys(self.steps);
            self.totalSteps = self.stepIds.length;

            self.bindEvents();

            console.log('Init', self, self.$updater);
        },

        bindEvents: function () {
            var self = this;
            self.$start.on('click', function (e) {
                event.preventDefault();

                self.start();
            });
        },

        start: function () {
            var self = this;

            self.reset();
            self.disableStart();

            if (!self.isVisible) {
                self.show();
            }

            self.next();
        },

        next: function () {
            var self = this;
            var step = null;
            var stepKey = null;
            self.currentStep = self.currentStep + 1;

            if (self.currentStep > (self.totalSteps - 1)) {
                //self.stop();
                self.$updater.addClass('is-done');
                self.updateStatus('Database is updated');
                self.enableStart();

                if (self.$dbWarning.length) {
                    self.$dbWarning.slideUp(500, function() {
                        self.$dbWarning.remove();
                    });
                }

                return false;
            }

            stepKey = self.stepIds[self.currentStep];
            step = self.steps[stepKey];

            self.updateStatus(step.text);

            Craft.postActionRequest(step.action, {}, function (response) {
                console.log(response);

                if (!response) {
                    self.updateErrorStatus('There occurred an error when running the step. Check the plugin logs (countryredirect.log) for further information.');
                    self.enableStart();

                    return false;
                }

                if (response.hasOwnProperty('success')) {
                    self.next();
                } else {
                    self.updateErrorStatus(response.error);
                    self.enableStart();

                    return false;
                }
            });
        },

        updateStatus: function (text) {
            this.$statusText.text(text);
        },

        updateErrorStatus: function (error) {
            this.$status.addClass('error');
            this.updateStatus(error);
        },

        show: function () {
            //this.$status.addClass('is-visible');
            this.$status.slideDown(500, function() {
            });
        },

        hide: function () {
            this.$status.slideUp(500);
        },

        disableStart: function () {
            this.$start.prop('disabled', true);
        },

        enableStart: function () {
            this.$start.prop('disabled', false);
        },

        reset: function () {
            this.$status.removeClass('error');
            this.$status.removeClass('is-visible');
            this.$statusText.text('');
        }
    }

    console.log(jQuery);

    var $updater = $('[data-audit-updater]');
    if ($updater.length) {
        AuditDatabaseUpdater.init($updater);
    }
})(window, Craft, jQuery);