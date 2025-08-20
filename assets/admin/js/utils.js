// ----- Utility Functions -----
const nl2br = (str) => str.replace(/\n/g, '<br>');

/**
 * Recursively build a string for nested plan sections.
 */
const buildSubSections = (data, indentLevel = 0) => {
    let content = '';
    const indent = '  ';
    for (const part in data) {
        let subContent = data[part];
        if (typeof subContent === 'object') {
            subContent = buildSubSections(subContent, indentLevel + 1);
        }
        const partTitle = part.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
        const indentation = indent.repeat(indentLevel);
        content += `${indentation}${partTitle}:\n${indentation}${subContent}\n`;
    }
    return content;
};

/**
 * Builds an accordion UI from the plugin plan object.
 */
function buildAccordion(plan) {
    if (typeof plan === 'string') {
        plan = JSON.parse(plan);
    }
    if (typeof plan !== 'object') {
        return 'Error: Invalid plan data.';
    }

    let accordion = '';
    let hasActive = false;
    let index = 0;
    const skipParts = new Set(['testing_plan', 'technically_feasible', 'explanation', 'hooks']);
    for (const part in plan) {
        // Hide testing_plan for now; store it globally for later display.
        if (skipParts.has(part)) {
            if (typeof plan[part] === 'object') {
                wp_autoplugin.testing_plan = buildSubSections(plan[part]);
            } else {
                wp_autoplugin.testing_plan = plan[part];
            }
            continue;
        }
        
        let content = plan[part];
        let className = 'autoplugin-accordion';
        if (part === 'plugin_name') {
            className += ' active';
            hasActive = true;
        }
        accordion += `<div class="${className}">`;
        accordion += '<h3 class="autoplugin-accordion-heading">';
        accordion += '<div class="autoplugin-accordion-trigger">';
        const partTitle = part.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
        accordion += `<span class="title">${partTitle}</span>`;
        accordion += '<span class="icon"></span>';
        accordion += '</div>';
        accordion += '</h3>';
        accordion += '<div class="autoplugin-accordion-content">';
        
        // Special handling for project_structure
        if (part === 'project_structure') {
            accordion += buildProjectStructureDisplay(content);
        } else if (typeof content === 'object') {
            content = buildSubSections(content);
            accordion += `<textarea rows="10">${content.trim()}</textarea>`;
        } else {
            // Normalize non-string values
            const normalized = (content === null || content === undefined) ? '' : String(content);
            // Put content in a textarea for editing (except plugin_name → text input)
            if (part === 'plugin_name') {
                accordion += `<input type="text" value="${normalized}" id="plugin_name" />`;
            } else {
                accordion += `<textarea rows="10">${normalized.trim()}</textarea>`;
            }
        }
        accordion += '</div>';
        accordion += '</div>';
        index++;
    }

    // If nothing marked active (e.g. no plugin_name in plan), open the first section
    if (!hasActive) {
        // Add ' active' to the first accordion container
        accordion = accordion.replace('class="autoplugin-accordion"', 'class="autoplugin-accordion active"');
    }

    return accordion;
}

/**
 * Build project structure display for complex plugins.
 */
function buildProjectStructureDisplay(structure) {
    let display = '<div class="project-structure-display">';
    
    // Display directories
    if (structure.directories && structure.directories.length > 0) {
        display += '<div class="directories-section">';
        display += '<h4>Directories:</h4>';
        display += '<div class="directory-tree">';
        structure.directories.forEach(dir => {
            display += `<div class="directory-item"><span class="folder-icon">📁</span> ${dir}</div>`;
        });
        display += '</div>';
        display += '</div>';
    }
    
    // Display files
    if (structure.files && structure.files.length > 0) {
        display += '<div class="files-section">';
        display += '<h4>Files:</h4>';
        display += '<div class="files-table">';
        display += '<table>';
        display += '<thead><tr><th>File</th><th>Type</th><th>Description</th></tr></thead>';
        display += '<tbody>';
        structure.files.forEach((file, index) => {
            const icon = getFileIcon(file.type);
            display += `<tr>`;
            display += `<td><span class="file-icon">${icon}</span> ${file.path}</td>`;
            display += `<td><span class="file-type ${file.type}">${file.type.toUpperCase()}</span></td>`;
            display += `<td><input type="text" value="${file.description}" data-file-index="${index}" class="file-description-input" /></td>`;
            display += `</tr>`;
        });
        display += '</tbody>';
        display += '</table>';
        display += '</div>';
        display += '</div>';
    }
    
    display += '</div>';
    return display;
}

