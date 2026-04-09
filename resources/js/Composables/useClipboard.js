import { useToast } from './useToast.js';

export function useClipboard() {
    const toast = useToast();

    async function copy(text) {
        try {
            await navigator.clipboard.writeText(text);
            toast.success('Copiado!');
        } catch {
            toast.error('Falha ao copiar.');
        }
    }

    return { copy };
}
