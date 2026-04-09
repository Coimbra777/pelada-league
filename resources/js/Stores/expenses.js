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

        async syncCharge(chargeId) {
            try {
                const data = await api.post(`/charges/${chargeId}/sync`);
                if (this.currentExpense?.charges) {
                    const idx = this.currentExpense.charges.findIndex(c => c.id === chargeId);
                    if (idx !== -1) {
                        this.currentExpense.charges[idx] = data.charge;
                    }
                }
                return data.charge;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao sincronizar.';
                throw err;
            }
        },

        getChargeById(chargeId) {
            return this.currentExpense?.charges?.find(
                c => c.id === Number(chargeId)
            ) || null;
        },
    },
});
