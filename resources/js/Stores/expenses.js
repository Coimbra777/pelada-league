import { defineStore } from 'pinia';
import { api } from '../Services/api.js';

export const useExpenseStore = defineStore('expenses', {
    state: () => ({
        expenses: [],
        currentExpense: null,
        loading: false,
        error: null,
    }),

    actions: {
        async fetchExpenses(teamId) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.get(`/teams/${teamId}/expenses`);
                this.expenses = data.expenses;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao carregar despesas.';
            } finally {
                this.loading = false;
            }
        },

        async fetchExpense(teamId, expenseId) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.get(`/teams/${teamId}/expenses/${expenseId}`);
                this.currentExpense = data.expense;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao carregar despesa.';
            } finally {
                this.loading = false;
            }
        },

        async createExpense(teamId, formData) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.post(`/teams/${teamId}/expenses`, formData);
                this.expenses.push(data.expense);
                return data.expense;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao criar despesa.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async validateCharge(chargeId) {
            try {
                const data = await api.patch(`/charges/${chargeId}/validate`);
                this._updateCharge(chargeId, data.charge);
                return data.charge;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao validar.';
                throw err;
            }
        },

        async rejectCharge(chargeId) {
            try {
                const data = await api.patch(`/charges/${chargeId}/reject`);
                this._updateCharge(chargeId, data.charge);
                return data.charge;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao rejeitar.';
                throw err;
            }
        },

        _updateCharge(chargeId, updatedCharge) {
            if (this.currentExpense?.charges) {
                const idx = this.currentExpense.charges.findIndex(c => c.id === chargeId);
                if (idx !== -1) {
                    this.currentExpense.charges[idx] = updatedCharge;
                }
            }
        },

        async downloadProofUrl(chargeId) {
            const token = localStorage.getItem('token');
            const res = await fetch(`/api/v1/charges/${chargeId}/proof`, {
                headers: { 'Authorization': `Bearer ${token}` },
            });
            if (!res.ok) throw new Error('Falha ao baixar comprovante.');
            const blob = await res.blob();
            return { url: URL.createObjectURL(blob), type: blob.type };
        },
    },
});
