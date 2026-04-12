/**
 * Despesas criadas neste navegador (sem login).
 * Chave única: hash (public_hash da despesa).
 *
 * Migração futura: sincronizar este array com o backend quando houver conta logada
 * (ex.: POST /api/v1/user/import-local-expenses com o mesmo formato).
 */
const STORAGE_KEY = 'caixinha_local_expenses_v1';

function readRaw() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function writeRaw(list) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
}

/**
 * @returns {Array<{ hash: string, manage_token: string, description: string, amount: number, created_at: string }>}
 */
export function getExpenses() {
    return readRaw();
}

/**
 * Insere ou atualiza por `hash`.
 * @param {object} expense
 * @param {string} expense.hash - public_hash
 * @param {string} expense.manage_token
 * @param {string} expense.description
 * @param {number} expense.amount
 * @param {string} [expense.created_at] - ISO; default agora
 */
export function saveExpense(expense) {
    const list = readRaw();
    const idx = list.findIndex((e) => e.hash === expense.hash);
    const row = {
        hash: expense.hash,
        manage_token: expense.manage_token,
        description: String(expense.description ?? ''),
        amount: Number(expense.amount),
        created_at: expense.created_at || new Date().toISOString(),
    };
    if (idx >= 0) {
        list[idx] = row;
    } else {
        list.push(row);
    }
    writeRaw(list);
}

/**
 * @param {string} hash
 * @returns {{ hash: string, manage_token: string, description: string, amount: number, created_at: string } | null}
 */
export function getByHash(hash) {
    return readRaw().find((e) => e.hash === hash) ?? null;
}

export function removeExpense(hash) {
    const list = readRaw().filter((e) => e.hash !== hash);
    writeRaw(list);
}

export function clearAll() {
    localStorage.removeItem(STORAGE_KEY);
}
