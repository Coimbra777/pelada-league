# Arquitetura

## Stack

- Backend: PHP 8.2 + Laravel 12
- Autenticação: Laravel Sanctum
- Frontend: React 18 + Vite + TypeScript
- Estado de sessão no frontend: contexto próprio + `localStorage`
- Comunicação: REST API JSON
- Testes: PHPUnit no backend e Vitest no frontend

## Backend

O backend segue uma organização por responsabilidade.

### Controllers

Recebem a requisição HTTP, delegam a validação para `FormRequest`, chamam actions/services e devolvem o envelope padrão da API.

Arquivos centrais:

- `app/Http/Controllers/Api/V1/ExpenseController.php`
- `app/Http/Controllers/Api/V1/PublicExpenseController.php`
- `app/Http/Controllers/Api/V1/ChargeValidationController.php`
- `app/Http/Controllers/Api/V1/Auth/AuthController.php`

### Requests

Centralizam validação de entrada e mensagens em PT-BR.

Exemplos:

- `StoreExpenseRequest`
- `AddExpenseParticipantsRequest`
- `SubmitPublicProofRequest`
- `ValidateParticipantPublicRequest`

### Services

Concentram regras de negócio mais amplas, com transações e coordenação entre modelos.

Arquivos principais:

- `ExpenseService`
- `PaymentProofService`
- `PublicExpenseCreatorService`
- `NotificationService`

### Actions

Representam operações específicas, normalmente chamadas por controllers, mantendo o fluxo mais explícito.

Exemplos:

- `CreateExpenseAction`
- `AddExpenseParticipantsAction`
- `ValidateChargeAction`
- `RejectChargeAction`
- `SubmitPaymentProofAction`

### Resources

Padronizam a saída da API e controlam exposição de dados.

Exemplos:

- `ExpenseResource`
- `ChargeResource`
- `PublicExpenseResource`
- `CreatedPublicExpenseResource`
- `UserResource`

### Support

Reúne helpers e políticas de apoio ao domínio.

Exemplos:

- `ManageTokenResolver`
- `ChargeStatusTransition`
- `PublicParticipantChargeResolver`
- `PhoneNormalizer`
- `ExpenseClosedPolicy`

### Rules

Validações reutilizáveis do Laravel.

Exemplo:

- `BrazilPhone`

## Frontend

O frontend é uma SPA com rotas públicas e privadas.

### pages

Páginas de alto nível:

- `Auth.tsx`
- `Dashboard.tsx`
- `NewExpense.tsx`
- `ExpenseDetail.tsx`
- `PublicExpense.tsx`
- `Demo.tsx`

### components

Componentes reutilizáveis de interface e fluxo:

- `AppShell`
- `ProofUpload`
- `PixKeyBox`
- `StatusBadge`
- `ProtectedRoute`

### lib

Concentra clientes HTTP, autenticação, máscaras, helpers de formato e lógica de formulário.

Arquivos importantes:

- `lib/api/client.ts`
- `lib/auth.tsx`
- `lib/publicManageToken.ts`
- `lib/splitAmountEqually.ts`
- `lib/inputMasks.ts`
- `lib/api/mockStore.ts`

## Comunicação entre frontend e backend

O frontend consome a API via `frontend/src/lib/api/client.ts`.

Pontos importantes:

- rotas autenticadas usam Bearer token
- rotas públicas não enviam Bearer
- gestão pública usa `X-Manage-Token`
- erros usam o envelope padrão de `ApiResponse`

## Padrão de resposta

O helper `app/Http/Responses/ApiResponse.php` padroniza respostas com:

- `success`
- `message`
- `data`
- `meta`

Nos erros, o payload inclui:

- `success: false`
- `message`
- `code`
- `errors`

## Storage

Os comprovantes usam o disk `local`, sem exposição direta de caminho público.

O `PaymentProofService` organiza os arquivos por despesa:

`payment-proofs/expense-{expense_id}/{phone_normalized}-{timestamp}.{ext}`

## Fluxo público

O fluxo público tem duas camadas:

- `public_hash`: identifica a cobrança pública
- `manage_token`: autoriza ações de gestão sem login tradicional

Sem `manage_token`, o visitante vê apenas resumo da cobrança.
Com `manage_token`, o organizador vê participantes, status, comprovantes e ações de aprovação/rejeição.
