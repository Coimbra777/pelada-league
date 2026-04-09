import { defineStore } from 'pinia';
import { api } from '../Services/api.js';

export const useTeamStore = defineStore('teams', {
    state: () => ({
        teams: [],
        currentTeam: null,
        members: [],
        dashboard: null,
        loading: false,
        error: null,
    }),

    actions: {
        async fetchTeams() {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.get('/teams');
                this.teams = data.teams;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao carregar equipes.';
            } finally {
                this.loading = false;
            }
        },

        async fetchTeam(id) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.get(`/teams/${id}`);
                this.currentTeam = data.team;
                this.members = data.members;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao carregar equipe.';
            } finally {
                this.loading = false;
            }
        },

        async createTeam(name) {
            this.loading = true;
            this.error = null;
            try {
                const data = await api.post('/teams', { name });
                this.teams.push(data.team);
                return data.team;
            } catch (err) {
                this.error = err.data?.message || 'Falha ao criar equipe.';
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async addMember(teamId, userId) {
            this.error = null;
            try {
                await api.post(`/teams/${teamId}/members`, { user_id: userId });
                await this.fetchTeam(teamId);
            } catch (err) {
                this.error = err.data?.message || 'Falha ao adicionar membro.';
                throw err;
            }
        },

        async removeMember(teamId, userId) {
            this.error = null;
            try {
                await api.delete(`/teams/${teamId}/members/${userId}`);
                this.members = this.members.filter(m => m.id !== userId);
            } catch (err) {
                this.error = err.data?.message || 'Falha ao remover membro.';
                throw err;
            }
        },

        async fetchDashboard(teamId) {
            this.error = null;
            try {
                this.dashboard = await api.get(`/teams/${teamId}/dashboard`);
            } catch (err) {
                this.error = err.data?.message || 'Falha ao carregar dashboard.';
            }
        },
    },
});
