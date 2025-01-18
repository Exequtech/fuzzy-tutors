import { services } from './dataHandler.js';
import { FormValidator } from './FormValidator.js';
import { DisplayLibrary } from './DisplayLibrary.js';

document.addEventListener('DOMContentLoaded', function() {
    const newPasswordTxt = document.getElementById('newPasswordTxt');
    const confirmNewPasswordTxt = document.getElementById('confirmNewPasswordTxt');
    const resetPasswordBtn = document.getElementById('resetPasswordBtn');

    // Get token from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (!token) {
        alert('Invalid or missing reset token');
        window.location.href = 'authentication.html';
        return;
    }

    // Password validation
    newPasswordTxt.addEventListener('focusout', (e) => {
        DisplayLibrary.indicateInputValidation(newPasswordTxt, 
            FormValidator.validatePassword(newPasswordTxt.value));
    });

    confirmNewPasswordTxt.addEventListener('focusout', (e) => {
        if(confirmNewPasswordTxt.value !== newPasswordTxt.value) {
            DisplayLibrary.indicateInputValidation(confirmNewPasswordTxt, 
                new ValidationResult(false, "Passwords do not match"));
        }
    });

    // Reset password button click handler
    resetPasswordBtn.addEventListener('click', async (e) => {
        e.preventDefault();

        const password = newPasswordTxt.value;
        const confirmPassword = confirmNewPasswordTxt.value;

        const passwordValidation = FormValidator.validatePassword(password);
        
        if (!passwordValidation.isValid) {
            alert(passwordValidation.message);
            return;
        }

        if (password !== confirmPassword) {
            alert('Passwords do not match');
            return;
        }

        try {
            const result = await services.auth.resetPassword(token, password);
            if (result.isSuccessful) {
                window.location.href = 'authentication.html';
            }
        } catch (error) {
            alert('Error resetting password. Please try again.');
        }
    });
});