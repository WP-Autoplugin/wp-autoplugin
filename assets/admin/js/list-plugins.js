(function () {
    window.onload = function () {
        // Add confirm dialog to <span class="delete"><a href="...">Delete</a></span>
        var deleteButtons = document.querySelectorAll('.delete a');
        for (var i = 0; i < deleteButtons.length; i++) {
            deleteButtons[i].addEventListener('click', function (e) {
                var message = wp_autoplugin?.messages?.delete_confirmation || 'Are you sure you want to delete this plugin?';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        }
    };
})();