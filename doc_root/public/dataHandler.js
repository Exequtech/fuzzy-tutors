import {ApiResponse} from '/utils/ApiResponse.js';
import {SessionManager} from '/services/SessionManager.js'
import { ResourceService } from './services/ResourceService.js';
import { AuthService } from '/services/AuthService.js';
import {ApiService} from '/services/ApiService.js';
import {API_CONFIG} from '/config/apiConfig.js';
import { formatDateForApi, dateDiffed } from './utils/utilityFunctions.js';

class StudentService extends ResourceService {
    constructor() {
        super('student');
    }

    async getTrackableDetails(studentId, trackableName, params) {

        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(`${this.endpoint}/${studentId}/trackables/${trackableName}`, 'GET', params)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response.data.results;
    }
}

class ClassService extends ResourceService {
    constructor() {
        super('class');
    }
}

class TopicService extends ResourceService {
    constructor() {
        super('topic');
    }
}

class SubjectService extends ResourceService {
    constructor() {
        super('subject');
    }
}

class TrackableService extends ResourceService {
    constructor() {
        super('trackable');
    }

    async getAll(order = "asc", filter = {}) {
        const params = { order, ...filter };
        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(this.endpoint, 'GET', params)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response.data.results;
    }

    async getReport(startDate = dateDiffed(-7), endDate = dateDiffed(0), subjects = null, students = null, classId = null, trackables = null) {
        const params = { 
            startDate,
            endDate,
        };
        const others = {subjects, students, classId, trackables};
        for(let key in others) {
            if(others[key] !== null)
                params[key] = others[key];
        }
        
        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(API_CONFIG.endpoints.resources.trackableReport, 'GET', params)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response.data.results;
    }
}

class LocationService extends ResourceService {
    constructor() {
        super('location');
    }

    async getAll(order = "asc", filter = {}) {
        const params = { order, ...filter };
        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(this.endpoint, 'GET', params)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response.data.results;
    }

    async getReport(after = dateDiffed(-7), before = dateDiffed(0), locations = null, subjects = null) {
        const params = { 
            before, 
            after
        };

        const others = { locations, subjects };
        for(let key in others) {
            if(others[key] !== null)
                params[key] = others[key];
        }

        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(API_CONFIG.endpoints.resources.calendar, 'GET', params)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response.data.results;
    }

    async createLesson(lessonData) {

        return await this.create(lessonData);
    }
}

class LessonService extends ResourceService {
    constructor() {
        super('lesson');
    }

    async getLessonsBetweenDates(after, before) {
        const params = { before, after };
        const response = await ApiService.withRetry(() => 
            ApiService.makeApiCall(API_CONFIG.endpoints.resources.calendar, 'GET', params)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response.data.results;
    }

    async createLesson(lessonData) {

        return await this.create(lessonData);
    }
}

// Initialize services
const services = {
    auth: AuthService,
    student: new StudentService(),
    class: new ClassService(),
    topic: new TopicService(),
    trackable: new TrackableService(),
    lesson: new LessonService(),
    subject: new SubjectService(),
    location: new LocationService()
};

export { services, ApiResponse, SessionManager };