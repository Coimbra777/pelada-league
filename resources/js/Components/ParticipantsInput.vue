<script setup>
import { ref, watch } from 'vue';
import { formatPhoneBr } from '../Composables/useInputMasks.js';
import { digitsOnly, parseParticipantLine, parseParticipantText } from '../utils/participantListParse.js';

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

/** Por indice de linha manual: '' | 'existing' */
const rowPhoneFlags = ref([]);

/** Avisos por linha do textarea (colar lista) */
const bulkLineFeedback = ref([]);

/** Resumo para banner (merge manual + bulk) */
const ignoredExistingDetails = ref([]);
const ignoredDuplicateDetails = ref([]);

function excludedSet() {
    return new Set(props.existingPhones.map((p) => digitsOnly(String(p))).filter(Boolean));
}

function rebuildBulkLineFeedback() {
    const excluded = excludedSet();
    const lines = bulkText.value.split(/\r?\n/);
    const msgs = [];
    const seen = new Set();
    let lineNo = 0;
    for (const raw of lines) {
        lineNo++;
        const t = raw.trim();
        if (!t) continue;
        const p = parseParticipantLine(raw);
        if (!p) {
            msgs.push({
                line: lineNo,
                text: 'Nao foi possivel ler nome e telefone nesta linha.',
                kind: 'invalid',
            });
            continue;
        }
        if (excluded.has(p.phone)) {
            msgs.push({
                line: lineNo,
                text: `${p.name} ja esta na lista desta despesa e foi ignorado.`,
                kind: 'existing',
            });
            continue;
        }
        if (seen.has(p.phone)) {
            msgs.push({
                line: lineNo,
                text: `${p.name} esta duplicado na lista; mantida apenas a primeira ocorrencia.`,
                kind: 'dup',
            });
            continue;
        }
        seen.add(p.phone);
    }
    bulkLineFeedback.value = msgs;
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
    const ignEx = [];
    const ignDup = [];

    for (const p of [...fromRows, ...fromBulk]) {
        if (excluded.has(p.phone)) {
            ignEx.push({ name: p.name });
            continue;
        }
        if (seen.has(p.phone)) {
            ignDup.push({ name: p.name });
            continue;
        }
        seen.add(p.phone);
        merged.push({ name: p.name, phone: p.phone });
    }

    ignoredExistingDetails.value = ignEx;
    ignoredDuplicateDetails.value = ignDup;
    participants.value = merged;

    rowPhoneFlags.value = rows.value.map((r) => {
        const ph = digitsOnly(r.phone);
        const n = r.name.trim();
        if (n && ph.length >= 10 && excluded.has(ph)) {
            return 'existing';
        }
        return '';
    });

    rebuildBulkLineFeedback();
}

watch([rows, bulkText], syncFromState, { deep: true });
watch(() => props.existingPhones, syncFromState, { deep: true });

syncFromState();

const bulkTextareaAlert = ref(false);

watch(
    bulkLineFeedback,
    (msgs) => {
        bulkTextareaAlert.value = msgs.some((m) => m.kind === 'existing' || m.kind === 'dup' || m.kind === 'invalid');
    },
    { deep: true, immediate: true },
);

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
    ignoredExistingDetails.value = [];
    ignoredDuplicateDetails.value = [];
    rowPhoneFlags.value = [];
    bulkLineFeedback.value = [];
    bulkTextareaAlert.value = false;
    participants.value = [];
}

defineExpose({ validate, reset });
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="ignoredExistingDetails.length || ignoredDuplicateDetails.length"
            class="rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-3 shadow-sm"
            role="alert"
        >
            <p class="font-semibold text-amber-950">Atencao — linhas ignoradas</p>
            <ul v-if="ignoredExistingDetails.length" class="mt-2 list-none space-y-1.5 text-sm text-amber-950">
                <li
                    v-for="(item, idx) in ignoredExistingDetails"
                    :key="'ex-' + idx"
                    class="rounded-lg bg-amber-100/80 px-2 py-1.5"
                >
                    <span class="font-semibold text-amber-900">Aviso:</span>
                    {{ item.name }} ja esta na lista desta despesa e foi ignorado.
                </li>
            </ul>
            <ul v-if="ignoredDuplicateDetails.length" class="mt-2 list-none space-y-1.5 text-sm text-amber-950">
                <li
                    v-for="(item, idx) in ignoredDuplicateDetails"
                    :key="'dup-' + idx"
                    class="rounded-lg bg-amber-100/80 px-2 py-1.5"
                >
                    <span class="font-semibold text-amber-900">Duplicado:</span>
                    {{ item.name }} entrou mais de uma vez; mantida apenas a primeira ocorrencia.
                </li>
            </ul>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Participantes (manual)</label>
            <div class="space-y-3">
                <div v-for="(row, i) in rows" :key="i" class="space-y-1">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-stretch sm:gap-2">
                        <input
                            v-model="row.name"
                            type="text"
                            placeholder="Nome"
                            :class="[
                                'min-h-[48px] flex-1 rounded-lg border px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0',
                                rowPhoneFlags[i] === 'existing'
                                    ? 'border-amber-500 ring-2 ring-amber-400 focus:border-amber-500 focus:ring-amber-400'
                                    : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
                            ]"
                        />
                        <input
                            :value="row.phone"
                            type="tel"
                            inputmode="tel"
                            autocomplete="tel"
                            placeholder="(11) 99999-9999"
                            :class="[
                                'min-h-[48px] w-full min-w-0 rounded-lg border px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 sm:max-w-[200px]',
                                rowPhoneFlags[i] === 'existing'
                                    ? 'border-amber-500 ring-2 ring-amber-400 focus:border-amber-500 focus:ring-amber-400'
                                    : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
                            ]"
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
                    <p
                        v-if="rowPhoneFlags[i] === 'existing' && row.name.trim() && digitsOnly(row.phone).length >= 10"
                        class="text-sm font-medium text-amber-900"
                    >
                        Aviso: {{ row.name.trim() }} ja esta na lista desta despesa e foi ignorado.
                    </p>
                </div>
            </div>
            <button
                type="button"
                class="mt-3 w-full min-h-[48px] rounded-lg border border-indigo-200 bg-indigo-50 px-4 text-sm font-semibold text-indigo-800 active:bg-indigo-100 sm:w-auto"
                @click="addRow"
            >
                + Adicionar participante
            </button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ou cole a lista (estilo WhatsApp)</label>
            <textarea
                v-model="bulkText"
                rows="4"
                placeholder="Joao 98999999999&#10;João - 98 99999-9999&#10;Maria (98) 98888-8888"
                :class="[
                    'block w-full rounded-lg border px-3 py-2.5 text-sm font-mono text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0',
                    bulkTextareaAlert
                        ? 'border-amber-500 ring-2 ring-amber-400 focus:border-amber-500 focus:ring-amber-400'
                        : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
                ]"
            />
            <ul v-if="bulkLineFeedback.length" class="mt-2 space-y-1.5 rounded-lg border border-amber-200 bg-amber-50/90 px-3 py-2">
                <li
                    v-for="(item, idx) in bulkLineFeedback"
                    :key="idx"
                    class="text-sm font-medium text-amber-950"
                >
                    <span class="text-amber-800">Linha {{ item.line }}:</span>
                    {{ item.text }}
                </li>
            </ul>
        </div>

        <p v-if="globalError" class="text-sm text-red-600">{{ globalError }}</p>
    </div>
</template>
