(function () {
    'use strict';

    const supportedImageModels = Array.isArray(window.wp_autoplugin?.supported_image_models)
        ? window.wp_autoplugin.supported_image_models
        : [];
    const supportedImageModelsNormalized = supportedImageModels.map((model) => (typeof model === 'string' ? model.toLowerCase().trim() : ''));

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
