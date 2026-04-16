import { defineStore } from 'pinia';
import { api, setToken, clearToken, getToken } from '../Services/api.js';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: getToken(),
        loading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        userName: (state) => state.user?.name ?? '',
    },

    actions: {
        async login(email, password) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.post('/auth/login', { email, password });
                this.user = data.user;
                this.token = data.token;
                setToken(data.token);
            } catch (err) {
                this.error = err.data?.message || 'Falha no login.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async register(formData) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.post('/auth/register', formData);
                this.user = data.user;
                this.token = data.token;
                setToken(data.token);
            } catch (err) {
                this.error = err.data?.message || 'Falha no registro.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async fetchUser() {
            if (!this.token) return;
            this.loading = true;
            try {
                const data = await api.get('/auth/me');
                this.user = data.user;
            } catch {
                this.user = null;
                this.token = null;
                clearToken();
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                await api.post('/auth/logout');
            } catch {
                // Clear local state even if API fails
            } finally {
                this.user = null;
                this.token = null;
                clearToken();
            }
        },
    },
});
