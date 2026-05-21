# Fluxos

## 1. Criar cobrança

### Passo a passo

1. O usuário autenticado acessa `/cobrancas/nova`
2. Preenche descrição, valor total, vencimento e chave Pix
3. Informa os participantes e valores
4. O frontend cria a despesa
5. Em seguida envia os participantes
6. O backend devolve a cobrança já com as `charges`

### Endpoints

- `POST /api/v1/expenses`
- `POST /api/v1/expenses/{expense}/participants`
- `GET /api/v1/expenses/{expense}`

### Arquivos principais

- `frontend/src/pages/NewExpense.tsx`
- `frontend/src/lib/api/client.ts`
- `app/Http/Controllers/Api/V1/ExpenseController.php`
- `app/Services/ExpenseService.php`

### Regras

- usuário precisa estar autenticado
- valor total deve ser maior que zero
- vencimento deve ser hoje ou futuro
- soma dos participantes deve ser `>= total`

## 2. Adicionar participantes

### Passo a passo

1. O organizador envia novos participantes
2. O backend cria `ExpenseParticipant`
3. O backend cria `Charge` para cada novo participante
4. O backend aplica os valores enviados sem zerar cobranças existentes

### Endpoints

- autenticado: `POST /api/v1/expenses/{expense}/participants`
- gestão pública: `POST /api/v1/public/expenses/{hash}/participants`

### Arquivos principais

- `AddExpenseParticipantsAction`
- `AddPublicExpenseParticipantsAction`
- `ExpenseService::addParticipantsToExpense`

### Regras

- só adiciona telefones ainda não usados na despesa
- só funciona quando todas as cobranças estão `pending`
- soma final precisa continuar `>= total`

## 3. Acessar link público

### Passo a passo

1. O organizador compartilha `public_url`
2. O participante acessa `/p/{hash}`
3. A página busca os dados públicos da cobrança
4. Sem token de gestão, a UI mostra apenas resumo

### Endpoints

- `GET /api/v1/public/expenses/{hash}`

### Arquivos principais

- `PublicExpense.tsx`
- `PublicExpenseController@show`
- `PublicExpenseResource`

### Regras

- sem `manage_token`, não expõe participantes nem dados sensíveis do organizador

## 4. Validar participante

### Passo a passo

1. O participante informa nome + telefone
2. O frontend normaliza o telefone
3. O backend procura uma `Charge` compatível naquela despesa
4. A API devolve status, motivo de rejeição e valor

### Endpoints

- `POST /api/v1/public/expenses/{hash}/validate-participant`

### Arquivos principais

- `ValidateParticipantPublicRequest`
- `PublicParticipantChargeResolver`
- `PublicExpenseController@validateParticipantPublic`

### Regras

- nome deve bater exatamente com o cadastrado
- telefone deve ser válido
- despesa fechada bloqueia o fluxo

## 5. Enviar comprovante

### Passo a passo

1. O participante escolhe imagem ou PDF
2. O frontend verifica limite local de 5 MB
3. O backend valida extensão e MIME declarado
4. O `PaymentProofService` valida magic bytes
5. O arquivo é salvo em storage privado
6. A cobrança muda para `proof_sent`

### Endpoints

- `POST /api/v1/public/expenses/{hash}/submit-proof`

### Arquivos principais

- `ProofUpload.tsx`
- `SubmitPublicProofRequest`
- `SubmitPaymentProofAction`
- `PaymentProofService`

### Regras

- aceita apenas `jpg`, `png`, `pdf`
- limite de 5 MB
- só envia em `pending` ou `rejected`

## 6. Rejeitar comprovante

### Passo a passo

1. O organizador rejeita a cobrança
2. O backend registra `rejection_reason`
3. O status da cobrança vira `rejected`
4. O último comprovante fica com status `rejected`

### Endpoints

- autenticado: `PATCH /api/v1/charges/{charge}/reject`
- gestão pública: `PATCH /api/v1/public/charges/{charge}/reject`

### Arquivos principais

- `RejectChargeAction`
- `RejectChargeRequest`
- `ChargeValidationController`
- `PublicExpenseController`

### Regras

- exige motivo
- só é permitido quando a cobrança está em `proof_sent`
- a rejeição sozinha não apaga o arquivo

## 7. Reenviar comprovante

### Passo a passo

1. A cobrança está `rejected`
2. O participante envia novo arquivo
3. O backend salva o novo comprovante
4. Os comprovantes antigos da cobrança são removidos
5. O status volta para `pending`
6. Em seguida a cobrança segue para `proof_sent`

### Endpoints

- `POST /api/v1/public/expenses/{hash}/submit-proof`

### Arquivos principais

- `SubmitPaymentProofAction`
- `PaymentProofService::uploadProof`

### Regras

- reenvio remove o arquivo anterior
- o histórico não permanece em disco após o novo envio

## 8. Validar pagamento

### Passo a passo

1. O organizador abre a cobrança
2. Visualiza ou baixa o comprovante
3. Valida a cobrança
4. O backend grava `paid_at`
5. O status vira `validated`
6. Se todas as cobranças estiverem validadas, a despesa é fechada

### Endpoints

- autenticado: `PATCH /api/v1/charges/{charge}/validate`
- gestão pública: `PATCH /api/v1/public/charges/{charge}/validate`
- download: `GET /api/v1/charges/{charge}/proof`
- preview: `GET /api/v1/charges/{charge}/proofs/latest/view`

### Arquivos principais

- `ValidateChargeAction`
- `ChargeProofHttpResponse`
- `ExpenseService::closeExpenseWhenAllChargesValidated`

### Regras

- só valida em `proof_sent`
- `validated` é terminal

## 9. Fechar cobrança

### Passo a passo

1. Todas as cobranças precisam estar `validated`
2. O backend marca a despesa como `closed`
3. Todos os comprovantes da despesa são removidos
4. `file_path` vira `null`
5. Preview e download passam a responder `PROOF_REMOVED_AFTER_EXPENSE_CLOSED`

### Endpoints

- gestão pública: `PATCH /api/v1/public/expenses/{hash}/close`
- fechamento automático também pode ocorrer após `validate`

### Arquivos principais

- `ExpenseService::closeExpense`
- `ExpenseService::closeExpenseWhenAllChargesValidated`
- `PaymentProofService::removeProofFilesForExpense`

### Regras

- despesa fechada vira somente leitura
- comprovantes deixam de ficar acessíveis
