(function () {
    function toNumber(val) {
        var num = parseFloat(val);
        return isFinite(num) ? num : NaN;
    }

    function getStep(input) {
        var step = input.getAttribute('step') || input.getAttribute('data-step') || input.dataset.mpcStep || '1';
        if (step === 'any') {
            step = '1';
        }
        var num = toNumber(step);
        return (isFinite(num) && num > 0) ? num : 1;
    }

    function getMin(input) {
        var min = toNumber(input.getAttribute('min'));
        return isFinite(min) ? min : 0;
    }

    function getMax(input) {
        var max = toNumber(input.getAttribute('max'));
        if (!isFinite(max) || max === 0) {
            return null;
        }
        return max;
    }

    function decimals(num) {
        var s = String(num);
        var i = s.indexOf('.');
        return i === -1 ? 0 : (s.length - i - 1);
    }

    function findQtyInput(btn) {
        var wrapper = btn.closest('.quantity');
        if (wrapper) {
            var input = wrapper.querySelector('input.qty');
            if (input) return input;
        }

        var parent = btn.parentElement;
        if (parent) {
            var input2 = parent.querySelector('input.qty');
            if (input2) return input2;
        }

        return null;
    }

    document.addEventListener('click', function (e) {
        var plus = e.target.closest('.plus');
        var minus = e.target.closest('.minus');
        if (!plus && !minus) return;

        var btn = plus || minus;
        var input = findQtyInput(btn);
        if (!input || input.disabled || input.readOnly) return;

        e.preventDefault();

        var step = getStep(input);
        var min = getMin(input);
        var max = getMax(input);

        var current = toNumber(input.value);
        if (!isFinite(current)) {
            current = min || 0;
        }

        var next = plus ? (current + step) : (current - step);

        if (next < min) next = min;
        if (max !== null && next > max) next = max;

        var places = Math.max(decimals(step), decimals(min));
        if (places > 0) {
            next = parseFloat(next.toFixed(places));
        }

        if (String(next) === String(current)) return;

        input.value = String(next);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
})();
