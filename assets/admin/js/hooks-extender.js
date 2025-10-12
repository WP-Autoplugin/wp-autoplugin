(function () {
    'use strict';

    // ----- DOM References -----
    const stepGeneratePlan = document.querySelector('.step-1-extend');
    const stepReviewPlan   = document.querySelector('.step-2-plan');
    const stepReviewCode   = document.querySelector('.step-3-done');

    const generatePlanForm = document.getElementById('extend-hooks-form');
    const generateCodeForm = document.getElementById('extend-hooks-code-form');
    const createPluginForm = document.getElementById('extended-hooks-plugin-form');

    const messageGeneratePlan = document.getElementById('extend-hooks-message');
    const messageReviewPlan   = document.getElementById('extend-hooks-code-message');
    const messageReviewCode   = document.getElementById('extended-hooks-plugin-message');

    const pluginPlanContainer = document.getElementById('plugin_plan_container');
    const pluginCodeTextarea  = document.getElementById('extended_plugin_code');
    const tabsContainer       = document.getElementById('files-tabs');
    const contentContainer    = document.getElementById('file-content');

    const hooksLoading = document.getElementById('hooks-loading');
    const hooksContent = document.getElementById('hooks-content');
    const hooksSummary = document.getElementById('hooks-summary');
    const hooksUl = document.getElementById('hooks-ul');
    const pluginFileInput = document.getElementById('plugin_file');
    const issueField      = document.getElementById('plugin_issue');

    const promptAttachments = wpAutoPluginCommon.initPromptAttachments({
        textarea: issueField,
        modelKey: 'planner'
    });

    // ----- State Variables -----
    let editorInstance    = null;
    let currentState      = 'generatePlan';
    let pluginCode        = '';
    let issueDescription  = '';
    let pluginPlan        = {};
    let pluginMode        = 'simple';
    let projectStructure  = {};
    let generatedFiles    = {};
    let fileEditors       = {};
    let currentFileIndex  = 0;
    let extractedHooks    = [];

    // Step mapping
    const steps = {
        generatePlan: stepGeneratePlan,
        reviewPlan:   stepReviewPlan,
        reviewCode:   stepReviewCode
    };

    // Logic to run on step show
    const onShowStep = {
        generatePlan: () => {
            if (issueDescription !== '') {
                document.getElementById('plugin_issue').value = issueDescription;
            }
        },
        reviewPlan: () => {
            renderPlanAccordion();
        },
        reviewCode: () => {
            if (pluginMode === 'complex') {
                showComplexPluginContent();
                startFileGeneration();
            } else {
                pluginCodeTextarea.value = pluginCode;
                editorInstance = wpAutoPluginCommon.updateCodeEditor(editorInstance, pluginCodeTextarea, pluginCode);
            }
        }
    };

    // ----- Extract Hooks on Page Load -----

    const pluginFile = pluginFileInput.value;
    if (pluginFile) {
        const loader = loadingIndicator(hooksLoading, wp_autoplugin.messages.extracting_hooks || 'Extracting plugin hooks, please wait...');
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_extract_hooks');
        formData.append('plugin_file', pluginFile);
        formData.append('security', wp_autoplugin.nonce);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loader.stop();
            hooksLoading.style.display = 'none';
            if (data.success) {
                const hooks = data.data;
                extractedHooks = hooks; // Store hooks for later use
                if (hooks.length > 0) {
                    hooksSummary.textContent = `${hooks.length} hooks found in the plugin code`;
                    hooksUl.innerHTML = hooks.map(hook => `<li>${hook.name} (${hook.type})</li>`).join('');
                    hooksContent.style.display = 'block';
                } else {
                    messageGeneratePlan.innerHTML = wp_autoplugin.messages.no_hooks_found || 'No hooks found in the plugin code. Cannot proceed with extension.';
                }
            } else {
                messageGeneratePlan.innerHTML = 'Error extracting hooks: ' + (data.data || 'Unknown error');
            }
        })
        .catch(error => {
            loader.stop();
            hooksLoading.style.display = 'none';
            messageGeneratePlan.innerHTML = 'Error extracting hooks: ' + error.message;
        });
    }

    // ----- Event Handlers -----

    async function handleGeneratePlanSubmit(event) {
        event.preventDefault();

        const issueValue = issueField.value.trim();
        if (issueValue === '' && !promptAttachments.hasImages()) {
            messageGeneratePlan.innerHTML = wp_autoplugin.messages.empty_description;
            return;
        }

        generatePlanForm.parentElement.classList.add('loading');
        issueDescription = issueField.value;

        const loader = loadingIndicator(messageGeneratePlan, wp_autoplugin.messages.generating_plan);
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_extend_hooks_plan');
        formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('security', wp_autoplugin.nonce);
        promptAttachments.appendToFormData(formData);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');

            if (!response.success) {
                messageGeneratePlan.innerHTML = wp_autoplugin.messages.plan_generation_error + ' <pre>' + response.data.plan + '</pre>';
                return;
            }

            pluginPlan = response.data.plan || response.data; // array/object

            // Decide mode based on presence of project_structure
            if (pluginPlan && pluginPlan.project_structure && Array.isArray(pluginPlan.project_structure.files)) {
                pluginMode = 'complex';
                projectStructure = pluginPlan.project_structure;
            } else {
                pluginMode = 'simple';
            }

            // Pre-render plan content so it's ready when the step switches
            renderPlanAccordion();

            // Check if the plan is technically feasible
            if (pluginPlan.technically_feasible === false) {
                messageGeneratePlan.innerHTML = wp_autoplugin.messages.plan_generation_error + ' <pre>' + pluginPlan.explanation + '</pre>';
                return;
            }
            
            currentState = 'reviewPlan';
            wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
        } catch (error) {
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');
            messageGeneratePlan.innerHTML = wp_autoplugin.messages.plan_generation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    async function handleGenerateCodeSubmit(event) {
        event.preventDefault();

        if (pluginMode === 'complex') {
            // Rebuild plan object from accordion into pluginPlan
            const planOut = (typeof pluginPlan === 'object' && pluginPlan) ? JSON.parse(JSON.stringify(pluginPlan)) : {};
            // Update text/textarea fields
            document.querySelectorAll('#plugin_plan_container .autoplugin-accordion').forEach(acc => {
                const title = acc.querySelector('.autoplugin-accordion-heading .title')?.textContent?.trim().toLowerCase().replace(/ /g, '_');
                const input = acc.querySelector('.autoplugin-accordion-content input');
                const textarea = acc.querySelector('.autoplugin-accordion-content textarea');
                if (title === 'project_structure') return; // keep object intact
                if (title && (input || textarea)) {
                    const val = input ? input.value : textarea.value;
                    planOut[title] = val;
                }
            });
            // Update file descriptions from table
            if (planOut.project_structure && planOut.project_structure.files) {
                document.querySelectorAll('.file-description-input').forEach(inp => {
                    const idx = parseInt(inp.getAttribute('data-file-index'), 10);
                    if (!isNaN(idx) && planOut.project_structure.files[idx]) {
                        planOut.project_structure.files[idx].description = inp.value;
                    }
                });
            }
            pluginPlan = planOut;
            projectStructure = planOut.project_structure || projectStructure;
            currentState = 'reviewCode';
            wpAutoPluginCommon.handleStepChange(steps, 'reviewCode', onShowStep);
        } else {
            generateCodeForm.parentElement.classList.add('loading');
            const loader = loadingIndicator(messageReviewPlan, wp_autoplugin.messages.generating_code);
            loader.start();

            const formData = new FormData();
            formData.append('action', 'wp_autoplugin_generate_extend_hooks_code');
            formData.append('plugin_file', document.getElementById('plugin_file').value);
            const planText = JSON.stringify(pluginPlan);
            let planHooks = Array.isArray(pluginPlan.hooks) ? pluginPlan.hooks : [];
            let planPluginName = (document.getElementById('plugin_name')?.value || '').trim();
            if (!planPluginName && pluginPlan.plugin_name) {
                planPluginName = pluginPlan.plugin_name;
            }
            formData.append('plugin_plan', planText);
            formData.append('plugin_name', planPluginName);
            formData.append('hooks', JSON.stringify(planHooks));
            formData.append('security', wp_autoplugin.nonce);

            try {
                const response = await wpAutoPluginCommon.sendRequest(formData);
                loader.stop();
                generateCodeForm.parentElement.classList.remove('loading');

                if (!response.success) {
                    messageReviewPlan.innerHTML = wp_autoplugin.messages.code_generation_error + ' <pre>' + response.data + '</pre>';
                    return;
                }

                pluginCode = response.data.code || response.data;
                currentState = 'reviewCode';
                wpAutoPluginCommon.handleStepChange(steps, 'reviewCode', onShowStep);
            } catch (error) {
                loader.stop();
                generateCodeForm.parentElement.classList.remove('loading');
                messageReviewPlan.innerHTML = wp_autoplugin.messages.code_generation_error + ' <pre>' + error.message + '</pre>';
            }
        }
    }

    async function handleCreatePluginSubmit(event) {
        event.preventDefault();

        createPluginForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(messageReviewCode, wp_autoplugin.messages.creating_plugin);
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_create_plugin');
        formData.append('security', wp_autoplugin.nonce);

        if (pluginMode === 'complex') {
            // Package multi-file payload
            const updatedFiles = { ...generatedFiles };
            for (const [filePath, editor] of Object.entries(fileEditors)) {
                if (editor && editor.codemirror) {
                    updatedFiles[filePath] = editor.codemirror.getValue();
                }
            }
            const planPluginName = (document.getElementById('plugin_name')?.value || pluginPlan.plugin_name || '').trim();
            formData.append('plugin_name', planPluginName);
            formData.append('project_structure', JSON.stringify(projectStructure));
            formData.append('generated_files', JSON.stringify(updatedFiles));
        } else {
            // Single-file fallback
            formData.append('plugin_file', document.getElementById('plugin_file').value);
            formData.append('plugin_code', editorInstance.codemirror.getValue());
            formData.append('plugin_name', (document.getElementById('plugin_name')?.value || pluginPlan.plugin_name || '').trim());
        }

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');

            if (response.success) {
                // Show success message
                messageReviewCode.innerHTML = '<p>' + wp_autoplugin.messages.plugin_created + '</p>';

                messageReviewCode
                    .insertAdjacentHTML(
                        'beforeend',
                        `<div><a href="${wp_autoplugin.activate_url}&plugin=${response.data}"
                           class="button button-primary">
                           ${wp_autoplugin.messages.activate}
                         </a></div>`
                    );

                // Hide form
                createPluginForm.style.display = 'none';
            } else {
                document.getElementById('create-plugin-message').innerHTML =
                    wp_autoplugin.messages.plugin_creation_error + ' <pre>' + response.data + '</pre>';
            }
        } catch (error) {
            console.error(error);
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');
            messageReviewCode.innerHTML = wp_autoplugin.messages.plugin_creation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    // ----- Attach Event Listeners -----
    generatePlanForm.addEventListener('submit', handleGeneratePlanSubmit);
    generateCodeForm.addEventListener('submit', handleGenerateCodeSubmit);
    createPluginForm.addEventListener('submit', handleCreatePluginSubmit);

    document.getElementById('edit-issue').addEventListener('click', function() {
        currentState = 'generatePlan';
        wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
    });

    document.getElementById('edit-plan').addEventListener('click', function() {
        currentState = 'reviewPlan';
        wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
    });

    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.state) {
            wpAutoPluginCommon.handleStepChange(steps, event.state.state, onShowStep);
        } else {
            wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
        }
    });

    document.getElementById('copy-hooks').addEventListener('click', function() {
        if (!extractedHooks.length) return;
        
        const hooksText = extractedHooks.map(hook => 
            `${hook.type.charAt(0).toUpperCase() + hook.type.slice(1)}: \`${hook.name.replace(/['"]/g, '')}\`\n\nContext:\n\`\`\`${hook.context}\n\`\`\`\n\n`
        ).join('');

        navigator.clipboard.writeText(hooksText).then(() => {
            const button = this;
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            setTimeout(() => {
                button.textContent = originalText;
            }, 2000);
        });
    });

    // Initialize the first step
    wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);

    // Initialize the submit handlers
    attachFormSubmitListeners();

    function renderPlanAccordion() {
        let planObj = pluginPlan;
        try {
            if (typeof planObj === 'string') planObj = JSON.parse(planObj);
        } catch (e) {
            planObj = pluginPlan;
        }
        const accordion = buildAccordion(planObj);
        if (accordion && accordion.trim()) {
            pluginPlanContainer.innerHTML = accordion;
            attachAccordionListeners();
        } else {
            const pretty = (typeof planObj === 'object') ? JSON.stringify(planObj, null, 2) : String(planObj || '');
            pluginPlanContainer.innerHTML = `<pre>${pretty.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]))}</pre>`;
        }
    }

    // ----- Complex plugin mode helpers -----
    function showComplexPluginContent() {
        const simple = document.getElementById('simple-plugin-content');
        const complex = document.getElementById('complex-plugin-content');
        if (simple) simple.style.display = 'none';
        if (complex) complex.style.display = 'block';
        const gp = document.querySelector('.generation-progress');
        if (gp) gp.style.display = 'block';
    }

    async function startFileGeneration() {
        if (!projectStructure.files || projectStructure.files.length === 0) {
            messageReviewCode.innerHTML = 'Error: No files to generate.';
            return;
        }
        generatedFiles = {};
        fileEditors = {};
        currentFileIndex = 0;
        createFileTabs();
        await generateNextFile();
    }

    function createFileTabs() {
        if (!tabsContainer || !contentContainer) return;
        tabsContainer.innerHTML = '';
        contentContainer.innerHTML = '';
        projectStructure.files.forEach((file, index) => {
            const tab = document.createElement('div');
            tab.className = 'file-tab';
            tab.dataset.file = file.path;
            tab.dataset.index = index;
            const icon = document.createElement('span');
            icon.className = `file-icon ${file.type}`;
            tab.appendChild(icon);
            const name = document.createElement('span');
            name.textContent = file.path;
            tab.appendChild(name);
            const status = document.createElement('span');
            status.className = 'status-indicator pending';
            tab.appendChild(status);
            tab.addEventListener('click', () => switchToFile(file.path));
            tabsContainer.appendChild(tab);

            const wrapper = document.createElement('div');
            wrapper.className = 'file-editor';
            wrapper.dataset.file = file.path;
            wrapper.style.display = 'none';
            const textarea = document.createElement('textarea');
            textarea.rows = 20; textarea.cols = 100;
            textarea.placeholder = `[Contents of ${file.path} will appear here]`;
            wrapper.appendChild(textarea);
            contentContainer.appendChild(wrapper);
        });
        if (projectStructure.files.length > 0) switchToFile(projectStructure.files[0].path);
    }

    function switchToFile(path) {
        document.querySelectorAll('.file-tab').forEach(t => t.classList.remove('active'));
        const active = document.querySelector(`.file-tab[data-file="${CSS.escape(path)}"]`);
        if (active) active.classList.add('active');
        document.querySelectorAll('.file-editor').forEach(ed => { ed.style.display = 'none'; });
        const shown = document.querySelector(`.file-editor[data-file="${CSS.escape(path)}"]`);
        if (shown) {
            shown.style.display = 'block';
            const ed = fileEditors[path];
            if (ed && ed.codemirror) setTimeout(() => ed.codemirror.refresh(), 50);
        }
    }

    async function generateNextFile() {
        if (currentFileIndex >= projectStructure.files.length) {
            const gp = document.querySelector('.generation-progress');
            if (gp) gp.style.display = 'none';
            return;
        }
        const file = projectStructure.files[currentFileIndex];
        updateProgress(currentFileIndex + 1, projectStructure.files.length, file.path);
        const tab = document.querySelector(`[data-index="${currentFileIndex}"]`);
        const status = tab ? tab.querySelector('.status-indicator') : null;
        if (status) status.className = 'status-indicator generating';

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_extend_hooks_file');
        formData.append('file_index', currentFileIndex);
        formData.append('plugin_plan', JSON.stringify(pluginPlan));
        formData.append('project_structure', JSON.stringify(projectStructure));
        formData.append('generated_files', JSON.stringify(generatedFiles));
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        const planHooks = Array.isArray(pluginPlan.hooks) ? pluginPlan.hooks : [];
        formData.append('hooks', JSON.stringify(planHooks));
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            if (!response.success) {
                if (status) { status.className = 'status-indicator error'; status.title = response.data; }
                messageReviewPlan.innerHTML = wp_autoplugin.messages.code_generation_error + ' <pre>' + response.data + '</pre>';
                return;
            }
            if (status) status.className = 'status-indicator generated';
            generatedFiles[file.path] = response.data.file_content;
            const editorEl = document.querySelector(`.file-editor[data-file="${CSS.escape(file.path)}"] textarea`);
            if (editorEl) {
                editorEl.value = response.data.file_content;
                fileEditors[file.path] = wpAutoPluginCommon.updateCodeEditor(fileEditors[file.path] || null, editorEl, response.data.file_content);
                if (fileEditors[file.path] && fileEditors[file.path].codemirror) {
                    setTimeout(() => fileEditors[file.path].codemirror.refresh(), 50);
                }
            }
            currentFileIndex++;
            await generateNextFile();
        } catch (error) {
            if (status) { status.className = 'status-indicator error'; status.title = error.message; }
            messageReviewPlan.innerHTML = wp_autoplugin.messages.code_generation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    function updateProgress(current, total, fileName) {
        const progressBar = document.getElementById('file-generation-progress');
        const progressText = document.getElementById('progress-text');
        const percentage = (current / total) * 100;
        if (progressBar) progressBar.style.width = percentage + '%';
        if (progressText) progressText.innerHTML = `Generating <code>${fileName}</code> (${current} of ${total})...`;
    }
})();
