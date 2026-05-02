# Sistema de Controle de Atendimentos

Este é um sistema simples e funcional para gestão de chamados de suporte. Ele combina um backend em PHP com um frontend moderno em Vue 3, permitindo controlar setor, prioridade, SLA e fluxo de atendimento.

## Tecnologias usadas

- Backend: PHP 8+ (API REST em PHP puro)
- Banco de dados: PostgreSQL
- Frontend: Vue 3 + Vite
- UI/CSS: Tailwind CSS e estilos personalizados
- Comunicação: Fetch API via endpoints REST
- Ambiente de desenvolvimento: npm, Vite e servidor PHP embutido

## Como o sistema funciona

O sistema permite:

- cadastrar setores e prioridades;
- abrir chamados com setor, prioridade e descrição do problema;
- iniciar o atendimento de um chamado aberto;
- finalizar chamados já em atendimento;
- cancelar chamados abertos ou em atendimento;
- calcular SLA em horas e identificar chamados atrasados;
- filtrar e paginar a lista de chamados.

No frontend, o usuário vê uma lista de chamados e pode abrir os detalhes do chamado com um duplo clique. No modal de detalhes é possível iniciar atendimento, salvar solução, finalizar ou cancelar o chamado.

## Estrutura do projeto

- `api/` – backend em PHP com endpoints REST.
- `api/database/schema.sql` – script para criar as tabelas.
- `api/database/seed.sql` – dados iniciais para teste.
- `frontend/` – aplicação Vue 3 usando Vite.

## Pré-requisitos

Antes de executar o sistema, instale estes itens:

- PHP 8+
- PostgreSQL
- Node.js 18+ (ou versão compatível com Vite)
- npm

## Passo a passo para rodar localmente

### 1. Configure o banco de dados

1. Crie um banco PostgreSQL chamado `atendimento`.
2. Execute o script de criação das tabelas:

```sql
\i api/database/schema.sql
```

3. (Opcional) Popule dados de exemplo:

```sql
\i api/database/seed.sql
```

### 2. Configure o backend

1. Vá para a pasta do backend:

```bash
cd api
```

2. Copie o arquivo de ambiente de exemplo:

```bash
copy .env.example .env
```

3. Atualize o arquivo `.env` com as credenciais do PostgreSQL:

```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=atendimento
DB_USER=seu_usuario
DB_PASS=sua_senha
```

4. Inicie o servidor PHP:

```bash
php -S localhost:8000 -t api
```

Se tudo estiver correto, a API ficará disponível em `http://localhost:8000`.

### 3. Configure o frontend

1. Entre na pasta do frontend:

```bash
cd frontend
```

2. Instale as dependências:

```bash
npm install
```

3. Crie ou edite o arquivo `.env` com o URL da API:

```env
VITE_API_URL=http://localhost:8000
```

4. Inicie a aplicação frontend:

```bash
npm run dev
```

5. Abra o navegador no endereço exibido pelo Vite (geralmente `http://localhost:5173`).

> Se não configurar `VITE_API_URL`, o frontend usa `/api` e o proxy do Vite redireciona para `http://127.0.0.1:8000`.

## Uso básico do sistema

1. Abra o frontend no navegador.
2. Crie setores e prioridades antes de abrir chamados.
3. Abra um novo chamado selecionando setor, prioridade e descrevendo o problema.
4. Na lista de chamados, clique duas vezes em um item para abrir o modal de detalhes.
5. Inicie atendimento, registre solução ou cancele quando necessário.
6. O SLA é calculado automaticamente e os chamados atrasados são destacados.

## Regras principais implementadas

- Chamado aberto inicia com status `Aberto`.
- Você só pode iniciar atendimento em chamados `Aberto`.
- Somente chamados em `Em Atendimento` podem ser finalizados.
- Chamados `Finalizado` ou `Cancelado` não podem ser reabertos.
- Cancelamento exige motivo e só funciona em chamados abertos ou em atendimento.
- SLA é calculado em horas no backend e atualizado conforme o chamado está aberto.

## Endpoints disponíveis

- `GET /setores`
- `POST /setores`
- `GET /prioridades`
- `POST /prioridades`
- `GET /chamados`
- `POST /chamados`
- `POST /chamados/{id}/iniciar`
- `POST /chamados/{id}/finalizar`
- `POST /chamados/{id}/cancelar`
- `POST /chamados/{id}/solucao`

Este projeto é uma base de sistema de atendimento com foco em praticidade e controle. Ele pode ser estendido com autenticação, relatórios, notificações e mais validações de negócios.e
