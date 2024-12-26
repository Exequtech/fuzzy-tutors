const API_CONFIG = {
    baseUrl: '/api',
    endpoints: {
        auth: {
            signup: '/auth/signup',
            login: '/auth/login',
            token: '/auth/ot-token'
        },
        resources: {
            student: '/student',
            class: '/class',
            topic: '/topic',
            subject: '/subject',
            calendar: '/calendar/lesson',
            lesson: '/lesson',
            trackable: '/trackable',
            location: '/location'
        }
    }
};

export {API_CONFIG};