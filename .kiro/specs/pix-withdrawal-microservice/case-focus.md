# Case Technical Requirements Focus

## Pontos Críticos do Case Técnico

### ⏰ Processamento de Saque Agendado
**Requisito Específico**: Uma cron irá verificar se há saques agendados pendentes e fará o processamento do saque. Caso no momento do saque for identificado que não há saldo suficiente, deve ser registrado no banco de dados que o saque foi processado, mas com falha de saldo insuficiente.

**Implementação**:
- Cron job verifica saques com `status = 'PENDING'` e `scheduled_for <= NOW()`
- Se saldo insuficiente: `status = 'REJECTED'`, `error_reason = 'saldo insuficiente'`
- Se sucesso: `status = 'DONE'`

### ⚠️ Pontos de Atenção Obrigatórios

#### 1. **Performance**
- Response time < 200ms para saques imediatos
- Connection pooling otimizado
- Índices de banco otimizados para queries de saque
- Swoole workers configurados para alta concorrência

#### 2. **Observabilidade**
- Logs estruturados em JSON
- Métricas de performance (response time, throughput)
- Health checks detalhados
- Tracing de transações críticas

#### 3. **Escalabilidade Horizontal**
- Aplicação stateless
- Cron jobs com leader election
- Operações atômicas para evitar duplicação
- Suporte a múltiplos containers

#### 4. **Segurança**
- Validação rigorosa de inputs
- Prevenção de SQL injection
- Rate limiting
- Logs de auditoria

#### 5. **Dockerização Completa**
- Zero dependências do ambiente host
- Docker Compose funcional do zero
- Volumes persistentes configurados
- Rede isolada entre containers

### 🎯 Foco no Case

**O que DEVE ser implementado:**
- ✅ Endpoint POST /account/{id}/balance/withdraw
- ✅ Validação de chave PIX (APENAS EMAIL)
- ✅ Saques imediatos e agendados
- ✅ Cron job para processamento
- ✅ Controle de concorrência
- ✅ Notificação por email
- ✅ Docker completo

**O que NÃO deve ser over-engineered:**
- ❌ Funcionalidades não solicitadas
- ❌ Complexidade desnecessária
- ❌ Abstrações excessivas
- ❌ Features "nice to have"

### 📋 Checklist de Entrega

- [ ] Projeto roda 100% no Docker
- [ ] Teste do zero (docker-compose up) funciona
- [ ] Performance atende requisitos
- [ ] Observabilidade implementada
- [ ] Escalabilidade horizontal testada
- [ ] Segurança validada
- [ ] README.md com decisões arquiteturais
- [ ] Foco mantido no que foi pedido

### 🏗️ Decisões Arquiteturais para Documentar

1. **Strategy Pattern**: Por que escolhemos para extensibilidade
2. **Pessimistic Locking**: Como evitamos race conditions
3. **Cron Job Design**: Como garantimos processamento único
4. **Docker Architecture**: Como garantimos isolamento completo
5. **Performance Optimizations**: Quais técnicas aplicamos
6. **Security Measures**: Quais proteções implementamos

Este documento serve como guia para manter o foco exato no que foi solicitado no case técnico.