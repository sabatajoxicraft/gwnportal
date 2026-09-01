/**
 * Global Form Submission Loading Indicator
 *
 * Portal-wide, reusable replacement for one-off "show a spinner on submit"
 * scripts. Applies automatically to every <form> on the page - no per-page
 * wiring required. Loaded once from every footer variant
 * (includes/components/footer-app.php, footer-auth.php, footer-public.php).
 *
 * What it does:
 * - Listens for the native 'submit' event on every form (bubble phase), so it
 *   never fires when the browser blocks submission via constraint validation
 *   (requirement: don't enter a loading state on failed validation) and it
 *   always runs after any page-local submit handler that may itself call
 *   preventDefault() (e.g. for custom validation or AJAX handling) - if the
 *   submission was cancelled, no loading state is shown.
 * - Decorates the actual clicked submit button (via the SubmitEvent
 *   `submitter`) with an inline SVG spinner + accessible "processing" text,
 *   and disables every other submit control in the same form to block
 *   duplicate submissions.
 * - Also patches HTMLFormElement.prototype.submit() so programmatic
 *   form.submit() calls (commonly used after a confirm() dialog) show a
 *   portal-wide processing cue even though there is no submitter to decorate.
 * - Never touches plain navigation links, Bootstrap modal
 *   open/close/cancel buttons (type="button", no submit event involved), or
 *   AJAX-driven UI (which isn't implemented via form submission).
 *
 * Opt-out:
 *   Add `data-no-loading` to a <form> to skip this behavior entirely for
 *   forms that intentionally manage their own submit UI.
 *   Add `data-no-loading` to a single <button type="submit">/<input
 *   type="submit"> to exclude just that control while the rest of the form
 *   is still tracked.
 *
 * Custom processing text:
 *   Add `data-loading-text="Sending voucher..."` to the submit button (or to
 *   the <form> as a fallback used for every button in it) to override the
 *   default "Processing..." accessible label shown while the request is in
 *   flight.
 *
 * Manual reset (rarely needed):
 *   window.GWNFormLoading.reset() clears every tracked loading state on the
 *   current page, e.g. from an AJAX handler that intercepted the submit and
 *   wants to restore the UI after a failed request without a page reload.
 */
