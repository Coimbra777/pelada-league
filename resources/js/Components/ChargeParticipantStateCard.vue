<script setup>
import { computed } from 'vue';
import { getChargeStatusUx } from '../constants/chargeStatusUx.js';

const props = defineProps({
    status: { type: String, required: true },
    rejectionReason: { type: [String, null], default: null },
});

const ux = computed(() => getChargeStatusUx(props.status));

const shellClass = computed(() => {
    switch (props.status) {
        case 'pending':
            return 'border-slate-200 bg-slate-50/90';
        case 'proof_sent':
            return 'border-amber-200 bg-amber-50/70';
        case 'rejected':
            return 'border-orange-200 bg-orange-50/80';
        case 'validated':
            return 'border-emerald-200 bg-emerald-50/80';
        default:
            return 'border-stone-200 bg-stone-50';
    }
});
</script>

<template>
    <div
        class="rounded-xl border px-3 py-3 sm:px-4 sm:py-4 text-sm space-y-2 shadow-sm"
        :class="shellClass"
    >
        <p class="text-base font-semibold text-gray-900 leading-snug">
            {{ ux.panelTitle }}
        </p>
        <p class="text-gray-600 leading-relaxed text-sm">
            {{ ux.panelBody }}
        </p>
        <div
            v-if="status === 'rejected' && rejectionReason"
            class="text-sm text-orange-950/95 bg-white/90 border border-orange-100 rounded-lg px-3 py-2.5 mt-1"
        >
            <p class="text-xs font-semibold text-orange-900 uppercase tracking-wide mb-1">
                O que o responsável comentou
            </p>
            <p class="leading-relaxed">
                {{ rejectionReason }}
            </p>
        </div>
    </div>
</template>
