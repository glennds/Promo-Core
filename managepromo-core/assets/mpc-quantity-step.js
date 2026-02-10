(function () {
    function toNumber(val) {
        var num = parseFloat(val);
        return isFinite(num) ? num : NaN;
    }

    function resolveStep(input) {
        var step = null;
        if (input) {
            if (input.dataset && input.dataset.mpcStep) {
                step = input.dataset.mpcStep;
            }
            if (!step) step = input.getAttribute('data-mpc-step');
            if (!step) step = input.getAttribute('data-step');
            if (!step) step = input.getAttribute('data-qty-step');
            if (!step) step = input.getAttribute('step');
        }
        if (!step) step = '1';
        if (step === 'any') step = '1';
        var num = toNumber(step);
        return (isFinite(num) && num > 0) ? num : 1;
    }

    function getMin(input) {
        var min = toNumber(input.getAttribute('min'));
        return isFinite(min) ? min : 0;
    }

    function getMax(input) {
        var max = toNumber(input.getAttribute('max'));
        if (!isFinite(max) || max === 0) return null;
        return max;
    }

    function getBase(min, input) {
        var base = isFinite(min) ? min : 0;
        if (input) {
            var stored = toNumber(input.getAttribute('data-mpc-base'));
            if (isFinite(stored) && stored > base) {
                base = stored;
            } else {
                var current = toNumber(input.value);
                if (isFinite(current) && current > base) {
                    base = current;
                }
            }
        }
        return base;
    }

    function decimals(num) {
        var s = String(num);
        var i = s.indexOf('.');
        return i === -1 ? 0 : (s.length - i - 1);
    }

    function formatValue(value, step, base) {
        var places = Math.max(decimals(step), decimals(base));
        if (places > 0) {
            value = parseFloat(value.toFixed(places));
        }
        return value;
    }

    function setValue(input, value) {
        if (String(value) === String(input.value)) return;
        if (input.__mpcAdjusting) return;
        input.__mpcAdjusting = true;
        input.value = String(value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.__mpcAdjusting = false;
    }

    function isQtyInput(el) {
        if (!el || !el.tagName || el.tagName !== 'INPUT') return false;
        var type = String(el.getAttribute('type') || '').toLowerCase();
        var name = String(el.getAttribute('name') || '');
        return el.classList.contains('qty') || name === 'quantity' || name.indexOf('quantity') === 0 || type === 'number';
    }

    function getPrev(input) {
        var prev = toNumber(input.getAttribute('data-mpc-prev'));
        if (!isFinite(prev)) {
            prev = toNumber(input.value);
        }
        return isFinite(prev) ? prev : 0;
    }

    function setPrev(input, value) {
        input.setAttribute('data-mpc-prev', String(value));
    }

    function syncStepAttribute(input) {
        var step = resolveStep(input);
        if (String(step) !== String(input.getAttribute('step'))) {
            input.setAttribute('step', String(step));
        }
        if (input.step !== String(step)) {
            input.step = String(step);
        }
        if (String(step) !== String(input.getAttribute('data-step'))) {
            input.setAttribute('data-step', String(step));
        }
        if (String(step) !== String(input.getAttribute('data-qty-step'))) {
            input.setAttribute('data-qty-step', String(step));
        }
    }

    function normalizeValue(input) {
        var step = resolveStep(input);
        var min = getMin(input);
        var max = getMax(input);
        var base = getBase(min, input);

        var current = toNumber(input.value);
        if (!isFinite(current) || current < base) {
            setValue(input, formatValue(base, step, base));
            return;
        }

        var steps = Math.round((current - base) / step);
        var next = base + (steps * step);

        if (max !== null && next > max) {
            next = base + (Math.floor((max - base) / step) * step);
        }
        if (next < base) next = base;

        next = formatValue(next, step, base);
        setValue(input, next);
    }

    function initInput(input) {
        var step = resolveStep(input);
        var min = getMin(input);
        var base = getBase(min, input);

        var current = toNumber(input.value);
        if (!isFinite(current) || current < base) {
            setValue(input, formatValue(base, step, base));
            current = base;
        }
        input.setAttribute('data-mpc-base', String(current));
        setPrev(input, current);
    }

    function handleStepperClick(btn, dir) {
        var wrapper = btn.closest('.quantity');
        var input = wrapper ? wrapper.querySelector('input.qty, input[name="quantity"], input[name^="quantity"]') : null;
        if (!input || input.disabled || input.readOnly) return;

        syncStepAttribute(input);

        var step = resolveStep(input);
        var min = getMin(input);
        var max = getMax(input);
        var currentValue = toNumber(input.value);
        if (!isFinite(currentValue)) currentValue = min;
        if (!isFinite(toNumber(input.getAttribute('data-mpc-base')))) {
            input.setAttribute('data-mpc-base', String(currentValue));
        }
        var base = getBase(min, input);

        var prev = getPrev(input);
        if (prev < base) prev = base;

        var next = (dir === 'up') ? (prev + step) : (prev - step);
        if (next < base) next = base;
        if (max !== null && next > max) next = max;

        next = formatValue(next, step, base);

        // Roll back to previous value then apply our step to bypass default behavior.
        setValue(input, prev);
        setValue(input, next);
        setPrev(input, next);
    }

    document.addEventListener('click', function (e) {
        var inc = e.target.closest('.bde-quantity-button--inc');
        if (inc) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            handleStepperClick(inc, 'up');
            return;
        }

        var dec = e.target.closest('.bde-quantity-button--dec');
        if (dec) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            handleStepperClick(dec, 'down');
            return;
        }
    }, true);

    document.addEventListener('change', function (e) {
        if (!isQtyInput(e.target)) return;
        normalizeValue(e.target);
        setPrev(e.target, toNumber(e.target.value));
    });

    document.addEventListener('blur', function (e) {
        if (!isQtyInput(e.target)) return;
        normalizeValue(e.target);
        setPrev(e.target, toNumber(e.target.value));
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        var inputs = document.querySelectorAll('input.qty, input[name="quantity"], input[name^="quantity"]');
        for (var i = 0; i < inputs.length; i++) {
            if (!isQtyInput(inputs[i])) continue;
            syncStepAttribute(inputs[i]);
            initInput(inputs[i]);
        }
    });

    if (window.jQuery) {
        window.jQuery(document).on('found_variation', '.variations_form', function (event, variation) {
            var form = event.currentTarget;
            if (!form) return;
            var input = form.querySelector('input.qty, input[name="quantity"], input[name^="quantity"]');
            if (!input) return;

            if (variation.step) input.setAttribute('data-mpc-step', variation.step);
            if (variation.min_qty) input.setAttribute('min', variation.min_qty);
            if (variation.max_qty) input.setAttribute('max', variation.max_qty);

            syncStepAttribute(input);
            initInput(input);
        });

        window.jQuery(document).on('reset_data', '.variations_form', function (event) {
            var form = event.currentTarget;
            if (!form) return;
            var input = form.querySelector('input.qty, input[name="quantity"], input[name^="quantity"]');
            if (!input) return;
            syncStepAttribute(input);
            initInput(input);
        });
    }
})();
