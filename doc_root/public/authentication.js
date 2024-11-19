// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
// Get references to the forms and toggle elements
const loginForm = document.querySelector('.container > form:nth-of-type(1)');
const registrationForm = document.querySelector('.container > form:nth-of-type(2)');
const toggleElements = document.querySelectorAll('#registerLoginToggle');

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

const loginBtn = document.getElementById('loginBtn');
const emailOrUsername = document.getElementById('loginUsername');
const password = document.getElementById('loginPassword');

loginBtn.addEventListener('click', async (e)=>{
    
    if (isValidLoginCredentials()) {
        let body = {
            password: password.value
        };
        if (emailOrUsername.value.includes('@')) {
            body.email = emailOrUsername.value;
            console.log('You have email');
        } else {
            body.username = emailOrUsername.value;
            console.log('You have username');
        }
        let response = await fetch("/api/auth/login",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(body)
            }).then(response => response.json())
            .catch((error) => console.log(error.message));
        if(response.status == 200)
        {
            // Successful login
            console.log("Awe ma se kind");
            window.location.replace('index.html');
        }
        else
        {
            // Not successful
            if(response.status == 400)
            {
                // Invalid body sent
                console.log(response.detail);
            }
            else if(response.status == 401)
            {
                // Still unauthorized (user doesn't exist / login doesn't exist / invalid credentials)
                console.log(response.detail);
            }
        }
    } else {
        alert("Fill in all the fucking fields");
    }
})

document.getElementById('registerBtn').addEventListener('click', async (e) => {
    let username = document.getElementById('username');
    let userType = document.getElementById('userType');
    let email = document.getElementById('email');
    let password = document.getElementById('password');

    if (isValidRegisterCredentials()) {
        let body = {
            username: username.value,
            userType: userType.value,
            email: email.value,
            password: password.value
        }
        let response = await fetch("/api/auth/register",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(body)
            }).then(response => response.json())
            .catch((error) => console.log(error.message));
        if(response.status == 200)
        {
            // Successful login
            console.log("Awe ma se kind");
        } else {
            console.log("Registration unsuccessful");
        }
    } else {
        alert("Fill in all the fucking fields");
    }
});

function isValidLoginCredentials() {
    if (emailOrUsername.value != "" && password != "") {
        return true;
    } else {
        return false;
    }
}

function isValidRegistrationCredentials() {
    if (true) {
        return true
    } else {
        return false
    }
}

// Add CSS for smooth transitions
const style = document.createElement('style');
style.textContent = `
    form {
        transition: opacity 0.3s ease-in-out;
    }
`;
document.head.appendChild(style);
});