import {API_CONFIG} from '../config/apiConfig.js';
import {ApiService} from '../services/ApiService.js';
import { SessionManager } from '../services/SessionManager.js';

class ResourceService {
    constructor(resourceType) {
        this.endpoint = API_CONFIG.endpoints.resources[resourceType];
    }

    /**
     * Sends a GET request for a page at this object's endpoint URL and returns the result.
     * 
     * @param {number} [page] - The target page. Defaults to 1
     * @param {number} [pageSize] - The amount of elements per page. Defaults to unset (resulting in server-side default)
     * @param {string} [order] - asc/desc
     * @param {string} [orderBy] - field name the server should order the responses by
     * @param {Object} [filter] - misc query params, overrides function parameters
     * @returns {Promise<(Array|null)>}
     */
    async getPage(page = 1, pageSize = null, order = "asc", orderBy = "id", filter = {}, internal = false) {
        if(!internal)
            console.error("GETPAGE: ", this.endpoint)
            
        const params = {
            page,
            order,
            orderBy,
            ...filter
        };

        if(!!pageSize)
            params.pageSize = pageSize;

        const response = await ApiService.withRetry(() =>
            ApiService.makeApiCall(this.endpoint, 'GET', params)
        );

        if(!response.isSuccessful) {
            console.log(`Failed to GET ${this.endpoint} data: `, response.message);
            return null;
        }
        if(!response.data?.results) {
            console.log(`Failed to GET ${this.endpoint} data: unexpected structure. Body:`, response.data);
            return null;
        }

        await SessionManager.getNewToken();

        return response.data.results;
    }

    async getAllPages(order = "asc", orderBy = "id", filter = {}) {
        let page = 1;
        const results = [];
        while(true) {
            const get = await this.getPage(page, null, order, orderBy, filter, true);
            if(get == null)
                return null;
            
            if(get.length === 0)
                return results;

            results.push(...get);
            page++;
        }
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