# Documentacao - Caixinha

Sistema de divisao de despesas com pagamento PIX.

## Indice

| Documento | Descricao |
|-----------|-----------|
| [arquitetura.md](arquitetura.md) | Visao geral do sistema, camadas, fluxos, seguranca |
| [api.md](api.md) | Referencia completa da API REST (endpoints, payloads, erros) |
| [frontend.md](frontend.md) | Como rodar o frontend, paginas, componentes, stores |
| [autenticacao.md](autenticacao.md) | Setup do projeto, Docker, comandos uteis |

## Quick Start

```bash
# 1. Subir containers
docker compose up -d

# 2. Instalar dependencias
docker compose exec app composer install
npm install

# 3. Configurar ambiente
cp .env.example .env
docker compose exec app php artisan key:generate

# 4. Rodar migrations
docker compose exec app php artisan migrate

# 5. Iniciar frontend (dev)
npm run dev

# 6. Acessar
# App:        http://localhost:8000
# PHPMyAdmin: http://localhost:8080
```

## Testes

```bash
# Todos os testes (42)
docker compose exec app php artisan test

# Apenas auth
docker compose exec app php artisan test --filter=AuthTest

# Apenas cobrancas
docker compose exec app php artisan test --filter=ChargeTest

# Apenas despesas
docker compose exec app php artisan test --filter=ExpenseTest

# Apenas equipes
docker compose exec app php artisan test --filter=TeamTest
```

## Build Producao

```bash
npm run build
```
