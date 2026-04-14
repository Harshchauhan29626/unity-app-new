(function () {
    'use strict';

    const FILTER_FORM_CLASS = 'admin-filter-form';
    const SEARCHABLE_SELECT_CLASS = 'js-searchable-select';

    function isLikelyFilterForm(form) {
        if (!(form instanceof HTMLFormElement)) {
            return false;
        }

        if ((form.getAttribute('method') || 'GET').toUpperCase() !== 'GET') {
            return false;
        }

        const idNameAction = [form.id, form.getAttribute('name'), form.getAttribute('action')].join(' ').toLowerCase();
        if (idNameAction.includes('export')) {
            return false;
        }

        if (form.dataset.filterForm === 'false' || form.dataset.enterSubmit === 'off') {
            return false;
        }

        const selector = [
            'input[type="text"]',
            'input[type="search"]',
            'input[type="date"]',
            'input[type="number"]',
            'input[type="email"]',
            'input[type="tel"]',
            'select'
        ].join(',');

        if (form.querySelector(selector)) {
            return true;
        }

        if (!form.id) {
            return false;
        }

        return !!document.querySelector(`${selector}[form="${form.id}"]`);
    }

    function markAdminFilterForms() {
        document.querySelectorAll('form').forEach(function (form) {
            if (isLikelyFilterForm(form)) {
                form.classList.add(FILTER_FORM_CLASS);
            }
        });
    }

    function resolvePlaceholder(select) {
        if (select.dataset.placeholder) {
            return select.dataset.placeholder;
        }

        const firstOption = select.options[0];
        if (firstOption && firstOption.value === '') {
            return (firstOption.textContent || '').trim();
        }

        return '';
    }

    function shouldEnableSearchableSelect(select) {
        if (!(select instanceof HTMLSelectElement)) {
            return false;
        }

        if (select.disabled || select.multiple || select.size > 1) {
            return false;
        }

        if (select.classList.contains('select2-hidden-accessible') || select.classList.contains('js-no-searchable-select')) {
            return false;
        }

        if (select.classList.contains(SEARCHABLE_SELECT_CLASS)) {
            return true;
        }

        return select.options.length >= 8;
    }

    function getFilterSelectsForForm(form) {
        const selects = Array.from(form.querySelectorAll('select'));
        if (!form.id) {
            return selects;
        }

        const attached = Array.from(document.querySelectorAll(`select[form="${form.id}"]`));
        return selects.concat(attached.filter(function (select) {
            return !selects.includes(select);
        }));
    }

    function initFilterSelects() {
        if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
            return;
        }

        document.querySelectorAll(`form.${FILTER_FORM_CLASS}`).forEach(function (form) {
            getFilterSelectsForForm(form).forEach(function (select) {
                if (!shouldEnableSearchableSelect(select)) {
                    return;
                }

                const placeholder = resolvePlaceholder(select);
                const config = {
                    width: '100%',
                    minimumResultsForSearch: 0,
                };

                if (placeholder) {
                    config.placeholder = placeholder;
                    config.allowClear = true;
                }

                window.jQuery(select).select2(config);
            });
        });
    }

    function isInteractiveTypingField(target) {
        if (!(target instanceof HTMLElement)) {
            return false;
        }

        if (target.tagName === 'TEXTAREA' || target.isContentEditable) {
            return true;
        }

        if (target.classList.contains('select2-search__field')) {
            return true;
        }

        if (target.closest('.select2-container--open')) {
            return true;
        }

        return false;
    }

    function resolveFilterFormFromTarget(target) {
        if (!(target instanceof HTMLElement)) {
            return null;
        }

        if (target.form && target.form.classList.contains(FILTER_FORM_CLASS)) {
            return target.form;
        }

        const nearest = target.closest(`form.${FILTER_FORM_CLASS}`);
        if (nearest) {
            return nearest;
        }

        const formId = target.getAttribute('form');
        if (!formId) {
            return null;
        }

        const linked = document.getElementById(formId);
        if (linked && linked.classList.contains(FILTER_FORM_CLASS)) {
            return linked;
        }

        return null;
    }

    function bindEnterSubmit() {
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            const target = event.target;
            if (isInteractiveTypingField(target)) {
                return;
            }

            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (!target.matches('input, select')) {
                return;
            }

            if (target.matches('input[type="submit"], input[type="button"], input[type="reset"], input[type="file"]')) {
                return;
            }

            const form = resolveFilterFormFromTarget(target);
            if (!form) {
                return;
            }

            event.preventDefault();
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.submit();
        });
    }

    function boot() {
        markAdminFilterForms();
        initFilterSelects();
        bindEnterSubmit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
