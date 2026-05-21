# Segurança

## Autenticação

- A API autenticada usa Laravel Sanctum com Bearer token.
- A expiração é configurável por `SANCTUM_TOKEN_EXPIRATION_MINUTES`.
- `POST /api/v1/auth/logout` revoga o token atual.
- Falhas de autenticação da API retornam `UNAUTHENTICATED`.

### Observação de trade-off

O frontend armazena o token em `localStorage`. Isso simplifica a SPA no MVP, mas não oferece o mesmo nível de proteção de cookies HttpOnly.

## Autorização

### Área autenticada

- O usuário só lista e consulta despesas criadas por ele.
- Validação e rejeição autenticadas passam por `ExpenseAuthorizer`.

### Área pública

- O visitante sem token recebe apenas resumo da cobrança.
- A gestão pública exige `X-Manage-Token`.
- O token de gestão não é aceito por query string nem body.

## `manage_token`

- Existe por despesa pública
- É resolvido por `ManageTokenResolver`
- O link de gestão usa fragmento `#manage=` para evitar envio ao servidor
- O frontend persiste o token localmente por hash da despesa

## Uploads e comprovantes

- O storage é privado (`local`)
- O backend aceita somente `jpg`, `png` e `pdf`
- Há validação por extensão, MIME declarado e magic bytes
- O limite atual é 5 MB
- O nome do arquivo usa telefone normalizado + timestamp
- O path é organizado por despesa
- O reenvio após rejeição remove o arquivo anterior
- A rejeição sozinha não apaga o comprovante
- O fechamento da despesa apaga todos os comprovantes e zera `file_path`
- Preview/download após fechamento retornam `PROOF_REMOVED_AFTER_EXPENSE_CLOSED`

## Headers de segurança

O middleware `SecurityHeaders` adiciona:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`
- `Content-Security-Policy`

HSTS é enviado apenas em produção com HTTPS.

## Rate limiting

Há limitadores específicos para:

- login
- registro
- visualização pública
- validação pública do participante
- envio de comprovante
- mutações públicas sensíveis
- preview/download de comprovantes

## CORS

- configurado em `config/cors.php`
- origens controladas por `CORS_ALLOWED_ORIGINS`
- `supports_credentials` está como `false`

## Validações

O projeto valida no backend:

- telefone brasileiro
- data de vencimento
- valor total
- valor por participante
- obrigatoriedade de campos
- soma dos participantes `>= total`

As mensagens foram adaptadas para PT-BR.

## LGPD mínima

Pontos já visíveis no código:

- CPF não é coletado no cadastro
- CPF não é exposto em `UserResource`
- visitante público não vê lista completa de participantes
- visitante público não vê nome/telefone do organizador
- comprovantes são temporários e removidos ao fechar a despesa

## Riscos residuais do MVP

- token autenticado em `localStorage`
- `manage_token` também fica persistido no navegador
- login distingue e-mail inexistente de senha inválida por decisão de UX
