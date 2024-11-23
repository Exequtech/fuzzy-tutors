// Response class for standardizing API responses
class ApiResponse {
    constructor(isSuccessful = false, message = '', data = null) {
        this.isSuccessful = isSuccessful;
        this.message = message;
        this.data = data;
    }
}

// Configuration object for API endpoints
const API_CONFIG = {
    baseUrl: '/api/auth',
    endpoints: {
        signup: '/signup',
        login: '/login'
    }
};

// Utility function to handle API calls
async function makeApiCall(endpoint, method, body) {
    try {
        const response = await fetch(`${API_CONFIG.baseUrl}${endpoint}`, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                // Add any authentication headers if needed
                // 'Authorization': `Bearer ${getToken()}`
            },
            body: JSON.stringify(body)
        });

        const data = await response.json();

        // Check if the response status is in the successful range (200-299)
        if (response.ok) {
            return new ApiResponse(true, data.detail || 'Operation successful', data);
        } else {
            return new ApiResponse(false, data.detail || 'Operation failed', null);
        }
    } catch (error) {
        console.error('API call failed:', error);
        return new ApiResponse(false, 'Network error or server is unavailable', null);
    }
}

// Retry mechanism for failed API calls
async function withRetry(fn, maxRetries = 3, delay = 1000) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            const result = await fn();
            if (result.isSuccessful) return result;
            
            // If it's the last attempt, return the failed result
            if (attempt === maxRetries) return result;
            
            // Wait before retrying
            await new Promise(resolve => setTimeout(resolve, delay * attempt));
        } catch (error) {
            if (attempt === maxRetries) throw error;
            await new Promise(resolve => setTimeout(resolve, delay * attempt));
        }
    }
}

/**
 * Register a new user
 * @param {string} username - The username
 * @param {string} userType - The user type (student, tutor, owner)
 * @param {string} email - The email address
 * @param {string} password - The password
 * @returns {Promise<ApiResponse>} Response object
 */
async function registerUser(username, userType, email, password) {
    const body = {
        username,
        userType,
        email,
        password
    };

    return await withRetry(() => 
        makeApiCall(API_CONFIG.endpoints.signup, 'POST', body)
    );
}

/**
 * Login a user
 * @param {string} usernameOrEmail - The username or email
 * @param {boolean} isEmail - Whether the identifier is an email
 * @param {string} password - The password
 * @returns {Promise<ApiResponse>} Response object
 */
async function loginUser(usernameOrEmail, isEmail, password) {
    const body = {
        password,
        ...(isEmail ? { email: usernameOrEmail } : { username: usernameOrEmail })
    };

    return await withRetry(() => 
        makeApiCall(API_CONFIG.endpoints.login, 'POST', body)
    );
}

// Optional: Add session management
const SessionManager = {
    setToken(token) {
        localStorage.setItem('authToken', token);
    },

    getToken() {
        return localStorage.getItem('authToken');
    },

    clearToken() {
        localStorage.removeItem('authToken');
    },

    isLoggedIn() {
        return !!this.getToken();
    }
};

// Export the functions and classes
export {
    registerUser,
    loginUser,
    SessionManager,
    ApiResponse
};