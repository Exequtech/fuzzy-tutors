import { SessionManager } from '/services/SessionManager.js';
import { API_CONFIG } from '/config/apiConfig.js';
import { ApiResponse } from '/utils/ApiResponse.js';

class ApiService {
    static async makeApiCall(endpoint, method, body = null) {
        try {
            const headers = {
                'Content-Type': 'application/json'
            };

            const token = SessionManager.getToken();
            if (token) {
                headers['X-OT-Token'] = token;
            }

            const config = {
                method,
                headers
            };

            let url = `${API_CONFIG.baseUrl}${endpoint}`;

            if (method === 'GET' && body) {
                const queryString = new URLSearchParams(body).toString();
                url = `${url}?${queryString}`;
            } else if (['POST', 'PATCH'].includes(method)) {
                config.body = JSON.stringify(body);
            }

            const response = await fetch(url, config);
            const data = await response.json();

            return response.ok
                ? ApiResponse.success(data.detail, data)
                : ApiResponse.error(data.detail);
        } catch (error) {
            console.error('API call failed:', error);
            return ApiResponse.error('Network error or server is unavailable');
        }
    }

    static async withRetry(fn, maxRetries = 3, delay = 1000) {
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
}

export {ApiService};