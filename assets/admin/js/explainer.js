// step-1-explain, step-2-explanation
(function () {
    'use strict';

    // ----- DOM References -----
    const stepAskQuestion  = document.querySelector('.step-1-explain');
    const stepShowExplanation = document.querySelector('.step-2-explanation');

    const explainPluginForm = document.getElementById('explain-plugin-form');
    const questionField = document.getElementById('plugin_question');
    const focusOptions = document.getElementsByName('explain_focus');

    const messageExplainPlugin = document.getElementById('explain-plugin-message');
    const messageExplanation = document.getElementById('explanation-message');
    
    const explanationContainer = document.getElementById('plugin_explanation_container');
    const questionDisplay = document.getElementById('question-display');
    
    // ----- State Variables -----
    let currentState = 'askQuestion';
    let pluginQuestion = '';
    let explanationFocus = 'general';
    let explanationText = '';

    // Step mapping
    const steps = {
        askQuestion: stepAskQuestion,
        showExplanation: stepShowExplanation
    };

    // Logic to run on step show
    const onShowStep = {
        askQuestion: () => {
            if (pluginQuestion) {
                questionField.value = pluginQuestion;
            }
            
            // Set the focus radio button
            for (const radio of focusOptions) {
                if (radio.value === explanationFocus) {
                    radio.checked = true;
                    break;
                }
            }
        },
        showExplanation: () => {
            // Update the question display
            if (pluginQuestion.trim() !== '') {
                questionDisplay.textContent = pluginQuestion;
            } else {
                // If no question, show the focus type
                let focusText = '';
                for (const radio of focusOptions) {
                    if (radio.checked) {
                        switch(radio.value) {
                            case 'security':
                                focusText = wp_autoplugin.messages.security_focus || 'Security Analysis';
                                break;
                            case 'performance':
                                focusText = wp_autoplugin.messages.performance_focus || 'Performance Review';
                                break;
                            case 'code-quality':
                                focusText = wp_autoplugin.messages.code_quality_focus || 'Code Quality Analysis';
                                break;
                            case 'usage':
                                focusText = wp_autoplugin.messages.usage_focus || 'Usage Instructions';
                                break;
                            default:
                                focusText = wp_autoplugin.messages.general_explanation || 'General Explanation';
                        }
                        break;
                    }
                }
                questionDisplay.textContent = focusText;
            }

            // Format the explanation with syntax highlighting if needed
            explanationContainer.innerHTML = formatExplanation(explanationText);
        }
    };

    // ----- Event Handlers -----

    async function handleExplainPluginSubmit(event) {
        event.preventDefault();

        // Get the question and focus
        pluginQuestion = questionField.value.trim();
        
        // Get selected focus
        for (const radio of focusOptions) {
            if (radio.checked) {
                explanationFocus = radio.value;
                break;
            }
        }

        explainPluginForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(messageExplainPlugin, wp_autoplugin.messages.generating_explanation || 'Generating plugin explanation...');
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_explain_plugin');
        formData.append('plugin_question', pluginQuestion);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('explain_focus', explanationFocus);
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            explainPluginForm.parentElement.classList.remove('loading');

            if (!response.success) {
                messageExplainPlugin.innerHTML = (wp_autoplugin.messages.explanation_error || 'Error generating explanation:') + ' <pre>' + response.data + '</pre>';
                return;
            }

            explanationText = response.data;
            currentState = 'showExplanation';
            wpAutoPluginCommon.handleStepChange(steps, 'showExplanation', onShowStep);
        } catch (error) {
            loader.stop();
            explainPluginForm.parentElement.classList.remove('loading');
            messageExplainPlugin.innerHTML = (wp_autoplugin.messages.explanation_error || 'Error generating explanation:') + ' <pre>' + error.message + '</pre>';
        }
    }

    // ----- Helper Functions -----

    /**
     * Format the explanation text by converting Markdown to HTML using marked.js.
     * @param {string} text - The explanation text in Markdown format.
     * @return {string} - HTML formatted explanation.
     */
    function formatExplanation(text) {
        // Configure marked.js
        marked.setOptions({
            breaks: true,
            gfm: true,
            silent: true
        });

        // Convert markdown to HTML.
        try {
            return DOMPurify.sanitize(marked.parse(text));
        } catch (e) {
            console.error('Markdown parsing failed:', e);
            return '<p>Error parsing explanation. Please try again.</p>';
        }
    }

    // ----- Copy and Download Functions -----

    function copyToClipboard() {
        navigator.clipboard.writeText(explanationText).then(() => {
            messageExplanation.innerHTML = wp_autoplugin.messages.copied || 'Explanation copied to clipboard!';
            setTimeout(() => {
                messageExplanation.innerHTML = '';
            }, 2000);
        }).catch(err => {
            messageExplanation.innerHTML = (wp_autoplugin.messages.copy_failed || 'Failed to copy:') + ' ' + err;
        });
    }

    function downloadAsText() {
        const pluginName = document.querySelector('h2').textContent.replace('Explanation for: ', '').trim();
        const filename = `${pluginName.toLowerCase().replace(/\s+/g, '-')}-explanation.txt`;
        
        const element = document.createElement('a');
        element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(explanationText));
        element.setAttribute('download', filename);
        element.style.display = 'none';
        document.body.appendChild(element);
        element.click();
        document.body.removeChild(element);
    }

    // ----- Attach Event Listeners -----
    explainPluginForm.addEventListener('submit', handleExplainPluginSubmit);

    // Button to go back to ask another question
    document.getElementById('new-question').addEventListener('click', function() {
        currentState = 'askQuestion';
        wpAutoPluginCommon.handleStepChange(steps, 'askQuestion', onShowStep);
    });

    // Copy and download buttons
    document.getElementById('copy-explanation').addEventListener('click', copyToClipboard);
    document.getElementById('download-explanation').addEventListener('click', downloadAsText);

    // Handle back button in browser
    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.state) {
            wpAutoPluginCommon.handleStepChange(steps, event.state.state, onShowStep);
        } else {
            wpAutoPluginCommon.handleStepChange(steps, 'askQuestion', onShowStep);
        }
    });

    // Initialize the first step
    wpAutoPluginCommon.handleStepChange(steps, 'askQuestion', onShowStep);

    // Initialize the submit handlers
    attachFormSubmitListeners();

})();
