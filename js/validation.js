document.addEventListener('DOMContentLoaded', () => {

    // UPPERCASE NAMES
    const nameFields = ['firstname', 'middlename', 'lastname'];

    nameFields.forEach(id => {

        const input = document.querySelector(`input[name="${id}"]`);

        if (input) {

            input.addEventListener('input', () => {

                input.value = input.value.toUpperCase();
            });
        }
    });

    // PASSWORD VALIDATION
    const passwordInput =
        document.querySelector('input[name="password"]');

    const confirmInput =
        document.querySelector('input[name="confirm_password"]');

    function validatePassword() {

        const pattern =
            /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/;

        passwordInput.setCustomValidity(

            pattern.test(passwordInput.value)
                ? ""
                : "Password must be at least 8 chars, with uppercase, lowercase, number & special char."
        );
    }

    if (passwordInput) {

        passwordInput.addEventListener('input', validatePassword);
    }

    if (confirmInput) {

        confirmInput.addEventListener('input', () => {

            confirmInput.setCustomValidity(

                confirmInput.value !== passwordInput.value
                    ? "Passwords do not match."
                    : ""
            );
        });
    }

    // SHOW BOTH PASSWORDS
    const showPasswordBtn =
        document.getElementById("showPasswordBtn");

    if (showPasswordBtn) {

        let showing = false;

        showPasswordBtn.addEventListener("click", function () {

            showing = !showing;

            const type = showing ? "text" : "password";
            passwordInput.type = type;
            confirmInput.type = type;
            this.textContent = showing ? "Hide Both" : "Show Both";
        });
    }
    
    // Restrict Name fields to letters only (block numbers/symbols as user types)
    document.querySelectorAll('input[name="firstname"], input[name="middlename"], input[name="lastname"]').forEach(input => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^A-Za-zÑñ\s\-\.]/g, '');
        });
    });

    // Restrict Phone field to digits only, max 11 chars
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', () => {
            phoneInput.value = phoneInput.value.replace(/\D/g, '').slice(0, 11);
        });
    }
    
});