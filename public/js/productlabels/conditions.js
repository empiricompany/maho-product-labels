/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 *
 * Date field switch for the product label conditions widget.
 *
 * A date attribute has two value fields: the date picker (absolute operators) and the value field
 * that holds the day offset (relative operators). Only the one the selected operator uses is
 * shown, and the date picked from the calendar is copied into the value field, which is what the
 * form submits and what the widget prints back after the row is applied.
 */
(function () {
    'use strict';

    const RELATIVE_OPERATORS = ['today_ge', 'today_le'];

    function rowOf(element) {
        const param = element.closest('span.rule-param');
        return param ? param.parentElement : null;
    }

    function valueInput(row) {
        return row.querySelector('input[name*="[value]"]');
    }

    function datePicker(row) {
        return row.querySelector('.pl-rel-date');
    }

    function operatorSelect(row) {
        return row.querySelector('select[name*="[operator]"]');
    }

    function isRelative(row) {
        const operator = operatorSelect(row);
        return !!operator && RELATIVE_OPERATORS.includes(operator.value);
    }

    /**
     * @param {Element} row
     * @param {boolean} reset force the value of the newly selected mode
     */
    function sync(row, reset) {
        const value = valueInput(row);
        const picker = datePicker(row);
        if (!value || !picker) {
            return;
        }

        const relative = isRelative(row);
        picker.style.display = relative ? 'none' : '';
        value.style.display = relative ? '' : 'none';

        if (relative) {
            if (reset || /[^0-9+-]/.test(value.value)) {
                value.value = '0';
            }
            picker.value = '';
            return;
        }

        if (reset || (picker.value !== '' && value.value !== picker.value)) {
            value.value = picker.value;
        }
    }

    /**
     * Rewrites the row summary: the widget writes the raw value when the row closes, which is
     * stale as soon as the operator switches the field.
     */
    function refreshLabel(row) {
        const value = valueInput(row);
        const picker = datePicker(row);
        if (!value) {
            return;
        }
        const label = value.closest('span.rule-param').querySelector('a.label');
        if (!label) {
            return;
        }
        const empty = picker ? (picker.dataset.emptyLabel || '') : '...';
        label.textContent = value.value !== '' ? value.value : empty;
    }

    function init(root) {
        const scope = root instanceof Element ? root : document;
        scope.querySelectorAll('.pl-rel-date').forEach(function (picker) {
            const row = rowOf(picker);
            if (row) {
                sync(row, false);
            }
        });
    }

    document.addEventListener('click', function (event) {
        const button = event.target instanceof Element ? event.target.closest('a.rule-param-apply') : null;
        if (!button) {
            return;
        }
        const row = rowOf(button);
        if (!row) {
            return;
        }
        setTimeout(function () {
            refreshLabel(row);
        }, 0);
    });

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.classList.contains('pl-rel-date')) {
            const row = rowOf(target);
            if (row && !isRelative(row)) {
                const value = valueInput(row);
                if (value) {
                    value.value = target.value;
                }
            }
            return;
        }

        if (typeof target.name !== 'string' || !/\[operator\]$/.test(target.name)) {
            return;
        }
        const row = rowOf(target);
        if (row) {
            sync(row, true);
            refreshLabel(row);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        });
    } else {
        init(document);
    }

    // The widget replaces the row when the attribute changes.
    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    init(node);
                }
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
