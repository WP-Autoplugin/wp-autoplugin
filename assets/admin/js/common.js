(function () {
    'use strict';

    const supportedImageModels = Array.isArray(window.wp_autoplugin?.supported_image_models)
        ? window.wp_autoplugin.supported_image_models
        : [];
    const supportedImageModelsNormalized = supportedImageModels.map((model) => (typeof model === 'string' ? model.toLowerCase().trim() : ''));

    const dropManager = (function createDropManager() {
        const handlers = [];
        let overlay = null;
        let overlayContent = null;
        let activeHandler = null;
        let dragCounter = 0;

        function ensureOverlay(text) {
            if (!overlay || !document.body.contains(overlay)) {
                overlay = document.createElement('div');
                overlay.className = 'autoplugin-drop-overlay';
                overlay.setAttribute('aria-hidden', 'true');
                overlayContent = document.createElement('div');
                overlayContent.className = 'autoplugin-drop-overlay__content';
                overlay.appendChild(overlayContent);
                document.body.appendChild(overlay);
            }

            if (!overlayContent || !overlay.contains(overlayContent)) {
                overlayContent = document.createElement('div');
                overlayContent.className = 'autoplugin-drop-overlay__content';
                overlay.appendChild(overlayContent);
            }

            overlayContent.textContent = text;
        }

        function hideOverlay() {
            if (overlay) {
                overlay.classList.remove('is-visible');
                overlay.setAttribute('aria-hidden', 'true');
                overlay.removeAttribute('data-active-id');
            }
            activeHandler = null;
            dragCounter = 0;
        }

        function isHandlerEligible(handler) {
            if (!handler || typeof handler.handleFiles !== 'function') {
                return false;
            }
            if (typeof handler.isSupportedModel === 'function' && !handler.isSupportedModel()) {
                return false;
            }
            if (typeof handler.isEnabled === 'function' && !handler.isEnabled()) {
                return false;
            }
            return true;
        }

        function getEligibleHandler() {
            if (activeHandler && isHandlerEligible(activeHandler)) {
                return activeHandler;
            }

            activeHandler = null;
            for (let i = handlers.length - 1; i >= 0; i--) {
                if (isHandlerEligible(handlers[i])) {
                    activeHandler = handlers[i];
                    break;
                }
            }

            return activeHandler;
        }

        function hasFiles(event) {
            const transfer = event?.dataTransfer;
            if (!transfer) {
                return false;
            }

            const types = transfer.types;
            if (types) {
                if (typeof types.includes === 'function' && types.includes('Files')) {
                    return true;
                }
                if (typeof types.contains === 'function' && types.contains('Files')) {
                    return true;
                }
                if (Array.isArray(types) && types.indexOf('Files') !== -1) {
                    return true;
                }
            }

            return (transfer.files && transfer.files.length > 0) || (transfer.items && transfer.items.length > 0);
        }

        function getFiles(event) {
            const transfer = event?.dataTransfer;
            if (!transfer) {
                return [];
            }

            if (transfer.files && transfer.files.length) {
                return Array.from(transfer.files);
            }

            if (transfer.items && transfer.items.length) {
                return Array.from(transfer.items)
                    .filter((item) => item.kind === 'file')
                    .map((item) => item.getAsFile())
                    .filter(Boolean);
            }

            return [];
        }

        function showOverlay(handler) {
            if (!handler) {
                return;
            }

            const text = typeof handler.getOverlayText === 'function'
                ? handler.getOverlayText()
                : 'Drop files to attach';

            ensureOverlay(text);

            overlay.classList.add('is-visible');
            overlay.setAttribute('aria-hidden', 'false');
            overlay.dataset.activeId = handler.id || '';
            activeHandler = handler;
        }

        function handleDragEnter(event) {
            if (!hasFiles(event)) {
                return;
            }

            const handler = getEligibleHandler();
            if (!handler) {
                hideOverlay();
                return;
            }

            dragCounter += 1;
            showOverlay(handler);

            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }

            event.preventDefault();
        }

        function handleDragOver(event) {
            if (!hasFiles(event)) {
                hideOverlay();
                return;
            }

            const handler = getEligibleHandler();
            if (!handler) {
                hideOverlay();
                return;
            }

            showOverlay(handler);

            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }

            event.preventDefault();
        }

        function handleDragLeave(event) {
            if (hasFiles(event)) {
                dragCounter = Math.max(0, dragCounter - 1);
            } else {
                dragCounter = 0;
            }

            if (dragCounter === 0) {
                hideOverlay();
            }
        }

        function handleDrop(event) {
            const hadFiles = hasFiles(event);
            const handler = getEligibleHandler();
            hideOverlay();

            if (hadFiles) {
                event.preventDefault();
            }

            if (!handler) {
                return;
            }

            const files = getFiles(event);
            if (!files.length) {
                return;
            }

            handler.handleFiles(files);
        }

        document.addEventListener('dragenter', handleDragEnter);
        document.addEventListener('dragover', handleDragOver);
        document.addEventListener('dragleave', handleDragLeave);
        document.addEventListener('drop', handleDrop);

        return {
            register(handler) {
                if (!handler || typeof handler.handleFiles !== 'function') {
                    return function noop() {};
                }

                handlers.push(handler);

                return function unregister() {
                    const index = handlers.indexOf(handler);
                    if (index !== -1) {
                        handlers.splice(index, 1);
                    }

                    if (activeHandler === handler) {
                        hideOverlay();
                    }
                };
            }
        };
    })();

    window.wpAutopluginDropManager = dropManager;

    function handleStepChange(steps, step, onShowStep) {
        Object.values(steps).forEach((el) => {
            if (el) {
                el.style.display = 'none';
            }
        });

        if (steps[step]) {
            steps[step].style.display = 'block';
        }

        if (onShowStep && typeof onShowStep[step] === 'function') {
            onShowStep[step]();
        }

        if (window.updateFooterModel && window.getModelForStep) {
            const modelType = window.getModelForStep(step);
            window.updateFooterModel(modelType);
        }

        document.body.setAttribute('data-current-step', step);
        history.pushState({ state: step }, '', null);
    }

    function updateCodeEditor(editorInstance, textarea, code) {
        if (!editorInstance) {
            editorInstance = wp.codeEditor.initialize(textarea, {
                lineNumbers: true,
                matchBrackets: true,
                indentUnit: 4,
                indentWithTabs: true,
                tabSize: 4,
                mode: 'application/x-httpd-php'
            });
        } else {
            editorInstance.codemirror.setValue(code);
            editorInstance.codemirror.refresh();
        }
        return editorInstance;
    }

    async function sendRequest(formData) {
        const response = await fetch(wp_autoplugin.ajax_url, {
            method: 'POST',
            body: formData
        });
        return await response.json();
    }

    function initPromptAttachments(options = {}) {
        const textarea = options.textarea;
        const modelKeys = Array.isArray(options.modelKey) ? options.modelKey : [options.modelKey || 'default'];
        const debugId = textarea ? (textarea.id || textarea.name || 'unknown-field') : 'missing-textarea';

        if (!textarea) {
            return {
                appendToFormData() {},
                clear() {},
                hasImages() { return false; },
                getImages() { return []; }
            };
        }

        if (textarea.dataset.autopluginAttachments === '1') {
            return {
                appendToFormData() {},
                clear() {},
                hasImages() { return false; },
                getImages() { return []; }
            };
        }

        const maxFiles = options.maxFiles || 6;
        const attachTitle = options.attachTitle || 'Attach images';

        const wrapper = document.createElement('div');
        wrapper.className = 'autoplugin-prompt-wrapper';
        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(textarea);

        const attachButton = document.createElement('button');
        attachButton.type = 'button';
        attachButton.className = 'autoplugin-attach-button';
        attachButton.innerHTML = '<span class="dashicons dashicons-paperclip" aria-hidden="true"></span>';
        attachButton.title = attachTitle;
        attachButton.setAttribute('aria-label', attachTitle);
        wrapper.appendChild(attachButton);

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.multiple = true;
        fileInput.className = 'autoplugin-attach-input';
        fileInput.setAttribute('aria-hidden', 'true');
        fileInput.style.display = 'none';
        wrapper.appendChild(fileInput);

        const attachmentsContainer = document.createElement('div');
        attachmentsContainer.className = 'autoplugin-attachments';
        wrapper.appendChild(attachmentsContainer);

        const attachments = [];
        let idCounter = 0;
        let unregisterDropHandler = null;

        function currentModel() {
            if (typeof options.getModelName === 'function') {
                return options.getModelName();
            }
            if (window.wpAutopluginModels) {
                for (const key of modelKeys) {
                    if (key && window.wpAutopluginModels[key]) {
                        return window.wpAutopluginModels[key];
                    }
                }
                return window.wpAutopluginModels.default || '';
            }
            return '';
        }

        function isSupportedModel() {
            const model = currentModel();
            if (!model || typeof model !== 'string') {
                return false;
            }
            const normalized = model.toLowerCase().trim();
            if (!normalized) {
                return false;
            }
            if (supportedImageModelsNormalized.includes(normalized)) {
                return true;
            }
            if (normalized.startsWith('gemini')) {
                return true;
            }
            if (normalized.startsWith('claude')) {
                return true;
            }
            return false;
        }

        function updateButtonVisibility() {
            const model = currentModel();
            const supported = isSupportedModel();
            if (supported) {
                attachButton.classList.add('is-visible');
                attachButton.disabled = false;
            } else {
                attachButton.classList.remove('is-visible');
                attachButton.disabled = true;
            }
            wrapper.classList.toggle('autoplugin-prompt-wrapper--supports-images', supported);
        }

        function renderAttachments() {
            attachmentsContainer.innerHTML = '';
            if (!attachments.length) {
                attachmentsContainer.classList.remove('has-attachments');
                return;
            }

            attachmentsContainer.classList.add('has-attachments');
            attachments.forEach((item) => {
                const thumb = document.createElement('div');
                thumb.className = 'autoplugin-attachment';

                const img = document.createElement('img');
                img.src = item.dataUrl;
                img.alt = item.name || 'Attached image';
                thumb.appendChild(img);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'autoplugin-attachment-remove';
                remove.setAttribute('aria-label', 'Remove image');
                remove.innerHTML = '&times;';
                remove.addEventListener('click', () => {
                    const index = attachments.findIndex((attachment) => attachment.id === item.id);
                    if (index !== -1) {
                        attachments.splice(index, 1);
                        renderAttachments();
                    }
                });
                thumb.appendChild(remove);

                attachmentsContainer.appendChild(thumb);
            });
        }

        function addAttachment(file) {
            if (!file || !file.type || !file.type.startsWith('image/')) {
                return;
            }

            if (attachments.length >= maxFiles) {
                return;
            }

            const reader = new FileReader();
            const attachmentId = `attachment-${Date.now()}-${idCounter++}`;
            reader.onload = () => {
                const result = reader.result;
                if (typeof result !== 'string') {
                    return;
                }
                const parts = result.split(',');
                if (parts.length < 2) {
                    return;
                }
                const mimeMatch = parts[0].match(/^data:(.*);base64$/i);
                const mimeType = mimeMatch ? mimeMatch[1] : (file.type || 'image/jpeg');
                attachments.push({
                    id: attachmentId,
                    name: file.name,
                    mimeType,
                    base64: parts[1],
                    dataUrl: result
                });
                renderAttachments();
            };
            reader.readAsDataURL(file);
        }

        function handleFiles(files) {
            if (!files || !files.length) {
                return;
            }
            for (const file of files) {
                if (attachments.length >= maxFiles) {
                    break;
                }
                addAttachment(file);
            }
        }

        attachButton.addEventListener('click', (event) => {
            if (!isSupportedModel()) {
                event.preventDefault();
                return;
            }
            fileInput.click();
        });

        fileInput.addEventListener('change', (event) => {
            handleFiles(Array.from(event.target.files || []));
            fileInput.value = '';
        });

        window.addEventListener('wpAutopluginModelsUpdated', updateButtonVisibility);
        updateButtonVisibility();

        window.addEventListener('DOMContentLoaded', updateButtonVisibility, { once: true });
        window.addEventListener('load', updateButtonVisibility, { once: true });
        renderAttachments();

        const dropOverlayText = options.dropOverlayText
            || window.wp_autoplugin?.messages?.drop_files_to_attach
            || 'Drop files to attach';

        function isWrapperVisible() {
            if (!wrapper) {
                return false;
            }
            return !!(wrapper.offsetWidth || wrapper.offsetHeight || wrapper.getClientRects().length);
        }

        unregisterDropHandler = dropManager.register({
            id: debugId,
            handleFiles: (files) => {
                const imageFiles = files.filter((file) => file && file.type && file.type.startsWith('image/'));
                if (imageFiles.length) {
                    handleFiles(imageFiles);
                }
            },
            isSupportedModel,
            isEnabled: () => isWrapperVisible() && isSupportedModel(),
            getOverlayText: () => dropOverlayText
        });

        textarea.dataset.autopluginAttachments = '1';

        return {
            appendToFormData(formData) {
                if (!formData || !attachments.length || !isSupportedModel()) {
                    return;
                }
                const payload = attachments.map(({ base64, mimeType, name }) => ({
                    data: base64,
                    mime: mimeType,
                    name
                }));
                formData.append('prompt_images', JSON.stringify(payload));
            },
            clear() {
                attachments.length = 0;
                renderAttachments();
            },
            hasImages() {
                return attachments.length > 0;
            },
            getImages() {
                return attachments.slice();
            },
            unregisterDrop() {
                if (typeof unregisterDropHandler === 'function') {
                    unregisterDropHandler();
                    unregisterDropHandler = null;
                }
            }
        };
    }

    window.wpAutoPluginCommon = {
        handleStepChange,
        updateCodeEditor,
        sendRequest,
        initPromptAttachments
    };
})();
