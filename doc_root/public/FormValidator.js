// Constants
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 50;
const USERNAME_MIN_LENGTH = 2;
const USERNAME_MAX_LENGTH = 60;
const USER_TYPES = ['tutor', 'student', 'owner'];

class ValidationResult {
    constructor(isValid = false, message = '') {
        this.isValid = isValid;
        this.message = message;
    }
}

class FormValidator {
    static validateUsername(username, strict = true) {
        if (!username) {
            return new ValidationResult(false, 'Username is required');
        }
        if (username.length < USERNAME_MIN_LENGTH) {
            return new ValidationResult(false, `Username must be at least ${USERNAME_MIN_LENGTH} characters`);
        }
        if (username.length > USERNAME_MAX_LENGTH) {
            return new ValidationResult(false, `Username must be less than ${USERNAME_MAX_LENGTH} characters`);
        }
        if (strict && !/^[\w\-\s]+$/.test(username)) {
            return new ValidationResult(false, 'Username can only contain alphanumeric chars, dash (-) and spaces');
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

    static validateEmail(email, strict = true) {
        if (!email) {
            return new ValidationResult(false, 'Email is required');
        }
        const emailRegex = /^(?=[^\.]+(\.[^\.]+)?@[^\.]+(\.[^\.]+)+)[\w\.]+@[\w\.]+$/;
        if (strict && !emailRegex.test(email)) {
            return new ValidationResult(false, 'Invalid email format');
        }
        return new ValidationResult(true);
    }

    static validatePassword(password, strict = true) {
        if (!password) {
            return new ValidationResult(false, 'Password is required');
        }
        if (password.length < PASSWORD_MIN_LENGTH) {
            return new ValidationResult(false, `Password must be at least ${PASSWORD_MIN_LENGTH} characters`);
        }
        if (password.length > PASSWORD_MAX_LENGTH) {
            return new ValidationResult(false, `Password must be less than ${PASSWORD_MAX_LENGTH} characters`);
        }
        
        if (strict) {
            if (!/[a-z]+/.test(password)) {
                return new ValidationResult(false, 'Password must contain a lower-case letter');
            }
            if (!/[A-Z]+/.test(password)) {
                return new ValidationResult(false, 'Password must contain an upper-case letter');
            }
            if (!/[\d]+/.test(password)) {
                return new ValidationResult(false, 'Password must contain at least 1 digit');
            }
            if (!/[^\w\s]+/.test(password)) {
                return new ValidationResult(false, 'Password must contain a special character');
            }
        }
        
        return new ValidationResult(true);
    }

    static validateLoginCredentials(username, password) {
        let isEmail = false;

        const usernameValidation = this.validateUsername(username, false);
        const emailValidation = this.validateEmail(username, false);
        const passwordValidation = this.validatePassword(password, false);
        
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

export {
    FormValidator
}