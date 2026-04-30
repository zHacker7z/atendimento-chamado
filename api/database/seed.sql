INSERT INTO setores (nome)
VALUES
    ('TI'),
    ('Financeiro'),
    ('RH'),
    ('Operações')
ON CONFLICT (nome) DO NOTHING;

INSERT INTO prioridades (descricao, tempo_estimado_horas)
VALUES
    ('Baixa', 24),
    ('Média', 8),
    ('Alta', 4),
    ('Crítica', 1)
ON CONFLICT (descricao) DO NOTHING;

INSERT INTO chamados (
    setor_id,
    prioridade_id,
    descricao_problema,
    status,
    data_abertura,
    data_inicio_atendimento,
    data_finalizacao,
    solucao_breve
)
SELECT
    s.id,
    p.id,
    'Erro de autenticação no sistema interno',
    'Finalizado',
    NOW() - INTERVAL '6 hours',
    NOW() - INTERVAL '5 hours',
    NOW() - INTERVAL '4 hours',
    'Reset de cache e sincronização de credenciais'
FROM setores s
INNER JOIN prioridades p ON p.descricao = 'Alta'
WHERE s.nome = 'TI'
  AND NOT EXISTS (
      SELECT 1
      FROM chamados c
      WHERE c.descricao_problema = 'Erro de autenticação no sistema interno'
  );

INSERT INTO chamados (
    setor_id,
    prioridade_id,
    descricao_problema,
    status,
    data_abertura
)
SELECT
    s.id,
    p.id,
    'Solicitação de criação de usuário para novo colaborador',
    'Aberto',
    NOW() - INTERVAL '2 hours'
FROM setores s
INNER JOIN prioridades p ON p.descricao = 'Média'
WHERE s.nome = 'RH'
  AND NOT EXISTS (
      SELECT 1
      FROM chamados c
      WHERE c.descricao_problema = 'Solicitação de criação de usuário para novo colaborador'
  );

INSERT INTO chamados (
    setor_id,
    prioridade_id,
    descricao_problema,
    status,
    data_abertura,
    data_inicio_atendimento
)
SELECT
    s.id,
    p.id,
    'Instabilidade no relatório de fechamento diário',
    'Em Atendimento',
    NOW() - INTERVAL '3 hours',
    NOW() - INTERVAL '2 hours'
FROM setores s
INNER JOIN prioridades p ON p.descricao = 'Crítica'
WHERE s.nome = 'Financeiro'
  AND NOT EXISTS (
      SELECT 1
      FROM chamados c
      WHERE c.descricao_problema = 'Instabilidade no relatório de fechamento diário'
  );
