/**
 * API клиент для взаимодействия с бэкендом
 */
const API = {
    baseURL: '/api',

    async request(method, url, data = null, options = {}) {
        try {
            const config = {
                method,
                url: this.baseURL + url,
                headers: { 'Content-Type': 'application/json' },
                ...options,
            };
            if (data && method !== 'GET') {
                config.data = data;
            }
            if (data && method === 'GET') {
                config.params = data;
            }
            const response = await axios(config);
            return response.data;
        } catch (error) {
            const msg = error.response?.data?.error || error.message || 'Ошибка сети';
            if (error.response?.status === 401) {
                // Разлогин при истечении сессии
                if (typeof app !== 'undefined' && app.currentUser) {
                    app.currentUser = null;
                }
            }
            throw new Error(msg);
        }
    },

    // Auth
    login(username, password) {
        return this.request('POST', '/auth/login', { username, password });
    },
    logout() {
        return this.request('POST', '/auth/logout');
    },
    getMe() {
        return this.request('GET', '/auth/me');
    },

    // Teachers
    getTeachers(params = {}) {
        return this.request('GET', '/teachers', params);
    },
    getTeacher(id) {
        return this.request('GET', '/teachers/' + id);
    },
    createTeacher(data) {
        return this.request('POST', '/teachers', data);
    },
    updateTeacher(id, data) {
        return this.request('PUT', '/teachers/' + id, data);
    },
    deleteTeacher(id) {
        return this.request('DELETE', '/teachers/' + id);
    },
    searchTeachers(params) {
        return this.request('GET', '/teachers/search', params);
    },

    // Classrooms
    getClassrooms(params = {}) {
        return this.request('GET', '/classrooms', params);
    },
    getClassroom(id) {
        return this.request('GET', '/classrooms/' + id);
    },
    createClassroom(data) {
        return this.request('POST', '/classrooms', data);
    },
    updateClassroom(id, data) {
        return this.request('PUT', '/classrooms/' + id, data);
    },
    deleteClassroom(id) {
        return this.request('DELETE', '/classrooms/' + id);
    },
    searchClassrooms(params) {
        return this.request('GET', '/classrooms/search', params);
    },
    getFreeClassrooms(params) {
        return this.request('GET', '/classrooms/free', params);
    },

    // Schedule
    getSchedule(params = {}) {
        return this.request('GET', '/schedule', params);
    },
    getScheduleItem(id) {
        return this.request('GET', '/schedule/' + id);
    },
    createScheduleItem(data) {
        return this.request('POST', '/schedule', data);
    },
    updateScheduleItem(id, data) {
        return this.request('PUT', '/schedule/' + id, data);
    },
    deleteScheduleItem(id) {
        return this.request('DELETE', '/schedule/' + id);
    },

    // Software
    getSoftware(params = {}) {
        return this.request('GET', '/software', params);
    },
    createSoftware(data) {
        return this.request('POST', '/software', data);
    },
    updateSoftware(id, data) {
        return this.request('PUT', '/software/' + id, data);
    },
    deleteSoftware(id) {
        return this.request('DELETE', '/software/' + id);
    },

    // Users
    getUsers() {
        return this.request('GET', '/users');
    },
    createUser(data) {
        return this.request('POST', '/users', data);
    },
    updateUser(id, data) {
        return this.request('PUT', '/users/' + id, data);
    },
    deleteUser(id) {
        return this.request('DELETE', '/users/' + id);
    },

    // Import
    importFile(url, formData) {
        return axios.post(this.baseURL + url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }).then(r => r.data);
    },

    // Export
    getExportUrl(type, params = {}) {
        const qs = Object.keys(params).map(k => k + '=' + encodeURIComponent(params[k])).join('&');
        return this.baseURL + '/export/' + type + (qs ? '?' + qs : '');
    }
};