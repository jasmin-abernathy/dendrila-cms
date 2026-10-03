(function () {
    'use strict';

    var STORAGE_KEY = 'dendrila_privacy_consent_v1';
    var root = document.getElementById('dendrila-privacy-consent');
    var configNode = document.getElementById('dendrila-privacy-consent-config');
    if (!root || !configNode) {
        return;
    }

    var config;
    try {
        config = JSON.parse(configNode.textContent || '{}');
    } catch (_error) {
        return;
    }
    if (!config || typeof config.fingerprint !== 'string' || !Array.isArray(config.categories)) {
        return;
    }

    var categories = config.categories.filter(function (category, index, list) {
        return typeof category === 'string' && list.indexOf(category) === index;
    });
    var memoryChoice = null;
    var opener = null;
    var dialog = root.querySelector('[role="dialog"]');
    var summaryView = root.querySelector('[data-dendrila-consent-view="summary"]');
    var preferencesView = root.querySelector('[data-dendrila-consent-view="preferences"]');

    function emptyCategories() {
        var state = {};
        categories.forEach(function (category) {
            state[category] = false;
        });
        return state;
    }

    function normalizedCategories(input) {
        var state = emptyCategories();
        categories.forEach(function (category) {
            state[category] = !!(input && input[category] === true);
        });
        return state;
    }

    function safeParse(value) {
        try {
            return JSON.parse(value);
        } catch (_error) {
            return null;
        }
    }

    function validChoice(choice) {
        return !!choice
            && choice.version === 1
            && choice.fingerprint === config.fingerprint
            && typeof choice.expiresAt === 'number'
            && choice.expiresAt > Date.now()
            && choice.categories
            && typeof choice.categories === 'object';
    }

    function readChoice() {
        if (validChoice(memoryChoice)) {
            return memoryChoice;
        }
        try {
            var stored = safeParse(window.localStorage.getItem(STORAGE_KEY));
            if (!validChoice(stored)) {
                if (stored) {
                    window.localStorage.removeItem(STORAGE_KEY);
                }
                return null;
            }
            stored.categories = normalizedCategories(stored.categories);
            memoryChoice = stored;
            return stored;
        } catch (_error) {
            return null;
        }
    }

    function storeChoice(choice) {
        memoryChoice = choice;
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(choice));
        } catch (_error) {
            // The current page can still respect the choice even when storage is unavailable.
        }
    }

    function emit(name, detail) {
        document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    }

    function decodeBase64Utf8(value) {
        var binary = window.atob(value);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i += 1) {
            bytes[i] = binary.charCodeAt(i);
        }
        if (window.TextDecoder) {
            return new TextDecoder('utf-8').decode(bytes);
        }
        var encoded = '';
        bytes.forEach(function (byte) {
            encoded += '%' + byte.toString(16).padStart(2, '0');
        });
        return decodeURIComponent(encoded);
    }

    function activateScript(node) {
        var script = document.createElement('script');
        var src = node.getAttribute('data-dendrila-src');
        var inline = node.getAttribute('data-dendrila-inline-base64');
        if (src) {
            script.src = src;
        } else if (inline) {
            script.text = decodeBase64Utf8(inline);
        } else {
            return;
        }
        if (node.getAttribute('data-dendrila-async') === '1') {
            script.async = true;
        }
        if (node.getAttribute('data-dendrila-defer') === '1') {
            script.defer = true;
        }
        node.setAttribute('data-dendrila-activated', '1');
        node.replaceWith(script);
    }

    function activateIframe(node) {
        var src = node.getAttribute('data-dendrila-src');
        if (!src) {
            return;
        }
        node.src = src;
        node.hidden = false;
        node.setAttribute('data-dendrila-activated', '1');
        var wrapper = node.closest('[data-dendrila-consent-placeholder]');
        if (wrapper) {
            var copy = wrapper.querySelector('[data-dendrila-placeholder-copy]');
            if (copy) {
                copy.hidden = true;
            }
        }
    }

    function activateStylesheet(node) {
        var href = node.getAttribute('data-dendrila-href');
        if (!href) {
            return;
        }
        node.rel = 'stylesheet';
        node.removeAttribute('as');
        node.href = href;
        node.media = node.getAttribute('data-dendrila-media') || 'all';
        node.setAttribute('data-dendrila-activated', '1');
    }

    function activateGeneric(node) {
        var src = node.getAttribute('data-dendrila-src');
        if (src && 'src' in node) {
            node.src = src;
        }
        node.hidden = false;
        node.setAttribute('data-dendrila-activated', '1');
    }

    function applyChoice(choice) {
        if (!validChoice(choice)) {
            return;
        }
        document.querySelectorAll('[data-dendrila-consent-category]').forEach(function (node) {
            var category = node.getAttribute('data-dendrila-consent-category');
            if (!choice.categories[category] || node.getAttribute('data-dendrila-activated') === '1') {
                return;
            }
            if (node.tagName === 'SCRIPT') {
                activateScript(node);
            } else if (node.tagName === 'IFRAME') {
                activateIframe(node);
            } else if (node.tagName === 'LINK') {
                activateStylesheet(node);
            } else {
                activateGeneric(node);
            }
        });
    }

    function shouldReloadAfterRevocation(previous, next) {
        if (!previous) {
            return false;
        }
        return categories.some(function (category) {
            return previous.categories[category] === true && next[category] !== true;
        });
    }

    function close() {
        if (root.hidden) {
            return;
        }
        root.hidden = true;
        document.documentElement.classList.remove('dendrila-privacy-consent-open');
        emit('dendrila-privacy:consent-closed');
        if (opener && document.contains(opener) && typeof opener.focus === 'function') {
            opener.focus();
        }
        opener = null;
    }

    function populatePreferences(choice) {
        var state = choice ? normalizedCategories(choice.categories) : emptyCategories();
        root.querySelectorAll('[data-dendrila-consent-category-input]').forEach(function (input) {
            var category = input.getAttribute('data-dendrila-consent-category-input');
            input.checked = state[category] === true;
        });
    }

    function showView(name) {
        if (summaryView) {
            summaryView.hidden = name !== 'summary';
        }
        if (preferencesView) {
            preferencesView.hidden = name !== 'preferences';
        }
    }

    function open(preferences) {
        opener = document.activeElement;
        root.hidden = false;
        document.documentElement.classList.add('dendrila-privacy-consent-open');
        if (preferences) {
            populatePreferences(readChoice());
            showView('preferences');
        } else {
            showView('summary');
        }
        emit('dendrila-privacy:consent-opened', { preferences: !!preferences });
        window.setTimeout(function () {
            var target = preferences
                ? root.querySelector('[data-dendrila-consent-category-input]')
                : root.querySelector('button');
            (target || dialog || root).focus();
        }, 0);
    }

    function persist(categoryState) {
        var previous = readChoice();
        var normalized = normalizedCategories(categoryState);
        var retentionDays = Number(config.retentionDays);
        if (!Number.isFinite(retentionDays) || retentionDays < 1) {
            retentionDays = 180;
        }
        var now = Date.now();
        var choice = {
            version: 1,
            fingerprint: config.fingerprint,
            updatedAt: now,
            expiresAt: now + Math.round(retentionDays * 86400000),
            categories: normalized
        };
        storeChoice(choice);
        emit('dendrila-privacy:consent-updated', {
            categories: Object.assign({}, normalized),
            fingerprint: choice.fingerprint
        });
        close();
        if (shouldReloadAfterRevocation(previous, normalized)) {
            window.location.reload();
            return choice;
        }
        applyChoice(choice);
        return choice;
    }

    function savePreferences() {
        var state = emptyCategories();
        root.querySelectorAll('[data-dendrila-consent-category-input]').forEach(function (input) {
            state[input.getAttribute('data-dendrila-consent-category-input')] = input.checked === true;
        });
        return persist(state);
    }

    root.addEventListener('click', function (event) {
        var actionNode = event.target.closest('[data-dendrila-consent-action]');
        if (!actionNode) {
            return;
        }
        var action = actionNode.getAttribute('data-dendrila-consent-action');
        if (action === 'close') {
            close();
        } else if (action === 'preferences') {
            populatePreferences(readChoice());
            showView('preferences');
        } else if (action === 'summary') {
            showView('summary');
        } else if (action === 'accept-all') {
            var accepted = {};
            categories.forEach(function (category) { accepted[category] = true; });
            persist(accepted);
        } else if (action === 'reject-all') {
            persist(emptyCategories());
        }
    });

    if (preferencesView) {
        preferencesView.addEventListener('submit', function (event) {
            event.preventDefault();
            savePreferences();
        });
    }

    document.addEventListener('click', function (event) {
        var openControl = event.target.closest('[data-dendrila-consent-open]');
        if (openControl) {
            event.preventDefault();
            opener = openControl;
            open(openControl.getAttribute('data-dendrila-consent-open') === 'preferences');
            return;
        }

        var allowControl = event.target.closest('[data-dendrila-consent-allow]');
        if (allowControl) {
            event.preventDefault();
            var category = allowControl.getAttribute('data-dendrila-consent-allow');
            if (categories.indexOf(category) === -1) {
                return;
            }
            var current = readChoice();
            var state = current ? normalizedCategories(current.categories) : emptyCategories();
            state[category] = true;
            persist(state);
        }
    }, true);

    root.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab' || !dialog) {
            return;
        }
        var focusable = Array.prototype.slice.call(
            dialog.querySelectorAll('button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])')
        ).filter(function (node) {
            return !node.hidden && node.offsetParent !== null;
        });
        if (focusable.length === 0) {
            event.preventDefault();
            dialog.focus();
            return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.DendrilaPrivacyConsentAPI = {
        open: function () { open(false); },
        openPreferences: function () { open(true); },
        close: close,
        getChoice: function () {
            var choice = readChoice();
            return choice ? JSON.parse(JSON.stringify(choice)) : null;
        },
        saveChoice: function (state) {
            return persist(state || {});
        },
        allows: function (category) {
            var choice = readChoice();
            return !!(choice && choice.categories[category] === true);
        }
    };
    window.DendrilaPrivacyConsent = window.DendrilaPrivacyConsentAPI;

    var initialChoice = readChoice();
    if (initialChoice) {
        applyChoice(initialChoice);
    } else {
        open(false);
    }
}());
