<script setup>
import { ref, watch } from 'vue';
import Modal from './Modal.vue';
import LoadingSpinner from './LoadingSpinner.vue';
import { useExpenseStore } from '../Stores/expenses.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    chargeId: { type: [Number, String], default: null },
    /** Quando definido, usa endpoint publico de comprovante (gestao sem login). */
    manageToken: { type: String, default: null },
});

const emit = defineEmits(['close']);

const expenseStore = useExpenseStore();
const proofUrl = ref(null);
const proofType = ref(null);
const loading = ref(false);
const error = ref(null);

watch(() => [props.show, props.chargeId, props.manageToken], async ([visible, id, token]) => {
    if (visible && id) {
        loading.value = true;
        error.value = null;
        proofUrl.value = null;
        try {
            if (token) {
                const res = await fetch(
                    `/api/v1/public/charges/${id}/proof?manage_token=${encodeURIComponent(token)}`,
                );
                if (!res.ok) {
                    throw new Error('fetch failed');
                }
                const blob = await res.blob();
                proofUrl.value = URL.createObjectURL(blob);
                proofType.value = blob.type;
            } else {
                const result = await expenseStore.downloadProofUrl(id);
                proofUrl.value = result.url;
                proofType.value = result.type;
            }
        } catch {
            error.value = 'Falha ao carregar comprovante.';
        } finally {
            loading.value = false;
        }
    } else {
        if (proofUrl.value) {
            URL.revokeObjectURL(proofUrl.value);
            proofUrl.value = null;
        }
    }
});
</script>

<template>
    <Modal :show="show" title="Comprovante" @close="emit('close')">
        <div class="flex items-center justify-center min-h-[200px]">
            <LoadingSpinner v-if="loading" />
            <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>
            <template v-else-if="proofUrl">
                <img
                    v-if="proofType?.startsWith('image/')"
                    :src="proofUrl"
                    alt="Comprovante"
                    class="max-w-full max-h-[60vh] rounded-lg"
                />
                <iframe
                    v-else-if="proofType === 'application/pdf'"
                    :src="proofUrl"
                    class="w-full h-[60vh] rounded-lg border"
                />
                <a v-else :href="proofUrl" download class="text-indigo-600 hover:text-indigo-800">Baixar comprovante</a>
            </template>
        </div>
    </Modal>
</template>
