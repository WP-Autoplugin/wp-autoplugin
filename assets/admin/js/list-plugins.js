(function () {
    window.onload = function () {
        // Add confirm dialog to <span class="delete"><a href="...">Delete</a></span>
        var deleteButtons = document.querySelectorAll('.delete a');
        for (var i = 0; i < deleteButtons.length; i++) {
            deleteButtons[i].addEventListener('click', function (e) {
                if (!confirm('Are you sure you want to delete this plugin?')) {
                    e.preventDefault();
                }
            });
        }
    };
})();