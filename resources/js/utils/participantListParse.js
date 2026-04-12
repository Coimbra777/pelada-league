/** @param {string} s */
export function digitsOnly(s) {
    return String(s ?? '').replace(/\D/g, '');
}

/**
 * Uma linha estilo WhatsApp: "Joao 98999999999" ou "Maria - 11988887777"
 * @param {string} line
 * @returns {{ name: string, phone: string } | null} phone so digitos
 */
export function parseParticipantLine(line) {
    const trimmed = String(line).trim();
    if (!trimmed) return null;
    const m = trimmed.match(/^(.+?)[\s\-–—]+([\d\s().-]+)$/u);
    if (!m) return null;
    const name = m[1].trim();
    const phone = digitsOnly(m[2]);
    if (name && phone.length >= 10) {
        return { name, phone };
    }
    return null;
}

/**
 * @param {string} text
 * @returns {{ name: string, phone: string }[]}
 */
export function parseParticipantText(text) {
    const out = [];
    for (const line of String(text).split(/\r?\n/u)) {
        const p = parseParticipantLine(line);
        if (p) out.push(p);
    }
    return out;
}
