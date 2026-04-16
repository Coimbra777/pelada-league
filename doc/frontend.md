# Frontend - Caixinha (Inertia.js + Vue 3 + Pinia)

Interface web completa para o sistema de divisao de despesas com pagamento PIX.

## Stack

- Vue 3 (Composition API, `<script setup>`)
- Inertia.js (navegacao SPA sem API separada para rotas)
- Pinia (gerenciamento de estado)
- Tailwind CSS v4 (CSS-first)
- Vite 6 (build e HMR)
- Fetch API (sem axios)

## Como Rodar

### Requisitos

- Node.js 18+
- Docker rodando (backend + MySQL + Redis)

### Instalacao

```bash
# Instalar dependencias JS
npm install

# Instalar dependencias PHP (se ainda nao fez)
docker compose exec app composer install
```

### Modo Desenvolvimento (hot reload)

```bash
npm run dev
```

Acesse: http://localhost:8000

### Build para Producao

```bash
npm run build
```

Os assets compilados vao para `public/build/`.

## Estrutura de Arquivos

```
resources/js/
  app.js                          # Boot Inertia + Vue + Pinia
  bootstrap.js                    # Setup minimo (sem axios)

  Services/
    api.js                        # Fetch wrapper com Bearer token

  Stores/
    auth.js                       # useAuthStore (login, register, logout)
    teams.js                      # useTeamStore (CRUD times, membros, dashboard)
    expenses.js                   # useExpenseStore (despesas, charges, sync)

  Composables/
    useToast.js                   # Notificacoes toast
    useClipboard.js               # Copiar texto para clipboard

  Components/
    Button.vue                    # Botao com variantes e loading
    Input.vue                     # Input com label e erro
    Card.vue                      # Container card com slots
    StatusBadge.vue               # Badge de status colorido
    LoadingSpinner.vue            # Spinner animado
    Modal.vue                     # Dialog modal com teleport
    ToastContainer.vue            # Container de notificacoes

  Layouts/
    GuestLayout.vue               # Layout para login/registro
    AppLayout.vue                 # Layout autenticado com navbar

  Pages/
    Auth/
      Login.vue                   # Tela de login
      Register.vue                # Tela de registro
    Dashboard.vue                 # Dashboard pessoal
    Teams/
      Index.vue                   # Lista de equipes
      Create.vue                  # Criar equipe
      Show.vue                    # Detalhe da equipe (dashboard + membros + despesas)
    Expenses/
      Create.vue                  # Criar despesa (split automatico)
      Show.vue                    # Detalhe da despesa com charges por membro
    Charges/
      Show.vue                    # QR Code PIX + copia-e-cola
```

## Paginas e Funcionalidades

### Login (`/login`)

| Campo | Tipo | Obrigatorio |
|-------|------|-------------|
| E-mail | email | Sim |
| Senha | password | Sim |

- Valida credenciais via API
- Salva token no localStorage
- Redireciona para `/dashboard`
- Link para criar conta

### Registro (`/register`)

| Campo | Tipo | Obrigatorio |
|-------|------|-------------|
| Nome | text | Sim |
| E-mail | email | Sim |
| Senha | password | Sim |
| Confirmar Senha | password | Sim |
| Telefone | text | Nao |
| CPF | text (11 digitos) | Nao |

- Cria conta e faz login automatico
- Redireciona para `/dashboard`

### Dashboard (`/dashboard`)

- Mensagem de boas-vindas com nome do usuario
- Grid de cards com equipes do usuario
- Cada card mostra nome e quantidade de membros
- Empty state quando nao tem equipes
- Botao "Nova Equipe"

### Lista de Equipes (`/teams`)

- Lista todas as equipes do usuario
- Cada item mostra nome e quantidade de membros
- Botao "Nova Equipe"

### Criar Equipe (`/teams/create`)

| Campo | Tipo | Obrigatorio |
|-------|------|-------------|
| Nome da equipe | text | Sim |

- Cria equipe e redireciona para detalhe
- Criador vira admin automaticamente

### Detalhe da Equipe (`/teams/{id}`)

