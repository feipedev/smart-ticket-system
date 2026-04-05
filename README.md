# Smart Ticket System — OS Manager

**API RESTful para gerenciamento de Ordens de Serviço**  
Stack: PHP 8.2 | MVC | Repository Pattern | JWT Auth | SQLite | Zero custo de deploy

---

## 🎯 Por que este projeto existe

Este sistema nasceu de experiência real do mercado: gerenciar ordens de serviço de manutenção de máquinas de refrigeração exige um tracking preciso de problemas, prioridades e resolução. Este projeto implementa essa necessidade usando **arquitetura de software moderna**, demonstrando capacidade de construir sistemas reais com padrões de mercado.

---

## 🏗️ Arquitetura

```
php-ticket-system/
├── public/
│   └── index.php          # Front Controller (único ponto de entrada)
├── src/
│   ├── Config/
│   │   └── Database.php   # Singleton PDO com auto-migração
│   ├── Controllers/
│   │   ├── AuthController.php    # Register, Login, Me
│   │   └── TicketController.php  # CRUD completo de Tickets
│   ├── Interfaces/
│   │   └── RepositoryInterface.php  # Contrato genérico (SOLID DIP)
│   ├── Middleware/
│   │   └── JwtAuth.php    # Autenticação Bearer JWT + RBAC
│   ├── Models/
│   │   └── Ticket.php     # Entidade de domínio (readonly, validação)
│   ├── Repositories/
│   │   └── TicketRepository.php  # Acesso a dados (PDO + SQLite)
│   └── Routes/
│       └── api.php        # Roteador manual com params dinâmicos
├── database/
│   ├── schema.sql         # DDL + índices + triggers + seed
│   └── tickets.sqlite     # Criado automaticamente na 1ª execução
├── composer.json
└── README.md
```

### Design Patterns Aplicados

| Pattern | Onde | Benefício |
|---|---|---|
| **MVC** | Controllers/Models/Repositories | Separação de responsabilidades |
| **Repository Pattern** | TicketRepository implements RepositoryInterface | Troca de banco sem alterar Controllers |
| **Front Controller** | public/index.php | Único ponto de entrada, centraliza headers/CORS |
| **Singleton** | Database.php | Uma única conexão PDO por ciclo de vida |
| **Dependency Injection** | TicketController recebe TicketRepository | Testabilidade e desacoplamento |
| **RBAC** | JwtAuth::requireRole() | Controle de acesso por papel (user/admin) |

---

## 🚀 Executando Localmente

### Pré-requisitos
- PHP 8.2+
- Composer

### Instalação
```bash
# Clone o repositório
git clone https://github.com/felipecorrea/smart-ticket-system

# Instale dependências
cd smart-ticket-system
composer install

# Configure variáveis de ambiente
cp .env.example .env
# Edite .env com seu JWT_SECRET

# Inicie o servidor de desenvolvimento
composer serve
# → http://localhost:8080
```

O banco SQLite é criado automaticamente na primeira requisição. Nenhuma configuração de banco necessária.

---

## 📡 Endpoints da API

### Autenticação

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/api/auth/register` | Cria nova conta |
| `POST` | `/api/auth/login` | Login — retorna JWT |
| `GET` | `/api/auth/me` | Perfil do usuário autenticado |
| `GET` | `/api/health` | Health check |

### Tickets (requer `Authorization: Bearer <token>`)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/tickets` | Lista tickets (paginado) |
| `GET` | `/api/tickets?status=open&priority=high` | Lista com filtros |
| `POST` | `/api/tickets` | Cria novo ticket |
| `GET` | `/api/tickets/{id}` | Detalhe de um ticket |
| `PUT` | `/api/tickets/{id}` | Atualiza ticket |
| `DELETE` | `/api/tickets/{id}` | Remove ticket *(admin only)* |

### Exemplos cURL

```bash
# 1. Login
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@ticketsystem.local","password":"Admin@1234"}'

# 2. Criar ticket (use o token retornado)
curl -X POST http://localhost:8080/api/tickets \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Samsung - não centrifuga",
    "description": "Motor gira mas para ao atingir velocidade máxima.",
    "priority": "high",
    "category": "Centrifugação"
  }'

# 3. Listar tickets abertos
curl http://localhost:8080/api/tickets?status=open \
  -H "Authorization: Bearer SEU_TOKEN"
```

---

## 🔐 Segurança

- **Senhas**: Argon2ID (mais seguro que bcrypt)
- **JWT**: HS256, expira em 8h, inclui `iat`/`nbf`/`exp`/`iss`
- **SQL Injection**: Prevenido com PDO Prepared Statements em 100% das queries
- **RBAC**: Rotas de delete exigem role `admin`
- **Headers**: `X-Content-Type-Options`, `X-Frame-Options` configurados
- **CORS**: Configurável via Front Controller

---

## 🧪 Testes

```bash
# Executa suite de testes (PHPUnit)
composer test
```

---

## 📦 Variáveis de Ambiente

```env
APP_ENV=development
JWT_SECRET=sua_chave_secreta_com_minimo_32_chars
DB_PATH=/caminho/para/database/tickets.sqlite
```

---

## 👨‍💻 Autor

**Felipe Correa** — Dev Pleno PHP/Python  
📧 felipediasdev8@gmail.com

