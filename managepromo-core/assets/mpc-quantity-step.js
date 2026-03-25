(function () {
    var DEBOUNCE_MS = 1500;
    var QTY_SELECTOR = 'input.qty, input[name="quantity"], input[name^="quantity"], input[name*="[qty]"]';
    var STEP_UP_SELECTOR = '.bde-quantity-button--inc, .plus, .qty-plus, [data-quantity-button="plus"], [data-quantity="plus"], [aria-label="Increase quantity"], [aria-label="increase quantity"]';
    var STEP_DOWN_SELECTOR = '.bde-quantity-button--dec, .minus, .qty-minus, [data-quantity-button="minus"], [data-quantity="minus"], [aria-label="Decrease quantity"], [aria-label="decrease quantity"]';

    function toNumber(value) {
        var number = parseFloat(value);
        return isFinite(number) ? number : NaN;
    }

    function decimals(number) {
        var stringValue = String(number);
        var decimalIndex = stringValue.indexOf(".");
        return decimalIndex === -1 ? 0 : stringValue.length - decimalIndex - 1;
    }

    function resolveStep(input) {
        var step = (input.dataset && input.dataset.mpcStep) || input.getAttribute("data-mpc-step") || input.getAttribute("data-step") || input.getAttribute("data-qty-step") || input.getAttribute("step") || "1";
        step = toNumber(step);
        return isFinite(step) && step > 0 ? step : 1;
    }

    function getMin(input) {
        var min = input.getAttribute("data-mpc-min");
        min = min === null || min === "" ? input.getAttribute("min") : min;
        min = toNumber(min);
        return isFinite(min) ? min : 0;
    }

    function getMax(input) {
        var max = toNumber(input.getAttribute("max"));
        return !isFinite(max) || max === 0 ? null : max;
    }

    function formatNumber(value, input) {
        var max = getMax(input);
        var places = Math.max(decimals(resolveStep(input)), decimals(getMin(input)), max === null ? 0 : decimals(max));
        return places > 0 ? parseFloat(value.toFixed(places)) : parseInt(value, 10);
    }

    function isQtyInput(element) {
        if (!element || element.tagName !== "INPUT") {
            return false;
        }

        var type = String(element.getAttribute("type") || "").toLowerCase();
        var name = String(element.getAttribute("name") || "");

        return type !== "hidden" && (
            element.classList.contains("qty") ||
            name === "quantity" ||
            name.indexOf("quantity[") === 0 ||
            (name.indexOf("cart[") === 0 && name.indexOf("[qty]") !== -1) ||
            name.indexOf("quantity") === 0
        );
    }

    function clearTimer(input) {
        if (!input.__mpcDebounceTimer) {
            return;
        }

        clearTimeout(input.__mpcDebounceTimer);
        input.__mpcDebounceTimer = null;
    }

    function setLastValue(input, value) {
        if (isFinite(value)) {
            input.setAttribute("data-mpc-last-value", String(value));
        }
    }

    function getLastValue(input) {
        var value = toNumber(input.getAttribute("data-mpc-last-value"));
        return isFinite(value) ? value : toNumber(input.value);
    }

    function syncStepAttribute(input) {
        var step = resolveStep(input);

        input.setAttribute("data-mpc-step", String(step));
        input.setAttribute("data-step", String(step));
        input.setAttribute("data-qty-step", String(step));
        input.setAttribute("step", String(step));

        if (input.step !== input.getAttribute("step")) {
            input.step = input.getAttribute("step");
        }
    }

    // Manual input is rounded to the nearest step, then clamped to min/max.
    function normalizeValueNumber(value, input) {
        var step = resolveStep(input);
        var min = getMin(input);
        var max = getMax(input);
        var normalized = value;

        if (!isFinite(normalized)) {
            return formatNumber(min, input);
        }

        if (max !== null && normalized > max) {
            normalized = max;
        }

        if (normalized <= min) {
            normalized = min;
        } else if (step > 1) {
            normalized = Math.round(normalized / step) * step;
            if (normalized < min) {
                normalized = min;
            }
        }

        if (max !== null && normalized > max) {
            normalized = step > 1 ? Math.floor(max / step) * step : max;
            if (normalized < min) {
                normalized = max >= min ? min : max;
            }
        }

        return formatNumber(normalized, input);
    }

    function setValue(input, value) {
        if (String(value) === String(input.value) || input.__mpcAdjusting) {
            return;
        }

        input.__mpcAdjusting = true;
        input.value = String(value);
        setLastValue(input, value);
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.dispatchEvent(new Event("change", { bubbles: true }));
        input.__mpcAdjusting = false;
    }

    function shouldDeferInput(input) {
        var rawValue = String(input.value || "");
        return rawValue === "" || rawValue === "-" || rawValue === "." || rawValue === "-.";
    }

    function normalizeNow(input) {
        clearTimer(input);
        if (input.__mpcAdjusting) {
            return;
        }

        syncStepAttribute(input);
        if (shouldDeferInput(input)) {
            setValue(input, formatNumber(getMin(input), input));
            return;
        }

        var normalizedValue = normalizeValueNumber(toNumber(input.value), input);
        setLastValue(input, normalizedValue);
        setValue(input, normalizedValue);
    }

    // Debounce prevents partial typing like "1" -> "10" -> "100" from being corrected too early.
    function scheduleNormalization(input) {
        var currentValue;
        var normalizedValue;

        clearTimer(input);
        if (input.__mpcAdjusting) {
            return;
        }

        syncStepAttribute(input);
        if (shouldDeferInput(input)) {
            return;
        }

        currentValue = toNumber(input.value);
        if (!isFinite(currentValue)) {
            return;
        }

        normalizedValue = normalizeValueNumber(currentValue, input);
        if (String(normalizedValue) === String(currentValue)) {
            return;
        }

        input.__mpcDebounceTimer = window.setTimeout(function () {
            normalizeNow(input);
        }, DEBOUNCE_MS);
    }

    function getFirstSteppedValue(min, step) {
        var firstValue = Math.ceil(min / step) * step;
        return firstValue < min ? min : firstValue;
    }

    function detectNativeStepDirection(event, input) {
        var previousValue;
        var currentValue;

        if (resolveStep(input) <= 1) {
            return null;
        }

        if (event && typeof event.inputType === "string" && (event.inputType.indexOf("insert") === 0 || event.inputType.indexOf("delete") === 0)) {
            return null;
        }

        previousValue = getLastValue(input);
        currentValue = toNumber(input.value);

        if (!isFinite(previousValue) || !isFinite(currentValue)) {
            return null;
        }

        if (currentValue > previousValue) {
            return "up";
        }

        if (currentValue < previousValue) {
            return "down";
        }

        return null;
    }

    function getSteppedButtonValue(input, direction) {
        var step = resolveStep(input);
        var min = getMin(input);
        var max = getMax(input);
        var currentValue = normalizeValueNumber(toNumber(input.value), input);
        var nextValue = currentValue;

        if (step <= 1) {
            nextValue = currentValue + (direction === "up" ? 1 : -1);
            if (nextValue < min) {
                nextValue = min;
            }
            if (max !== null && nextValue > max) {
                nextValue = max;
            }
            return formatNumber(nextValue, input);
        }

        if (direction === "up") {
            if (currentValue <= min) {
                nextValue = getFirstSteppedValue(min, step);
                if (nextValue <= currentValue) {
                    nextValue = currentValue + step;
                }
            } else {
                nextValue = Math.ceil(currentValue / step) * step;
                if (nextValue <= currentValue) {
                    nextValue += step;
                }
            }
        } else if (currentValue <= min) {
            nextValue = min;
        } else if (Math.abs((currentValue / step) - Math.round(currentValue / step)) > 0.000001) {
            nextValue = Math.floor(currentValue / step) * step;
        } else {
            nextValue = currentValue - step;
            if (nextValue < min) {
                nextValue = min;
            }
        }

        if (max !== null && nextValue > max) {
            nextValue = Math.floor(max / step) * step;
            if (nextValue < min) {
                nextValue = max >= min ? min : max;
            }
        }

        return formatNumber(nextValue, input);
    }

    function handleStepperClick(button, direction) {
        var wrapper = button.closest(".quantity");
        var input = wrapper ? wrapper.querySelector(QTY_SELECTOR) : null;

        if (!input || input.disabled || input.readOnly) {
            return;
        }

        clearTimer(input);
        syncStepAttribute(input);
        setValue(input, getSteppedButtonValue(input, direction));
    }

    function initInput(input) {
        if (!isQtyInput(input)) {
            return;
        }

        clearTimer(input);
        syncStepAttribute(input);

        if (shouldDeferInput(input)) {
            setValue(input, formatNumber(getMin(input), input));
            return;
        }

        var normalizedValue = normalizeValueNumber(toNumber(input.value), input);
        setLastValue(input, normalizedValue);
        if (String(normalizedValue) !== String(input.value)) {
            setValue(input, normalizedValue);
        }
    }

    document.addEventListener("click", function (event) {
        var increaseButton = event.target.closest(STEP_UP_SELECTOR);
        var decreaseButton;

        if (increaseButton) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            handleStepperClick(increaseButton, "up");
            return;
        }

        decreaseButton = event.target.closest(STEP_DOWN_SELECTOR);
        if (!decreaseButton) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        handleStepperClick(decreaseButton, "down");
    }, true);

    document.addEventListener("input", function (event) {
        var direction;
        var previousValue;

        if (!isQtyInput(event.target) || event.target.__mpcAdjusting) {
            return;
        }

        direction = detectNativeStepDirection(event, event.target);
        if (!direction) {
            scheduleNormalization(event.target);
            return;
        }

        previousValue = getLastValue(event.target);
        event.target.value = String(previousValue);
        handleStepperClick(event.target, direction);
    });

    document.addEventListener("change", function (event) {
        if (isQtyInput(event.target) && !event.target.__mpcAdjusting) {
            normalizeNow(event.target);
        }
    });

    document.addEventListener("blur", function (event) {
        if (isQtyInput(event.target) && !event.target.__mpcAdjusting) {
            normalizeNow(event.target);
        }
    }, true);

    document.addEventListener("DOMContentLoaded", function () {
        var inputs = document.querySelectorAll(QTY_SELECTOR);
        var index;

        for (index = 0; index < inputs.length; index += 1) {
            initInput(inputs[index]);
        }
    });

    if (!window.jQuery) {
        return;
    }

    // Variations replace the allowed step/min values dynamically.
    window.jQuery(document).on("found_variation", ".variations_form", function (event, variation) {
        var form = event.currentTarget;
        var input = form ? form.querySelector('input.qty, input[name="quantity"]') : null;

        if (!input) {
            return;
        }

        if (variation.step) {
            input.setAttribute("data-mpc-step", variation.step);
        }
        if (variation.min_qty !== undefined) {
            input.setAttribute("min", variation.min_qty);
            input.setAttribute("data-mpc-min", variation.min_qty);
        }
        if (variation.max_qty !== undefined) {
            input.setAttribute("max", variation.max_qty);
        }

        initInput(input);
    });

    window.jQuery(document).on("reset_data", ".variations_form", function (event) {
        var form = event.currentTarget;
        var input = form ? form.querySelector('input.qty, input[name="quantity"]') : null;

        if (input) {
            initInput(input);
        }
    });
})();
