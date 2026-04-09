# App Laravel - API de Autenticacao

API REST construida com Laravel 12 + Sanctum, preparada para futura integracao com Asaas (pagamentos PIX).

## Stack

- PHP 8.4
- Laravel 12
- Laravel Sanctum (autenticacao via token)
- MySQL 8.0
- Redis
- Nginx
- Docker

## Setup do Projeto

### 1. Clonar o repositorio

```sh
git clone <url-do-repositorio> app-laravel
cd app-laravel
```

### 2. Criar arquivo .env

```sh
cp .env.example .env
```

### 3. Subir os containers

```sh
docker compose up -d
```

### 4. Instalar dependencias

```sh
docker compose exec app composer install
```

### 5. Gerar chave da aplicacao

```sh
docker compose exec app php artisan key:generate
```

### 6. Rodar migrations

```sh
docker compose exec app php artisan migrate
```

### 7. Acessar a aplicacao

- **App:** http://localhost:8000
- **PHPMyAdmin:** http://localhost:8080

## Endpoints da API

Todas as rotas possuem o prefixo `/api/v1`.

### Autenticacao

| Metodo | Endpoint              | Auth     | Descricao                    |
|--------|-----------------------|----------|------------------------------|
| POST   | `/api/v1/auth/register` | Nao    | Registro de novo usuario     |
| POST   | `/api/v1/auth/login`    | Nao    | Login (retorna token)        |
| POST   | `/api/v1/auth/logout`   | Sim    | Logout (revoga token atual)  |
| GET    | `/api/v1/auth/me`       | Sim    | Dados do usuario autenticado |

### Registro

```sh
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Joao Silva",
    "email": "joao@email.com",
    "password": "123456",
    "password_confirmation": "123456",
    "phone": "11999999999",
    "cpf": "12345678901"
  }'
```

### Login

```sh
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "joao@email.com",
    "password": "123456"
  }'
```

### Rotas autenticadas

Use o token retornado no login/registro no header `Authorization`:

```sh
curl http://localhost:8000/api/v1/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

### Logout

```sh
curl -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

## Estrutura do Projeto (Auth)

```
app/
  Http/
    Controllers/Api/V1/Auth/
      AuthController.php          # Endpoints de autenticacao
    Requests/Api/V1/Auth/
      RegisterRequest.php         # Validacao de registro
      LoginRequest.php            # Validacao de login
    Resources/
      UserResource.php            # Formatacao JSON do usuario
  Models/
    User.php                      # Model com HasApiTokens

routes/
  api.php                         # Rotas da API (v1)

database/
  migrations/
    2026_04_08_000001_add_profile_fields_to_users_table.php
  factories/
    UserFactory.php

tests/
  Feature/Auth/
    AuthTest.php                  # 7 testes de autenticacao
```

## Campos do Usuario

| Campo              | Tipo    | Observacao                              |
|--------------------|---------|-----------------------------------------|
| name               | string  | Obrigatorio                             |
| email              | string  | Obrigatorio, unico                      |
| password           | string  | Obrigatorio, min 6 caracteres           |
| phone              | string  | Opcional                                |
| cpf                | string  | Opcional, 11 digitos, unico             |
| asaas_customer_id  | string  | Preenchido pela integracao Asaas futura |
| is_active          | boolean | Default: true                           |

## Testes

### Rodar todos os testes

```sh
docker compose exec app php artisan test
```

### Rodar apenas testes de autenticacao

```sh
docker compose exec app php artisan test --filter=AuthTest
```

### Rodar com cobertura de codigo

```sh
docker compose exec app php artisan test --coverage
```

### Testes implementados

| Teste                                          | Cenario                              |
|------------------------------------------------|--------------------------------------|
| test_user_can_register_successfully            | Registro com sucesso (201 + token)   |
| test_register_fails_with_duplicate_email       | Email duplicado retorna 422          |
| test_user_can_login_successfully               | Login valido retorna token           |
| test_login_fails_with_wrong_password           | Senha errada retorna 401             |
| test_authenticated_user_can_access_me          | Token valido acessa /me              |
| test_unauthenticated_user_cannot_access_me     | Sem token retorna 401                |
| test_user_can_logout_and_token_is_invalidated  | Logout remove token do banco         |

## Comandos Uteis

```sh
# Acessar o container
docker compose exec app bash

# Listar rotas da API
docker compose exec app php artisan route:list --path=api

# Limpar caches
docker compose exec app php artisan optimize:clear

# Rodar migrations fresh (reset + migrate)
docker compose exec app php artisan migrate:fresh

# Criar novo usuario via tinker
docker compose exec app php artisan tinker
# > User::factory()->create(['email' => 'teste@email.com', 'password' => '123456'])

# Parar os containers
docker compose down

# Parar e remover volumes (apaga dados do banco)
docker compose down -v
```

## Preparacao para Asaas

O campo `asaas_customer_id` ja existe na tabela `users` e esta indexado. Na proxima etapa:

1. Criar um `AsaasService` para comunicacao com a API
2. Apos o registro, criar o customer no Asaas
3. Salvar o `asaas_customer_id` retornado
4. Implementar geracoes de cobrancas PIX