Pagina mais completa do sistema. Dividida em 3 secoes:

**Secao 1 - Dashboard Financeiro:**
- Total de despesas
- Valor em aberto (amarelo)
- Valor pago (verde)
- Membros pendentes

**Secao 2 - Membros:**
- Lista com nome, email e role (admin/member)
- Admin pode adicionar membros (modal com ID do usuario)
- Admin pode remover membros (exceto o owner)

**Secao 3 - Despesas:**
- Lista com descricao, valor e status (badge colorido)
- Admin pode criar nova despesa
- Cada despesa linka para detalhe

### Criar Despesa (`/teams/{id}/expenses/create`)

| Campo | Tipo | Obrigatorio |
|-------|------|-------------|
| Descricao | text | Sim |
| Valor Total (R$) | number (min 5) | Sim |
| Vencimento | date (futuro) | Sim |

- Backend divide automaticamente entre membros
- Gera cobrancas PIX individuais via Asaas
- Redireciona para equipe com toast de sucesso
- Se algum membro nao tem Asaas configurado, bloqueia com erro

### Detalhe da Despesa (`/teams/{id}/expenses/{id}`)

- Cabecalho: descricao, valor total, vencimento, status
- Tabela de cobrancas por membro:
  - Nome do membro
  - Valor individual
  - Status (badge)
  - Link "Ver PIX" (quando disponivel)
  - Botao "Sync" (atualiza status do Asaas)

### Cobranca PIX (`/charges/{id}`)

- Valor em destaque
- QR Code PIX (imagem base64)
- Codigo copia-e-cola com botao "Copiar Codigo PIX"
- Link de pagamento (abre em nova aba)
- Botao "Sincronizar Status"
- Informacoes de vencimento e pagamento

## Autenticacao

### Como funciona

1. Login/Registro retornam `token` do Sanctum
2. Token salvo em `localStorage`
3. Todas as requisicoes API incluem header `Authorization: Bearer {token}`
4. Se API retorna 401, token e limpo e usuario redirecionado para `/login`

### Fluxo de protecao

- `GuestLayout` redireciona para `/dashboard` se ja autenticado
- `AppLayout` redireciona para `/login` se nao autenticado
- No mount, valida token chamando `GET /api/v1/auth/me`

## API Service (`Services/api.js`)

Wrapper centralizado para `fetch`:

```js
import { api } from './Services/api.js';

// GET
const data = await api.get('/teams');

// POST
const data = await api.post('/auth/login', { email, password });

// DELETE
await api.delete(`/teams/${teamId}/members/${userId}`);
```

Funcionalidades:
- Adiciona `Authorization: Bearer` automaticamente
- Trata 401 globalmente (limpa token, redireciona)
- Trata 204 No Content
- Lanca erro com `{ status, data }` para erros HTTP

## Pinia Stores

### useAuthStore

| State | Tipo | Descricao |
|-------|------|-----------|
| user | object/null | Dados do usuario |
| token | string/null | Token Sanctum |
| loading | boolean | Requisicao em andamento |
| error | string/null | Mensagem de erro |

| Action | Descricao |
|--------|-----------|
| login(email, password) | Faz login e salva token |
| register(formData) | Cria conta e salva token |
| fetchUser() | Busca dados do usuario autenticado |
| logout() | Revoga token e limpa estado |

| Getter | Descricao |
|--------|-----------|
| isAuthenticated | true se tem token |
| userName | Nome do usuario ou '' |

### useTeamStore

| Action | Descricao |
|--------|-----------|
| fetchTeams() | Lista equipes do usuario |
| fetchTeam(id) | Busca equipe + membros |
| createTeam(name) | Cria equipe |
| addMember(teamId, userId) | Adiciona membro |
| removeMember(teamId, userId) | Remove membro |
| fetchDashboard(teamId) | Busca resumo financeiro |

### useExpenseStore

