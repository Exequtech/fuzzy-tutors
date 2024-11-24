const DisplayLibrary = {
    // Helper function to display validation errors
    indicateInputValidation(inputElement, validationResult) {
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
    },

    indicateProcessLoading(parentElement, currentProcessMessage) {
        // Remove existing loader if any
        const existingLoader = document.querySelector('.process-loader-container');
        if (existingLoader) {
            existingLoader.remove();
        }

        // If no message is provided, just remove the loader and return
        if (!currentProcessMessage) {
            return;
        }

        // Create loader container
        const loaderContainer = document.createElement('div');
        loaderContainer.className = 'process-loader-container';
        Object.assign(loaderContainer.style, {
            position: 'absolute',
            top: '0',
            left: '0',
            width: '100%',
            height: '100%',
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: 'rgba(255, 255, 255, 0.9)',
            zIndex: '1000'
        });

        // Create spinner
        const spinner = document.createElement('div');
        spinner.className = 'process-loader-spinner';
        Object.assign(spinner.style, {
            width: '48px',
            height: '48px',
            border: '5px solid #f3f3f3',
            borderTop: '5px solid #3498db',
            borderRadius: '50%',
            animation: 'spin 1s linear infinite',
            marginBottom: '15px'
        });

        // Create message
        const message = document.createElement('div');
        message.className = 'process-loader-message';
        message.textContent = currentProcessMessage;
        Object.assign(message.style, {
            color: '#333',
            fontSize: '14px',
            textAlign: 'center',
            maxWidth: '40%'
        });

        // Add keyframe animation for spinner
        const styleSheet = document.createElement('style');
        styleSheet.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(styleSheet);

        // Assemble and append to parent
        loaderContainer.appendChild(spinner);
        loaderContainer.appendChild(message);
        
        // Ensure parent element has position relative if not already set
        if (getComputedStyle(parentElement).position === 'static') {
            parentElement.style.position = 'relative';
        }
        
        parentElement.appendChild(loaderContainer);
    }
}

export {
    DisplayLibrary
};