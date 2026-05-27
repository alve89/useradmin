(function () {
    const form = document.querySelector('form[data-user-form="1"]');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const hidden2faInput = document.getElementById('kas_2fa');
    const modal = document.getElementById('kas2faModal');
    const modalInput = document.getElementById('kas2faInput');
    const cancelButton = document.getElementById('kas2faCancel');
    const confirmButton = document.getElementById('kas2faConfirm');

    let confirmed = false;

    if (form && passwordInput && passwordConfirmInput && hidden2faInput && modal && modalInput && cancelButton && confirmButton) {
        function clearFieldErrors() {
            passwordInput.setCustomValidity('');
            passwordConfirmInput.setCustomValidity('');
        }

        passwordInput.addEventListener('input', clearFieldErrors);
        passwordConfirmInput.addEventListener('input', clearFieldErrors);

        form.addEventListener('submit', function (event) {
            clearFieldErrors();

            const password = passwordInput.value;
            const passwordConfirm = passwordConfirmInput.value;
            const passwordWasEntered = password !== '' || passwordConfirm !== '';

            if (!passwordWasEntered || confirmed) {
                return;
            }

            if (password === '') {
                event.preventDefault();
                passwordInput.setCustomValidity('Bitte gib das neue Passwort ein.');
                passwordInput.reportValidity();
                passwordInput.focus();
                return;
            }

            if (passwordConfirm === '') {
                event.preventDefault();
                passwordConfirmInput.setCustomValidity('Bitte wiederhole das neue Passwort.');
                passwordConfirmInput.reportValidity();
                passwordConfirmInput.focus();
                return;
            }

            if (password !== passwordConfirm) {
                event.preventDefault();
                passwordConfirmInput.setCustomValidity('Die Passwörter stimmen nicht überein.');
                passwordConfirmInput.reportValidity();
                passwordConfirmInput.focus();
                return;
            }

            event.preventDefault();

            modal.hidden = false;
            modalInput.value = '';
            modalInput.focus();
        });

        cancelButton.addEventListener('click', function () {
            modal.hidden = true;
            hidden2faInput.value = '';
            confirmed = false;
        });

        confirmButton.addEventListener('click', function () {
            const code = modalInput.value.trim();

            if (code === '') {
                modalInput.focus();
                return;
            }

            hidden2faInput.value = code;
            confirmed = true;
            modal.hidden = true;
            form.submit();
        });

        modalInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                confirmButton.click();
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                cancelButton.click();
            }
        });
    }

    const deleteModal = document.getElementById('deleteUserModal');
    const deleteModalInput = document.getElementById('deleteKas2faInput');
    const deleteCancelButton = document.getElementById('deleteUserCancel');
    const deleteConfirmButton = document.getElementById('deleteUserConfirm');
    const deleteUserLabel = document.getElementById('deleteUserLabel');
    const deleteUserUid = document.getElementById('deleteUserUid');
    const deleteUserMail = document.getElementById('deleteUserMail');

    let pendingDeleteForm = null;

    if (deleteModal && deleteModalInput && deleteCancelButton && deleteConfirmButton && deleteUserLabel && deleteUserUid && deleteUserMail) {
        document.querySelectorAll('.delete-user-form').forEach(function (deleteForm) {
            deleteForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const button = deleteForm.querySelector('.delete-user-button');

                if (!button) {
                    return;
                }

                pendingDeleteForm = deleteForm;

                deleteUserLabel.textContent = button.dataset.userLabel || '';
                deleteUserUid.textContent = 'UID: ' + (button.dataset.userUid || '');
                deleteUserMail.textContent = 'E-Mail: ' + (button.dataset.userMail || '');

                deleteModalInput.value = '';
                deleteModal.hidden = false;
                deleteModalInput.focus();
            });
        });

        deleteCancelButton.addEventListener('click', function () {
            deleteModal.hidden = true;
            pendingDeleteForm = null;
        });

        deleteConfirmButton.addEventListener('click', function () {
            const code = deleteModalInput.value.trim();

            if (code === '') {
                deleteModalInput.focus();
                return;
            }

            if (!pendingDeleteForm) {
                return;
            }

            const hiddenDelete2faInput = pendingDeleteForm.querySelector('input[name="kas_2fa"]');

            if (hiddenDelete2faInput) {
                hiddenDelete2faInput.value = code;
            }

            deleteModal.hidden = true;
            pendingDeleteForm.submit();
        });

        deleteModalInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                deleteConfirmButton.click();
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                deleteCancelButton.click();
            }
        });
    }
})();