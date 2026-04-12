/** @param {string} s */
export function digitsOnly(s) {
    return String(s ?? '').replace(/\D/g, '');
}

/**
 * Uma linha estilo WhatsApp:
 * Joao 98999999999 | Maria - 11988887777 | João - 98 99999-9999 | Maria (98) 98888-8888
 * @param {string} line
 * @returns {{ name: string, phone: string } | null} phone so digitos
 */
export function parseParticipantLine(line) {
    const trimmed = String(line).trim();
    if (!trimmed) return null;

    const primary = trimmed.match(/^(.+?)[\s\-–—:,]+([\d\s().+/-]+)$/u);
    if (primary) {
        let name = primary[1].trim().replace(/[.:,;\-–—]+$/u, '').trim();
        if (!name) name = primary[1].trim();
        const phone = digitsOnly(primary[2]);
        if (name && phone.length >= 10) {
            return { name, phone };
        }
    }

    const loose = trimmed.match(/^(.+?)\s+(\(\d{2}\)[\d\s().+/-]+|\d[\d\s().+/-]{8,})$/u);
    if (loose) {
        let name = loose[1].trim().replace(/[.:,;\-–—]+$/u, '').trim();
        if (!name) name = loose[1].trim();
        const phone = digitsOnly(loose[2]);
        if (name && phone.length >= 10) {
            return { name, phone };
        }
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
