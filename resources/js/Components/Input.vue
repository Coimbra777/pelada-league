<script setup>
import { formatPhoneBr } from '../Composables/useInputMasks.js';

const model = defineModel();

defineProps({
    type: { type: String, default: 'text' },
    label: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    /** Aplica mascara (XX) XXXXX-XXXX ao digitar. */
    phoneMask: { type: Boolean, default: false },
});

function onPhoneInput(e) {
    model.value = formatPhoneBr(e.target.value);
}
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        <input
            v-if="phoneMask"
            :value="model"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            :placeholder="placeholder"
            :required="required"
            :class="[
                'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-0',
                error
                    ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 text-gray-900 focus:border-indigo-500 focus:ring-indigo-500',
            ]"
            @input="onPhoneInput"
        />
        <input
            v-else
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :required="required"
            :class="[
                'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-0',
                error
                    ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 text-gray-900 focus:border-indigo-500 focus:ring-indigo-500',
            ]"
        />
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
