# Visão Geral

## Nome do projeto

ContaCerta Pix

## O que o sistema faz

O projeto organiza cobranças compartilhadas via Pix. Um usuário autenticado cria uma despesa, informa valor total, vencimento e chave Pix, adiciona participantes e compartilha um link público para que cada pessoa consulte o próprio valor e envie o comprovante de pagamento.

O sistema não movimenta dinheiro. Ele funciona como uma camada de organização do processo: distribuição de valores, identificação do participante, recebimento de comprovantes, validação manual e fechamento da cobrança.

## Problema que resolve

O código foi estruturado para resolver um cenário comum: alguém paga uma despesa coletiva, divide os valores com outras pessoas e precisa controlar quem já pagou, quem ainda não pagou e quais comprovantes precisam ser conferidos.

Sem esse tipo de fluxo, o organizador costuma depender de mensagens soltas, comprovantes espalhados e conferência manual sem histórico.

## Público-alvo

- Organizadores de despesas compartilhadas
- Pequenos grupos informais que usam Pix para dividir custos
- Pessoas estudando Laravel + React com um projeto full stack real

## Principais funcionalidades

- Criação de cobrança autenticada
- Adição e redistribuição de participantes
- Geração de link público por `public_hash`
- Identificação pública do participante por nome + telefone
- Envio de comprovante pelo participante
- Aprovação ou rejeição do comprovante pelo organizador
- Reenvio após rejeição
- Fechamento da cobrança quando todas as cobranças individuais estão validadas

## Diferenciais observáveis no código

- Fluxo público com separação entre acesso do participante e acesso de gestão
- `manage_token` separado do link público e aceito via header
- Validação duplicada entre frontend e backend
- Mensagens de erro em PT-BR com códigos estáveis
- Comprovantes em storage privado com limpeza automática no fechamento
- Modo demonstração isolado do fluxo real

## Limites atuais do MVP

- A validação do pagamento é manual
- O login autenticado usa Bearer token em `localStorage`
- A criação pública anônima existe no backend, mas está em standby por middleware
- Notificações existem como serviço, mas o fluxo ainda é simples e síncrono
