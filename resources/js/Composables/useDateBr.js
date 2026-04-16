/**
 * API / backend: datas em ISO (YYYY-MM-DD ou datetime).
 * Exibição: DD/MM/YYYY
 */
export function formatDateIsoToBr(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    const s = String(value);
    const datePart = s.includes('T') ? s.split('T')[0] : s.split(' ')[0];
    const parts = datePart.split('-');
    if (parts.length !== 3) {
        return s;
    }
    const [y, m, d] = parts;
    return `${d.padStart(2, '0')}/${m.padStart(2, '0')}/${y}`;
}

export function useDateBr() {
    return { formatDateIsoToBr };
}
