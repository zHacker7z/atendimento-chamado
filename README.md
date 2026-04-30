# Sistema de Controle de Atendimentos

Projeto fullstack com backend em PHP 8+, PostgreSQL e frontend em Vue 3 (Vite + Composition API).

## Estrutura

- `api/`: API REST em PHP puro.
- `api/database/schema.sql`: script de criação das tabelas.
- `frontend/`: interface Vue.js.

## Banco de dados (PostgreSQL)

1. Crie um banco chamado `atendimento`.
2. Execute o script:

```sql
\i api/database/schema.sql
```

3. (Opcional) Popule dados iniciais para homologação:

```sql
\i api/database/seed.sql
```

## Backend (PHP)

1. Configure variáveis de ambiente (copie `api/.env.example` para `.env` no seu ambiente de execução).
2. Defina:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
3. Suba o servidor PHP na pasta `api`:

```bash
php -S localhost:8000 -t api
```

## Frontend (Vue + Vite)

1. No `frontend`, configure o endpoint da API:

```bash
# frontend/.env
VITE_API_URL=http://localhost:8000
```

Se não definir `VITE_API_URL`, o frontend usa `/api` com proxy do Vite para `http://127.0.0.1:8000`.

2. Execute:

```bash
npm install
npm run dev
```

## Regras implementadas

- Cadastro de setores e prioridades.
- Abertura de chamado com status inicial fixo em `Aberto`.
- Check-in:
  - define `data_inicio_atendimento`;
  - bloqueia chamados `Finalizado` e `Cancelado`;
  - evita check-in duplicado.
- Check-out:
  - exige chamado já iniciado;
  - exige `solucao_breve`;
  - define `data_finalizacao` e status `Finalizado`.
- Cancelamento:
  - exige `motivo_cancelamento`;
  - bloqueia cancelamento de chamados `Finalizado` e `Cancelado`;
  - define status `Cancelado` e registra o motivo.
- SLA:
  - calculado no backend em horas (`sla_horas`);
  - usa `data_inicio_atendimento` quando existir;
  - para chamados abertos, considera tempo até o momento atual.
- Filtros na listagem:
  - `GET /chamados?status=...`
  - `GET /chamados?setor_id=...`
  - `GET /chamados?prioridade_id=...`
  - combináveis entre si.
- Paginação e ordenação:
  - `GET /chamados?page=1&limit=10`
  - `GET /chamados?order_by=data_abertura&order_dir=DESC`
  - `order_by`: `id`, `status`, `data_abertura`, `data_inicio_atendimento`, `data_finalizacao`, `setor`, `prioridade`.

## Endpoints

- `GET /setores`
- `POST /setores`
- `GET /prioridades`
- `POST /prioridades`
- `GET /chamados`
- `POST /chamados`
- `POST /chamados/{id}/iniciar`
- `POST /chamados/{id}/finalizar`
- `POST /chamados/{id}/cancelar`
