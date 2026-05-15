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
 * @P3.3 Batch row expand/collapse
 *
 * Each <button class="audit-batch-toggle"> toggles `.hidden` on
 * <tr.audit-child-row[data-parent=<id>]> rows. Plain JS (no jQuery)
 * so it doesn't depend on the Craft jQuery bundle being ready.
 */
(function () {
    'use strict';

    function init() {
        var toggles = document.querySelectorAll('.audit-batch-toggle');
        toggles.forEach(function (toggle) {
            // Skip disabled toggles — empty batches have nothing to expand.
            if (toggle.disabled) {
                return;
            }
            toggle.addEventListener('click', onToggle);
        });
    }

    function onToggle(event) {
        event.preventDefault();
        var btn = event.currentTarget;
        var controls = btn.getAttribute('aria-controls') || '';
        var batchId = controls.replace(/^batch-/, '');
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        var next = !expanded;

        btn.setAttribute('aria-expanded', next ? 'true' : 'false');
        btn.setAttribute(
            'aria-label',
            (next ? 'Collapse batch' : 'Expand batch')
        );

        var selector = 'tr.audit-child-row[data-parent="' + cssEscape(batchId) + '"]';
        var children = document.querySelectorAll(selector);
        children.forEach(function (row) {
            row.classList.toggle('hidden', !next);
        });
    }

    function cssEscape(str) {
        if (window.CSS && window.CSS.escape) {
            return window.CSS.escape(str);
        }
        return String(str).replace(/[^a-zA-Z0-9_-]/g, function (c) {
            return '\\' + c;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

