> **AVISO:** Este documento foi gerado com auxílio de Inteligência Artificial.

# Guia de Setup do Projeto - PIX Withdrawal Microservice

## O que é o Makefile?

O **Makefile** é um arquivo que automatiza comandos repetitivos do projeto. Ele funciona como um "atalho" para comandos Docker complexos, facilitando o desenvolvimento.

### Exemplo Prático:
```bash
# Sem Makefile (comando longo)
docker-compose exec app php bin/hyperf.php migrate

# Com Makefile (comando curto)
make migrate
```

---

## Comandos Principais do Makefile

### Setup e Inicialização
```bash
make setup          # Setup completo: build + up + install + migrate + seed
make build          # Constrói as imagens Docker
make up             # Inicia todos os containers
make down           # Para todos os containers
make restart        # Reinicia todos os containers
```

### Desenvolvimento
```bash
make logs           # Mostra logs de todos os serviços
make logs-app       # Mostra apenas logs da aplicação
make shell          # Acessa o terminal do container da aplicação
make mysql          # Acessa o MySQL CLI
make redis          # Acessa o Redis CLI
```

### Banco de Dados
```bash
make migrate        # Executa migrations
make migrate-rollback  # Reverte última migration
make seed           # Executa seeders (cria dados de teste)
make fresh          # Reseta banco: drop + migrate + seed
```

### Testes e Qualidade
```bash
make test           # Executa testes automatizados
make analyse        # Análise estática de código
make cs-fix         # Corrige estilo de código
```

### Limpeza
```bash
make clean          # Remove containers, volumes e limpa Docker
```

---

## Arquivos Necessários para Executar o Projeto

### Arquivos OBRIGATÓRIOS (Já no Repositório)

#### 1. Docker
- `docker-compose.yml` - Orquestra todos os containers (app, MySQL, Mailhog)
- `Dockerfile` - Define como construir a imagem da aplicação
- `docker/` - Configurações adicionais do Docker

#### 2. Configuração PHP/Hyperf
- `composer.json` - Dependências PHP do projeto
- `composer.lock` - Versões exatas das dependências
- `.env` - Variáveis de ambiente (já vem configurado)
- `.env.example` - Template de variáveis de ambiente
- `phpunit.xml` - Configuração de testes

#### 3. Código da Aplicação
- `app/` - Todo código PHP (Controllers, Services, Models, etc)
- `config/` - Configurações do Hyperf
- `migrations/` - Migrations do banco de dados
- `seeders/` - Dados iniciais para testes
- `bin/hyperf.php` - CLI do Hyperf

#### 4. Automação
- `Makefile` - Comandos automatizados
- `test-api.sh` - Script de teste da API

#### 5. Documentação
- `README.md` - Documentação principal
- `TESTE_MANUAL.md` - Guia de testes manuais
- Outros arquivos `.md` - Documentação adicional

---

## Setup do Zero (Passo a Passo)

### Configuração Zero-Friction

**Boa notícia:** Este projeto foi configurado para funcionar **sem precisar configurar nada**!

O arquivo `.env` **já vem no repositório** com:
- Usuário e senha padrão do MySQL (`root`/`root`)
- Porta padrão do MySQL (3306)
- Configurações padrão do Redis
- Mailhog configurado para testes de email
- Todos os nomes de serviços do Docker Compose

**Você não precisa criar, copiar ou editar nada!** Clone e rode.

### Pré-requisitos no Computador
```bash
# Verificar se tem Docker instalado
docker --version
# Deve mostrar: Docker version 20.x ou superior

# Verificar se tem Docker Compose
docker-compose --version
# Deve mostrar: docker-compose version 1.29.x ou superior

# Verificar se tem Make (Linux/Mac já vem, Windows precisa instalar)
make --version
# Deve mostrar: GNU Make 4.x
```

### Instalação Completa

#### Opção 1: Usando Makefile (Recomendado)
```bash
# 1. Clone o repositório
git clone <url-do-repositorio>
cd pix-withdrawal-microservice

# 2. Execute o setup completo (faz tudo automaticamente)
make setup

# 3. Verifique se está funcionando
curl http://localhost:9501/health
```

> **Dica:** O comando `make setup` faz tudo: build, up, install, migrate e seed. Aguarde ~2 minutos.

