(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var forms = document.querySelectorAll('.zbp-confirm-action');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!confirm(zbpAdmin.i18n.confirmBulk)) {
                    e.preventDefault();
                }
            });
        });

        var dismiss = document.querySelector('.notice.is-dismissible');
        if (dismiss) {
            dismiss.addEventListener('click', function() {
                this.style.display = 'none';
            });
        }
    });
})();
