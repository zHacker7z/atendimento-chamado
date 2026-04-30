CREATE TABLE IF NOT EXISTS setores (
    id BIGSERIAL PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS prioridades (
    id BIGSERIAL PRIMARY KEY,
    descricao VARCHAR(120) NOT NULL UNIQUE,
    tempo_estimado_horas INTEGER NOT NULL CHECK (tempo_estimado_horas > 0)
);

CREATE TABLE IF NOT EXISTS chamados (
    id BIGSERIAL PRIMARY KEY,
    setor_id BIGINT NOT NULL REFERENCES setores(id),
    prioridade_id BIGINT NOT NULL REFERENCES prioridades(id),
    descricao_problema TEXT NOT NULL,
    status VARCHAR(30) NOT NULL CHECK (
        status IN ('Aberto', 'Em Atendimento', 'Finalizado', 'Cancelado')
    ),
    data_abertura TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_inicio_atendimento TIMESTAMPTZ NULL,
    data_finalizacao TIMESTAMPTZ NULL,
    solucao_breve TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_chamados_status ON chamados(status);
CREATE INDEX IF NOT EXISTS idx_chamados_setor_id ON chamados(setor_id);
CREATE INDEX IF NOT EXISTS idx_chamados_prioridade_id ON chamados(prioridade_id);
CREATE INDEX IF NOT EXISTS idx_chamados_data_abertura ON chamados(data_abertura DESC);