| Action | Descricao |
|--------|-----------|
| fetchExpenses(teamId) | Lista despesas da equipe |
| fetchExpense(teamId, expenseId) | Busca despesa + charges |
| createExpense(teamId, data) | Cria despesa (split automatico) |
| syncCharge(chargeId) | Sincroniza status da cobranca |
| getChargeById(id) | Busca charge da expense carregada |

## Componentes Reutilizaveis

### Button

```vue
<Button variant="primary" :loading="isLoading">Salvar</Button>
<Button variant="secondary" size="sm">Cancelar</Button>
<Button variant="danger" :disabled="true">Excluir</Button>
```

Variantes: `primary` (indigo), `secondary` (branco), `danger` (vermelho)
Tamanhos: `sm`, `md`, `lg`

### Input

```vue
<Input v-model="email" type="email" label="E-mail" :error="errors.email" required />
```

Exibe label acima, borda vermelha e mensagem de erro quando `error` nao e vazio.

### Card

```vue
<Card title="Titulo">
  Conteudo aqui
  <template #footer>Rodape</template>
</Card>
```

Slots: `header`, `default`, `footer`

### StatusBadge

```vue
<StatusBadge status="RECEIVED" />   <!-- Verde: "Pago" -->
<StatusBadge status="PENDING" />    <!-- Amarelo: "Pendente" -->
<StatusBadge status="OVERDUE" />    <!-- Vermelho: "Vencida" -->
<StatusBadge status="PARTIALLY_PAID" />  <!-- Azul: "Parcial" -->
```

### Modal

```vue
<Modal :show="showModal" title="Titulo" @close="showModal = false">
  Conteudo
  <template #footer>
    <Button @click="showModal = false">Fechar</Button>
  </template>
</Modal>
```

Fecha com Escape ou clicando no backdrop.

### LoadingSpinner

```vue
<LoadingSpinner />           <!-- md -->
<LoadingSpinner size="sm" /> <!-- pequeno -->
<LoadingSpinner size="lg" /> <!-- grande -->
```

## Composables

### useToast

```js
const toast = useToast();
toast.success('Salvo com sucesso!');
toast.error('Algo deu errado.');
toast.warning('Atencao!');
```

Toasts aparecem no canto superior direito com animacao. Somem apos 4 segundos.

### useClipboard

```js
const { copy } = useClipboard();
await copy('texto para copiar');
// Mostra toast "Copiado!" automaticamente
```

## Rotas Web (Inertia)

| Rota | Pagina | Descricao |
|------|--------|-----------|
| `/` | - | Redireciona para `/login` |
| `/login` | Auth/Login | Tela de login |
| `/register` | Auth/Register | Tela de registro |
| `/dashboard` | Dashboard | Dashboard pessoal |
| `/teams` | Teams/Index | Lista de equipes |
| `/teams/create` | Teams/Create | Criar equipe |
| `/teams/{id}` | Teams/Show | Detalhe da equipe |
| `/teams/{id}/expenses/create` | Expenses/Create | Criar despesa |
| `/teams/{id}/expenses/{id}` | Expenses/Show | Detalhe da despesa |
| `/charges/{id}` | Charges/Show | QR Code PIX |

## Design

- **Mobile-first** com Tailwind CSS
- **Cores**: indigo (primario), verde (pago), amarelo (pendente), vermelho (vencido), azul (parcial)
- **Fonte**: Instrument Sans
- **Feedback**: toasts para sucesso/erro, spinners para loading
- **Navbar** responsiva com menu hamburger no mobile

## Troubleshooting

### Pagina em branco

```bash
# Verificar se o Vite esta rodando
npm run dev

# Ou fazer build
npm run build
```

### Erro 401 constante

- Token pode estar expirado ou invalido
- Limpar localStorage: `localStorage.removeItem('token')`
- Fazer login novamente

### Pagina nao encontrada (Page not found)

- Verificar se o arquivo `.vue` existe em `resources/js/Pages/`
- Verificar se o nome no `Inertia::render()` bate com o path do arquivo

### Estilos nao aplicando

```bash
# Verificar se Tailwind esta escaneando arquivos Vue
# Em resources/css/app.css deve ter:
# @source "../**/*.vue";
```
