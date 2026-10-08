(function () {
    'use strict';

    const Core = window.DendrilaMediaCore;
    if (!Core) throw new Error('Media core unavailable.');

    const $ = function (id) { return document.getElementById(id); };
    const apiBase = document.querySelector('meta[name="dendrila-api-base"]').getAttribute('content');
    const tokenKey = 'dendrila.media.device_token';
    const viewKey = 'dendrila.media.view';
    const client = new Core.MediaApiClient({
        apiBase: apiBase,
        documentUrl: document.baseURI,
        token: sessionStorage.getItem(tokenKey) || ''
    });

    const state = {
        config: null,
        module: null,
        page: { items: [], total: 0, limit: 24, offset: 0, has_more: false, sort: 'date', direction: 'desc' },
        filters: { limit: 24, offset: 0, sort: 'date', direction: 'desc', usage: 'all', alt: 'all' },
        view: sessionStorage.getItem(viewKey) === 'list' ? 'list' : 'grid',
        editor: null,
        editorTrigger: null,
        pendingMutation: null,
        challengeId: null,
        uploadObjectUrl: null,
        uploadRequestId: null
    };

    function setMessage(element, message, tone) {
        element.textContent = message || '';
        element.hidden = !message;
        element.dataset.tone = tone || '';
    }

    function formatError(error) {
        if (!(error instanceof Core.MediaApiError)) return 'Une erreur inattendue est survenue.';
        if (error.code === 'network_error') return 'Le serveur est inaccessible. Vérifiez votre connexion puis réessayez.';
        if (error.status === 401) return 'Votre session n’est plus valide. Reconnectez-vous.';
        if (error.status === 403) return 'Vous n’avez pas les droits nécessaires pour cette opération.';
        if (error.code === 'media_in_use') return 'Cette image est encore utilisée dans un brouillon ou une publication. Retirez ces usages avant de la supprimer.';
        if (error.code === 'conflict') return 'Cette image a changé depuis son ouverture.';
        if (error.status === 422) return error.message || 'Les données envoyées ne sont pas valides.';
        if (error.status === 503) return 'Ce service n’est pas encore configuré sur ce site.';
        return error.message || 'Le serveur a refusé la requête.';
    }

    function setBusy(buttons, busy) {
        buttons.forEach(function (button) { if (button) button.disabled = busy; });
    }

    function rememberToken(payload) {
        if (!payload || payload.status !== 'authenticated' || typeof payload.device_token !== 'string') {
            throw new Core.MediaApiError('Réponse de connexion incomplète.', 0, 'invalid_auth_response');
        }
        sessionStorage.setItem(tokenKey, payload.device_token);
        client.setToken(payload.device_token);
    }

    function localLogout(message) {
        sessionStorage.removeItem(tokenKey);
        client.setToken('');
        state.config = null;
        state.module = null;
        $('library-panel').hidden = true;
        $('session-actions').hidden = true;
        $('auth-panel').hidden = false;
        if (message) setMessage($('global-message'), message, 'error');
        $('username').focus();
    }

    async function loadSession() {
        setMessage($('global-message'), 'Chargement de la médiathèque…', '');
        try {
            const config = await client.configuration();
            const module = Array.isArray(config.modules)
                ? config.modules.find(function (candidate) { return candidate.kind === 'media_library'; })
                : null;
            if (!module) {
                state.config = config;
                $('auth-panel').hidden = true;
                $('library-panel').hidden = true;
                $('session-actions').hidden = false;
                $('site-name').textContent = config.site && config.site.display_name ? config.site.display_name : '';
                setMessage($('global-message'), 'La médiathèque n’est pas activée pour ce compte ou ce site.', 'error');
                return;
            }
            state.config = config;
            state.module = module;
            $('site-name').textContent = config.site && config.site.display_name ? config.site.display_name : '';
            $('auth-panel').hidden = true;
            $('library-panel').hidden = false;
            $('session-actions').hidden = false;
            $('upload-open').hidden = !(module.media && module.media.upload_enabled === true);
            applyView(state.view);
            setMessage($('global-message'), '', '');
            await loadMedia();
        } catch (error) {
            if (error instanceof Core.MediaApiError && error.status === 401) {
                localLogout('Votre session n’est plus valide.');
                return;
            }
            $('library-panel').hidden = true;
            $('session-actions').hidden = !client.token;
            setMessage($('global-message'), formatError(error), 'error');
        }
    }

    function mbToBytes(value) {
        if (value === '' || value === null || typeof value === 'undefined') return undefined;
        const number = Number(value);
        return Number.isFinite(number) && number >= 0 ? Math.round(number * 1000000) : undefined;
    }

    function filtersFromForm() {
        const data = new FormData($('filters'));
        return {
            query: String(data.get('query') || '').trim(),
            mime: String(data.get('mime') || ''),
            usage: String(data.get('usage') || 'all'),
            alt: String(data.get('alt') || 'all'),
            created_from: String(data.get('created_from') || ''),
            created_to: String(data.get('created_to') || ''),
            min_bytes: mbToBytes(data.get('min_mb')),
            max_bytes: mbToBytes(data.get('max_mb')),
            sort: String(data.get('sort') || 'date'),
            direction: String(data.get('direction') || 'desc'),
            limit: 24,
            offset: 0
        };
    }

    async function loadMedia() {
        if (!state.module) return;
        const results = $('media-results');
        results.setAttribute('aria-busy', 'true');
        results.replaceChildren(makeState('Chargement…', 'loading-state'));
        $('previous-page').disabled = true;
        $('next-page').disabled = true;
        try {
            const page = await client.listMedia(state.module.id, state.filters);
            state.page = page;
            renderResults();
        } catch (error) {
            results.replaceChildren(makeState(formatError(error), 'empty-state'));
            $('results-status').textContent = '';
            if (error instanceof Core.MediaApiError && error.status === 401) {
                localLogout('Votre session n’est plus valide.');
            } else if (error instanceof Core.MediaApiError && error.status === 403 && state.filters.usage !== 'all') {
                setMessage($('global-message'), 'Le filtre d’utilisation nécessite le droit de lire les brouillons. Les autres filtres restent disponibles.', 'error');
            } else {
                setMessage($('global-message'), formatError(error), 'error');
            }
        } finally {
            results.setAttribute('aria-busy', 'false');
        }
    }

    function makeState(text, className) {
        const node = document.createElement('div');
        node.className = className;
        node.textContent = text;
        return node;
    }

    function formatBytes(bytes) {
        const value = Number(bytes);
        if (!Number.isFinite(value)) return '—';
        if (value < 1000) return value + ' o';
        if (value < 1000000) return (value / 1000).toFixed(1) + ' Ko';
        return (value / 1000000).toFixed(1) + ' Mo';
    }

    function formatDate(value) {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('fr-FR', {
            dateStyle: 'medium', timeStyle: 'short'
        }).format(date);
    }

    function activeFilters() {
        return Boolean(state.filters.query || state.filters.mime || state.filters.usage !== 'all'
            || state.filters.alt !== 'all' || state.filters.created_from || state.filters.created_to
            || Number.isInteger(state.filters.min_bytes) || Number.isInteger(state.filters.max_bytes));
    }

    function renderResults() {
        const results = $('media-results');
        results.replaceChildren();
        const items = Array.isArray(state.page.items) ? state.page.items : [];
        if (!items.length) {
            results.appendChild(makeState(
                activeFilters() ? 'Aucune image ne correspond à ces filtres.' : 'La médiathèque est vide. Ajoutez une première image.',
                'empty-state'
            ));
        } else {
            items.forEach(function (item) { results.appendChild(mediaCard(item)); });
        }

        const total = Number(state.page.total) || 0;
        const first = total === 0 ? 0 : Number(state.page.offset) + 1;
        const last = Math.min(total, Number(state.page.offset) + items.length);
        $('results-status').textContent = total === 0 ? '0 image' : first + '–' + last + ' sur ' + total + ' image' + (total > 1 ? 's' : '');
        $('library-summary').textContent = total + ' image' + (total > 1 ? 's' : '') + ' trouvée' + (total > 1 ? 's' : '') + '.';

        const limit = Number(state.page.limit) || 24;
        const offset = Number(state.page.offset) || 0;
        const pageNumber = Math.floor(offset / limit) + 1;
        const pageCount = Math.max(1, Math.ceil(total / limit));
        $('page-label').textContent = 'Page ' + pageNumber + ' sur ' + pageCount;
        $('previous-page').disabled = offset <= 0;
        $('next-page').disabled = !state.page.has_more;
    }

    function mediaCard(item) {
        const article = document.createElement('article');
        article.className = 'media-card';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'card-button';
        const title = String(item.title || item.filename || 'Image');
        button.setAttribute('aria-label', 'Modifier ' + title);
        button.addEventListener('click', function () { openEditor(item, button); });

        const image = document.createElement('img');
        image.className = 'media-thumb';
        image.src = item.thumb || item.path || '';
        image.alt = '';
        image.loading = 'lazy';

        const info = document.createElement('div');
        info.className = 'media-info';
        const heading = document.createElement('h3');
        heading.className = 'media-title';
        heading.textContent = title;
        const meta = document.createElement('p');
        meta.className = 'media-meta';
        meta.textContent = String(item.mime || '') + ' · ' + formatBytes(item.bytes) + ' · ' + formatDate(item.created_at);
        info.append(heading, meta);
        if (!String(item.alt || '').trim()) {
            const badge = document.createElement('span');
            badge.className = 'badge warning';
            badge.textContent = 'Description alternative manquante';
            info.appendChild(badge);
        }
        button.append(image, info);
        article.appendChild(button);
        return article;
    }

    function applyView(view) {
        state.view = view === 'list' ? 'list' : 'grid';
        sessionStorage.setItem(viewKey, state.view);
        $('media-results').classList.toggle('is-list', state.view === 'list');
        $('grid-view').classList.toggle('is-active', state.view === 'grid');
        $('list-view').classList.toggle('is-active', state.view === 'list');
        $('grid-view').setAttribute('aria-pressed', String(state.view === 'grid'));
        $('list-view').setAttribute('aria-pressed', String(state.view === 'list'));
    }

    function fieldControl(field, value, prefix) {
        const wrapper = document.createElement('label');
        wrapper.textContent = field.label || field.id;
        const id = prefix + '-' + field.id;
        let input;
        if (field.type === 'multiline') {
            input = document.createElement('textarea');
        } else if (field.type === 'single_choice') {
            input = document.createElement('select');
            (field.choices || []).forEach(function (choice) {
                const option = document.createElement('option');
                option.value = choice.value;
                option.textContent = choice.label;
                input.appendChild(option);
            });
        } else {
            input = document.createElement('input');
            input.type = field.type === 'number' ? 'number' : field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'text';
            if (field.type === 'number') {
                if (typeof field.min === 'number') input.min = String(field.min);
                if (typeof field.max === 'number') input.max = String(field.max);
            }
        }
        input.id = id;
        input.dataset.fieldId = field.id;
        if (field.required) input.required = true;
        if (typeof field.max_length === 'number' && input.tagName !== 'SELECT') input.maxLength = field.max_length;
        input.value = value === null || typeof value === 'undefined' ? '' : String(value);
        wrapper.appendChild(input);
        if (field.hint) {
            const hint = document.createElement('span');
            hint.className = 'hint';
            hint.textContent = field.hint;
            wrapper.appendChild(hint);
        }
        return wrapper;
    }

    function renderEditor() {
        const session = state.editor;
        if (!session) return;
        const item = session.item;
        $('edit-title').textContent = item.title || item.filename || 'Modifier l’image';
        $('edit-image').src = item.path || item.thumb || '';
        $('edit-image').alt = '';

        const details = $('edit-details');
        details.replaceChildren();
        [
            ['Fichier', item.filename],
            ['Original', item.original_width + ' × ' + item.original_height + ' px'],
            ['Taille originale', formatBytes(item.bytes)],
            ['Image optimisée', item.variants && item.variants.web ? item.variants.web.width + ' × ' + item.variants.web.height + ' px · ' + formatBytes(item.variants.web.bytes) : '—'],
            ['Mise à jour', formatDate(item.updated_at)],
            ['Révision', item.revision]
        ].forEach(function (entry) {
            const dt = document.createElement('dt'); dt.textContent = entry[0];
            const dd = document.createElement('dd'); dd.textContent = String(entry[1] == null ? '—' : entry[1]);
            details.append(dt, dd);
        });

        const fields = $('edit-fields');
        fields.replaceChildren();
        session.fields.forEach(function (field) {
            fields.appendChild(fieldControl(field, session.values[field.id], 'edit'));
        });
        fields.querySelectorAll('[data-field-id]').forEach(function (input) {
            input.addEventListener('input', function () {
                state.pendingMutation = null;
                session.setValue(input.dataset.fieldId, input.value);
            });
            input.addEventListener('change', function () {
                state.pendingMutation = null;
                session.setValue(input.dataset.fieldId, input.value);
            });
        });

        const allowUpdate = state.module.options && state.module.options.allow_update === true;
        $('save-media').hidden = !allowUpdate;
        $('rotate-left').hidden = !(state.module.options && state.module.options.allow_rotate === true);
        $('rotate-right').hidden = !(state.module.options && state.module.options.allow_rotate === true);
        $('delete-media').hidden = !(state.module.options && state.module.options.allow_delete === true);
        $('edit-conflict').hidden = !session.conflict;
    }

    function openEditor(item, trigger) {
        state.editor = new Core.MediaEditorSession(item, state.module.fields || []);
        state.editorTrigger = trigger;
        state.pendingMutation = null;
        setMessage($('edit-message'), '', '');
        $('edit-conflict').hidden = true;
        renderEditor();
        $('edit-dialog').showModal();
    }

    function closeEditor() {
        if ($('edit-dialog').open) $('edit-dialog').close();
        const trigger = state.editorTrigger;
        state.editor = null;
        state.editorTrigger = null;
        state.pendingMutation = null;
        if (trigger && document.contains(trigger)) trigger.focus();
    }

    function collectEditorValues() {
        if (!state.editor) return;
        $('edit-fields').querySelectorAll('[data-field-id]').forEach(function (input) {
            state.editor.setValue(input.dataset.fieldId, input.value);
        });
    }

    function mutationKey(action, payload) {
        return action + ':' + JSON.stringify(payload);
    }

    async function mutateEditor(action) {
        const session = state.editor;
        if (!session) return;
        if (action === 'update_item') collectEditorValues();
        const payload = action === 'update_item' ? session.payload() : session.actionPayload();
        const key = mutationKey(action, payload);
        if (!state.pendingMutation || state.pendingMutation.key !== key) {
            state.pendingMutation = { key: key, id: Core.requestId() };
        }
        const id = state.pendingMutation.id;
        setBusy([$ ('save-media'), $('rotate-left'), $('rotate-right'), $('delete-media')], true);
        setMessage($('edit-message'), 'Enregistrement…', '');
        try {
            await client.change(state.module.id, action, payload, id);
            state.pendingMutation = null;
            if (action === 'delete_item') {
                closeEditor();
                setMessage($('global-message'), 'Image retirée de la médiathèque.', 'success');
                await loadMedia();
                return;
            }
            const fresh = await client.mediaById(state.module.id, session.item.id);
            if (fresh) state.editor = new Core.MediaEditorSession(fresh, state.module.fields || []);
            setMessage($('edit-message'), action === 'update_item' ? 'Modifications enregistrées.' : 'Rotation appliquée.', 'success');
            renderEditor();
            await loadMedia();
        } catch (error) {
            if (error instanceof Core.MediaApiError && error.status === 409 && error.code === 'conflict') {
                state.pendingMutation = null;
                try {
                    const fresh = await client.mediaById(state.module.id, session.item.id);
                    if (fresh) {
                        session.markConflict(fresh);
                        $('edit-conflict').hidden = false;
                        setMessage($('edit-message'), 'Vos saisies n’ont pas été écrasées.', 'error');
                    } else {
                        setMessage($('edit-message'), 'Cette image n’est plus disponible.', 'error');
                    }
                } catch (reloadError) {
                    setMessage($('edit-message'), formatError(reloadError), 'error');
                }
            } else {
                if (!(error instanceof Core.MediaApiError) || (error.status !== 0 && error.status < 500)) {
                    state.pendingMutation = null;
                }
                setMessage($('edit-message'), formatError(error), 'error');
            }
        } finally {
            setBusy([$ ('save-media'), $('rotate-left'), $('rotate-right'), $('delete-media')], false);
        }
    }

    function resetUpload() {
        if (state.uploadObjectUrl) URL.revokeObjectURL(state.uploadObjectUrl);
        state.uploadObjectUrl = null;
        state.uploadRequestId = null;
        $('upload-form').reset();
        $('upload-after-file').hidden = true;
        $('upload-submit').disabled = true;
        $('upload-fields').replaceChildren();
        $('upload-progress-wrap').hidden = true;
        $('upload-progress').value = 0;
        $('upload-progress-text').textContent = '0 %';
        setMessage($('upload-message'), '', '');
    }

    function uploadFields() {
        const values = {};
        $('upload-fields').querySelectorAll('[data-field-id]').forEach(function (input) {
            const field = (state.module.media.fields || []).find(function (candidate) { return candidate.id === input.dataset.fieldId; });
            values[input.dataset.fieldId] = Core.normalizeFieldValue(field, input.value);
        });
        return values;
    }

    function prepareUpload(file) {
        setMessage($('upload-message'), '', '');
        state.uploadRequestId = null;
        if (!file) {
            $('upload-after-file').hidden = true;
            $('upload-submit').disabled = true;
            return;
        }
        const media = state.module.media || {};
        const accepted = Array.isArray(media.accepted_mime_types) ? media.accepted_mime_types : [];
        if (accepted.length && !accepted.includes(file.type)) {
            setMessage($('upload-message'), 'Ce format n’est pas accepté par le site.', 'error');
            $('upload-after-file').hidden = true;
            $('upload-submit').disabled = true;
            return;
        }
        if (Number.isFinite(media.max_bytes) && file.size > media.max_bytes) {
            setMessage($('upload-message'), 'Ce fichier dépasse la taille maximale de ' + formatBytes(media.max_bytes) + '.', 'error');
            $('upload-after-file').hidden = true;
            $('upload-submit').disabled = true;
            return;
        }

        if (state.uploadObjectUrl) URL.revokeObjectURL(state.uploadObjectUrl);
        state.uploadObjectUrl = URL.createObjectURL(file);
        $('upload-preview').src = state.uploadObjectUrl;
        $('upload-preview').alt = '';

        const fields = $('upload-fields');
        fields.replaceChildren();
        (media.fields || []).forEach(function (field) {
            let value = '';
            if (field.id === 'title') value = file.name.replace(/\.[^.]+$/, '');
            fields.appendChild(fieldControl(field, value, 'upload'));
        });
        fields.querySelectorAll('[data-field-id]').forEach(function (input) {
            input.addEventListener('input', function () { state.uploadRequestId = null; });
            input.addEventListener('change', function () { state.uploadRequestId = null; });
        });
        $('upload-after-file').hidden = false;
        $('upload-submit').disabled = false;
    }

    function uploadMedia(file, metadata) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', client.url('v1/media'));
            xhr.setRequestHeader('X-Lepotager-Protocol', '1');
            xhr.setRequestHeader('X-Lepotager-Device-Token', client.token);
            xhr.responseType = 'json';
            xhr.upload.addEventListener('progress', function (event) {
                if (!event.lengthComputable) return;
                const percent = Math.min(100, Math.round((event.loaded / event.total) * 100));
                $('upload-progress').value = percent;
                $('upload-progress-text').textContent = percent + ' %';
            });
            xhr.addEventListener('load', function () {
                const payload = xhr.response || {};
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(payload);
                    return;
                }
                const problem = payload.error || {};
                reject(new Core.MediaApiError(problem.message || 'Envoi refusé.', xhr.status, problem.code || 'http_error', payload));
            });
            xhr.addEventListener('error', function () {
                reject(new Core.MediaApiError('Impossible de joindre le serveur.', 0, 'network_error'));
            });
            const form = new FormData();
            form.set('module_id', state.module.id);
            form.set('item_id', state.module.id);
            form.set('client_request_id', state.uploadRequestId);
            form.set('metadata', JSON.stringify(metadata));
            form.set('media', file, file.name);
            xhr.send(form);
        });
    }

    async function submitUpload() {
        const file = $('upload-file').files && $('upload-file').files[0];
        if (!file) return;
        if (!$('upload-form').reportValidity()) return;
        const metadata = uploadFields();
        if (!state.uploadRequestId) state.uploadRequestId = Core.requestId();
        $('upload-submit').disabled = true;
        $('upload-progress-wrap').hidden = false;
        setMessage($('upload-message'), 'Envoi de l’image…', '');
        try {
            await uploadMedia(file, metadata);
            setMessage($('upload-message'), 'Image ajoutée.', 'success');
            $('upload-dialog').close();
            resetUpload();
            setMessage($('global-message'), 'Image ajoutée à la médiathèque.', 'success');
            await loadMedia();
            $('upload-open').focus();
        } catch (error) {
            if (!(error instanceof Core.MediaApiError) || (error.status !== 0 && error.status < 500)) {
                state.uploadRequestId = null;
            }
            setMessage($('upload-message'), formatError(error), 'error');
            $('upload-submit').disabled = false;
        }
    }

    $('password-form').addEventListener('submit', async function (event) {
        event.preventDefault();
        const submit = event.submitter;
        setBusy([submit], true);
        setMessage($('global-message'), 'Connexion…', '');
        try {
            const payload = await client.startAuthentication(
                $('username').value.trim(), $('password').value, $('device-name').value.trim()
            );
            $('password').value = '';
            if (payload.status === 'mfa_required') {
                state.challengeId = payload.challenge_id;
                $('password-form').hidden = true;
                $('totp-form').hidden = false;
                setMessage($('global-message'), 'Saisissez le code de vérification.', '');
                $('totp-code').focus();
            } else {
                rememberToken(payload);
                await loadSession();
            }
        } catch (error) {
            $('password').value = '';
            setMessage($('global-message'), formatError(error), 'error');
        } finally {
            setBusy([submit], false);
        }
    });

    $('totp-form').addEventListener('submit', async function (event) {
        event.preventDefault();
        const submit = event.submitter;
        setBusy([submit], true);
        try {
            const payload = await client.verifyAuthentication(state.challengeId, $('totp-code').value.trim());
            rememberToken(payload);
            state.challengeId = null;
            $('totp-code').value = '';
            await loadSession();
        } catch (error) {
            $('totp-code').value = '';
            setMessage($('global-message'), formatError(error), 'error');
            $('totp-code').focus();
        } finally {
            setBusy([submit], false);
        }
    });

    $('cancel-totp').addEventListener('click', function () {
        state.challengeId = null;
        $('totp-code').value = '';
        $('totp-form').hidden = true;
        $('password-form').hidden = false;
        $('username').focus();
    });

    $('pair-form').addEventListener('submit', async function (event) {
        event.preventDefault();
        const submit = event.submitter;
        setBusy([submit], true);
        try {
            const payload = await client.pair($('pair-code').value.trim(), $('pair-device-name').value.trim());
            rememberToken(payload);
            $('pair-code').value = '';
            await loadSession();
        } catch (error) {
            $('pair-code').value = '';
            setMessage($('global-message'), formatError(error), 'error');
        } finally {
            setBusy([submit], false);
        }
    });

    $('logout-button').addEventListener('click', function () { localLogout('Session fermée dans ce navigateur.'); });

    $('filters').addEventListener('submit', function (event) {
        event.preventDefault();
        state.filters = filtersFromForm();
        setMessage($('global-message'), '', '');
        loadMedia();
    });
    $('reset-filters').addEventListener('click', function () {
        $('filters').reset();
        state.filters = { limit: 24, offset: 0, sort: 'date', direction: 'desc', usage: 'all', alt: 'all' };
        setMessage($('global-message'), '', '');
        loadMedia();
    });
    $('previous-page').addEventListener('click', function () {
        state.filters.offset = Math.max(0, (Number(state.page.offset) || 0) - (Number(state.page.limit) || 24));
        loadMedia();
    });
    $('next-page').addEventListener('click', function () {
        if (!state.page.has_more) return;
        state.filters.offset = (Number(state.page.offset) || 0) + (Number(state.page.limit) || 24);
        loadMedia();
    });
    $('grid-view').addEventListener('click', function () { applyView('grid'); });
    $('list-view').addEventListener('click', function () { applyView('list'); });

    $('edit-form').addEventListener('submit', function (event) {
        event.preventDefault();
        if ($('edit-form').reportValidity()) mutateEditor('update_item');
    });
    $('edit-close').addEventListener('click', closeEditor);
    $('rotate-left').addEventListener('click', function () { mutateEditor('rotate_left'); });
    $('rotate-right').addEventListener('click', function () { mutateEditor('rotate_right'); });
    $('delete-media').addEventListener('click', function () {
        if (window.confirm('Supprimer cette image de la médiathèque ? Cette action sera refusée si elle est encore utilisée.')) {
            mutateEditor('delete_item');
        }
    });
    $('conflict-adopt').addEventListener('click', function () {
        if (!state.editor || !state.editor.conflict) return;
        state.editor.adoptServer();
        state.pendingMutation = null;
        renderEditor();
        setMessage($('edit-message'), 'La version actuelle du serveur a été rechargée.', '');
    });
    $('conflict-keep').addEventListener('click', function () {
        if (!state.editor || !state.editor.conflict) return;
        collectEditorValues();
        state.editor.rebaseLocalAfterReview();
        state.pendingMutation = null;
        $('edit-conflict').hidden = true;
        setMessage($('edit-message'), 'Vos saisies sont conservées sur la révision actuelle. Vérifiez-les puis enregistrez.', '');
    });
    $('edit-dialog').addEventListener('cancel', function (event) {
        event.preventDefault();
        closeEditor();
    });

    $('upload-open').addEventListener('click', function () {
        resetUpload();
        $('upload-dialog').showModal();
        $('upload-file').focus();
    });
    $('upload-close').addEventListener('click', function () { $('upload-dialog').close(); resetUpload(); $('upload-open').focus(); });
    $('upload-dialog').addEventListener('cancel', function (event) {
        event.preventDefault();
        $('upload-dialog').close();
        resetUpload();
        $('upload-open').focus();
    });
    $('upload-file').addEventListener('change', function () {
        prepareUpload(this.files && this.files[0] ? this.files[0] : null);
    });
    $('upload-form').addEventListener('submit', function (event) {
        event.preventDefault();
        submitUpload();
    });

    $('device-name').value = 'Navigateur web';
    $('pair-device-name').value = 'Navigateur web';
    applyView(state.view);
    if (client.token) loadSession();
    else $('username').focus();
})();
