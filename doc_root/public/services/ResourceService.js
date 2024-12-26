import {API_CONFIG} from '/config/apiConfig.js';

class ResourceService {
    constructor(resourceType) {
        this.endpoint = API_CONFIG.endpoints.resources[resourceType];
    }

    async getPage(page = 1, pageSize = 10, order = "asc", orderBy = "id", filter = {}) {
        const params = {
            page,
            pageSize,
            order,
            orderBy,
            ...filter
        };

        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(this.endpoint, 'GET', params)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response.data.results;
    }

    async create(data) {
        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(this.endpoint, 'POST', data)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response;
    }

    async update(id, data) {
        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(`${this.endpoint}/${id}`, 'PATCH', data)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response;
    }

    async delete(id) {
        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(`${this.endpoint}/${id}`, 'DELETE')
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response;
    }

    async getDetails(id) {
        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(`${this.endpoint}/${id}`, 'GET')
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response.data.result;
    }
}

export {ResourceService};