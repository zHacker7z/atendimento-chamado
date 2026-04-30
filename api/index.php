<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Response.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function badRequest(string $message): void
{
    Response::json(['error' => $message], 400);
    exit;
}

function notFound(string $message = 'Rota não encontrada.'): void
{
    Response::json(['error' => $message], 404);
    exit;
}

function normalizePath(string $uri): string
{
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';
    $path = rtrim($path, '/');
    return $path === '' ? '/' : $path;
}

function getAllowedStatus(string $status): ?string
{
    $allowed = ['Aberto', 'Em Atendimento', 'Finalizado', 'Cancelado'];
    return in_array($status, $allowed, true) ? $status : null;
}

function getOrderByClause(string $orderBy): string
{
    $allowed = [
        'id' => 'c.id',
        'status' => 'c.status',
        'data_abertura' => 'c.data_abertura',
        'data_inicio_atendimento' => 'c.data_inicio_atendimento',
        'data_finalizacao' => 'c.data_finalizacao',
        'setor' => 's.nome',
        'prioridade' => 'p.descricao',
    ];

    return $allowed[$orderBy] ?? 'c.data_abertura';
}

try {
    $pdo = Database::getConnection();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = normalizePath($_SERVER['REQUEST_URI'] ?? '/');

    if ($method === 'GET' && $path === '/setores') {
        $stmt = $pdo->query('SELECT id, nome FROM setores ORDER BY nome ASC');
        Response::json(['data' => $stmt->fetchAll()]);
        exit;
    }

    if ($method === 'POST' && $path === '/setores') {
        $body = readJsonBody();
        $nome = trim((string)($body['nome'] ?? ''));
        if ($nome === '') {
            badRequest('O nome do setor é obrigatório.');
        }

        $stmt = $pdo->prepare('INSERT INTO setores (nome) VALUES (:nome) RETURNING id, nome');
        $stmt->execute([':nome' => $nome]);
        Response::json(['data' => $stmt->fetch()], 201);
        exit;
    }

    if ($method === 'GET' && $path === '/prioridades') {
        $stmt = $pdo->query('SELECT id, descricao, tempo_estimado_horas FROM prioridades ORDER BY tempo_estimado_horas ASC');
        Response::json(['data' => $stmt->fetchAll()]);
        exit;
    }

    if ($method === 'POST' && $path === '/prioridades') {
        $body = readJsonBody();
        $descricao = trim((string)($body['descricao'] ?? ''));
        $tempo = (int)($body['tempo_estimado_horas'] ?? 0);

        if ($descricao === '') {
            badRequest('A descrição da prioridade é obrigatória.');
        }
        if ($tempo <= 0) {
            badRequest('tempo_estimado_horas deve ser maior que zero.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO prioridades (descricao, tempo_estimado_horas)
             VALUES (:descricao, :tempo)
             RETURNING id, descricao, tempo_estimado_horas'
        );
        $stmt->execute([':descricao' => $descricao, ':tempo' => $tempo]);
        Response::json(['data' => $stmt->fetch()], 201);
        exit;
    }

    if ($method === 'GET' && $path === '/chamados') {
        $status = trim((string)($_GET['status'] ?? ''));
        $setorId = (int)($_GET['setor_id'] ?? 0);
        $prioridadeId = (int)($_GET['prioridade_id'] ?? 0);
        $page = max((int)($_GET['page'] ?? 1), 1);
        $limit = max((int)($_GET['limit'] ?? 10), 1);
        $limit = min($limit, 100);
        $offset = ($page - 1) * $limit;
        $orderBy = trim((string)($_GET['order_by'] ?? 'data_abertura'));
        $orderDir = strtoupper(trim((string)($_GET['order_dir'] ?? 'DESC')));
        $orderDir = $orderDir === 'ASC' ? 'ASC' : 'DESC';
        $orderBySql = getOrderByClause($orderBy);

        $where = [];
        $params = [];

        if ($status !== '') {
            $statusNormalizado = getAllowedStatus($status);
            if ($statusNormalizado === null) {
                badRequest('Status de filtro inválido.');
            }
            $where[] = 'c.status = :status';
            $params[':status'] = $statusNormalizado;
        }

        if ($setorId > 0) {
            $where[] = 'c.setor_id = :setor_id';
            $params[':setor_id'] = $setorId;
        }

        if ($prioridadeId > 0) {
            $where[] = 'c.prioridade_id = :prioridade_id';
            $params[':prioridade_id'] = $prioridadeId;
        }

        $sql = "SELECT
                c.id,
                c.setor_id,
                c.prioridade_id,
                c.descricao_problema,
                c.status,
                c.data_abertura,
                c.data_inicio_atendimento,
                c.data_finalizacao,
                c.solucao_breve,
                s.nome AS setor_nome,
                p.descricao AS prioridade_descricao,
                p.tempo_estimado_horas,
                ROUND(
                    EXTRACT(EPOCH FROM (
                        COALESCE(c.data_finalizacao, NOW()) -
                        COALESCE(c.data_inicio_atendimento, c.data_abertura)
                    )) / 3600.0,
                    2
                ) AS sla_horas
            FROM chamados c
            INNER JOIN setores s ON s.id = c.setor_id
            INNER JOIN prioridades p ON p.id = c.prioridade_id
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $countSql = 'SELECT COUNT(*) AS total FROM chamados c';
        if ($where !== []) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql .= " ORDER BY {$orderBySql} {$orderDir} LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        Response::json([
            'data' => $stmt->fetchAll(),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int)ceil($total / $limit),
                'order_by' => $orderBy,
                'order_dir' => $orderDir,
            ],
        ]);
        exit;
    }

    if ($method === 'POST' && $path === '/chamados') {
        $body = readJsonBody();
        $setorId = (int)($body['setor_id'] ?? 0);
        $prioridadeId = (int)($body['prioridade_id'] ?? 0);
        $descricao = trim((string)($body['descricao_problema'] ?? ''));

        if ($setorId <= 0 || $prioridadeId <= 0 || $descricao === '') {
            badRequest('setor_id, prioridade_id e descricao_problema são obrigatórios.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO chamados (
                setor_id, prioridade_id, descricao_problema, status, data_abertura
            ) VALUES (
                :setor_id, :prioridade_id, :descricao_problema, :status, NOW()
            )
            RETURNING id, setor_id, prioridade_id, descricao_problema, status, data_abertura'
        );
        $stmt->execute([
            ':setor_id' => $setorId,
            ':prioridade_id' => $prioridadeId,
            ':descricao_problema' => $descricao,
            ':status' => 'Aberto',
        ]);

        Response::json(['data' => $stmt->fetch()], 201);
        exit;
    }

    if ($method === 'POST' && preg_match('#^/chamados/(\d+)/iniciar$#', $path, $matches) === 1) {
        $id = (int)$matches[1];
        $stmt = $pdo->prepare('SELECT id, status, data_inicio_atendimento FROM chamados WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $chamado = $stmt->fetch();

        if (!$chamado) {
            notFound('Chamado não encontrado.');
        }

        if (in_array($chamado['status'], ['Finalizado', 'Cancelado'], true)) {
            badRequest('Não é possível iniciar um chamado finalizado ou cancelado.');
        }

        if ($chamado['data_inicio_atendimento'] !== null) {
            badRequest('Este chamado já foi iniciado.');
        }

        $update = $pdo->prepare(
            "UPDATE chamados
             SET status = 'Em Atendimento', data_inicio_atendimento = NOW()
             WHERE id = :id
             RETURNING id, status, data_inicio_atendimento"
        );
        $update->execute([':id' => $id]);
        Response::json(['data' => $update->fetch()]);
        exit;
    }

    if ($method === 'POST' && preg_match('#^/chamados/(\d+)/finalizar$#', $path, $matches) === 1) {
        $id = (int)$matches[1];
        $body = readJsonBody();
        $solucao = trim((string)($body['solucao_breve'] ?? ''));

        if ($solucao === '') {
            badRequest('A solucao_breve é obrigatória para finalizar.');
        }

        $stmt = $pdo->prepare('SELECT id, status, data_inicio_atendimento FROM chamados WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $chamado = $stmt->fetch();

        if (!$chamado) {
            notFound('Chamado não encontrado.');
        }

        if ($chamado['data_inicio_atendimento'] === null) {
            badRequest('Não é possível finalizar um chamado sem check-in.');
        }

        if (in_array($chamado['status'], ['Finalizado', 'Cancelado'], true)) {
            badRequest('Este chamado não pode mais ser finalizado.');
        }

        $update = $pdo->prepare(
            "UPDATE chamados
             SET status = 'Finalizado', data_finalizacao = NOW(), solucao_breve = :solucao
             WHERE id = :id
             RETURNING id, status, data_finalizacao, solucao_breve"
        );
        $update->execute([':id' => $id, ':solucao' => $solucao]);
        Response::json(['data' => $update->fetch()]);
        exit;
    }

    if ($method === 'POST' && preg_match('#^/chamados/(\d+)/cancelar$#', $path, $matches) === 1) {
        $id = (int)$matches[1];
        $body = readJsonBody();
        $motivo = trim((string)($body['motivo_cancelamento'] ?? ''));

        if ($motivo === '') {
            badRequest('O motivo_cancelamento é obrigatório.');
        }

        $stmt = $pdo->prepare('SELECT id, status FROM chamados WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $chamado = $stmt->fetch();

        if (!$chamado) {
            notFound('Chamado não encontrado.');
        }

        if (in_array($chamado['status'], ['Finalizado', 'Cancelado'], true)) {
            badRequest('Este chamado não pode mais ser cancelado.');
        }

        $update = $pdo->prepare(
            "UPDATE chamados
             SET status = 'Cancelado',
                 data_finalizacao = NOW(),
                 solucao_breve = :motivo
             WHERE id = :id
             RETURNING id, status, data_finalizacao, solucao_breve"
        );
        $update->execute([
            ':id' => $id,
            ':motivo' => 'Cancelado: ' . $motivo,
        ]);

        Response::json(['data' => $update->fetch()]);
        exit;
    }

    notFound();
} catch (PDOException $exception) {
    $code = (int)$exception->getCode();
    if ($code === 23505) {
        Response::json(['error' => 'Registro duplicado.'], 409);
        exit;
    }

    Response::json(['error' => 'Erro de banco de dados.', 'details' => $exception->getMessage()], 500);
} catch (Throwable $exception) {
    Response::json(['error' => 'Erro interno.', 'details' => $exception->getMessage()], 500);
}
