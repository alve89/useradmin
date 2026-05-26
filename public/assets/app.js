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

    if (!form || !passwordInput || !passwordConfirmInput || !hidden2faInput || !modal || !modalInput || !cancelButton || !confirmButton) {
        return;
    }

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
})();