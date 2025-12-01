#!/bin/bash

# Script para testar a API de Saque PIX
# Certifique-se de que os containers estão rodando: docker-compose ps

echo "=========================================="
echo "Testando API de Saque PIX"
echo "=========================================="
echo ""

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

API_URL="http://localhost:9501"
ACCOUNT_ID="550e8400-e29b-41d4-a716-446655440000"

echo -e "${YELLOW}1. Testando Health Check${NC}"
curl -s -X GET "$API_URL/health" | jq '.' || echo "Health check endpoint não encontrado"
echo ""
echo ""

echo -e "${YELLOW}2. Consultando saldo da conta (João Silva - R$ 1000.00)${NC}"
curl -s -X GET "$API_URL/account/$ACCOUNT_ID/balance" | jq '.' || echo "Endpoint não encontrado"
echo ""
echo ""

echo -e "${YELLOW}3. Testando Saque PIX Imediato - R$ 100.00${NC}"
curl -s -X POST "$API_URL/account/$ACCOUNT_ID/balance/withdraw" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "PIX",
    "amount": 100.00,
    "pix_key": "joao@email.com",
    "pix_type": "EMAIL"
  }' | jq '.'
echo ""
echo ""

echo -e "${YELLOW}4. Consultando saldo após saque (deve ser R$ 900.00)${NC}"
curl -s -X GET "$API_URL/account/$ACCOUNT_ID/balance" | jq '.'
echo ""
echo ""

echo -e "${YELLOW}5. Testando Saque PIX Agendado - R$ 50.00 para daqui 1 minuto${NC}"
SCHEDULED_TIME=$(date -u -d '+1 minute' '+%Y-%m-%d %H:%M:%S')
curl -s -X POST "$API_URL/account/$ACCOUNT_ID/balance/withdraw" \
  -H "Content-Type: application/json" \
  -d "{
    \"method\": \"PIX\",
    \"amount\": 50.00,
    \"pix_key\": \"joao@email.com\",
    \"pix_type\": \"EMAIL\",
    \"scheduled_for\": \"$SCHEDULED_TIME\"
  }" | jq '.'
echo ""
echo ""

echo -e "${YELLOW}6. Testando Saque com Saldo Insuficiente - R$ 10000.00${NC}"
curl -s -X POST "$API_URL/account/$ACCOUNT_ID/balance/withdraw" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "PIX",
    "amount": 10000.00,
    "pix_key": "joao@email.com",
    "pix_type": "EMAIL"
  }' | jq '.'
echo ""
echo ""

echo -e "${YELLOW}7. Testando Chave PIX Inválida${NC}"
curl -s -X POST "$API_URL/account/$ACCOUNT_ID/balance/withdraw" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "PIX",
    "amount": 50.00,
    "pix_key": "email-invalido",
    "pix_type": "EMAIL"
  }' | jq '.'
echo ""
echo ""

echo -e "${GREEN}=========================================="
echo "Testes Concluídos!"
echo "==========================================${NC}"
echo ""
echo "Contas disponíveis para teste:"
echo "  - 550e8400-e29b-41d4-a716-446655440000 (João Silva - R$ 1000.00)"
echo "  - 550e8400-e29b-41d4-a716-446655440001 (Maria Santos - R$ 5000.00)"
echo "  - 550e8400-e29b-41d4-a716-446655440002 (Pedro Costa - R$ 100.00)"
