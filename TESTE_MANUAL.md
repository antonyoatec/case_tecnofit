# Manual de Testes - PIX Withdrawal Microservice

## Objetivo

Este documento contém todos os comandos necessários para testar o sistema do zero, validando todas as funcionalidades implementadas.

---

## Pré-requisitos

- Docker e Docker Compose instalados
- Portas disponíveis: 9501 (API), 3306 (MySQL), 8025 (Mailhog)
- Terminal com suporte a curl e python3

---

## 1. Setup Inicial - Limpar e Subir Containers

### 1.1. Parar e Limpar Tudo

```bash
echo "=== PARANDO E LIMPANDO CONTAINERS ===" && docker-compose down -v
```

**Resultado esperado**: Todos os containers parados e volumes removidos.

---

### 1.2. Subir Containers

```bash
echo "=== SUBINDO CONTAINERS ===" && docker-compose up -d
```

**Nota**: Se o container `app` falhar por MySQL não estar pronto, aguarde e execute:

```bash
echo "=== AGUARDANDO MYSQL ===" && sleep 15 && docker-compose up -d app
```

---

### 1.3. Aguardar Inicialização

```bash
echo "=== AGUARDANDO APP INICIALIZAR ===" && sleep 10 && docker-compose ps
```

**Resultado esperado**: Todos os containers com status `Up (healthy)`.

---

## 2. Testes Funcionais

### TESTE 1: Health Check

**Objetivo**: Verificar se a API está respondendo.

```bash
echo "=== TESTE 1: HEALTH CHECK ===" && \
curl -s http://localhost:9501/health | python3 -m json.tool
```

**Resultado esperado**:
```json
{
    "status": "ok",
    "timestamp": "2025-11-25 16:23:22"
}
```

---

### TESTE 2: Consultar Saldo Inicial

**Objetivo**: Verificar saldo inicial da conta de teste.

```bash
echo "=== TESTE 2: SALDO INICIAL ===" && \
curl -s http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance | python3 -m json.tool
```

**Resultado esperado**:
```json
{
    "success": true,
    "data": {
        "account_id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "Test Account",
        "balance": 1000
    }
}
```

---

### TESTE 3: Saque Imediato (Sucesso)

**Objetivo**: Processar um saque PIX imediato de R$ 100,00.

```bash
echo "=== TESTE 3: SAQUE IMEDIATO R$ 100 ===" && \
curl -s -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"pix","amount":100.00,"pix_key":"cliente@email.com"}' | python3 -m json.tool
```

**Resultado esperado**:
```json
{
    "success": true,
    "data": {
        "withdraw_id": "uuid-gerado",
        "status": "completed",
        "amount": "100.00",
        "pix_key": "cliente@email.com",
        "previous_balance": "1000.00",
        "new_balance": 900,
        "processed_at": "2025-11-25 16:24:49"
    }
}
```

---

### TESTE 4: Verificar Saldo Após Saque

**Objetivo**: Confirmar que o saldo foi debitado corretamente.

```bash
echo "=== TESTE 4: SALDO APÓS SAQUE ===" && \
curl -s http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance | python3 -m json.tool
```

**Resultado esperado**:
```json
{
    "success": true,
    "data": {
        "account_id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "Test Account",
        "balance": 900
    }
}
```

---

### TESTE 5: Saldo Insuficiente

**Objetivo**: Validar rejeição por saldo insuficiente.

```bash
echo "=== TESTE 5: SALDO INSUFICIENTE ===" && \
curl -s -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"pix","amount":10000.00,"pix_key":"cliente@email.com"}' | python3 -m json.tool
```

**Resultado esperado**:
```json
{
    "success": false,
    "error": {
        "code": "INSUFFICIENT_BALANCE",
        "message": "saldo insuficiente"
    }
}
```

---

### TESTE 6: Conta Inexistente

**Objetivo**: Validar rejeição de conta que não existe.

```bash
echo "=== TESTE 6: CONTA INEXISTENTE ===" && \
curl -s -X POST http://localhost:9501/account/99999999-9999-9999-9999-999999999999/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"pix","amount":50.00,"pix_key":"cliente@email.com"}' | python3 -m json.tool
```

**Resultado esperado**:
```json
{
    "success": false,
    "error": {
        "code": "ACCOUNT_NOT_FOUND",
        "message": "Account not found"
    }
}
```

---

### TESTE 7: Criar Saque Agendado