#### Opção 2: Comandos Manuais (Sem Makefile)
```bash
# 1. Clone o repositório
git clone <url-do-repositorio>
cd pix-withdrawal-microservice

# 2. Construa as imagens Docker
docker-compose build

# 3. Inicie os containers
docker-compose up -d

# 4. Aguarde MySQL inicializar (~30 segundos)
sleep 30

# 5. Instale dependências PHP
docker-compose exec app composer install

# 6. Execute migrations
docker-compose exec app php bin/hyperf.php migrate

# 7. Execute seeders (dados de teste)
docker-compose exec app php bin/hyperf.php db:seed

# 8. Verifique se está funcionando
curl http://localhost:9501/health
```

---

## Arquivo .env (Configuração)

O arquivo `.env` contém as configurações do projeto. **A boa notícia:** o `.env` **já vem no repositório** com **tudo configurado e pronto para usar**!

```bash
# Não precisa fazer nada! O .env já existe.
# Apenas clone e rode: make setup
```

### Por que funciona sem configurar?

O `.env` já vem commitado no repositório com **configurações padrão** que funcionam perfeitamente com o Docker Compose:

```env
# Banco de Dados - Configurações padrão do container MySQL
DB_HOST=mysql          # Nome do serviço no docker-compose
DB_PORT=3306           # Porta padrão MySQL
DB_DATABASE=pix_withdrawal
DB_USERNAME=root       # Usuário padrão
DB_PASSWORD=root       # Senha padrão

# Redis - Configurações padrão do container Redis
REDIS_HOST=redis       # Nome do serviço no docker-compose
REDIS_PORT=6379        # Porta padrão Redis

# Email - Mailhog para testes (não precisa configurar)
MAIL_HOST=mailhog      # Nome do serviço no docker-compose
MAIL_PORT=1025         # Porta SMTP do Mailhog
```

### Quando você precisaria editar?

**Nunca!** Para desenvolvimento local com Docker, os valores padrão funcionam perfeitamente.

Você só editaria o `.env` se:
- Estivesse fazendo deploy em produção (senhas fortes, hosts externos)
- Quisesse mudar portas (ex: MySQL na porta 3307)
- Quisesse usar banco de dados externo (não Docker)
- Quisesse personalizar configurações específicas

**Mas para começar a desenvolver:** não precisa tocar em nada!

---

## Testando o Sistema

Após o setup, você pode testar se tudo está funcionando corretamente.

### Testes Rápidos

#### 1. Health Check
```bash
curl http://localhost:9501/health
# Deve retornar: {"status":"ok","timestamp":"2025-11-28 18:00:00"}
```

#### 2. Consultar Saldo
```bash
curl http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance
# Deve retornar o saldo da conta de teste
```

#### 3. Fazer um Saque
```bash
curl -X POST http://localhost:9501/account/550e8400-e29b-41d4-a716-446655440000/balance/withdraw \
  -H "Content-Type: application/json" \
  -d '{"method":"pix","amount":10.00,"pix_key":"teste@email.com"}'
# Deve processar o saque com sucesso
```

#### 4. Verificar Mailhog (Email)
Abra no navegador: http://localhost:8025
Deve mostrar os emails de notificação de saque.

---

### Testes Completos

Para testar **todas as funcionalidades** do sistema de forma detalhada, consulte o:

**[TESTE_MANUAL.md](TESTE_MANUAL.md)** - Guia Completo de Testes

Este guia contém:
- 15 testes passo a passo
- Comandos prontos para copiar e colar
- Resultados esperados para cada teste
- Testes de saque imediato
- Testes de saque agendado
- Testes de validação (saldo insuficiente, conta inexistente)
- Testes de concorrência
- Testes de cron job
- Verificação de emails

**Recomendação:** Execute os testes do TESTE_MANUAL.md para validar que o sistema está 100% funcional.

---

## Troubleshooting

### Problema: "make: command not found"
**Solução Windows:**
```bash
# Instalar Make no Windows via Chocolatey
choco install make

# OU usar comandos Docker diretamente (sem Makefile)
docker-compose up -d
```

### Problema: "Port 9501 already in use"
**Solução:**
```bash
# Parar o que está usando a porta
docker-compose down

# OU mudar a porta no docker-compose.yml
ports:
  - "9502:9501"  # Usar porta 9502 no host
```

