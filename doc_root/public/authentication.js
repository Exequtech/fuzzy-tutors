import { services } from './dataHandler.js';
import { FormValidator } from './FormValidator.js';
import {DisplayLibrary} from './DisplayLibrary.js';

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get references to the forms and toggle elements
    const loginForm = document.querySelector('.container > form:nth-of-type(1)');
    const registrationForm = document.querySelector('.container > form:nth-of-type(2)');
    const toggleElements = document.querySelectorAll('#registerLoginToggle');

    // References to form elements
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');

    const loginUsernameTxt = document.getElementById('loginUsernameTxt');
    const loginPasswordTxt = document.getElementById('loginPasswordTxt');
    const usernameTxt = document.getElementById('usernameTxt');
    const userTypeSlt = document.getElementById('userTypeSlt');
    const emailTxt = document.getElementById("emailTxt");

    // Forgot Password Modal Elements
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    const closeModalBtn = document.querySelector('.close');
    const sendResetLinkBtn = document.getElementById('sendResetLinkBtn');
    const resetEmailTxt = document.getElementById('resetEmailTxt');

    // Initially hide the registration form
    if (registrationForm) {
        registrationForm.style.display = 'none';
    }

    // Add click event listeners to all toggle elements
    toggleElements.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default link behavior
            
            // Toggle visibility of forms
            if (loginForm && registrationForm) {
                if (loginForm.style.display === 'none') {
                    // Show login form, hide registration form
                    loginForm.style.display = 'block';
                    registrationForm.style.display = 'none';
                    
                    loginForm.style.opacity = '0';
                    setTimeout(() => {
                        loginForm.style.opacity = '1';
                    }, 10);
                } else {
                    // Show registration form, hide login form
                    loginForm.style.display = 'none';
                    registrationForm.style.display = 'block';
                    
                    registrationForm.style.opacity = '0';
                    setTimeout(() => {
                        registrationForm.style.opacity = '1';
                    }, 10);
                }
            }
        });
    });

    /* ===================================== Forgot Password =============================================== */
    // Forgot Password Modal Functionality
    forgotPasswordLink?.addEventListener('click', (e) => {
        e.preventDefault();
        forgotPasswordModal.style.display = 'block';
    });

    closeModalBtn?.addEventListener('click', () => {
        forgotPasswordModal.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target === forgotPasswordModal) {
            forgotPasswordModal.style.display = 'none';
        }
    });

    sendResetLinkBtn?.addEventListener('click', async () => {
        const email = resetEmailTxt.value;
        const emailValidation = FormValidator.validateEmail(email);
        
        if (emailValidation.isValid) {
            try {
                const result = await services.auth.requestPasswordReset(email);
                forgotPasswordModal.style.display = 'none';
            } catch (error) {
                alert('Error sending reset link. Please try again.');
            }
        } else {
            alert(emailValidation.message);
        }
    });

    /* ===================================== User Login =============================================== */
    loginBtn.addEventListener('click', async (e)=>{
        e.preventDefault();

        let formValidation = FormValidator.validateLoginCredentials(loginUsernameTxt.value, loginPasswordTxt.value);
        if (formValidation.isValid) {
            let loginResult = await services.auth.login(loginUsernameTxt.value, formValidation.isEmail, loginPasswordTxt.value);
            if (!loginResult.isSuccessful)
                alert(loginResult.message);
        } else {
            alert(formValidation.message);
        }
    });


    /* ===================================== User Registration =============================================== */
    registerBtn.addEventListener('click', async (e) => {
        e.preventDefault();

        let formValidation = FormValidator.validateRegistrationCredentials(usernameTxt.value, userTypeSlt.value, emailTxt.value, passwordTxt.value, passwordConfirmationTxt.value);
        if (formValidation.isValid) {
            let registrationResult = await services.auth.register(usernameTxt.value, userTypeSlt.value, emailTxt.value, passwordTxt.value);
            if (!registrationResult.isSuccessful)
                alert(registrationResult.message);
        } else {
            alert(formValidation.message);
        }
    });

    /* ------------------------------------- Input Validation -------------------------------------------------*/
    usernameTxt.addEventListener('focusout', (e) => {
        DisplayLibrary.indicateInputValidation(usernameTxt, FormValidator.validateUsername(usernameTxt.value));
    });

    userTypeSlt.addEventListener('focusout', (e) => {
        DisplayLibrary.indicateInputValidation(userTypeSlt, FormValidator.validateUserType(userTypeSlt.value));
    });

    emailTxt.addEventListener('focusout', (e) => {
        DisplayLibrary.indicateInputValidation(emailTxt, FormValidator.validateEmail(emailTxt.value));
    });

    passwordTxt.addEventListener('focusout', (e) => {
        DisplayLibrary.indicateInputValidation(passwordTxt, FormValidator.validatePassword(passwordTxt.value));
    });

    passwordConfirmationTxt.addEventListener('focusout', (e) => {
        if(passwordConfirmationTxt.value != passwordTxt.value) {
            DisplayLibrary.indicateInputValidation(passwordConfirmationTxt, new ValidationResult(false, "Passwords does not match"));
        }
    });

    /* =============================== Animations ==================================== */
    // Add CSS for smooth transitions
    const style = document.createElement('style');
    style.textContent = `
        form {
            transition: opacity 0.3s ease-in-out;
        }
    `;
    document.head.appendChild(style);
});