**Objetivo**: Criar um saque agendado para processamento futuro.

```bash
echo "=== TESTE 7: SAQUE AGENDADO ===" && \
curl -s -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"pix","amount":50.00,"pix_key":"agendado@email.com","scheduled_for":"2025-11-25 23:59:00"}' | python3 -m json.tool
```

**Resultado esperado**:
```json
{
    "success": true,
    "data": {
        "withdraw_id": "uuid-gerado",
        "status": "scheduled",
        "amount": "50.00",
        "pix_key": "agendado@email.com",
        "scheduled_for": "2025-11-25 23:59:00",
        "created_at": "2025-11-25 16:25:44"
    }
}
```

**Importante**: Copie o `withdraw_id` retornado para usar nos próximos testes.

---

### TESTE 8: Verificar Saque no Banco de Dados

**Objetivo**: Confirmar que os saques foram salvos no banco.

```bash
echo "=== TESTE 8: VERIFICAR BANCO ===" && \
docker-compose exec -T mysql mysql -uroot -proot pix_withdrawal \
  -e "SELECT id, account_id, method, amount, status, scheduled, scheduled_for FROM account_withdraw ORDER BY created_at DESC LIMIT 3;" 2>/dev/null
```

**Resultado esperado**: Lista dos últimos 3 saques com seus status.

---

### TESTE 9: Alterar Horário do Saque Agendado

**Objetivo**: Alterar o horário do saque agendado para que possa ser processado imediatamente.

**Importante**: Substitua `WITHDRAW_ID` pelo ID retornado no TESTE 7.

```bash
echo "=== TESTE 9: ALTERAR HORÁRIO PARA PROCESSAR ===" && \
docker-compose exec -T mysql mysql -uroot -proot pix_withdrawal \
  -e "UPDATE account_withdraw SET scheduled_for = '2025-11-25 16:00:00' WHERE id = 'WITHDRAW_ID';" 2>/dev/null && \
echo "Horário alterado com sucesso!"
```

**Resultado esperado**: Mensagem "Horário alterado com sucesso!"

---

### TESTE 10: Executar Cron Job Manualmente

**Objetivo**: Processar os saques agendados via cron job.

```bash
echo "=== TESTE 10: EXECUTAR CRON JOB ===" && \
docker-compose exec app php bin/hyperf.php crontab:run 2>&1 | tail -5
```

**Resultado esperado**: Mensagem indicando que o cron foi executado com sucesso.

---

### TESTE 11: Verificar Status do Saque Agendado

**Objetivo**: Confirmar que o saque agendado foi processado.

**Importante**: Substitua `WITHDRAW_ID` pelo ID do saque agendado.

```bash
echo "=== TESTE 11: STATUS DO SAQUE AGENDADO ===" && \
docker-compose exec -T mysql mysql -uroot -proot pix_withdrawal \
  -e "SELECT id, amount, status, error_reason FROM account_withdraw WHERE id = 'WITHDRAW_ID';" 2>/dev/null
```

**Resultado esperado**: Status = `DONE` e `error_reason` = `NULL`.

---

### TESTE 12: Verificar Saldo Final

**Objetivo**: Confirmar que todos os saques foram debitados corretamente.

```bash
echo "=== TESTE 12: SALDO FINAL ===" && \
curl -s http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance | python3 -m json.tool
```

**Resultado esperado**:
```json
{
    "success": true,
    "data": {
        "account_id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "Test Account",
        "balance": 850
    }
}
```

**Cálculo**: 1000 - 100 (imediato) - 50 (agendado) = 850

---

### TESTE 13: Verificar Dados PIX no Banco

**Objetivo**: Confirmar que os dados PIX foram salvos corretamente.

```bash
echo "=== TESTE 13: DADOS PIX ===" && \
docker-compose exec -T mysql mysql -uroot -proot pix_withdrawal \
  -e "SELECT id, account_withdraw_id, type, pix_key FROM account_withdraw_pix ORDER BY created_at DESC LIMIT 3;" 2>/dev/null
```

**Resultado esperado**: Lista com as chaves PIX dos saques processados.

---

### TESTE 14: Teste de Concorrência

**Objetivo**: Validar que o sistema suporta múltiplas requisições simultâneas sem duplicação.

