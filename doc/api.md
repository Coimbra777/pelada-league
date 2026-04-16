# API REST - Referencia Completa

Base URL: `/api/v1`

Autenticacao: Bearer Token (Sanctum)
Rate Limit: 60 requisicoes por minuto

## Autenticacao

### Registro

```
POST /api/v1/auth/register
```

| Campo | Tipo | Obrigatorio | Regras |
|-------|------|-------------|--------|
| name | string | Sim | max:255 |
| email | string | Sim | email, unique |
| password | string | Sim | min:6 |
| password_confirmation | string | Sim | deve bater com password |
| phone | string | Nao | max:20 |
| cpf | string | Nao | 11 digitos, unique |

Resposta (201):
```json
{
  "user": { "id": 1, "name": "...", "email": "...", ... },
  "token": "1|abc123..."
}
```

Cria customer no Asaas automaticamente. Falha no Asaas nao impede o registro.

### Login

```
POST /api/v1/auth/login
```

| Campo | Tipo | Obrigatorio |
|-------|------|-------------|
| email | string | Sim |
| password | string | Sim |

Resposta (200):
```json
{
  "user": { "id": 1, "name": "...", "email": "...", ... },
  "token": "2|xyz789..."
}
```

Erro (401):
```json
{ "message": "Invalid credentials." }
```

### Logout

```
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

Resposta (200):
```json
{ "message": "Successfully logged out." }
```

### Dados do Usuario

```
GET /api/v1/auth/me
Authorization: Bearer {token}
```

Resposta (200):
```json
{
  "user": {
    "id": 1,
    "name": "Joao",
    "email": "joao@email.com",
    "phone": "11999999999",
    "cpf": "12345678901",
    "is_active": true,
    "email_verified_at": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

## Equipes

### Criar Equipe

```
POST /api/v1/teams
Authorization: Bearer {token}
```

| Campo | Tipo | Obrigatorio |
|-------|------|-------------|
| name | string | Sim |

Resposta (201):
```json
{
  "team": { "id": 1, "name": "Minha Equipe", "members_count": 1, "created_at": "..." }
}
```

O criador vira admin automaticamente.

### Listar Equipes

```
GET /api/v1/teams
Authorization: Bearer {token}
```

Retorna apenas equipes que o usuario participa.

### Detalhes da Equipe

```
GET /api/v1/teams/{id}
Authorization: Bearer {token}
```

Resposta (200):
```json
{
  "team": { "id": 1, "name": "...", "owner": { ... }, "created_at": "..." },
  "members": [
    { "id": 5, "user": { "name": "...", "email": "..." }, "role": "admin", "joined_at": "..." }
  ]
}
```

Erro (403) se nao e membro.

### Dashboard da Equipe

```
GET /api/v1/teams/{id}/dashboard
Authorization: Bearer {token}
```

Resposta (200):
```json
{
  "total_expenses": 3,
  "total_open": 150.00,
  "total_paid": 300.00,
  "members_paid": 4,
  "members_pending": 2
}
```

## Membros

### Adicionar Membro

```
POST /api/v1/teams/{id}/members
Authorization: Bearer {token}
```

| Campo | Tipo | Obrigatorio |
|-------|------|-------------|
| user_id | integer | Sim |

Apenas admin pode adicionar. Erro 403 se nao e admin. Erro 422 se usuario ja e membro.

### Remover Membro

```
DELETE /api/v1/teams/{id}/members/{userId}
Authorization: Bearer {token}
```

Apenas admin pode remover. Nao e possivel remover o owner (erro 422).

## Despesas

### Criar Despesa (Split Automatico)

```
POST /api/v1/teams/{id}/expenses
Authorization: Bearer {token}
```

| Campo | Tipo | Obrigatorio | Regras |
|-------|------|-------------|--------|
| description | string | Sim | max:255 |
| total_amount | numeric | Sim | min:5 |
| due_date | date | Sim | futuro ou hoje |

Apenas admin pode criar.

Fluxo automatico:
1. Valida que TODOS os membros tem Asaas configurado
2. Divide valor entre membros (arredondamento: ultimo absorve diferenca)
3. Cria cobranca PIX individual para cada membro via Asaas
4. Retorna expense com charges

Erro 422 se algum membro nao tem Asaas:
```json
{ "message": "All members must have payment enabled. Members without: Joao, Maria" }
```

### Listar Despesas

```
GET /api/v1/teams/{id}/expenses
Authorization: Bearer {token}
```

### Detalhe da Despesa

```
GET /api/v1/teams/{id}/expenses/{expenseId}
Authorization: Bearer {token}
```

Retorna despesa com charges e dados do usuario de cada charge.

## Cobrancas

### Criar Cobranca Individual

```
POST /api/v1/charges
Authorization: Bearer {token}
```

| Campo | Tipo | Obrigatorio | Regras |
|-------|------|-------------|--------|
| description | string | Sim | max:255 |
| amount | numeric | Sim | min:5 |
| due_date | date | Sim | futuro ou hoje |

### Sincronizar Status

```
POST /api/v1/charges/{id}/sync
Authorization: Bearer {token}
```

Consulta o Asaas e atualiza o status da cobranca. Apenas o dono da cobranca pode sincronizar.

## Webhook Asaas

```
POST /api/v1/webhooks/asaas
Header: asaas-access-token: {ASAAS_WEBHOOK_TOKEN}
```

Eventos tratados:
- `PAYMENT_RECEIVED` → status `RECEIVED`
- `PAYMENT_CONFIRMED` → status `CONFIRMED`
- `PAYMENT_RECEIVED_IN_CASH` → status `RECEIVED_IN_CASH`
- `PAYMENT_OVERDUE` → status `OVERDUE`

Comportamento:
- Idempotente: nao sobrescreve status se ja esta pago
- Atualiza `paid_at` na primeira transicao para pago
- Recalcula automaticamente o status da expense associada (open → PARTIALLY_PAID → PAID)

## Status

### Charge (cobranca)

| Status | Descricao |
|--------|-----------|
| PENDING | Aguardando pagamento |
| RECEIVED | Pagamento recebido |
| CONFIRMED | Pagamento confirmado |
| RECEIVED_IN_CASH | Pago em especie |
| OVERDUE | Vencida |

### Expense (despesa)

| Status | Descricao |
|--------|-----------|
| open | Nenhuma cobranca paga |
| PARTIALLY_PAID | Algumas cobrancas pagas |
| PAID | Todas as cobrancas pagas |
| OVERDUE | Alguma cobranca vencida (nenhuma paga) |

## Codigos de Erro

| Codigo | Descricao |
|--------|-----------|
| 200 | Sucesso |
| 201 | Recurso criado |
| 401 | Nao autenticado / token invalido |
| 403 | Sem permissao (nao e membro/admin) |
| 404 | Recurso nao encontrado |
| 422 | Erro de validacao / regra de negocio |
| 429 | Rate limit excedido |
| 500 | Erro interno (ex: falha na API Asaas) |

Erros de validacao (422) retornam:
```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```
