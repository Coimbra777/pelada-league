Você é um Tech Lead responsável por documentar um sistema completo para estudo, portfólio e evolução futura.

Objetivo:
Criar documentação completa dentro da pasta /doc com foco em:

- entendimento do sistema
- arquitetura
- decisões técnicas
- padrões utilizados
- segurança
- fluxo de negócio
- evolução futura

IMPORTANTE:

- Não inventar nada que não exista no código
- Basear tudo em arquivos reais
- Usar linguagem clara e didática
- Pensar em alguém que vai estudar o projeto depois
- Não alterar código, apenas gerar documentação

---

# Estrutura da documentação

Criar os arquivos:

doc/

- OVERVIEW.md
- ARCHITECTURE.md
- BACKEND.md
- FRONTEND.md
- FLOWS.md
- SECURITY.md (complementar ao existente, se necessário)
- PATTERNS.md
- DECISIONS.md
- ROADMAP.md

---

# 1. OVERVIEW.md

Conteúdo:

- Nome do projeto
- Descrição do sistema (cobrança compartilhada)
- Problema que resolve
- Público alvo
- Principais funcionalidades:
    - criação de cobrança
    - link público
    - validação de participante
    - envio de comprovante
    - aprovação/rejeição
    - fechamento da cobrança
- Diferenciais do sistema:
    - fluxo público seguro com token
    - validação completa frontend/backend
    - exclusão automática de comprovantes
    - modo demonstração

---

# 2. ARCHITECTURE.md

Explicar:

## Backend

- Laravel
- organização em:
    - Controllers
    - Requests
    - Services
    - Actions
    - Resources
    - Support
    - Rules

## Frontend

- React + Vite
- organização por:
    - pages
    - components
    - lib
    - api

## Comunicação

- REST API
- padrão de resposta (ApiResponse)

## Storage

- arquivos privados
- organização por expense

## Fluxo público

- uso de public_hash
- uso de manage_token

---

# 3. BACKEND.md

Detalhar:

- estrutura de pastas
- responsabilidades de cada camada:
    - Controller → entrada HTTP
    - Request → validação
    - Service → regras de negócio
    - Action → operações específicas
    - Resource → saída da API
    - Support → utilitários
    - Rules → validações reutilizáveis

## Modelagem

- Expense
- Charge
- PaymentProof
- User

## Regras importantes:

- soma >= total
- estados de cobrança
- lifecycle do comprovante

---

# 4. FRONTEND.md

Detalhar:

- organização do projeto
- fluxo de autenticação
- fluxo de demo
- tratamento de erros
- validação por etapa
- máscaras (telefone, moeda)

## UX

- sem alert()
- erros por campo
- mensagens PT-BR
- feedback de diferença (faltante/excedente)

---

# 5. FLOWS.md

Explicar passo a passo:

## Fluxos principais:

1. Criar cobrança
2. Adicionar participantes
3. Acessar link público
4. Validar participante
5. Enviar comprovante
6. Rejeitar comprovante
7. Reenviar comprovante
8. Validar pagamento
9. Fechar cobrança

Para cada fluxo:

- endpoints envolvidos
- arquivos principais
- regras de negócio
- estados

---

# 6. PATTERNS.md

Identificar padrões reais usados:

- Service Layer
- Action Pattern
- Form Request Validation
- API Resource
- Rule Objects
- Separation of concerns

Explicar:

- onde estão no código
- por que foram usados
- benefícios

---

# 7. SECURITY.md (complementar)

Garantir que esteja documentado:

- token de autenticação
- manage_token via header
- rate limiting
- headers de segurança
- CORS
- storage privado
- upload seguro
- exclusão de comprovantes
- CPF não coletado
- LGPD (mínimo)

---

# 8. DECISIONS.md

Aqui é MUITO importante.

Documentar decisões técnicas:

## Exemplos:

- uso de localStorage para token (trade-off)
- manage_token para fluxo público
- exclusão de comprovantes após fechamento
- não usar CPF
- validação dupla (frontend + backend)
- permitir valor excedente
- UX sem alert()

Para cada decisão:

- contexto
- decisão tomada
- alternativa descartada
- trade-offs

---

# 9. ROADMAP.md

Futuras melhorias:

## Segurança

- cookies HttpOnly
- auditoria
- logs estruturados

## Backend

- fila (queues)
- notificações async
- integração com WhatsApp/email

## Infra

- S3
- CI/CD completo
- monitoramento

## Produto

- dashboard melhor
- relatórios
- multi-empresa

---

# Formato

- Markdown organizado
- títulos claros
- exemplos quando necessário
- evitar texto genérico
- citar arquivos reais sempre que possível

---

# Entrega final

- lista de arquivos criados
- resumo do conteúdo
- se algum ponto não foi encontrado no código
