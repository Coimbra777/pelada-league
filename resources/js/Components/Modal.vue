<script setup>
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'md' },
});

const emit = defineEmits(['close']);

const widthClasses = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
};

function onEscape(e) {
    if (e.key === 'Escape') emit('close');
}

watch(() => props.show, (val) => {
    if (val) {
        document.addEventListener('keydown', onEscape);
        document.body.style.overflow = 'hidden';
    } else {
        document.removeEventListener('keydown', onEscape);
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="emit('close')" />
                <div :class="['relative bg-white rounded-xl shadow-xl w-full', widthClasses[maxWidth]]">
                    <div v-if="title" class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                    </div>
                    <div class="px-6 py-4">
                        <slot />
                    </div>
                    <div v-if="$slots.footer" class="px-6 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl flex justify-end gap-3">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
