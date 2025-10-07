// step-1-extend, step-2-plan, step-3-done
(function () {
    'use strict';

    // ----- DOM References -----
    const stepGeneratePlan = document.querySelector('.step-1-extend');
    const stepReviewPlan   = document.querySelector('.step-2-plan');
    const stepReviewCode   = document.querySelector('.step-3-done');

    const generatePlanForm = document.getElementById('extend-plugin-form');
    const generateCodeForm = document.getElementById('extend-code-form');
    const createPluginForm = document.getElementById('extended-plugin-form');

    const messageGeneratePlan = document.getElementById('extend-plugin-message');
    const messageReviewPlan   = document.getElementById('extend-code-message');
    const messageReviewCode   = document.getElementById('extended-plugin-message');

    const pluginPlanContainer = document.getElementById('plugin_plan_container');
    const pluginCodeTextarea  = document.getElementById('extended_plugin_code'); // hidden fallback
    const tabsContainer       = document.getElementById('files-tabs');
    const contentContainer    = document.getElementById('file-content');
    const issueField          = document.getElementById('plugin_issue');

    const promptAttachments = wpAutoPluginCommon.initPromptAttachments({
        textarea: issueField,
        modelKey: 'planner'
    });

    // ----- State Variables -----
    let editorInstance    = null; // legacy single-file editor (kept for fallback)
    let currentState      = 'generatePlan';
    let pluginCode        = '';
    let issueDescription  = '';
    let pluginPlan        = {};
    let totalTokenUsage   = { input_tokens: 0, output_tokens: 0 };
    let fileEditors       = {}; // path => wp.codeEditor instance
    let filesMap          = {}; // path => contents
    let pluginMode        = 'simple';
    let projectStructure  = {};
    let generatedFiles    = {};
    let currentFileIndex  = 0;

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
            // Render accordion UI like Generate flow
            const accordion = buildAccordion(pluginPlan);
            pluginPlanContainer.innerHTML = accordion;
            attachAccordionListeners();
        },
        reviewCode: () => {
            if (pluginMode === 'complex') {
                showComplexPluginContent();
                disableSave(true);
                startFileGeneration();
            } else {
                // Fallback single file
                pluginCodeTextarea.value = pluginCode;
                editorInstance = wpAutoPluginCommon.updateCodeEditor(editorInstance, pluginCodeTextarea, pluginCode);
            }
        }
    };

    // ----- Event Handlers -----

    async function handleGeneratePlanSubmit(event) {
        event.preventDefault();

        if (issueField.value.trim() === '') {
            messageGeneratePlan.innerHTML = wp_autoplugin.messages.empty_description;
            return;
        }

        generatePlanForm.parentElement.classList.add('loading');
        issueDescription = issueField.value;

        const loader = loadingIndicator(messageGeneratePlan, wp_autoplugin.messages.generating_plan);
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_extend_plan');
        formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('security', wp_autoplugin.nonce);
        promptAttachments.appendToFormData(formData);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');

            if (!response.success) {
                messageGeneratePlan.innerHTML = wp_autoplugin.messages.plan_generation_error + ' <pre>' + response.data + '</pre>';
                return;
            }

            // Expect JSON plan similar to generator (with project_structure.files)
            let planRaw = response.data.plan_data || response.data;
            try {
                const parsed = JSON.parse(planRaw);
                pluginPlan = parsed;
                if (parsed && parsed.project_structure && Array.isArray(parsed.project_structure.files)) {
                    projectStructure = parsed.project_structure;
                    pluginMode = 'complex';
                } else {
                    pluginMode = 'simple';
                }
            } catch (e) {
                pluginPlan = planRaw;
                pluginMode = 'simple';
            }
            
            // Track token usage
            if (response.data.token_usage) {
                totalTokenUsage.input_tokens += response.data.token_usage.input_tokens || 0;
                totalTokenUsage.output_tokens += response.data.token_usage.output_tokens || 0;
                updateTokenDisplay();
                addTokenUsageToGlobal('Extend Plan Generation', response.data.token_usage.input_tokens || 0, response.data.token_usage.output_tokens || 0);
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

        generateCodeForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(messageReviewPlan, wp_autoplugin.messages.generating_code);
        loader.start();

        // Rebuild plan from accordion inputs (plan_summary text + project_structure file descriptions)
        const planOut = (typeof pluginPlan === 'object' && pluginPlan) ? JSON.parse(JSON.stringify(pluginPlan)) : {};
        // Update text/textarea fields
        document.querySelectorAll('#plugin_plan_container .autoplugin-accordion').forEach(acc => {
            const title = acc.querySelector('.autoplugin-accordion-heading .title')?.textContent?.trim().toLowerCase().replace(/ /g, '_');
            const input = acc.querySelector('.autoplugin-accordion-content input');
            const textarea = acc.querySelector('.autoplugin-accordion-content textarea');
            // Do NOT overwrite project_structure from a single input inside the table; keep the object.
            if (title === 'project_structure') {
                // handled below via file-description inputs
                return;
            }
            if (title && (input || textarea)) {
                const val = input ? input.value : textarea.value;
                planOut[title] = val;
            }
        });
        // Update file descriptions from project structure table
        if (planOut.project_structure && planOut.project_structure.files) {
            const descInputs = document.querySelectorAll('.file-description-input');
            descInputs.forEach(inp => {
                const idx = parseInt(inp.getAttribute('data-file-index'), 10);
                if (!isNaN(idx) && planOut.project_structure.files[idx]) {
                    planOut.project_structure.files[idx].description = inp.value;
                }
            });
        }

        // Determine complexity from planOut
        if (planOut && planOut.project_structure && Array.isArray(planOut.project_structure.files) && planOut.project_structure.files.length > 0) {
            pluginMode = 'complex';
            pluginPlan = planOut;
            projectStructure = planOut.project_structure;
        } else {
            pluginMode = 'simple';
            pluginPlan = planOut;
        }

        loader.stop();
        generateCodeForm.parentElement.classList.remove('loading');
        currentState = 'reviewCode';
        wpAutoPluginCommon.handleStepChange(steps, 'reviewCode', onShowStep);
    }

    async function handleCreatePluginSubmit(event) {
        event.preventDefault();

        createPluginForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(messageReviewCode, wp_autoplugin.messages.creating_plugin);
        loader.start();

        // Collect current editor contents into a files map
        const updatedFiles = {};
        if (Object.keys(fileEditors).length > 0) {
            for (const [path, editor] of Object.entries(fileEditors)) {
                if (editor && editor.codemirror) {
                    updatedFiles[path] = editor.codemirror.getValue();
                }
            }
        } else {
            // Fallback to single-textarea content
            updatedFiles[document.getElementById('plugin_file').value] = editorInstance?.codemirror?.getValue?.() || pluginCodeTextarea.value || '';
        }

        const payload = JSON.stringify({ files: updatedFiles });

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_extend_plugin');
        formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('plugin_code', payload);
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');

            if (!response.success) {
                messageReviewCode.innerHTML = wp_autoplugin.messages.plugin_creation_error + ' <pre>' + response.data + '</pre>';
                return;
            }

            let activateBtnHtml = `<div style="margin-top: 10px;"><a href="${wp_autoplugin.activate_url}&plugin=${response.data}" class="button button-primary">${wp_autoplugin.messages.activate}</a></div>`;
            if (wp_autoplugin.is_plugin_active) {
                activateBtnHtml = '';
            }
            messageReviewCode.innerHTML = wp_autoplugin.messages.code_updated + activateBtnHtml;

            // Hide the form
            createPluginForm.style.display = 'none';
        } catch (error) {
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');
            messageReviewCode.innerHTML = wp_autoplugin.messages.plugin_creation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    // ----- Helper Functions -----
    
    function updateTokenDisplay() {
        if (window.updateTokenDisplay) {
            window.updateTokenDisplay(totalTokenUsage.input_tokens, totalTokenUsage.output_tokens);
        }
    }
    
    function addTokenUsageToGlobal(stepName, inputTokens, outputTokens) {
        if (window.addTokenUsage) {
            var currentStep = document.body.getAttribute('data-current-step') || stepName;
            var modelType = window.getModelForStep ? window.getModelForStep(currentStep) : 'default';
            var modelName = window.wpAutopluginModels ? window.wpAutopluginModels[modelType] : 'Unknown';
            window.addTokenUsage(stepName, modelName, inputTokens, outputTokens);
        }
    }

    // ----- Attach Event Listeners -----
    generatePlanForm.addEventListener('submit', handleGeneratePlanSubmit);
    generateCodeForm.addEventListener('submit', handleGenerateCodeSubmit);
    createPluginForm.addEventListener('submit', handleCreatePluginSubmit);

    // "Edit" buttons
    document.getElementById('edit-issue').addEventListener('click', function() {
        currentState = 'generatePlan';
        wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
    });

    document.getElementById('edit-plan').addEventListener('click', function() {
        currentState = 'reviewPlan';
        wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
    });

    // Handle back button in browser
    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.state) {
            wpAutoPluginCommon.handleStepChange(steps, event.state.state, onShowStep);
        } else {
            wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
        }
    });

    // Initialize the first step
    wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);

    // Initialize the submit handlers
    attachFormSubmitListeners();

    // ----- Multi-file editor helpers -----
    function setupMultiFileEditor() {
        // If we already have generated files, show them; else initialize empty editors by structure
        filesMap = {};
        if (Object.keys(generatedFiles).length > 0) {
            filesMap = { ...generatedFiles };
        } else if (projectStructure.files && projectStructure.files.length) {
            projectStructure.files.forEach(f => { filesMap[f.path] = ''; });
        }
        buildTabsAndEditors(filesMap);
    }

    function buildTabsAndEditors(files) {
        // Clear any existing
        fileEditors = {};
        if (tabsContainer) tabsContainer.innerHTML = '';
        if (contentContainer) contentContainer.innerHTML = '';

        const paths = Object.keys(files);
        if (paths.length === 0) return;

        paths.forEach((path, idx) => {
            // Tab
            const tab = document.createElement('div');
            tab.className = 'file-tab' + (idx === 0 ? ' active' : '');
            tab.dataset.file = path;

            const icon = document.createElement('span');
            icon.className = `file-icon ${extToType(path)}`;
            tab.appendChild(icon);

            const name = document.createElement('span');
            name.textContent = path;
            tab.appendChild(name);

            const status = document.createElement('span');
            status.className = 'status-indicator generated';
            tab.appendChild(status);

            tab.addEventListener('click', () => switchToFile(path));
            tabsContainer.appendChild(tab);

            // Editor container
            const wrapper = document.createElement('div');
            wrapper.className = 'file-editor';
            wrapper.dataset.file = path;
            wrapper.style.display = idx === 0 ? 'block' : 'none';

            const textarea = document.createElement('textarea');
            textarea.rows = 20; textarea.cols = 100;
            textarea.value = files[path] || '';
            wrapper.appendChild(textarea);
            contentContainer.appendChild(wrapper);

            // Initialize editor (use PHP mode globally for simplicity)
            fileEditors[path] = wpAutoPluginCommon.updateCodeEditor(null, textarea, textarea.value);
        });
    }

    function switchToFile(path) {
        // Tabs
        document.querySelectorAll('.file-tab').forEach(t => t.classList.remove('active'));
        const active = document.querySelector(`.file-tab[data-file="${CSS.escape(path)}"]`);
        if (active) active.classList.add('active');

        // Editors
        document.querySelectorAll('.file-editor').forEach(ed => { ed.style.display = 'none'; });
        const shown = document.querySelector(`.file-editor[data-file="${CSS.escape(path)}"]`);
        if (shown) {
            shown.style.display = 'block';
            const ed = fileEditors[path];
            if (ed && ed.codemirror) setTimeout(() => ed.codemirror.refresh(), 50);
        }
    }

    function extToType(path) {
        if (/\.css$/i.test(path)) return 'css';
        if (/\.(js|mjs)$/i.test(path)) return 'js';
        return 'php';
    }

    // ----- Complex generation (per-file) -----
    function showComplexPluginContent() {
        if (pluginCodeTextarea) pluginCodeTextarea.style.display = 'none';
        const gp = document.querySelector('.generation-progress');
        if (gp) gp.style.display = 'block';
    }

    async function startFileGeneration() {
        if (!projectStructure.files || projectStructure.files.length === 0) {
            messageReviewCode.innerHTML = 'Error: No files to generate.';
            return;
        }
        generatedFiles = {};
        currentFileIndex = 0;
        updateTokenDisplay();
        createFileTabsFromStructure();
        await generateNextFile();
    }

    function createFileTabsFromStructure() {
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

    async function generateNextFile() {
        if (currentFileIndex >= projectStructure.files.length) {
            const gp = document.querySelector('.generation-progress');
            if (gp) gp.style.display = 'none';
            disableSave(false);
            if (projectStructure.files.length) switchToFile(projectStructure.files[projectStructure.files.length - 1].path);
            return;
        }
        const file = projectStructure.files[currentFileIndex];
        updateProgress(currentFileIndex + 1, projectStructure.files.length, file.path);
        const tab = document.querySelector(`[data-index="${currentFileIndex}"]`);
        const status = tab ? tab.querySelector('.status-indicator') : null;
        if (status) status.className = 'status-indicator generating';

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_extend_file');
        formData.append('file_index', currentFileIndex);
        formData.append('plugin_plan', JSON.stringify(pluginPlan));
        formData.append('project_structure', JSON.stringify(projectStructure));
        formData.append('generated_files', JSON.stringify(generatedFiles));
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            if (!response.success) {
                if (status) { status.className = 'status-indicator error'; status.title = response.data; }
                messageReviewCode.innerHTML = wp_autoplugin.messages.code_generation_error + ' <pre>' + response.data + '</pre>';
                return;
            }
            if (status) status.className = 'status-indicator generated';
            generatedFiles[file.path] = response.data.file_content;

            if (response.data.token_usage) {
                totalTokenUsage.input_tokens += response.data.token_usage.input_tokens || 0;
                totalTokenUsage.output_tokens += response.data.token_usage.output_tokens || 0;
                updateTokenDisplay();
                addTokenUsageToGlobal('Extend File: ' + file.path, response.data.token_usage.input_tokens || 0, response.data.token_usage.output_tokens || 0);
            }

            // Update editor
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
            messageReviewCode.innerHTML = wp_autoplugin.messages.code_generation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    function updateProgress(current, total, fileName) {
        const progressBar = document.getElementById('file-generation-progress');
        const progressText = document.getElementById('progress-text');
        const percentage = (current / total) * 100;
        if (progressBar) progressBar.style.width = percentage + '%';
        if (progressText) progressText.innerHTML = `Generating <code>${fileName}</code> (${current} of ${total})...`;
    }

    function disableSave(disabled) {
        const btn = document.querySelector('input[name="extended_plugin"]');
        if (btn) btn.disabled = !!disabled;
    }
})();
