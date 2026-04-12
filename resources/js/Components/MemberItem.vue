<script setup>
import StatusBadge from './StatusBadge.vue';

const props = defineProps({
    member: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
    showResend: { type: Boolean, default: false },
});

const emit = defineEmits(['validate', 'reject', 'viewProof', 'resend']);

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

const status = props.member.charge_status || props.member.status;

const canResend = props.showResend && ['pending', 'rejected'].includes(status);
</script>

<template>
    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ member.name }}</p>
            <p v-if="member.phone" class="text-xs text-gray-500">{{ member.phone }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:ml-3">
            <span class="text-sm font-semibold text-gray-900 whitespace-nowrap">{{ formatCurrency(member.amount) }}</span>
            <StatusBadge :status="status" />
        </div>
        <div v-if="canResend" class="w-full sm:w-auto mt-1 sm:mt-0">
            <button
                type="button"
                class="w-full sm:w-auto px-3 py-2 text-xs font-medium text-indigo-700 bg-indigo-50 rounded-lg min-h-[44px]"
                @click="emit('resend', member.id)"
            >
                Reenviar link
            </button>
        </div>
        <div v-if="isAdmin" class="flex flex-wrap gap-1 sm:ml-2 sm:justify-end">
            <button
                v-if="status === 'proof_sent'"
                @click="emit('viewProof', member.charge_id)"
                class="px-2 py-1.5 text-xs text-indigo-600 hover:bg-indigo-50 rounded min-h-[44px] sm:min-h-0"
            >
                Ver comprovante
            </button>
            <button
                v-if="status === 'proof_sent'"
                @click="emit('validate', member.charge_id)"
                class="px-2 py-1 text-xs text-green-700 hover:bg-green-50 rounded font-medium"
            >
                Validar
            </button>
            <button
                v-if="status === 'proof_sent'"
                @click="emit('reject', member.charge_id)"
                class="px-2 py-1 text-xs text-red-700 hover:bg-red-50 rounded font-medium"
            >
                Rejeitar
            </button>
            <button
                v-if="status === 'validated' || status === 'rejected'"
                @click="emit('viewProof', member.charge_id)"
                class="px-2 py-1.5 text-xs text-indigo-600 hover:bg-indigo-50 rounded min-h-[44px] sm:min-h-0"
            >
                Ver comprovante
            </button>
        </div>
    </div>
</template>
