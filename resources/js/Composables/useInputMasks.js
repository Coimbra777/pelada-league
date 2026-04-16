/**
 * Mascaras de entrada (BR): telefone e moeda.
 */

/** @param {string} digits apenas numeros, ate 11 */
export function formatPhoneBr(digits) {
    const d = String(digits).replace(/\D/g, '').slice(0, 11);
    if (!d.length) return '';
    if (d.length <= 2) return `(${d}`;
    if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
    return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

/**
 * Valor em centavos digitados -> exibicao pt-BR (ex.: 1.234,56).
 * @param {string} raw texto do input
 */
export function maskCurrencyFromDigits(raw) {
    const digits = String(raw).replace(/\D/g, '');
    if (!digits) return '';
    const cents = parseInt(digits, 10);
    if (Number.isNaN(cents)) return '';
    const value = cents / 100;
    return value.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/**
 * "1.234,56" ou "12,50" -> numero
 */
export function parseCurrencyBrToNumber(str) {
    const s = String(str).trim();
    if (!s) return NaN;
    const normalized = s.replace(/\./g, '').replace(',', '.');
    const n = parseFloat(normalized);
    return Number.isFinite(n) ? n : NaN;
}
