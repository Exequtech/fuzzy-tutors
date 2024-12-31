import {ApiResponse} from '/utils/ApiResponse.js';
import {SessionManager} from '/services/SessionManager.js'
import { ResourceService } from './services/ResourceService.js';
import { AuthService } from '/services/AuthService.js';
import {ApiService} from '/services/ApiService.js';
import {API_CONFIG} from '/config/apiConfig.js';

class StudentService extends ResourceService {
    constructor() {
        super('student');
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

    async getReport(startDate, endDate, subjects, students, classId, trackables) {
        const params = { 
            startDate,
            endDate,
            subjects,
            trackables 
        };

        if (classId != null && students != null) {
            // TODO: Create (One must be null) and return error response
        } else if (classId != null) {
            params.classId = classId;
        } else if (students !=  null) {
            params.students = students;
        } else {
            // TODO: Create (both can't be null) and return error response
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
            ApiService.makeApiCall(API_CONFIG.endpoints.resources.calendar, 'GET', params)
        );

        if (response.isSuccessful) {
            await SessionManager.getNewToken();
        }

        return response.data.results;
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