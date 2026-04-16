import { ref, watch, onUnmounted } from 'vue';
import { api } from '../Services/api.js';

const DEBOUNCE_MS = 700;

/**
 * Validação automática (debounce) contra POST .../validate-participant.
 *
 * @param {() => string} getExpenseHash
 * @param {import('vue').Ref<string>} nameRef
 * @param {import('vue').Ref<string>} phoneRef
 * @param {() => boolean} [isDisabled] — quando true, limpa estado e não chama API
 */
export function useDebouncedParticipantValidation(getExpenseHash, nameRef, phoneRef, isDisabled = () => false) {
    const validated = ref(null);
    const validationLoading = ref(false);
    const validationError = ref(null);

    let debounceTimer = null;
    let requestSeq = 0;

    function clearDebounce() {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
    }

    async function runValidate(trimmedName, phoneDigits) {
        const seq = ++requestSeq;
        validationLoading.value = true;
        validationError.value = null;
        validated.value = null;

        try {
            const data = await api.post(`/public/expenses/${getExpenseHash()}/validate-participant`, {
                name: trimmedName,
                phone: phoneDigits,
            });
            if (seq !== requestSeq) {
                return;
            }
            validated.value = {
                status: data.status,
                message: data.message,
                rejection_reason: data.rejection_reason ?? null,
                can_submit_proof: !!data.can_submit_proof,
            };
        } catch (err) {
            if (seq !== requestSeq) {
                return;
            }
            validated.value = null;
            validationError.value = err.data?.message || 'Nao foi possivel verificar seus dados.';
        } finally {
            if (seq === requestSeq) {
                validationLoading.value = false;
            }
        }
    }

    function scheduleOrReset() {
        clearDebounce();

        if (isDisabled()) {
            requestSeq++;
            validationLoading.value = false;
            validationError.value = null;
            validated.value = null;
            return;
        }

        const trimmedName = (nameRef.value || '').trim();
        const digits = (phoneRef.value || '').replace(/\D/g, '');

        if (!trimmedName || digits.length < 10) {
            requestSeq++;
            validationLoading.value = false;
            validationError.value = null;
            validated.value = null;
            return;
        }

        debounceTimer = setTimeout(() => {
            debounceTimer = null;
            if (isDisabled()) {
                requestSeq++;
                validationLoading.value = false;
                validationError.value = null;
                validated.value = null;
                return;
            }
            const t = (nameRef.value || '').trim();
            const d = (phoneRef.value || '').replace(/\D/g, '');
            if (!t || d.length < 10) {
                requestSeq++;
                validationLoading.value = false;
                validationError.value = null;
                validated.value = null;
                return;
            }
            void runValidate(t, d);
        }, DEBOUNCE_MS);
    }

    watch([nameRef, phoneRef], scheduleOrReset);

    onUnmounted(() => {
        clearDebounce();
        requestSeq++;
    });

    return {
        validated,
        validationLoading,
        validationError,
    };
}
