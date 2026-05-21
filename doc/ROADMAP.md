# Roadmap

Este arquivo lista evoluções futuras coerentes com a arquitetura atual. São direções possíveis, não funcionalidades já implementadas.

## Segurança

- Migrar autenticação da SPA para cookies HttpOnly
- Adicionar trilha de auditoria para ações de aprovação/rejeição
- Introduzir logs estruturados para eventos críticos
- Definir política mais explícita de retenção e expurgo de dados

## Backend

- Mover notificações para filas
- Tornar envios de notificação assíncronos
- Evoluir integração de WhatsApp e e-mail
- Criar histórico mais detalhado de mudanças de status
- Expandir documentação de contratos públicos e versionamento

## Frontend

- Melhorar visualização histórica do fluxo da cobrança
- Expandir o modo demo para cobrir mais cenários
- Adicionar estados vazios e loading ainda mais específicos
- Evoluir a UX de acompanhamento do organizador

## Infraestrutura

- Migrar storage de comprovantes para S3 ou compatível
- Separar melhor ambientes de build e publicação da SPA
- Adicionar observabilidade mais completa
- Definir pipeline de deploy e rollback

## Produto

- Reativar, revisar ou remover definitivamente a criação pública anônima
- Adicionar canais de lembrete automatizado
- Explorar conciliação ou confirmação semi-automática de pagamento
