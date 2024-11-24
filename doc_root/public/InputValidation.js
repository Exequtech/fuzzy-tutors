// Constants
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 50;
const USERNAME_MIN_LENGTH = 2;
const USERNAME_MAX_LENGTH = 30;
const USER_TYPES = ['tutor', 'student', 'owner'];

class ValidationResult {
    constructor(isValid = false, message = '') {
        this.isValid = isValid;
        this.message = message;
    }
}

class FormValidator {
    static validateUsername(username) {
        if (!username) {
            return new ValidationResult(false, 'Username is required');
        }
        if (username.length < USERNAME_MIN_LENGTH) {
            return new ValidationResult(false, `Username must be at least ${USERNAME_MIN_LENGTH} characters`);
        }
        if (username.length > USERNAME_MAX_LENGTH) {
            return new ValidationResult(false, `Username must be less than ${USERNAME_MAX_LENGTH} characters`);
        }
        if (!/^[a-zA-Z0-9_-]+$/.test(username)) {
            return new ValidationResult(false, 'Username can only contain letters, numbers, underscore and hyphen');
        }
        return new ValidationResult(true);
    }

    static validateUserType(userType) {
        if (!userType) {
            return new ValidationResult(false, 'User type is required');
        }
        if (!USER_TYPES.includes(userType.toLowerCase())) {
            return new ValidationResult(false, 'Invalid user type');
        }
        return new ValidationResult(true);
    }

    static validateEmail(email) {
        if (!email) {
            return new ValidationResult(false, 'Email is required');
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            return new ValidationResult(false, 'Invalid email format');
        }
        return new ValidationResult(true);
    }

    static validatePassword(password) {
        if (!password) {
            return new ValidationResult(false, 'Password is required');
        }
        if (password.length < PASSWORD_MIN_LENGTH) {
            return new ValidationResult(false, `Password must be at least ${PASSWORD_MIN_LENGTH} characters`);
        }
        if (password.length > PASSWORD_MAX_LENGTH) {
            return new ValidationResult(false, `Password must be less than ${PASSWORD_MAX_LENGTH} characters`);
        }
        
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumbers = /\d/.test(password);
        const hasSpecialChar = /[!@#$%^&*]/.test(password);
        
        if (!(hasUpperCase && hasLowerCase && hasNumbers && hasSpecialChar)) {
            return new ValidationResult(false, 'Password must contain uppercase, lowercase, numbers and special characters');
        }
        
        return new ValidationResult(true);
    }

    static validateLoginCredentials(username, password) {
        let isEmail = false;

        const usernameValidation = this.validateUsername(username);
        const emailValidation = this.validateEmail(username);
        const passwordValidation = this.validatePassword(password);
        
        if (emailValidation.isValid) {
            isEmail = true; 
        } else if (!usernameValidation.isValid) 
            return { isValid: usernameValidation.isValid, message: usernameValidation.message, isEmail };
                
        if (!passwordValidation.isValid) 
            return { isValid: passwordValidation.isValid, message: passwordValidation.message, isEmail };
        
        return { isValid: true, message: '', isEmail };
    }

    static validateRegistrationCredentials(username, userType, email, password, confirmPassword) {
        const usernameValidation = this.validateUsername(username);
        const userTypeValidation = this.validateUserType(userType);
        const emailValidation = this.validateEmail(email);
        const passwordValidation = this.validatePassword(password);
        
        if (!usernameValidation.isValid) return usernameValidation;
        if (!userTypeValidation.isValid) return userTypeValidation;
        if (!emailValidation.isValid) return emailValidation;
        if (!passwordValidation.isValid) return passwordValidation;
        
        if (password !== confirmPassword) {
            return new ValidationResult(false, 'Passwords do not match');
        }
        
        return new ValidationResult(true);
    }
}

// Helper function to display validation errors
function displayValidationIndication(inputElement, validationResult) {
    // Remove any existing error messages
    const existingError = inputElement.previousElementSibling;
    if (existingError?.classList.contains('validation-error')) {
        existingError.remove();
    }
    
    if (!validationResult.isValid) {
        const errorSpan = document.createElement('span');
        errorSpan.className = 'validation-error';
        errorSpan.style.color = 'var(--danger-color)';
        errorSpan.textContent = validationResult.message;
        inputElement.before(errorSpan);
        inputElement.classList.add('invalid');
    } else {
        inputElement.classList.remove('invalid');
    }
}

export {
    FormValidator,
    displayValidationIndication
}