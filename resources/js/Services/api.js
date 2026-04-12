const API_BASE = '/api/v1';

function getToken() {
    return localStorage.getItem('token');
}

function setToken(token) {
    localStorage.setItem('token', token);
}

function clearToken() {
    localStorage.removeItem('token');
}

async function request(method, endpoint, body = null) {
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };

    const token = getToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const options = { method, headers };
    if (body && method !== 'GET') {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(`${API_BASE}${endpoint}`, options);

    if (response.status === 401) {
        const isPublicEndpoint = endpoint.startsWith('/public/');
        if (!isPublicEndpoint && endpoint !== '/auth/login' && endpoint !== '/auth/register') {
            clearToken();
        }
        throw { status: 401, data: { message: 'Unauthorized' } };
    }

    if (response.status === 204) {
        return null;
    }

    const data = await response.json();

    if (!response.ok) {
        throw { status: response.status, data };
    }

    return data;
}

async function upload(endpoint, file, fieldName = 'file') {
    const headers = {
        'Accept': 'application/json',
    };

    const token = getToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const formData = new FormData();
    formData.append(fieldName, file);

    const response = await fetch(`${API_BASE}${endpoint}`, {
        method: 'POST',
        headers,
        body: formData,
    });

    if (response.status === 401) {
        const isPublicEndpoint = endpoint.startsWith('/public/');
        if (!isPublicEndpoint && endpoint !== '/auth/login') {
            clearToken();
        }
        throw { status: 401, data: { message: 'Unauthorized' } };
    }

    const data = await response.json();

    if (!response.ok) {
        throw { status: response.status, data };
    }

    return data;
}

async function requestAbsolute(method, url, body = null) {
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };

    const token = getToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const options = { method, headers };
    if (body && method !== 'GET') {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);

    if (response.status === 204) {
        return null;
    }

    const data = await response.json();

    if (!response.ok) {
        throw { status: response.status, data };
    }

    return data;
}

export const api = {
    get: (url) => request('GET', url),
    post: (url, body) => request('POST', url, body),
    put: (url, body) => request('PUT', url, body),
    patch: (url, body) => request('PATCH', url, body),
    delete: (url) => request('DELETE', url),
    upload: (url, file, fieldName) => upload(url, file, fieldName),
    createPublicExpense: (body) => requestAbsolute('POST', '/api/public/expenses', body),
};

export { getToken, setToken, clearToken };
