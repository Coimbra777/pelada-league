# Frontend

## Organização do projeto

O frontend é uma SPA em React + Vite, com rotas definidas em `src/App.tsx`.

Estrutura principal:

- `pages/`: páginas completas
- `components/`: componentes reutilizáveis
- `lib/`: autenticação, cliente HTTP, máscaras, helpers e mock/demo

## Rotas principais

### Públicas

- `/`
- `/login`
- `/cadastro`
- `/demo`
- `/p/:hash`
- `/cobranca-publica/nova`

### Protegidas

- `/dashboard`
- `/cobrancas/nova`
- `/cobrancas/:id`
- `/cobrancas/:id/sucesso`

As rotas protegidas usam `ProtectedRoute`.

## Fluxo de autenticação

O contexto de autenticação fica em `lib/auth.tsx`.

Ele controla:

- leitura da sessão atual
- login
- cadastro
- logout
- modo demo
- sincronização entre abas via evento `storage`

O token real fica salvo em `localStorage` pela chave `contacerta:auth:v1`.

## Fluxo de demo

O modo demonstração é isolado do fluxo real.

Pontos relevantes:

- a sessão demo usa flags próprias no `localStorage`
- os dados demo vivem em `lib/api/mockStore.ts`
- o login demo limpa a sessão real antes de entrar no mock
- o hash `demo-churrasco-2025` é usado como cobrança seed de apresentação

## Cliente HTTP

O arquivo `lib/api/client.ts` separa claramente:

- chamadas autenticadas com Bearer token
- chamadas públicas sem Bearer
- chamadas públicas de gestão com `X-Manage-Token`
- modo demo usando `mockApi`

Esse arquivo também converte o envelope da API em erros amigáveis para a UI.

## Tratamento de erros

O frontend usa `ApiClientError` e tenta manter mensagens amigáveis em PT-BR.

Comportamentos observáveis:

- 401 autenticado limpa a sessão local
- erros de validação retornam para o formulário
- `NewExpense` mapeia erros por campo vindos da API
- o fluxo público exibe mensagens inline

## Validação por etapa

`pages/NewExpense.tsx` é um formulário em etapas.

Etapas principais:

1. dados da cobrança
2. Pix
3. participantes

O formulário valida localmente antes de avançar:

- descrição
- valor total
- vencimento
- chave Pix
- participantes
- telefone
- soma mínima dos valores

Quando a API também rejeita o envio, os erros voltam para a etapa correta.

## Máscaras e helpers

### Telefone

As máscaras e validações ficam em `lib/inputMasks.ts`.

### Moeda

O campo monetário usa `CurrencyBrInput` e helpers como `parseMoneyInput`.

### Participantes

`lib/splitAmountEqually.ts` centraliza:

- divisão igual com centavos
- montagem do payload para a API
- validação local da lista de participantes

## UX observável

O frontend já segue alguns padrões importantes:

- sem `alert()` no fluxo principal
- erros por campo
- mensagens em PT-BR
- feedback visual para telefone inválido
- feedback visual para vencimento passado
- mensagem de diferença entre total e participantes

Sobre a diferença:

- diferença positiva bloqueia envio
- diferença zero permite envio
- diferença negativa é informativa e indica excedente

## Fluxo público

`pages/PublicExpense.tsx` concentra o uso público e o modo organizador público.

Comportamentos reais:

- lê `#manage=` do fragmento e salva localmente
- ignora `manage` e `manage_token` em query string
- visitante sem token vê apenas resumo
- organizador com token vê participantes e ações
- participante identifica-se por nome + telefone
- participante envia comprovante
- se a despesa estiver fechada, a página vira somente leitura

## Componentes importantes

- `ProofUpload`: envio de comprovante com erro inline
- `PixKeyBox`: exibição da chave Pix
- `StatusBadge`: status visual da cobrança
- `CopyButton`: cópia de links e valores
- `AppShell`: estrutura das páginas autenticadas
