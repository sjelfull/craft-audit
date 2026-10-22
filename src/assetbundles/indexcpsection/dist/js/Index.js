/**
 * Audit log screen: live filters and the details slideout.
 */
(function (Craft, Garnish, $) {
    'use strict';

    if (!Craft || !$) {
        return;
    }

    var form = document.getElementById('audit-filters');
    var results = document.getElementById('audit-results');
    var request = null;

    function openLog(id) {
        new Craft.CpScreenSlideout('audit/default/details', {
            params: { id: id },
            containerElement: 'div',
        });
    }

    // Clicking anywhere on a row opens it, except on links and buttons that do their own thing.
    $(document).on('click', '[data-audit-open]', function (event) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button === 1) {
            return;
        }
        var id = this.getAttribute('data-audit-log') || $(this).closest('[data-audit-log]').attr('data-audit-log');
        if (!id) {
            return;
        }
        event.preventDefault();
        openLog(id);
    });

    $(document).on('click', 'tr.audit-row, .audit-history__item', function (event) {
        if ($(event.target).closest('a, button, input, select, label').length || window.getSelection().toString()) {
            return;
        }
        openLog(this.getAttribute('data-audit-log'));
    });

    if (!form || !results) {
        return;
    }

    function isoDate(input) {
        if (input && input.type === 'date') {
            return input.value;
        }
        var date = input && $(input).datepicker('getDate');
        if (!date) {
            return '';
        }
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function params() {
        var data = new URLSearchParams();
        new FormData(form).forEach(function (value, key) {
            if (/^(from|to)\[/.test(key) || value === '') {
                return;
            }
            data.set(key, value);
        });
        ['from', 'to'].forEach(function (name) {
            var value = isoDate(form.querySelector('[name="' + name + '[date]"]'));
            if (value) {
                data.set(name, value);
            }
        });
        // Picking an event narrows the kind, so the kind would only hide it.
        if (data.has('event')) {
            data.delete('kind');
        }
        return data;
    }

    function syncControls() {
        var count = ['kind', 'event', 'userId', 'elementType', 'siteId'].filter(function (name) {
            var field = form.elements[name];
            return field && field.value !== '';
        }).length;
        var badge = form.querySelector('[data-audit-filter-count]');
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
        form.querySelector('[data-audit-reset]').classList.toggle('hidden', params().toString() === '');
    }

    function load(url, push) {
        if (request) {
            request.abort();
        }
        request = new AbortController();
        results.classList.add('audit-results--loading');

        return fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: request.signal,
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                return response.json();
            })
            .then(function (data) {
                results.innerHTML = data.html;
                Craft.initUiElements($(results));
                history[push ? 'pushState' : 'replaceState']({ audit: true }, '', url);
                syncControls();
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    Craft.cp.displayError(Craft.t('app', 'A server error occurred.'));
                }
            })
            .finally(function () {
                results.classList.remove('audit-results--loading');
            });
    }

    function apply() {
        var query = params().toString();
        load(form.action + (query ? (form.action.indexOf('?') === -1 ? '?' : '&') + query : ''), true);
    }

    function clearControl(name) {
        if (name === 'from' || name === 'to') {
            $(form.querySelector('[name="' + name + '[date]"]')).val('');
            return;
        }
        var field = form.elements[name];
        if (field) {
            field.value = '';
        } else {
            var hidden = form.querySelector('input[type=hidden][name="' + name + '"]');
            hidden && hidden.remove();
        }
        if (name === 'event') {
            form.elements.kind.value = '';
        }
    }

    $(form).find('[data-audit-dates] input[name$="[date]"]').each(function () {
        this.placeholder = this.name.indexOf('from') === 0 ? Craft.t('audit', 'From') : Craft.t('audit', 'To');
    });

    var searchTimeout = null;
    form.elements.search.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(apply, 300);
    });

    $(form).on('change', 'select', apply);
    $(form).on('change', '[data-audit-dates] input', function () {
        setTimeout(apply, 0);
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        apply();
    });

    form.querySelector('[data-audit-reset]').addEventListener('click', function (event) {
        event.preventDefault();
        form.reset();
        Array.prototype.forEach.call(form.querySelectorAll('input:not([type=hidden]), select'), function (field) {
            field.value = '';
        });
        Array.prototype.forEach.call(form.querySelectorAll('input[type=hidden][name="elementId"], input[type=hidden][name="sessionId"]'), function (field) {
            field.remove();
        });
        apply();
    });

    $(results).on('click', '[data-audit-remove]', function (event) {
        event.preventDefault();
        clearControl(this.getAttribute('data-audit-remove'));
        apply();
    });

    $(results).on('click', '.pagination a.page-link', function (event) {
        event.preventDefault();
        load(this.href, true);
        results.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });

    window.addEventListener('popstate', function () {
        window.location.reload();
    });

    var trigger = form.querySelector('[data-audit-filter-trigger]');
    if (trigger && Garnish.DisclosureMenu) {
        new Garnish.DisclosureMenu(trigger);
    }
})(window.Craft, window.Garnish, window.jQuery);
