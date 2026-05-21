# Padrões Utilizados

## Service Layer

### Onde aparece

- `app/Services/ExpenseService.php`
- `app/Services/PaymentProofService.php`
- `app/Services/PublicExpenseCreatorService.php`

### Por que foi usado

Há regras que envolvem múltiplos modelos, transações, validações de estado e efeitos colaterais. Isso ficaria pesado demais em controllers ou models.

### Benefícios

- centraliza regra de negócio
- reduz acoplamento com HTTP
- facilita teste por caso de uso

## Action Pattern

### Onde aparece

- `app/Actions/Expense/*`
- `app/Actions/Charge/*`

### Por que foi usado

O projeto separa operações específicas em classes curtas, deixando o controller mais enxuto e o fluxo mais explícito.

### Benefícios

- cada ação representa um caso de uso
- melhora legibilidade
- reduz controllers inchados

## Form Request Validation

### Onde aparece

- `app/Http/Requests/Api/V1/*`

### Por que foi usado

A validação de entrada fica fora do controller e mantém mensagens de erro centralizadas.

### Benefícios

- regras e mensagens agrupadas
- menos lógica repetida
- contrato de entrada mais claro

## API Resource

### Onde aparece

- `ExpenseResource`
- `ChargeResource`
- `PublicExpenseResource`
- `CreatedPublicExpenseResource`
- `UserResource`

### Por que foi usado

Os resources controlam exatamente o que sai na API e evitam exposição acidental de campos sensíveis.

### Benefícios

- contrato JSON consistente
- redução de vazamento de dados
- adaptação por contexto, como no fluxo público

## Rule Objects

### Onde aparece

- `app/Rules/BrazilPhone.php`

### Por que foi usado

Telefone brasileiro é uma validação reutilizável que aparece em múltiplos formulários.

### Benefícios

- evita duplicação
- mantém validação consistente entre endpoints

## Separation of Concerns

### Onde aparece

Na divisão entre:

- controller
- request
- action
- service
- resource
- support

### Por que foi usado

O projeto trata autenticação, validação, regra de negócio, serialização e utilitários em camadas diferentes.

### Benefícios

- facilita leitura para estudo
- reduz efeitos colaterais entre partes do sistema
- ajuda a evoluir o código sem mover tudo ao mesmo tempo

## Client Adapter + Mock no frontend

### Onde aparece

- `frontend/src/lib/api/client.ts`
- `frontend/src/lib/api/mockStore.ts`

### Por que foi usado

O mesmo frontend consegue operar com API real e com modo demonstração.

### Benefícios

- facilita apresentação e portfólio
- permite explorar o fluxo sem backend real
- mantém a UI principal reutilizável
