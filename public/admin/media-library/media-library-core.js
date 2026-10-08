(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.DendrilaMediaCore = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    class MediaApiError extends Error {
        constructor(message, status = 0, code = 'network_error', payload = null) {
            super(message);
            this.name = 'MediaApiError';
            this.status = status;
            this.code = code;
            this.payload = payload;
        }
    }

    function requestId() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        const random = Math.random().toString(16).slice(2);
        return 'web-' + Date.now().toString(36) + '-' + random;
    }

    function buildMediaQuery(moduleId, filters) {
        const params = new URLSearchParams();
        params.set('module_id', moduleId);
        const stringKeys = ['id', 'query', 'mime', 'usage', 'created_from', 'created_to', 'alt', 'sort', 'direction'];
        const numberKeys = ['limit', 'offset', 'min_bytes', 'max_bytes'];
        stringKeys.forEach(function (key) {
            const value = filters[key];
            if (typeof value === 'string' && value !== '' && !(key === 'usage' && value === 'all')
                && !(key === 'alt' && value === 'all')) {
                params.set(key, value);
            }
        });
        numberKeys.forEach(function (key) {
            const value = filters[key];
            if (Number.isInteger(value) && value >= 0) params.set(key, String(value));
        });
        return params.toString();
    }

    class MediaApiClient {
        constructor(options) {
            options = options || {};
            this.baseUrl = new URL(options.apiBase || './', options.documentUrl || (typeof document !== 'undefined' ? document.baseURI : 'https://example.test/'));
            this.token = options.token || '';
            this.fetchImpl = options.fetchImpl || (typeof fetch !== 'undefined' ? fetch.bind(globalThis) : null);
            if (!this.fetchImpl) throw new Error('fetch is required');
        }

        setToken(token) {
            this.token = typeof token === 'string' ? token : '';
        }

        url(path) {
            return new URL(String(path).replace(/^\/+/, ''), this.baseUrl).toString();
        }

        async request(path, options, authenticated) {
            const headers = new Headers((options && options.headers) || {});
            headers.set('X-Lepotager-Protocol', '1');
            if (authenticated !== false) {
                if (!this.token) throw new MediaApiError('Connexion requise.', 401, 'unauthorized');
                headers.set('X-Lepotager-Device-Token', this.token);
            }
            const init = Object.assign({}, options || {}, { headers: headers });
            let response;
            try {
                response = await this.fetchImpl(this.url(path), init);
            } catch (error) {
                throw new MediaApiError('Impossible de joindre le serveur.', 0, 'network_error', error);
            }
            let payload = null;
            try {
                payload = await response.json();
            } catch (_) {
                payload = null;
            }
            if (!response.ok) {
                const problem = payload && payload.error ? payload.error : {};
                throw new MediaApiError(
                    typeof problem.message === 'string' ? problem.message : 'Le serveur a refusé la requête.',
                    response.status,
                    typeof problem.code === 'string' ? problem.code : 'http_error',
                    payload
                );
            }
            return payload || {};
        }

        startAuthentication(username, password, deviceName) {
            return this.request('v1/auth/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: username, password: password, device_name: deviceName })
            }, false);
        }

        verifyAuthentication(challengeId, code) {
            return this.request('v1/auth/verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ challenge_id: challengeId, method: 'totp', code: code })
            }, false);
        }

        pair(code, deviceName) {
            return this.request('v1/auth/pair', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: code, device_name: deviceName })
            }, false);
        }

        configuration() {
            return this.request('v1/config', { method: 'GET' }, true);
        }

        listMedia(moduleId, filters) {
            return this.request('v1/media?' + buildMediaQuery(moduleId, filters || {}), { method: 'GET' }, true);
        }

        async mediaById(moduleId, mediaId) {
            const result = await this.listMedia(moduleId, { id: mediaId, limit: 1, offset: 0 });
            return Array.isArray(result.items) && result.items.length ? result.items[0] : null;
        }

        change(moduleId, action, payload, clientRequestId) {
            return this.request('v1/changes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    module_id: moduleId,
                    action: action,
                    client_request_id: clientRequestId || requestId(),
                    payload: payload
                })
            }, true);
        }
    }

    function defaultFieldValue(field) {
        if (field.type === 'boolean') return false;
        if (field.type === 'number') return '';
        if (field.type === 'single_choice') {
            return Array.isArray(field.choices) && field.choices.length ? field.choices[0].value : '';
        }
        return '';
    }

    function normalizeFieldValue(field, raw) {
        if (field.type === 'number') {
            if (raw === '' || raw === null || typeof raw === 'undefined') return '';
            const value = Number(raw);
            return Number.isFinite(value) ? value : raw;
        }
        if (field.type === 'boolean') return raw === true || raw === 'true';
        return raw === null || typeof raw === 'undefined' ? '' : String(raw);
    }

    class MediaEditorSession {
        constructor(item, fields) {
            this.item = item;
            this.fields = Array.isArray(fields) ? fields.slice() : [];
            this.expectedRevision = Number(item.revision);
            this.values = {};
            this.conflict = null;
            const self = this;
            this.fields.forEach(function (field) {
                self.values[field.id] = Object.prototype.hasOwnProperty.call(item, field.id)
                    ? normalizeFieldValue(field, item[field.id])
                    : defaultFieldValue(field);
            });
        }

        setValue(id, value) {
            if (!this.fields.some(function (field) { return field.id === id; })) {
                throw new Error('Unknown media field: ' + id);
            }
            this.values[id] = value;
        }

        payload() {
            const payload = {
                item_id: this.item.id,
                parent_id: this.item.parent_id,
                expected_revision: this.expectedRevision
            };
            const self = this;
            this.fields.forEach(function (field) {
                payload[field.id] = normalizeFieldValue(field, self.values[field.id]);
            });
            return payload;
        }

        actionPayload() {
            return {
                item_id: this.item.id,
                parent_id: this.item.parent_id,
                expected_revision: this.expectedRevision
            };
        }

        markConflict(serverItem) {
            if (!serverItem || serverItem.id !== this.item.id) throw new Error('Invalid conflict item.');
            this.conflict = serverItem;
        }

        adoptServer() {
            if (!this.conflict) return;
            const next = this.conflict;
            this.item = next;
            this.expectedRevision = Number(next.revision);
            const self = this;
            this.fields.forEach(function (field) {
                self.values[field.id] = Object.prototype.hasOwnProperty.call(next, field.id)
                    ? normalizeFieldValue(field, next[field.id])
                    : defaultFieldValue(field);
            });
            this.conflict = null;
        }

        rebaseLocalAfterReview() {
            if (!this.conflict) return;
            this.item = this.conflict;
            this.expectedRevision = Number(this.conflict.revision);
            this.conflict = null;
        }
    }

    return {
        MediaApiError: MediaApiError,
        MediaApiClient: MediaApiClient,
        MediaEditorSession: MediaEditorSession,
        buildMediaQuery: buildMediaQuery,
        requestId: requestId,
        normalizeFieldValue: normalizeFieldValue
    };
});
