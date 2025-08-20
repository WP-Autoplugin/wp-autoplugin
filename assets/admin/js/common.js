(function () {
    'use strict';

    // Expose shared logic on a global object for easy access in other scripts.
    window.wpAutoPluginCommon = {
        /**
         * Hides all step elements, shows the requested step, and updates browser history.
         *
         * @param {Object} steps        - Map of stepName => DOM element
         * @param {string} step         - The step to show (e.g. 'generatePlan', 'reviewPlan', etc.)
         * @param {Object} onShowStep   - Callbacks keyed by step name for step-specific logic
         */
        handleStepChange(steps, step, onShowStep) {
            Object.values(steps).forEach((el) => {
                el.style.display = 'none';
            });

            if (steps[step]) {
                steps[step].style.display = 'block';
            }

            if (onShowStep && typeof onShowStep[step] === 'function') {
                onShowStep[step]();
            }

            // Update footer model based on current step
            if (window.updateFooterModel && window.getModelForStep) {
                const modelType = window.getModelForStep(step);
                window.updateFooterModel(modelType);
            }
            
            // Set current step on body for reference
            document.body.setAttribute('data-current-step', step);

            history.pushState({ state: step }, '', null);
        },

        /**
         * Initializes or updates the WP Code Editor instance.
         *
         * @param {object|null} editorInstance - Existing editor instance or null
         * @param {HTMLElement} textarea       - The textarea element to enhance
         * @param {string} code                - The code to set in the editor
         * @returns {object}                   - Updated or newly created editor instance
         */
        updateCodeEditor(editorInstance, textarea, code) {
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
        },

        /**
         * Sends a request using Fetch; expects a FormData with 'action' and other fields.
         *
         * @param {FormData} formData - The form data to send
         * @returns {Promise<object>}  - JSON-parsed server response
         */
        async sendRequest(formData) {
            const response = await fetch(wp_autoplugin.ajax_url, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            return result;
        }
    };
})();
