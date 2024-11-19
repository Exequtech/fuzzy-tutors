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
    
    // Add CSS for smooth transitions
    const style = document.createElement('style');
    style.textContent = `
        form {
            transition: opacity 0.3s ease-in-out;
        }
    `;
    document.head.appendChild(style);
});