(function () {
    'use strict';

    if (window.__gwnFormLoadingInitialized) {
        return;
    }
    window.__gwnFormLoadingInitialized = true;

    var DEFAULT_TEXT = 'Processing...';
    var SUBMIT_SELECTOR = 'button[type="submit"], input[type="submit"]';

    var SPINNER_SVG =
        '<svg class="gwn-spinner" viewBox="0 0 24 24" width="1em" height="1em" ' +
        'aria-hidden="true" focusable="false">' +
        '<circle class="gwn-spinner-track" cx="12" cy="12" r="9" fill="none" stroke-width="3"></circle>' +
        '<circle class="gwn-spinner-arc" cx="12" cy="12" r="9" fill="none" stroke-width="3" ' +
        'stroke-linecap="round" stroke-dasharray="28 57"></circle>' +
        '</svg>';

    var srStatusEl = null;
    var progressBarEl = null;
    var activeSubmissions = 0;

    function ensureGlobalIndicators() {
        if (progressBarEl) {
            return;
        }

        progressBarEl = document.createElement('div');
        progressBarEl.className = 'gwn-page-loading-bar';
        progressBarEl.setAttribute('aria-hidden', 'true');
        progressBarEl.innerHTML =
            '<svg viewBox="0 0 200 6" preserveAspectRatio="none">' +
            '<line class="gwn-page-loading-bar-fill" x1="0" y1="3" x2="200" y2="3"></line>' +
            '</svg>';

        srStatusEl = document.createElement('div');
        srStatusEl.className = 'gwn-sr-only-status';
        srStatusEl.setAttribute('role', 'status');
        srStatusEl.setAttribute('aria-live', 'polite');

        document.body.appendChild(progressBarEl);
        document.body.appendChild(srStatusEl);
    }

    function announce(text) {
        if (srStatusEl) {
            // Reset then set so repeated identical messages still get announced.
            srStatusEl.textContent = '';
            window.setTimeout(function () {
                srStatusEl.textContent = text;
            }, 30);
        }
    }

    function beginGlobalLoading(text) {
        ensureGlobalIndicators();
        activeSubmissions += 1;
        document.documentElement.classList.add('gwn-loading');
        announce(text || DEFAULT_TEXT);
    }

    function endGlobalLoading() {
        activeSubmissions = Math.max(0, activeSubmissions - 1);
        if (activeSubmissions === 0) {
            document.documentElement.classList.remove('gwn-loading');
        }
    }

    function isOptedOut(el) {
        return !!(el && el.hasAttribute && el.hasAttribute('data-no-loading'));
    }

    function getLoadingText(button, form) {
        if (button && button.getAttribute('data-loading-text')) {
            return button.getAttribute('data-loading-text');
        }
        if (form && form.getAttribute('data-loading-text')) {
            return form.getAttribute('data-loading-text');
        }
        return DEFAULT_TEXT;
    }

    function getSubmitControls(form) {
        try {
            return Array.prototype.slice.call(form.querySelectorAll(SUBMIT_SELECTOR));
        } catch (err) {
            return [];
        }
    }

    /**
     * Puts the clicked submit button into its loading visual state while
     * preserving its original markup so it can be restored (e.g. on bfcache
     * restore) without a full page reload.
     */
    function decorateSubmitter(button, text) {
        if (!button || button.dataset.gwnDecorated === 'true') {
            return;
        }
        button.dataset.gwnDecorated = 'true';
        button.dataset.gwnOriginalHtml = button.innerHTML;
        button.dataset.gwnOriginalWidth = String(button.getBoundingClientRect().width);
        button.style.minWidth = button.dataset.gwnOriginalWidth + 'px';
        button.disabled = true;
        button.classList.add('gwn-btn-loading');
        button.setAttribute('aria-busy', 'true');
        button.innerHTML =
            SPINNER_SVG + '<span class="gwn-btn-loading-text">' + escapeHtml(text) + '</span>';
    }

    function disableDuplicate(button) {
        if (!button || button.dataset.gwnDecorated === 'true') {
            return;
        }
        button.dataset.gwnDecorated = 'true';
        button.disabled = true;
        button.classList.add('gwn-btn-disabled-duplicate');
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    /**
     * Core entry point shared by the 'submit' event handler and the patched
     * HTMLFormElement.prototype.submit(). Marks the form as submitting,
     * decorates the submitter (if any/eligible) and disables the remaining
     * submit controls to block duplicate submissions.
     */
    function activateLoadingState(form, submitter) {
        if (!form || isOptedOut(form)) {
            return;
        }
        if (form.dataset.gwnSubmitting === 'true') {
            return; // Already tracked - avoids double-processing duplicate submits.
        }
        form.dataset.gwnSubmitting = 'true';
        form.classList.add('gwn-form-submitting');

        var controls = getSubmitControls(form);
        var resolvedSubmitter = submitter && controls.indexOf(submitter) !== -1 ? submitter : null;

        if (!resolvedSubmitter) {
            // Implicit submission (e.g. Enter key) with no reported submitter:
            // fall back to the first eligible submit control for visual context.
            for (var i = 0; i < controls.length; i++) {
                if (!controls[i].disabled && !isOptedOut(controls[i])) {
                    resolvedSubmitter = controls[i];
                    break;
                }
            }
        }

        var text = getLoadingText(resolvedSubmitter, form);

        controls.forEach(function (control) {
            if (isOptedOut(control)) {
                return;
            }
            if (control === resolvedSubmitter) {
                decorateSubmitter(control, text);
            } else {
                disableDuplicate(control);
            }
        });

        beginGlobalLoading(text);
    }

    function resetForm(form) {
        if (!form) {
            return;
        }
        if (form.dataset.gwnSubmitting === 'true') {
            endGlobalLoading();
        }
        delete form.dataset.gwnSubmitting;
        form.classList.remove('gwn-form-submitting');

        getSubmitControls(form).forEach(function (control) {
            if (control.dataset.gwnDecorated !== 'true') {
                return;
            }
            control.disabled = false;
            control.removeAttribute('aria-busy');
            control.classList.remove('gwn-btn-loading', 'gwn-btn-disabled-duplicate');
            if (typeof control.dataset.gwnOriginalHtml === 'string') {
                control.innerHTML = control.dataset.gwnOriginalHtml;
            }
            control.style.minWidth = '';
            delete control.dataset.gwnDecorated;
            delete control.dataset.gwnOriginalHtml;
            delete control.dataset.gwnOriginalWidth;
        });
    }

    function resetAll() {
        Array.prototype.slice.call(document.forms).forEach(resetForm);
        activeSubmissions = 0;
        document.documentElement.classList.remove('gwn-loading');
    }

    // Bubble phase (not capture): runs after any page-local submit handler,
    // so a preventDefault() from custom validation/AJAX logic is respected -
    // if the submission was cancelled, e.defaultPrevented is already true and
    // no loading state is shown.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || e.defaultPrevented) {
            return;
        }
        activateLoadingState(form, e.submitter || null);
    });

    // Programmatic form.submit() bypasses the 'submit' event entirely (per
    // the HTML spec) and skips constraint validation, so it needs its own
    // hook to show the portal-wide processing cue (used after confirm()
    // dialogs throughout the manager/admin/student pages).
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        try {
            activateLoadingState(this, null);
        } catch (err) {
            // Never let the loading UI break an actual form submission.
        }
        return nativeSubmit.apply(this, arguments);
    };

    // Restore any lingering loading state when a page is revived from the
    // back/forward cache (bfcache) instead of a fresh navigation.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            resetAll();
        }
    });

    window.GWNFormLoading = {
        reset: resetAll,
        resetForm: resetForm
    };
})();
