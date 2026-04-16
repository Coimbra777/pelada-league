<script setup>
import { computed } from 'vue';
import StatusBadge from './StatusBadge.vue';

const props = defineProps({
    member: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
    moderationEnabled: { type: Boolean, default: true },
    /** 'admin' no painel do responsável para rótulos de cobrança adequados */
    chargePerspective: {
        type: String,
        default: 'participant',
        validator: (v) => ['participant', 'admin'].includes(v),
    },
});

const emit = defineEmits(['validate', 'reject', 'viewProof']);

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

const status = computed(() => props.member.charge_status || props.member.status);

/** Somente com comprovante aguardando decisão — em `rejected` o participante deve reenviar primeiro. */
const canValidateOrReject = computed(
    () => props.moderationEnabled && status.value === 'proof_sent',
);
</script>

<template>
    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ member.name }}</p>
            <p v-if="member.phone" class="text-xs text-gray-500">{{ member.phone }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:ml-3">
            <span class="text-sm font-semibold text-gray-900 whitespace-nowrap">{{ formatCurrency(member.amount) }}</span>
            <StatusBadge :status="status" :charge-perspective="chargePerspective" />
        </div>
        <div v-if="isAdmin" class="flex flex-wrap gap-1 sm:ml-2 sm:justify-end w-full sm:w-auto">
            <button
                v-if="['proof_sent', 'validated', 'rejected'].includes(status)"
                type="button"
                class="px-2 py-1.5 text-xs text-indigo-600 hover:bg-indigo-50 rounded min-h-[44px] sm:min-h-0"
                @click="emit('viewProof', member.charge_id)"
            >
                Ver comprovante
            </button>
            <button
                v-if="canValidateOrReject"
                type="button"
                class="px-2 py-1 text-xs text-green-700 hover:bg-green-50 rounded font-medium"
                @click="emit('validate', member.charge_id)"
            >
                Validar
            </button>
            <button
                v-if="canValidateOrReject"
                type="button"
                class="px-2 py-1 text-xs text-red-700 hover:bg-red-50 rounded font-medium"
                @click="emit('reject', member.charge_id)"
            >
                Rejeitar
            </button>
        </div>
    </div>
</template>
