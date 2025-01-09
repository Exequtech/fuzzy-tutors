import { API_CONFIG } from "/config/apiConfig.js";

class SessionManager {
    static TOKEN_KEY = 'authToken';
    
    static async getNewToken() {
        try {
            const response = await fetch(`${API_CONFIG.baseUrl}${API_CONFIG.endpoints.auth.token}`);
            const data = await response.json();
            
            if (response.ok) {
                this.setToken(data.token);
                return data.token;
            }
            return null;
        } catch (error) {
            console.error('Token acquisition failed:', error);
            throw error;
        }
    }

    static setToken(token) {
        localStorage.setItem(this.TOKEN_KEY, token);
    }

    static getToken() {
        return localStorage.getItem(this.TOKEN_KEY);
    }

    static clearToken() {
        localStorage.removeItem(this.TOKEN_KEY);
    }

    static isLoggedIn() {
        return !!this.getToken();
    }

    static logOut() {
        localStorage.removeItem(this.TOKEN_KEY);
        window.location.href = '/authentication.html';
    }

    static redirectToHome() {
        window.location.href = '/index.html';
    }
}

export {SessionManager};
