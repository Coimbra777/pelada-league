/**
 * Rótulos e textos para status de cobrança (Charge) na experiência do participante.
 * Chaves permanecem alinhadas à API; apenas apresentação.
 */

export const CHARGE_STATUS_UX = {
    pending: {
        badgeLabel: 'Enviar comprovante',
        badgeClass: 'bg-slate-100 text-slate-800 ring-1 ring-inset ring-slate-200',
        panelTitle: 'Envie seu comprovante',
        panelBody:
            'Faça o PIX e anexe o arquivo do comprovante. Assim o responsável consegue confirmar seu pagamento.',
    },
    proof_sent: {
        badgeLabel: 'Em análise',
        badgeClass: 'bg-amber-50 text-amber-950 ring-1 ring-inset ring-amber-200',
        panelTitle: 'Comprovante em análise',
        panelBody:
            'O responsável vai revisar em breve. Você pode fechar esta página — quando houver novidade, o conteúdo aqui atualiza.',
    },
    rejected: {
        badgeLabel: 'Reenviar comprovante',
        badgeClass: 'bg-orange-50 text-orange-950 ring-1 ring-inset ring-orange-200',
        panelTitle: 'Envie um novo comprovante',
        panelBody:
            'O arquivo anterior não foi aceito. Escolha outro comprovante e envie de novo — ainda dá tempo de regularizar.',
    },
    validated: {
        badgeLabel: 'Pagamento confirmado',
        badgeClass: 'bg-emerald-50 text-emerald-900 ring-1 ring-inset ring-emerald-200',
        panelTitle: 'Pagamento confirmado',
        panelBody: 'Tudo certo por aqui. Não é necessário enviar mais nada.',
    },
};

const FALLBACK_UX = {
    badgeLabel: 'Situação em andamento',
    badgeClass: 'bg-gray-100 text-gray-800 ring-1 ring-inset ring-gray-200',
    panelTitle: 'Atualizando sua situação',
    panelBody: 'Se algo não aparecer como esperado, atualize a página em alguns instantes.',
};

/**
 * @param {string} status
 * @returns {{ badgeLabel: string, badgeClass: string, panelTitle: string, panelBody: string }}
 */
export function getChargeStatusUx(status) {
    return CHARGE_STATUS_UX[status] ?? FALLBACK_UX;
}

/** Rótulos do badge para o painel do responsável (admin). */
const ADMIN_CHARGE_BADGE_LABEL = {
    pending: 'Aguardando envio',
    proof_sent: 'Em análise',
    rejected: 'Aguardando novo comprovante',
    validated: 'Pagamento confirmado',
};

/**
 * Badge de cobrança: participante vs responsável (mesmas cores, textos distintos no admin).
 *
 * @param {string} status
 * @param {'participant'|'admin'} perspective
 * @returns {{ label: string, class: string }}
 */
export function getChargeStatusBadgeUx(status, perspective = 'participant') {
    const base = getChargeStatusUx(status);
    if (perspective !== 'admin' || !Object.prototype.hasOwnProperty.call(ADMIN_CHARGE_BADGE_LABEL, status)) {
        return { label: base.badgeLabel, class: base.badgeClass };
    }
    return {
        label: ADMIN_CHARGE_BADGE_LABEL[status],
        class: base.badgeClass,
    };
}
