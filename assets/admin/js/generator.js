(function () {
    'use strict';

    // ----- DOM References -----
    const stepGeneratePlan = document.querySelector('.step-1-generation');
    const stepReviewPlan   = document.querySelector('.step-2-plan');
    const stepReviewCode   = document.querySelector('.step-3-code');

    const generatePlanForm = document.getElementById('generate-plan-form');
    const generateCodeForm = document.getElementById('generate-code-form');
    const createPluginForm = document.getElementById('create-plugin-form');

    const pluginPlanContainer = document.getElementById('plugin_plan_container');
    const pluginCodeTextarea  = document.getElementById('plugin_code');

    // ----- State Variables -----
    let currentState       = 'generatePlan';
    let pluginDescription  = '';
    let pluginPlan         = {};
    let pluginCode         = '';
    let editorInstance     = null;
    let pluginMode         = 'simple';
    let projectStructure   = {};
    let generatedFiles     = {};
    let currentFileIndex   = 0;
    let fileEditors        = {};

    // Step mapping
    const steps = {
        generatePlan: stepGeneratePlan,
        reviewPlan:   stepReviewPlan,
        reviewCode:   stepReviewCode
    };

    // Logic to run on step show
    const onShowStep = {
        generatePlan: () => {
            document.getElementById('plugin_description').value = pluginDescription;
        },
        reviewPlan: () => {
            const accordion = buildAccordion(pluginPlan);
            pluginPlanContainer.innerHTML = accordion;
            attachAccordionListeners();
        },
        reviewCode: () => {
            if (pluginMode === 'complex') {
                showComplexPluginContent();
                document.getElementById('create_plugin').disabled = true;
                startFileGeneration();
            } else {
                pluginCodeTextarea.value = pluginCode;
                editorInstance = wpAutoPluginCommon.updateCodeEditor(editorInstance, pluginCodeTextarea, pluginCode);
            }
        }
    };

    // ----- Event Handlers -----

    async function handleGeneratePlanSubmit(event) {
        event.preventDefault();

        const descField = document.getElementById('plugin_description');
        if (descField.value.trim() === '') {
            document.getElementById('generate-plan-message').innerHTML = wp_autoplugin.messages.empty_description;
            return;
        }

        generatePlanForm.parentElement.classList.add('loading');
        pluginDescription = descField.value;

        const loader = loadingIndicator(document.getElementById('generate-plan-message'), wp_autoplugin.messages.generating_plan);
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_plan');
        formData.append('plugin_description', pluginDescription);
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');

            if (!response.success) {
                document.getElementById('generate-plan-message').innerHTML =
                    wp_autoplugin.messages.plan_generation_error + ' <pre>' + response.data + '</pre>';
                return;
            }

            pluginPlan = response.data;
            pluginMode = pluginPlan.project_structure ? 'complex' : 'simple';
            if (pluginMode === 'complex') {
                projectStructure = pluginPlan.project_structure;
            }
            currentState = 'reviewPlan';
            wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
        } catch (error) {
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');
            document.getElementById('generate-plan-message').innerHTML =
                wp_autoplugin.messages.plan_generation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    async function handleGenerateCodeSubmit(event) {
        event.preventDefault();

        generateCodeForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(document.getElementById('generate-code-message'), wp_autoplugin.messages.generating_code);
        loader.start();

        // Rebuild plugin plan from the accordion textareas/inputs
        const plan = {};
        const accordions = document.querySelectorAll('.autoplugin-accordion');
        accordions.forEach((accordion) => {
            const heading    = accordion.querySelector('.autoplugin-accordion-heading .title');
            const part       = heading.textContent;
            const contentEl  = accordion.querySelector('.autoplugin-accordion-content textarea')
                               || accordion.querySelector('.autoplugin-accordion-content input');
            const content    = contentEl ? contentEl.value : '';
            plan[part.toLowerCase().replace(/ /g, '_')] = content;
        });
        pluginPlan = plan;

        if (pluginMode === 'complex') {
            // For complex mode, we don't generate code here, just move to next step
            loader.stop();
            generateCodeForm.parentElement.classList.remove('loading');
            currentState = 'reviewCode';
            wpAutoPluginCommon.handleStepChange(steps, 'reviewCode', onShowStep);
        } else {
            // For simple mode, generate code as before
            const formData = new FormData();
            formData.append('action', 'wp_autoplugin_generate_code');
            formData.append('plugin_description', pluginDescription);
            formData.append('plugin_plan', JSON.stringify(plan));
            formData.append('security', wp_autoplugin.nonce);

            try {
                const response = await wpAutoPluginCommon.sendRequest(formData);
                loader.stop();
                generateCodeForm.parentElement.classList.remove('loading');

                if (!response.success) {
                    document.getElementById('generate-code-message').innerHTML =
                        wp_autoplugin.messages.code_generation_error + ' <pre>' + response.data + '</pre>';
                    return;
                }

                pluginCode = response.data;
                currentState = 'reviewCode';
                wpAutoPluginCommon.handleStepChange(steps, 'reviewCode', onShowStep);
            } catch (error) {
                loader.stop();
                generateCodeForm.parentElement.classList.remove('loading');
                document.getElementById('generate-code-message').innerHTML =
                    wp_autoplugin.messages.code_generation_error + ' <pre>' + error.message + '</pre>';
            }
        }
    }

    async function handleCreatePluginSubmit(event) {
        event.preventDefault();

        createPluginForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(document.getElementById('create-plugin-message'), wp_autoplugin.messages.creating_plugin);
        loader.start();

        const pluginName = document.getElementById('plugin_name').value;

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_create_plugin');
        formData.append('plugin_name', pluginName);
        formData.append('security', wp_autoplugin.nonce);
        
        if (pluginMode === 'complex') {
            // Update generated files with current editor content
            const updatedFiles = {...generatedFiles};
            for (const [filePath, editor] of Object.entries(fileEditors)) {
                if (editor && editor.codemirror) {
                    updatedFiles[filePath] = editor.codemirror.getValue();
                }
            }
            
            formData.append('project_structure', JSON.stringify(projectStructure));
            formData.append('generated_files', JSON.stringify(updatedFiles));
        } else {
            formData.append('plugin_code', editorInstance.codemirror.getValue());
        }

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');

            if (response.success) {
                // Show success message
                document.getElementById('create-plugin-message').innerHTML = wp_autoplugin.messages.plugin_created;
                document
                    .getElementById('create-plugin-message')
                    .insertAdjacentHTML(
                        'beforeend',
                        `<h2>${wp_autoplugin.messages.how_to_test}</h2>
                         <pre class="autoplugin-testing-plan">${nl2br(wp_autoplugin.testing_plan)}</pre>
                         <p>${wp_autoplugin.messages.use_fixer}</p>`
                    );

                document
                    .getElementById('create-plugin-message')
                    .insertAdjacentHTML(
                        'beforeend',
                        `<a href="${wp_autoplugin.activate_url}&plugin=${response.data}"
                           class="button button-primary">
                           ${wp_autoplugin.messages.activate}
                         </a>`
                    );

                // Hide form
                createPluginForm.style.display = 'none';
            } else {
                document.getElementById('create-plugin-message').innerHTML =
                    wp_autoplugin.messages.plugin_creation_error + ' <pre>' + response.data + '</pre>';
            }
        } catch (error) {
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');
            document.getElementById('create-plugin-message').innerHTML =
                wp_autoplugin.messages.plugin_creation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    // ----- Attach Event Listeners -----
    generatePlanForm.addEventListener('submit', handleGeneratePlanSubmit);
    generateCodeForm.addEventListener('submit', handleGenerateCodeSubmit);
    createPluginForm.addEventListener('submit', handleCreatePluginSubmit);

    document.getElementById('edit-description').addEventListener('click', () => {
        currentState = 'generatePlan';
        wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
    });

    document.getElementById('edit-plan').addEventListener('click', () => {
        currentState = 'reviewPlan';
        wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
    });

    // Handle back button
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.state) {
            wpAutoPluginCommon.handleStepChange(steps, event.state.state, onShowStep);
        } else {
            wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
        }
    });

    // Initialize step
    wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);

    // Placeholder typing effect for plugin_description
    let messages = wp_autoplugin.plugin_examples;
    messages.sort(() => Math.random() - 0.5);
    typingPlaceholder(document.getElementById('plugin_description'), messages);

    // Initialize the submit handlers
    attachFormSubmitListeners();

    // ----- Complex Plugin Mode Functions -----
    
    function showComplexPluginContent() {
        document.getElementById('simple-plugin-content').style.display = 'none';
        document.getElementById('complex-plugin-content').style.display = 'block';
        document.querySelector('.generation-progress').style.display = 'block';
    }

    async function startFileGeneration() {
        if (!projectStructure.files || projectStructure.files.length === 0) {
            document.getElementById('create-plugin-message').innerHTML = 'Error: No files to generate.';
            return;
        }

        generatedFiles = {};
        currentFileIndex = 0;
        
        // Create file tabs
        createFileTabs();
        
        // Generate files one by one
        await generateNextFile();
    }

    function createFileTabs() {
        const tabsContainer = document.getElementById('files-tabs');
        const contentContainer = document.getElementById('file-content');
                
        tabsContainer.innerHTML = '';
        contentContainer.innerHTML = '';
        
        projectStructure.files.forEach((file, index) => {
            // Create tab
            const tab = document.createElement('div');
            tab.className = 'file-tab';
            tab.dataset.file = file.path;
            tab.dataset.index = index;
            
            const icon = document.createElement('span');
            icon.className = `file-icon ${file.type}`;
            tab.appendChild(icon);
            
            const fileName = document.createElement('span');
            fileName.textContent = file.path;
            tab.appendChild(fileName);
            
            const status = document.createElement('span');
            status.className = 'status-indicator pending';
            tab.appendChild(status);
            
            tab.addEventListener('click', () => switchToFile(file.path));
            tabsContainer.appendChild(tab);
            
            // Create content area
            const content = document.createElement('div');
            content.className = 'file-editor';
            content.dataset.file = file.path;
            content.style.display = 'none';
            
            const textarea = document.createElement('textarea');
            textarea.id = `file-editor-${index}`;
            textarea.rows = 20;
            textarea.cols = 100;
            content.appendChild(textarea);
            
            contentContainer.appendChild(content);
        });
        
        // Activate first tab
        if (projectStructure.files.length > 0) {
            switchToFile(projectStructure.files[0].path);
        }
    }

    function switchToFile(filePath) {        
        // Update tabs
        document.querySelectorAll('.file-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        const activeTab = document.querySelector(`[data-file="${filePath}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
        }
        
        // Update content
        document.querySelectorAll('.file-editor').forEach(editor => {
            editor.style.display = 'none';
        });
        
        const activeEditor = document.querySelector(`.file-editor[data-file="${filePath}"]`);
        if (activeEditor) {
            activeEditor.style.display = 'block';
            
            // Check if there's a textarea inside
            const textarea = activeEditor.querySelector('textarea');
            
            // Force refresh CodeMirror if it exists
            const editor = fileEditors[filePath];
            if (editor && editor.codemirror) {
                setTimeout(() => {
                    editor.codemirror.refresh();
                }, 50);
            }
        }
    }

    async function generateNextFile() {
        if (currentFileIndex >= projectStructure.files.length) {
            // All files generated
            document.querySelector('.generation-progress').style.display = 'none';
            document.getElementById('create_plugin').disabled = false;
            
            // Switch to the last generated file
            const lastFile = projectStructure.files[projectStructure.files.length - 1];
            switchToFile(lastFile.path);
            
            return;
        }

        const file = projectStructure.files[currentFileIndex];
        updateProgress(currentFileIndex + 1, projectStructure.files.length);
        
        // Update tab status
        const tab = document.querySelector(`[data-index="${currentFileIndex}"]`);
        const status = tab.querySelector('.status-indicator');
        status.className = 'status-indicator generating';
        
        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_file');
        formData.append('file_index', currentFileIndex);
        formData.append('plugin_plan', JSON.stringify(pluginPlan));
        formData.append('project_structure', JSON.stringify(projectStructure));
        formData.append('generated_files', JSON.stringify(generatedFiles));
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            
            if (!response.success) {
                status.className = 'status-indicator error';
                status.title = response.data;
                showFileGenerationError(file, response.data);
                return;
            }

            // File generated successfully
            status.className = 'status-indicator generated';
            generatedFiles[file.path] = response.data.file_content;
            
            // Update editor content
            const textarea = document.getElementById(`file-editor-${currentFileIndex}`);
            
            if (textarea) {
                textarea.value = response.data.file_content;
                
                // Initialize CodeMirror editor
                const mode = getCodeMirrorMode(response.data.file_type);
                
                fileEditors[file.path] = wpAutoPluginCommon.updateCodeEditor(
                    fileEditors[file.path], 
                    textarea, 
                    response.data.file_content,
                    mode
                );
                
                // Force refresh the editor display
                if (fileEditors[file.path] && fileEditors[file.path].codemirror) {
                    setTimeout(() => {
                        fileEditors[file.path].codemirror.refresh();
                    }, 100);
                }
            }
            
            currentFileIndex++;
            
            // Debug: Check all editor states
            debugAllEditors();
            
            await generateNextFile();
            
        } catch (error) {
            status.className = 'status-indicator error';
            status.title = error.message;
            showFileGenerationError(file, error.message);
        }
    }

    function updateProgress(current, total) {
        const progressBar = document.getElementById('file-generation-progress');
        const progressText = document.getElementById('progress-text');
        
        const percentage = (current / total) * 100;
        progressBar.style.width = percentage + '%';
        progressText.textContent = `Generating file ${current} of ${total}...`;
    }

    function getCodeMirrorMode(fileType) {
        switch (fileType) {
            case 'php':
                return 'php';
            case 'js':
                return 'javascript';
            case 'css':
                return 'css';
            default:
                return 'text';
        }
    }

    function debugAllEditors() {
        // Check which tab is currently active
        const activeTab = document.querySelector('.file-tab.active');
        const activeEditor = document.querySelector('.file-editor[style*="block"]');
        
        // Check all textareas
        for (let i = 0; i < projectStructure.files.length; i++) {
            const file = projectStructure.files[i];
            const textarea = document.getElementById(`file-editor-${i}`);
            const editor = fileEditors[file.path];
            const fileEditor = document.querySelector(`.file-editor[data-file="${file.path}"]`);
        }
    }

    function showFileGenerationError(file, errorMessage) {
        const messageContainer = document.getElementById('create-plugin-message');
        messageContainer.innerHTML = `
            <div class="error-message">
                <strong>Error generating ${file.path}:</strong><br>
                ${errorMessage}
            </div>
            <div class="retry-actions">
                <button type="button" class="button" onclick="retryCurrentFile()">
                    Retry Current File
                </button>
                <button type="button" class="button" onclick="retryFromFile(${currentFileIndex})">
                    Retry From Here
                </button>
                <button type="button" class="button" onclick="skipCurrentFile()">
                    Skip This File
                </button>
            </div>
        `;
        messageContainer.style.display = 'block';
    }

    window.retryCurrentFile = async function() {
        document.getElementById('create-plugin-message').innerHTML = '';
        document.getElementById('create-plugin-message').style.display = 'none';
        
        // Reset the current file status
        const tab = document.querySelector(`[data-index="${currentFileIndex}"]`);
        if (tab) {
            const status = tab.querySelector('.status-indicator');
            status.className = 'status-indicator pending';
            status.title = '';
        }
        
        await generateNextFile();
    };

    window.retryFromFile = async function(fileIndex) {
        document.getElementById('create-plugin-message').innerHTML = '';
        document.getElementById('create-plugin-message').style.display = 'none';
        
        // Reset all files from this index onwards
        for (let i = fileIndex; i < projectStructure.files.length; i++) {
            const tab = document.querySelector(`[data-index="${i}"]`);
            if (tab) {
                const status = tab.querySelector('.status-indicator');
                status.className = 'status-indicator pending';
                status.title = '';
            }
            
            // Remove from generated files
            const file = projectStructure.files[i];
            if (generatedFiles[file.path]) {
                delete generatedFiles[file.path];
            }
        }
        
        currentFileIndex = fileIndex;
        await generateNextFile();
    };

    window.skipCurrentFile = async function() {
        document.getElementById('create-plugin-message').innerHTML = '';
        document.getElementById('create-plugin-message').style.display = 'none';
        
        // Mark current file as skipped
        const file = projectStructure.files[currentFileIndex];
        const tab = document.querySelector(`[data-index="${currentFileIndex}"]`);
        if (tab) {
            const status = tab.querySelector('.status-indicator');
            status.className = 'status-indicator error';
            status.title = 'Skipped';
        }
        
        // Create empty content for skipped file
        generatedFiles[file.path] = `// This file was skipped during generation\n// Please add your ${file.type.toUpperCase()} code here\n`;
        
        // Update editor content
        const textarea = document.getElementById(`file-editor-${currentFileIndex}`);
        if (textarea) {
            textarea.value = generatedFiles[file.path];
        }
        
        currentFileIndex++;
        await generateNextFile();
    };

    // Expose debug functions to global scope for manual debugging
    window.debugWPAutoplugin = {
        debugAllEditors: debugAllEditors,
        fixBlankEditors: function() {
            for (let i = 0; i < projectStructure.files.length; i++) {
                const file = projectStructure.files[i];
                const textarea = document.getElementById(`file-editor-${i}`);
                
                if (textarea && generatedFiles[file.path] && !textarea.value) {
                    textarea.value = generatedFiles[file.path];
                    
                    // Re-initialize CodeMirror if needed
                    if (!fileEditors[file.path] || !fileEditors[file.path].codemirror) {
                        const mode = getCodeMirrorMode(file.type);
                        fileEditors[file.path] = wpAutoPluginCommon.updateCodeEditor(
                            fileEditors[file.path], 
                            textarea, 
                            generatedFiles[file.path],
                            mode
                        );
                    }
                }
            }
        },
        switchToLastFile: function() {
            if (projectStructure && projectStructure.files && projectStructure.files.length > 0) {
                const lastFile = projectStructure.files[projectStructure.files.length - 1];
                switchToFile(lastFile.path);
            }
        },
        refreshAllEditors: function() {
            Object.entries(fileEditors).forEach(([filePath, editor]) => {
                if (editor && editor.codemirror) {
                    editor.codemirror.refresh();
                }
            });
        },
        state: {
            get currentFileIndex() { return currentFileIndex; },
            get generatedFiles() { return generatedFiles; },
            get fileEditors() { return fileEditors; },
            get projectStructure() { return projectStructure; }
        }
    };

})();
