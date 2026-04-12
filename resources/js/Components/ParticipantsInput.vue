<script setup>
import { ref, watch } from 'vue';
import { formatPhoneBr } from '../Composables/useInputMasks.js';
import { digitsOnly, parseParticipantText } from '../utils/participantListParse.js';

const props = defineProps({
    /** Telefones (somente digitos) ja na despesa — nao entram no v-model */
    existingPhones: { type: Array, default: () => [] },
    /**
     * Se false, linhas incompletas sao ignoradas sem erro (fluxo criacao opcional).
     * Se true, linha iniciada exige nome e telefone valido antes de enviar.
     */
    blockIncompleteRows: { type: Boolean, default: true },
});

const participants = defineModel({ type: Array, default: () => [] });

const rows = ref([{ name: '', phone: '' }]);
const bulkText = ref('');
const globalError = ref('');
const skippedExistingCount = ref(0);

function excludedSet() {
    return new Set(props.existingPhones.map((p) => digitsOnly(String(p))).filter(Boolean));
}

function syncFromState() {
    const excluded = excludedSet();
    const fromRows = [];
    for (const r of rows.value) {
        const name = r.name.trim();
        const ph = digitsOnly(r.phone);
        if (!name && !ph) {
            continue;
        }
        if (name && ph.length >= 10) {
            fromRows.push({ name, phone: ph });
        }
    }

    const fromBulk = parseParticipantText(bulkText.value);
    const seen = new Set();
    const merged = [];
    let skipped = 0;

    for (const p of [...fromRows, ...fromBulk]) {
        if (seen.has(p.phone)) {
            continue;
        }
        if (excluded.has(p.phone)) {
            skipped++;
            continue;
        }
        seen.add(p.phone);
        merged.push({ name: p.name, phone: p.phone });
    }

    skippedExistingCount.value = skipped;
    participants.value = merged;
}

watch([rows, bulkText], syncFromState, { deep: true });
watch(() => props.existingPhones, syncFromState, { deep: true });

syncFromState();

function addRow() {
    rows.value.push({ name: '', phone: '' });
}

function removeRow(i) {
    rows.value.splice(i, 1);
    if (!rows.value.length) {
        rows.value.push({ name: '', phone: '' });
    }
}

function onPhoneInput(i, e) {
    rows.value[i].phone = formatPhoneBr(e.target.value);
}

function validate() {
    globalError.value = '';
    if (!props.blockIncompleteRows) {
        return true;
    }
    const partial = rows.value.some((r) => {
        const n = r.name.trim();
        const d = digitsOnly(r.phone);
        return (n && d.length < 10) || (!n && d.length > 0);
    });
    if (partial) {
        globalError.value = 'Preencha nome e telefone (min. 10 digitos) em cada linha iniciada.';
        return false;
    }
    return true;
}

function reset() {
    rows.value = [{ name: '', phone: '' }];
    bulkText.value = '';
    globalError.value = '';
    skippedExistingCount.value = 0;
    participants.value = [];
}

defineExpose({ validate, reset });
</script>

<template>
    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Participantes (manual)</label>
            <div class="space-y-3">
                <div
                    v-for="(row, i) in rows"
                    :key="i"
                    class="flex flex-col gap-2 sm:flex-row sm:items-stretch sm:gap-2"
                >
                    <input
                        v-model="row.name"
                        type="text"
                        placeholder="Nome"
                        class="min-h-[48px] flex-1 rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-0"
                    />
                    <input
                        :value="row.phone"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        placeholder="(11) 99999-9999"
                        class="min-h-[48px] w-full min-w-0 rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-0 sm:max-w-[200px]"
                        @input="onPhoneInput(i, $event)"
                    />
                    <button
                        type="button"
                        class="min-h-[48px] shrink-0 rounded-lg border border-gray-200 bg-white px-4 text-sm font-medium text-red-600 shadow-sm active:bg-red-50 sm:w-12 sm:px-0"
                        title="Remover linha"
                        @click="removeRow(i)"
                    >
                        x
                    </button>
                </div>
            </div>
            <button
                type="button"
                class="mt-3 w-full min-h-[48px] rounded-lg border border-indigo-200 bg-indigo-50 px-4 text-sm font-semibold text-indigo-800 active:bg-indigo-100 sm:w-auto"
                @click="addRow"
            >
                + Adicionar linha
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ou cole a lista (estilo WhatsApp)</label>
            <textarea
                v-model="bulkText"
                rows="4"
                placeholder="Joao 98999999999&#10;Maria 98988888888"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-0"
            />
        </div>

        <p v-if="skippedExistingCount > 0" class="text-sm text-amber-700">
            {{ skippedExistingCount }} telefone(s) ja estao nesta despesa e foram ignorados.
        </p>
        <p v-if="globalError" class="text-sm text-red-600">{{ globalError }}</p>
    </div>
</template>
