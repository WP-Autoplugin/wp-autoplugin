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
    for (const part in plan) {
        // Hide testing_plan for now; store it globally for later display.
        if (part === 'testing_plan') {
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
        if (typeof content === 'object') {
            content = buildSubSections(content);
        }
        // Put content in a textarea for editing (except plugin_name → text input)
        if (part === 'plugin_name') {
            accordion += `<input type="text" value="${content}" id="plugin_name" />`;
        } else {
            accordion += `<textarea rows="10">${content.trim()}</textarea>`;
        }
        accordion += '</div>';
        accordion += '</div>';
    }

    return accordion;
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
