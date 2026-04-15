<script setup>
import { computed } from 'vue';
import MemberItem from './MemberItem.vue';

const props = defineProps({
    members: { type: Array, required: true },
    isAdmin: { type: Boolean, default: false },
    showResend: { type: Boolean, default: false },
    /** Quando false, oculta validar/rejeitar/reenviar/copiar link; mantem ver comprovante para leitura */
    moderationEnabled: { type: Boolean, default: true },
    /**
     * Rótulos de status de cobrança no badge. Se null, admin usa 'admin', senão 'participant'.
     */
    chargeBadgePerspective: {
        type: String,
        default: null,
        validator: (v) => v === null || ['participant', 'admin'].includes(v),
    },
});

const emit = defineEmits(['validate', 'reject', 'viewProof', 'resend', 'copyParticipantLink']);

// Ordem: pagos, aguardando validacao, pendentes, rejeitados
const statusOrder = { validated: 0, proof_sent: 1, pending: 2, rejected: 3 };

const sorted = computed(() => {
    return [...props.members].sort((a, b) => {
        const sa = statusOrder[a.charge_status || a.status] ?? 4;
        const sb = statusOrder[b.charge_status || b.status] ?? 4;
        return sa - sb;
    });
});

const resolvedChargePerspective = computed(() => {
    if (props.chargeBadgePerspective) {
        return props.chargeBadgePerspective;
    }
    return props.isAdmin ? 'admin' : 'participant';
});
</script>

<template>
    <div class="divide-y divide-gray-100">
               <MemberItem
            v-for="member in sorted"
            :key="member.charge_id || member.id"
            :member="member"
            :is-admin="isAdmin"
            :show-resend="showResend"
            :moderation-enabled="moderationEnabled"
            :charge-perspective="resolvedChargePerspective"
            @validate="emit('validate', $event)"
            @reject="emit('reject', $event)"
            @view-proof="emit('viewProof', $event)"
            @resend="emit('resend', $event)"
            @copy-participant-link="emit('copyParticipantLink', $event)"
        />
    </div>
</template>