/**
 * Get icon for file type.
 */
function getFileIcon(type) {
    switch (type) {
        case 'php':
            return '🐘';
        case 'js':
            return '📜';
        case 'css':
            return '🎨';
        default:
            return '📄';
    }
}

/**
 * Shows a dynamic loader indicator using emojis and text updates.
 */
function loadingIndicator(element, message) {
    const emojis = ['🤔', '🧐', '💭', '🤖', '🧠', '🛠️', '🔍', '👨‍💻', '🌀', '⏳'];
    let intervalId;
    let timeoutId;

    function getRandomInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function updateMessage() {
        let dotCount = 0;
        const totalDots = getRandomInt(10, 30);
        const emoji = emojis[getRandomInt(0, emojis.length - 1)];

        intervalId = setInterval(() => {
            if (dotCount < totalDots) {
                element.textContent = message + '.'.repeat(dotCount + 1);
                dotCount++;
            } else {
                clearInterval(intervalId);
                element.textContent += emoji;
                timeoutId = setTimeout(() => {
                    element.textContent = message;
                    updateMessage();
                }, 1500);
            }
        }, 100);
    }

    function start() {
        if (!intervalId) {
            updateMessage();
        }
    }

    function stop() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
        if (timeoutId) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
        element.textContent = '';
    }

    return { start, stop };
}

/**
 * Types/deletes placeholders in an input field for a "typing" effect.
 */
function typingPlaceholder(inputElement, placeholders) {
    let placeholderIndex = 0;
    let charIndex = 0;
    let isDeleting = false;
    let typingSpeed = 70;
    let pauseDuration = 1000;
    let typingInterval;
    
    function type() {
        if (inputElement.value !== '') return;  // If user has typed, stop.

        const currentPlaceholder = placeholders[placeholderIndex];
        
        if (isDeleting) {
            inputElement.setAttribute('placeholder', currentPlaceholder.substring(0, charIndex - 1));
            charIndex--;
        } else {
            inputElement.setAttribute('placeholder', currentPlaceholder.substring(0, charIndex + 1));
            charIndex++;
        }

        if (!isDeleting && charIndex === currentPlaceholder.length) {
            isDeleting = true;
            typingSpeed = 20;
            typingInterval = setTimeout(type, pauseDuration);
        } else if (isDeleting && charIndex === 0) {
            isDeleting = false;
            placeholderIndex = (placeholderIndex + 1) % placeholders.length;
            typingSpeed = 70;
            typingInterval = setTimeout(type, pauseDuration);
        } else {
            typingInterval = setTimeout(type, typingSpeed);
        }
    }
    
    function startTyping() {
        if (inputElement.value === '') {
            type();
        }
    }
    
    function stopTyping() {
        clearTimeout(typingInterval);
        inputElement.setAttribute('placeholder', '');
    }
    
    inputElement.addEventListener('focus', stopTyping);
    inputElement.addEventListener('blur', startTyping);
    inputElement.addEventListener('input', function() {
        if (this.value === '') {
            startTyping();
        } else {
            stopTyping();
        }
    });
    
    startTyping();
}

/**
 * Simple function to toggle .active on accordion triggers.
 */
function attachAccordionListeners() {
    const accordionTriggers = document.querySelectorAll('.autoplugin-accordion-trigger');
    accordionTriggers.forEach(trigger => {
        trigger.addEventListener('click', function () {
            const accordion = this.parentElement.parentElement;
            accordion.classList.toggle('active');
        });
    });
}

/**
 * CMD/CTRL + Enter on a textarea inside a form inside .wp-autoplugin elements submits the form.
 */
function attachFormSubmitListeners() {
    document.querySelectorAll('.wp-autoplugin form').forEach(form => {
        form.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('keydown', function (event) {
                if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                    form.querySelector('input[type="submit"]').click();
                }
            });
        });
    });
}
