const API_CONFIG = {
    baseUrl: '/api',
    endpoints: {
        auth: {
            signup: '/auth/signup',
            login: '/auth/login',
            logout: '/auth/logout',
            token: '/auth/ot-token',
            settings: '/settings/fields',
            changePassword: '/settings/password'
        },
        resources: {
            student: '/student',
            class: '/class',
            topic: '/topic',
            subject: '/subject',
            calendar: '/calendar/lesson',
            lesson: '/lesson',
            trackable: '/trackable',
            location: '/location',
            trackableReport: '/stats/trackables'
        }
    }
};

export {API_CONFIG};