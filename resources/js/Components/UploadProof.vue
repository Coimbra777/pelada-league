<script setup>
import { ref } from 'vue';
import Button from './Button.vue';
import { usePublicExpenseStore } from '../Stores/publicExpense.js';
import { useToast } from '../Composables/useToast.js';

const props = defineProps({
    chargeId: { type: [Number, String], required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['uploaded']);

const store = usePublicExpenseStore();
const toast = useToast();
const fileInput = ref(null);
const uploaded = ref(false);

async function handleUpload() {
    const file = fileInput.value?.files?.[0];
    if (!file) {
        toast.error('Selecione um arquivo.');
        return;
    }

    const maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
        toast.error('Arquivo muito grande. Maximo 5MB.');
        return;
    }

    const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!allowed.includes(file.type)) {
        toast.error('Tipo invalido. Use JPG, PNG ou PDF.');
        return;
    }

    try {
        await store.uploadProof(props.chargeId, file);
        uploaded.value = true;
        toast.success('Comprovante enviado!');
        emit('uploaded');
    } catch {
        // Error handled in store
    }
}
</script>

<template>
    <div>
        <div v-if="!uploaded">
            <label class="block text-sm font-medium text-gray-700 mb-2">Enviar comprovante</label>
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,application/pdf"
                :disabled="disabled"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 disabled:opacity-50"
            />
            <p class="text-xs text-gray-400 mt-1">JPG, PNG ou PDF. Max 5MB.</p>
            <p v-if="store.error" class="text-sm text-red-600 mt-1">{{ store.error }}</p>
            <Button @click="handleUpload" :loading="store.loading" :disabled="disabled" class="w-full mt-3">
                Enviar comprovante
            </Button>
        </div>
        <div v-else class="text-center py-3 bg-green-50 rounded-lg space-y-3">
            <p class="text-sm text-green-700 font-medium">Comprovante enviado!</p>
            <slot name="after-upload" />
        </div>
    </div>
</template>
