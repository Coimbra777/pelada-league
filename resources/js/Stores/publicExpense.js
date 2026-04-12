import { defineStore } from 'pinia';
import { api } from '../Services/api.js';

export const usePublicExpenseStore = defineStore('publicExpense', {
    state: () => ({
        expense: null,
        members: null,
        selectedMember: null,
        loading: false,
        error: null,
    }),

    actions: {
        async fetchExpense(hash) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.get(`/public/expenses/${hash}`);
                this.expense = data.expense;
            } catch (err) {
                this.error = err.data?.message || 'Despesa nao encontrada.';
            } finally {
                this.loading = false;
            }
        },

        async identifyMember(hash, name) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.post(`/public/expenses/${hash}/identify`, { name });
                this.members = data.members;
                return data.members;
            } catch (err) {
                this.error = err.data?.message || 'Membro nao encontrado.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        selectMember(member) {
            this.selectedMember = member;
        },

        async uploadProof(chargeId, file) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.upload(`/public/charges/${chargeId}/upload-proof`, file);
                return data.proof;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao enviar comprovante.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async markAsPaid(chargeId) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.post(`/public/charges/${chargeId}/mark-as-paid`);
                return data;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao marcar como pago.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        reset() {
            this.expense = null;
            this.members = null;
            this.selectedMember = null;
            this.error = null;
        },
    },
});
