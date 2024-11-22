/* ================================================= Input Validation ===================================================== */
let result = {
    isValid: false,
    message: ""
};

let userTypes = ['tutor', 'student', 'owner'];

function validateUsername(username) {
    if(username == "") {
        result.isValid = false;
        result.message = "Fill in";
    } else if (username.length <= 2) {
        result.isValid = false;
        result.message = "Too short";
    } else if (username.length >= 30) {
        result.isValid = false;
        result.message = "Too long";
    } else {
        result.isValid = true;
        result.message = "";
    }
    
    return result;
}

function validateUserType(userType) {
    if(userType == "") {
        result.isValid = false;
        result.message = "Cannot be empty";
    } else if (!userTypes.includes(userType)) {
        result.isValid = false;
        result.message = "Does not exist";
    } else {
        result.isValid = true;
        result.message = "";
    }

    return result;
}

function validateEmail(email) {
    if(email == "") {
        result.isValid = false;
        result.message = "Email is required"
    } else if(!email.includes('@')) {
        result.isValid = false;
        result.message = "Email must contain @"
    } else {
        result.isValid = true;
        result.message = "";
    }

    return result;
}

function validatePassword(password) {
    if(password.length < 8) {
        result.isValid = false;
        result.message = "Password must at least be 8 characters long"
    } else if (password.length > 80) {
        result.isValid = false;
        result.message = "Password must be less than 80 characters";
    } else {
        result.isValid = true;
        result.message = "";
    }

    return result;
}

function validateLoginCredentials() {
    // todo: implement overall validation
    result.isValid = true;
    result.message = "Not implemented yet";
    return result;
}

function validateRegistrationCredentials() {
    // todo: implement overall validation
    result.isValid = true;
    result.message = "Not implemented yet";
    return result;
}

/* ------------------------------------------- Display Validation Errors ----------------------------------------------- */
function indicateValidation(inputElement, validationResult) {
    const validationErrorSpan = document.createElement('span');
    validationErrorSpan.style.color = '#ff3b3b';

    validationErrorSpan.textContent = validationResult.message;
    inputElement.before(validationErrorSpan);
}