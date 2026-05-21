# Decisões Técnicas

## 1. Usar Bearer token em `localStorage`

### Contexto

O frontend é uma SPA consumindo a API Laravel via `fetch`.

### Decisão tomada

O token autenticado é salvo em `localStorage` e enviado como Bearer nas rotas privadas.

### Alternativa não adotada

Sessão baseada em cookie HttpOnly.

### Trade-offs

- mais simples para o MVP e para rodar a SPA separada do backend
- mais fácil de demonstrar em ambiente local
- aumenta a sensibilidade a XSS em comparação com cookie HttpOnly

## 2. Usar `manage_token` para gestão pública

### Contexto

O sistema precisa permitir aprovação/rejeição pública sem exigir conta para o organizador em todos os cenários.

### Decisão tomada

Cada despesa pública tem `public_hash` e `manage_token`. O token de gestão é aceito somente via `X-Manage-Token`.

### Alternativa não adotada

Expor ações de gestão no mesmo link público sem token adicional.

### Trade-offs

- separa visualização pública e gestão
- reduz vazamento de dados para visitantes comuns
- ainda depende de um segredo compartilhável fora do login tradicional

## 3. Excluir comprovantes ao fechar a despesa

### Contexto

Os comprovantes são arquivos sensíveis e o MVP não precisa retê-los depois que a cobrança termina.

### Decisão tomada

Ao fechar a despesa, o backend apaga todos os arquivos da pasta da despesa e zera `file_path`.

### Alternativa não adotada

Reter comprovantes indefinidamente para consulta histórica.

### Trade-offs

- reduz retenção de dados
- simplifica LGPD mínima
- perde acesso futuro ao arquivo após encerramento

## 4. Não coletar CPF

### Contexto

O fluxo principal depende de nome, telefone, valor e chave Pix.

### Decisão tomada

CPF não faz parte do cadastro nem do fluxo de cobrança.

### Alternativa não adotada

Solicitar CPF como identificador adicional.

### Trade-offs

- reduz dado pessoal armazenado
- simplifica formulários e documentação
- perde um possível fator extra de identificação

## 5. Validar no frontend e no backend

### Contexto

O projeto tem formulários com múltiplas etapas e um fluxo público exposto a entradas diretas.

### Decisão tomada

Há validação local no frontend e validação autoritativa no backend.

### Alternativa não adotada

Confiar apenas no backend ou apenas no frontend.

### Trade-offs

- melhora UX e feedback imediato
- mantém segurança e consistência no servidor
- aumenta duplicação controlada de regras

## 6. Permitir soma dos participantes acima do total

### Contexto

O sistema representa cobranças compartilhadas em que pode existir excedente de caixa.

### Decisão tomada

A regra atual aceita `soma >= total`.

### Alternativa não adotada

Exigir soma exatamente igual ao total.

### Trade-offs

- cobre cenários de excedente
- deixa o frontend informar “sobra” sem bloquear
- exige clareza de UX para explicar que o excedente fica em caixa

## 7. Manter UX sem `alert()`

### Contexto

O frontend já usa mensagens inline e feedback por campo.

### Decisão tomada

Os fluxos principais exibem erros inline e resumos por etapa, inclusive no upload.

### Alternativa não adotada

Usar `alert()` nativo do navegador.

### Trade-offs

- melhor acessibilidade e consistência visual
- mais trabalho de UI
- comportamento mais previsível em testes

## 8. Agrupar comprovantes por despesa

### Contexto

O sistema precisa manter comprovantes privados e simples de limpar no fechamento.

### Decisão tomada

Os arquivos ficam em `payment-proofs/expense-{expense_id}/...`.

### Alternativa não adotada

Criar pasta separada por comprovante ou por cobrança individual.

### Trade-offs

- facilita limpeza total no fechamento
- melhora organização por contexto de negócio
- exige nome de arquivo com timestamp para evitar colisão

## 9. Manter criação pública anônima em standby

### Contexto

O backend possui infraestrutura para criação pública, mas o produto ainda não expõe esse fluxo como funcionalidade ativa.

### Decisão tomada

O endpoint existe, porém está bloqueado por middleware de standby.

### Alternativa não adotada

Remover completamente a implementação ou deixar a funcionalidade aberta.

### Trade-offs

- preserva o código para evolução futura
- evita expor um fluxo ainda não consolidado
- adiciona um caminho “parcialmente pronto” que precisa ser bem documentado
