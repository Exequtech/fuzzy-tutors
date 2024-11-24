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
        login: '/login',
        token: '/ot-token'
    }
};

// Enhanced SessionManager with token handling
const SessionManager = {
    TOKEN_KEY: 'authToken',
    
    async getNewToken() {
        try {
            const response = await fetch(`${API_CONFIG.baseUrl}${API_CONFIG.endpoints.token}`);
            const data = await response.json();
            
            if (response.ok && data.token) {
                this.setToken(data.token);
                return data.token;
            }
            throw new Error('Failed to obtain token');
        } catch (error) {
            console.error('Token acquisition failed:', error);
            throw error;
        }
    },

    setToken(token) {
        localStorage.setItem(this.TOKEN_KEY, token);
    },

    getToken() {
        return localStorage.getItem(this.TOKEN_KEY);
    },

    clearToken() {
        localStorage.removeItem(this.TOKEN_KEY);
    },

    isLoggedIn() {
        return !!this.getToken();
    },

    redirectToHome() {
        window.location.href = '/index.html';
    }
};

// Utility function to handle API calls with token
async function makeApiCall(endpoint, method, body) {
    try {
        const headers = {
            'Content-Type': 'application/json'
        };

        // Add token if available
        const token = SessionManager.getToken();
        if (token) {
            headers['X-OT-Token'] = token;
        }

        const response = await fetch(`${API_CONFIG.baseUrl}${endpoint}`, {
            method,
            headers,
            body: JSON.stringify(body)
        });

        const data = await response.json();

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
            
            if (attempt === maxRetries) return result;
            
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

    const response = await withRetry(() => 
        makeApiCall(API_CONFIG.endpoints.signup, 'POST', body)
    );

    if (response.isSuccessful) {
        await SessionManager.getNewToken();
    }

    return response;
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

    const response = await withRetry(() => 
        makeApiCall(API_CONFIG.endpoints.login, 'POST', body)
    );

    if (response.isSuccessful) {
        await SessionManager.getNewToken();
        SessionManager.redirectToHome();
    }

    return response;
}

// Export the functions and classes
export {
    registerUser,
    loginUser,
    SessionManager,
    ApiResponse
};