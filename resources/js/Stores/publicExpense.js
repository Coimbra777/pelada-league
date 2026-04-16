import { defineStore } from 'pinia';
import { api } from '../Services/api.js';

export const usePublicExpenseStore = defineStore('publicExpense', {
    state: () => ({
        expense: null,
        loading: false,
        error: null,
    }),

    actions: {
        reset() {
            this.expense = null;
            this.error = null;
        },

        async fetchExpense(hash, manage = null) {
            this.loading = true;
            this.error = null;
            try {
                const q = manage ? `?manage=${encodeURIComponent(manage)}` : '';
                const data = await api.get(`/public/expenses/${hash}${q}`);
                this.expense = data.expense;
            } catch (err) {
                this.error = err.data?.message || 'Erro ao carregar.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async validateCharge(chargeId, manageToken) {
            return api.patch(`/public/charges/${chargeId}/validate`, { manage_token: manageToken });
        },

        async rejectCharge(chargeId, manageToken) {
            return api.patch(`/public/charges/${chargeId}/reject`, { manage_token: manageToken });
        },

        async patchExpense(hash, manageToken, payload) {
            this.error = null;
            const q = manageToken ? `?manage=${encodeURIComponent(manageToken)}` : '';
            const data = await api.patch(`/public/expenses/${hash}${q}`, payload);
            this.expense = data.expense;
            return data;
        },

        async closeExpense(hash, manageToken) {
            this.error = null;
            const q = manageToken ? `?manage=${encodeURIComponent(manageToken)}` : '';
            const data = await api.patch(`/public/expenses/${hash}/close${q}`, {});
            this.expense = data.expense;
            return data;
        },

        async addExpenseParticipants(hash, manageToken, payload) {
            this.error = null;
            const q = manageToken ? `?manage=${encodeURIComponent(manageToken)}` : '';
            const data = await api.post(`/public/expenses/${hash}/participants${q}`, payload);
            this.expense = data.expense;
            return data;
        },

        async submitProof(hash, { name, phone, file }) {
            this.error = null;
            const formData = new FormData();
            formData.append('name', name);
            formData.append('phone', phone);
            formData.append('proof', file);
            try {
                return await api.postFormData(`/public/expenses/${hash}/submit-proof`, formData);
            } catch (err) {
                this.error = err.data?.message || 'Falha ao enviar comprovante.';
                throw err;
            }
        },
    },
});
