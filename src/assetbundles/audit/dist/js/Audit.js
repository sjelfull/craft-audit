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

/**
 * Batch rows expand and collapse their child rows. Delegated, so rows loaded by the filters work too.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var btn = event.target.closest && event.target.closest('.audit-batch-toggle');
        if (!btn || btn.disabled) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();

        var batchId = (btn.getAttribute('aria-controls') || '').replace(/^batch-/, '');
        var next = btn.getAttribute('aria-expanded') !== 'true';

        btn.setAttribute('aria-expanded', next ? 'true' : 'false');
        btn.setAttribute('aria-label', next ? 'Collapse batch' : 'Expand batch');

        document.querySelectorAll('tr.audit-child-row[data-parent="' + CSS.escape(batchId) + '"]').forEach(function (row) {
            row.classList.toggle('hidden', !next);
        });
    });
})();
