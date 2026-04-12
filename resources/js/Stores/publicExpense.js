import { defineStore } from 'pinia';
import { api } from '../Services/api.js';

export const usePublicExpenseStore = defineStore('publicExpense', {
    state: () => ({
        expense: null,
        participantBundle: null,
        members: null,
        selectedMember: null,
        loading: false,
        error: null,
    }),

    actions: {
        reset() {
            this.expense = null;
            this.participantBundle = null;
            this.members = null;
            this.selectedMember = null;
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

        async fetchParticipantBundle(expenseHash, participantHash) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.get(`/public/expenses/${expenseHash}/participants/${participantHash}`);
                this.participantBundle = data;
                this.expense = data.expense;
            } catch (err) {
                this.error = err.data?.message || 'Erro ao carregar.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async identifyMember(hash, { name, phone }) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.post(`/public/expenses/${hash}/identify`, { name, phone });
                this.members = data.members;
                return data.members;
            } catch (err) {
                this.error = err.data?.message || 'Nao foi possivel identificar.';
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
                this.error = err.data?.message || 'Falha ao confirmar.';
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

        async resendParticipantLink(expenseHash, memberId, manageToken) {
            return api.post(`/public/expenses/${expenseHash}/participants/${memberId}/resend-link`, {
                manage_token: manageToken,
            });
        },
    },
});