### Problema: "MySQL connection refused"
**Solução:**
```bash
# Aguardar MySQL inicializar (pode levar 30 segundos)
docker-compose logs mysql

# Verificar se MySQL está pronto
docker-compose exec mysql mysqladmin ping -h localhost -uroot -proot
```

### Problema: "Composer dependencies not installed"
**Solução:**
```bash
# Instalar dependências manualmente
docker-compose exec app composer install
```

---

## Estrutura de Arquivos Essenciais

```
pix-withdrawal-microservice/
├── docker-compose.yml          # OBRIGATÓRIO - Orquestra containers
├── Dockerfile                  # OBRIGATÓRIO - Build da aplicação
├── Makefile                    # RECOMENDADO - Automatiza comandos
├── composer.json               # OBRIGATÓRIO - Dependências PHP
├── .env                        # Configurações (já vem pronto)
├── .env.example                # Template de config
├── README.md                   # Documentação principal
├── TESTE_MANUAL.md             # Guia de testes
│
├── app/                        # Código da aplicação
│   ├── Controller/
│   ├── Service/
│   ├── Repository/
│   ├── Model/
│   └── ...
│
├── config/                     # Configurações Hyperf
├── migrations/                 # Migrations do banco
├── seeders/                    # Dados de teste
├── test/                       # Testes automatizados
└── vendor/                     # NÃO COMMITAR - Gerado pelo Composer
```

---

## Resumo para Quem Vai Pegar o Projeto

### O que você precisa ter instalado:
1. **Docker** (versão 20+)
2. **Docker Compose** (versão 1.29+)
3. **Make** (opcional, mas recomendado)
4. **Git** (para clonar o repositório)

### Comandos para começar:
```bash
# 1. Clonar
git clone <url>
cd pix-withdrawal-microservice

# 2. Iniciar (escolha uma opção)
make setup                    # Com Makefile (recomendado)
# OU
docker-compose up -d          # Sem Makefile

# 3. Testar
curl http://localhost:9501/health
```

### Arquivos que você VAI ENCONTRAR no repositório:
- Todos os arquivos de código
- docker-compose.yml
- Dockerfile
- Makefile
- composer.json
- `.env` - **Já vem pronto com configurações padrão!**
- `.env.example` - Template para referência
- Documentação completa

### Arquivos que você NÃO PRECISA CRIAR:
- `.env` - **Já vem no repositório!**

### Arquivos que serão GERADOS automaticamente:
- `vendor/` (dependências PHP)
- `runtime/` (logs e cache)
- Volumes Docker (banco de dados)

---

## Serviços Disponíveis

Após executar `make setup`, você terá acesso aos seguintes serviços:

| Serviço | URL | Descrição |
|---------|-----|-----------|
| **API** | http://localhost:9501 | Aplicação principal (Hyperf) |
| **Mailhog UI** | http://localhost:8025 | Interface web para visualizar emails |
| **MySQL** | localhost:3306 | Banco de dados (usuário: root, senha: root) |
| **Redis** | localhost:6379 | Cache e sessões |

---

## Dicas Finais

1. **Use o Makefile** - Facilita muito o desenvolvimento
2. **Execute os testes** - Veja [TESTE_MANUAL.md](TESTE_MANUAL.md) para validar o sistema
3. **Verifique os logs** - `make logs` mostra tudo que está acontecendo
4. **Mailhog é seu amigo** - http://localhost:8025 para ver emails de teste
5. **Health check sempre** - `curl http://localhost:9501/health` para verificar status
6. **Acesse o MySQL** - `make mysql` para entrar no console do banco
7. **Resete quando precisar** - `make fresh` reseta o banco de dados

---

## Próximos Passos

Após o setup, recomendamos:

1. **Executar os testes** - [TESTE_MANUAL.md](TESTE_MANUAL.md)
2. **Ler a documentação** - [README.md](README.md)
3. **Explorar o código** - Comece por `app/Controller/AccountController.php`
4. **Ver os logs** - `make logs` para entender o fluxo
5. **Testar a API** - Use Postman ou curl

---

**Pronto! Com este guia, qualquer pessoa consegue executar o projeto do zero.**
