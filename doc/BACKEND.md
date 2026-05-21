# Backend

## Estrutura de pastas

### `app/Http/Controllers`

Entrada HTTP. Decide qual action/service chamar e retorna `ApiResponse`.

### `app/Http/Requests`

Validação de entrada com regras do Laravel e mensagens em PT-BR.

### `app/Services`

Regras de negócio mais amplas, geralmente com transações.

### `app/Actions`

Operações específicas, normalmente um caso de uso por classe.

### `app/Http/Resources`

Transformação da saída JSON.

### `app/Support`

Helpers, políticas e pequenas regras de domínio reutilizáveis.

### `app/Rules`

Objetos de validação reutilizáveis.

## Responsabilidade de cada camada

### Controller

Ponto de entrada HTTP.

Exemplo real:

- `ExpenseController@store` recebe a requisição autenticada e delega a criação para `CreateExpenseAction`

### Request

Valida os dados antes de entrar no domínio.

Exemplo real:

- `StoreExpenseRequest` valida descrição, valor total, vencimento e chave Pix

### Service

Executa regra de negócio e orquestra múltiplos modelos.

Exemplos reais:

- `ExpenseService::addParticipantsToExpense`
- `ExpenseService::closeExpense`
- `PaymentProofService::uploadProof`

### Action

Empacota uma operação específica e deixa o fluxo do controller mais explícito.

Exemplos reais:

- `ValidateChargeAction`
- `SubmitPaymentProofAction`

### Resource

Garante contrato consistente de saída e controle de exposição de dados.

Exemplos reais:

- `PublicExpenseResource` retorna resumo diferente quando não há `manage_token`
- `UserResource` não expõe CPF

### Support

Contém pequenas regras reutilizáveis que não precisam virar service.

Exemplos reais:

- `ChargeStatusTransition` valida a máquina de estados da cobrança
- `ManageTokenResolver` lê o token de gestão do header
- `PublicParticipantChargeResolver` encontra a cobrança exata pelo nome + telefone

### Rules

Usado para regras de validação isoladas.

Exemplo real:

- `BrazilPhone`

## Modelagem principal

### Expense

Representa a cobrança principal.

Campos relevantes:

- `created_by`
- `description`
- `total_amount`
- `amount_per_participant`
- `due_date`
- `pix_key`
- `pix_qr_code`
- `status`
- `public_hash`
- `manage_token`

Relações:

- pertence a `User`
- possui muitas `Charge`
- possui muitos `ExpenseParticipant`

### Charge

Representa a cobrança individual de um participante.

Campos relevantes:

- `expense_id`
- `expense_participant_id`
- `amount`
- `status`
- `rejection_reason`
- `paid_at`

Estados válidos:

- `pending`
- `proof_sent`
- `validated`
- `rejected`

### PaymentProof

Representa o comprovante enviado para uma cobrança individual.

Campos relevantes:

- `charge_id`
- `file_path`
- `original_filename`
- `mime_type`
- `status`

### User

Representa o organizador autenticado.

Campos de preenchimento:

- `name`
- `email`
- `password`
- `phone`

### ExpenseParticipant

Embora não estivesse na lista mínima pedida, ele é parte importante do domínio.
É o cadastro persistido do participante dentro da despesa e serve de base para a `Charge`.

## Regras importantes do domínio

### Soma dos participantes

O backend trabalha com a regra:

- soma dos participantes deve ser `>= total_amount`

Quando a soma fica abaixo do total, a API retorna:

- mensagem: `Os valores dos participantes ainda não fecham o total da cobrança.`
- código: `PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL`

### Redistribuição

A redistribuição de valores só é permitida quando todas as cobranças da despesa ainda estão `pending`.

Se já existir cobrança em andamento, o backend bloqueia atualização total e inclusão com redistribuição.

### Estados da cobrança

A máquina de estados está em `ChargeStatusTransition`:

- `pending -> proof_sent`
- `proof_sent -> validated`
- `proof_sent -> rejected`
- `rejected -> pending`

`validated` é estado terminal.

### Lifecycle do comprovante

1. O participante envia um arquivo permitido
2. O backend salva em storage privado
3. O status do comprovante fica `pending`
4. A cobrança vai para `proof_sent`
5. O organizador pode validar ou rejeitar
6. Se rejeitado, o arquivo continua acessível enquanto a despesa está aberta
7. Se houver reenvio após rejeição, o arquivo anterior é removido
8. Ao fechar a despesa, todos os comprovantes da despesa são apagados e `file_path` vira `null`

## Endpoints principais

### Área autenticada

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `GET /api/v1/expenses`
- `POST /api/v1/expenses`
- `GET /api/v1/expenses/{expense}`
- `PATCH /api/v1/expenses/{expense}`
- `DELETE /api/v1/expenses/{expense}`
- `POST /api/v1/expenses/{expense}/participants`
- `PATCH /api/v1/charges/{charge}/validate`
- `PATCH /api/v1/charges/{charge}/reject`

### Área pública

- `GET /api/v1/public/expenses/{hash}`
- `POST /api/v1/public/expenses/{hash}/validate-participant`
- `POST /api/v1/public/expenses/{hash}/submit-proof`
- `PATCH /api/v1/public/charges/{charge}/validate`
- `PATCH /api/v1/public/charges/{charge}/reject`
- `PATCH /api/v1/public/expenses/{hash}`
- `POST /api/v1/public/expenses/{hash}/participants`
- `PATCH /api/v1/public/expenses/{hash}/close`

## Autorização

### Área autenticada

As cobranças pertencem ao usuário criador. A listagem e visualização filtram por `created_by`.

### Área pública

- sem `manage_token`: resumo público
- com `manage_token`: gestão da cobrança pública

O token de gestão é resolvido por `ManageTokenResolver` e aceito somente por `X-Manage-Token`.
