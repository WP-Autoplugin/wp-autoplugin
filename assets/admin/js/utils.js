// ----- Utility Functions -----
const nl2br = (str) => str.replace(/\n/g, '<br>');

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

function buildAccordion( plan ) {
    var accordion = '';
    if ( typeof plan === 'string' ) {
        plan = JSON.parse( plan );
    }
    if ( typeof plan !== 'object' ) {
        return 'Error: Invalid plan data.';
    }

    for ( var part in plan ) {
        // Hide testing_plan for now, we'll show it later.
        if ( part === 'testing_plan' ) {
            // If it's an object, let's turn it into a string.
            if ( typeof plan[part] === 'object' ) {
                wp_autoplugin.testing_plan = buildSubSections( plan[part] );
            } else {
                wp_autoplugin.testing_plan = plan[part];
            }
            continue;
        }
        var content = plan[part];
        var className = 'autoplugin-accordion';
        if ( part === 'plugin_name' ) {
            className += ' active';
        }
        accordion += '<div class="'+className+'">';
        accordion += '<h3 class="autoplugin-accordion-heading">';
        accordion += '<div class="autoplugin-accordion-trigger">';
        var partTitle = part.replace( /_/g, ' ' ).replace( /\b\w/g, function( letter ) {
            return letter.toUpperCase();
        } );
        accordion += '<span class="title">' + partTitle + '</span>';
        accordion += '<span class="icon"></span>';
        accordion += '</div>';
        accordion += '</h3>';
        accordion += '<div class="autoplugin-accordion-content">';
        if ( typeof content === 'object' ) {
            content = buildSubSections( content );
        }
        // Put the content inside a textarea for editing
        // Except for plugin_name, which should be a text input
        if ( part === 'plugin_name' ) {
            accordion += '<input type="text" value="' + content + '" id="plugin_name" />';
        } else {
            accordion += '<textarea rows="10">' + content.trim() + '</textarea>';
        }
        accordion += '</div>';
        accordion += '</div>';
    }

    return accordion;
}

function loadingIndicator(element, message) {
    const emojis = ['🤔', '🧐', '💭', '🤖', '🧠', '🛠️', '🔍', '👨‍💻', '🌀', '⏳'];
    let intervalId;
    let timeoutId; // Add this to track the timeout

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
                clearInterval(intervalId); // Clear the interval when done
                element.textContent += emoji;
                timeoutId = setTimeout(() => { // Assign timeout to a variable
                    element.textContent = message;
                    updateMessage();
                }, 1500); // Show emoji for 1.5s
            }
        }, 100); // Add dot every 100ms
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
            clearTimeout(timeoutId); // Clear the timeout as well
            timeoutId = null;
        }
        element.textContent = ''; // clear the element's text content
    }

    return { start, stop }; // return these to call them outside
}

function typingPlaceholder(inputElement, placeholders) {
    let placeholderIndex = 0;
    let charIndex = 0;
    let isDeleting = false;
    let typingSpeed = 70;
    let pauseDuration = 1000;
    let typingInterval;
    
    function type() {
        if (inputElement.value !== '') return;  // Stop if there's user input
    
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

function attachAccordionListeners() {
    var accordionTriggers = document.querySelectorAll('.autoplugin-accordion-trigger');
    accordionTriggers.forEach(function(trigger) {
        trigger.addEventListener('click', function() {
            var accordion = this.parentElement.parentElement;
            accordion.classList.toggle('active');
        });
    } );
}