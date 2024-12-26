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
        if (lessonData.classId !== null && lessonData.students !== null) {
            return ApiResponse.error('You can only select a class or students, NOT BOTH!');
        }

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
};

export { services, ApiResponse, SessionManager };