```bash
echo "=== TESTE 14: CONCORRÊNCIA (10 SAQUES SIMULTÂNEOS) ===" && \
for i in {1..10}; do
  curl -s -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance/withdraw \
    -H "Content-Type: application/json" \
    -d '{"method":"pix","amount":10.00,"pix_key":"concorrencia@email.com"}' &
done
wait
echo "Todos os saques foram enviados!"
```

**Aguardar processamento**:
```bash
sleep 2 && echo "=== VERIFICAR SALDO APÓS CONCORRÊNCIA ===" && \
curl -s http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance | python3 -m json.tool
```

**Resultado esperado**: Saldo = 750 (850 - 100 = 10 saques × R$ 10)

---

### TESTE 15: Contar Total de Saques

**Objetivo**: Verificar quantos saques foram processados no total.

```bash
echo "=== TESTE 15: TOTAL DE SAQUES ===" && \
docker-compose exec -T mysql mysql -uroot -proot pix_withdrawal \
  -e "SELECT status, COUNT(*) as total FROM account_withdraw GROUP BY status;" 2>/dev/null
```

**Resultado esperado**: 12 saques com status `DONE`.

---

## 3. Testes Adicionais (Opcionais)

### Teste de Email (Mailhog)

**Objetivo**: Verificar se os emails de notificação foram enviados.

1. Abra o navegador em: http://localhost:8025
2. Verifique se há emails de notificação de saque

---

### Teste de Logs

**Objetivo**: Verificar logs da aplicação.

```bash
docker-compose logs app | tail -50
```

---

### Teste de Performance

**Objetivo**: Medir tempo de resposta.

```bash
time curl -s -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"pix","amount":5.00,"pix_key":"performance@email.com"}' > /dev/null
```

**Resultado esperado**: Tempo < 200ms

---

## 4. Limpeza e Reset

### Resetar Banco de Dados

```bash
docker-compose down -v && docker-compose up -d
```

**Aguardar**: 30 segundos para inicialização completa.

---

## 5. Checklist de Validação

Marque os testes conforme forem sendo executados:

- [ ] TESTE 1: Health Check
- [ ] TESTE 2: Saldo Inicial
- [ ] TESTE 3: Saque Imediato
- [ ] TESTE 4: Saldo Atualizado
- [ ] TESTE 5: Saldo Insuficiente
- [ ] TESTE 6: Conta Inexistente
- [ ] TESTE 7: Saque Agendado
- [ ] TESTE 8: Dados no Banco
- [ ] TESTE 9: Alterar Horário
- [ ] TESTE 10: Executar Cron
- [ ] TESTE 11: Status Agendado
- [ ] TESTE 12: Saldo Final
- [ ] TESTE 13: Dados PIX
- [ ] TESTE 14: Concorrência
- [ ] TESTE 15: Total de Saques

---

## 6. Troubleshooting

### Container não sobe

```bash
docker-compose logs app
```

### MySQL não está pronto

```bash
docker-compose exec mysql mysqladmin ping -h localhost -uroot -proot
```

### Limpar cache do Docker

```bash
docker system prune -a
```

### Verificar portas em uso

```bash
netstat -tuln | grep -E '9501|3306|8025'
```

---

## 7. Resultados Esperados - Resumo

| Teste | Descrição | Resultado Esperado |
|-------|-----------|-------------------|
| 1 | Health Check | Status: ok |
| 2 | Saldo Inicial | R$ 1000,00 |
| 3 | Saque Imediato | Status: completed |
| 4 | Saldo Atualizado | R$ 900,00 |
| 5 | Saldo Insuficiente | Error: INSUFFICIENT_BALANCE |
| 6 | Conta Inexistente | Error: ACCOUNT_NOT_FOUND |
| 7 | Saque Agendado | Status: scheduled |
| 8 | Dados no Banco | 2 registros |
| 9 | Alterar Horário | Sucesso |
| 10 | Executar Cron | Sucesso |
| 11 | Status Agendado | Status: DONE |
| 12 | Saldo Final | R$ 850,00 |
| 13 | Dados PIX | 2 registros |
| 14 | Concorrência | R$ 750,00 |
| 15 | Total Saques | 12 saques |

---

## 8. Conclusão

Se todos os testes passarem, o sistema está:

- Funcionando corretamente
- Processando saques imediatos
- Processando saques agendados via cron
- Validando saldo insuficiente
- Validando conta inexistente
- Suportando concorrência
- Persistindo dados corretamente

**Sistema pronto para produção!**

---

**Última atualização**: 2025-11-25  
**Versão**: 1.0
