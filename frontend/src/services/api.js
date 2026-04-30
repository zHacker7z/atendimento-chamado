const API_BASE_URL = import.meta.env.VITE_API_URL ?? '/api';

async function request(path, options = {}) {
  let response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      headers: {
        'Content-Type': 'application/json',
        ...(options.headers ?? {}),
      },
      ...options,
    });
  } catch {
    throw new Error('Falha de conexão com a API. Verifique se o backend está ativo na porta 8000.');
  }

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || 'Erro na requisição.');
  }
  return payload.data;
}

async function requestWithMeta(path, options = {}) {
  let response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      headers: {
        'Content-Type': 'application/json',
        ...(options.headers ?? {}),
      },
      ...options,
    });
  } catch {
    throw new Error('Falha de conexão com a API. Verifique se o backend está ativo na porta 8000.');
  }

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || 'Erro na requisição.');
  }
  return payload;
}

export function listarSetores() {
  return request('/setores');
}

export function criarSetor(data) {
  return request('/setores', { method: 'POST', body: JSON.stringify(data) });
}

export function listarPrioridades() {
  return request('/prioridades');
}

export function criarPrioridade(data) {
  return request('/prioridades', { method: 'POST', body: JSON.stringify(data) });
}

export function listarChamados(filters = {}) {
  const query = new URLSearchParams();
  if (filters.status) query.set('status', filters.status);
  if (filters.setor_id) query.set('setor_id', String(filters.setor_id));
  if (filters.prioridade_id) query.set('prioridade_id', String(filters.prioridade_id));
  if (filters.page) query.set('page', String(filters.page));
  if (filters.limit) query.set('limit', String(filters.limit));
  if (filters.order_by) query.set('order_by', filters.order_by);
  if (filters.order_dir) query.set('order_dir', filters.order_dir);

  const qs = query.toString();
  return requestWithMeta(`/chamados${qs ? `?${qs}` : ''}`);
}

export function abrirChamado(data) {
  return request('/chamados', { method: 'POST', body: JSON.stringify(data) });
}

export function iniciarChamado(id) {
  return request(`/chamados/${id}/iniciar`, { method: 'POST' });
}

export function finalizarChamado(id, data) {
  return request(`/chamados/${id}/finalizar`, { method: 'POST', body: JSON.stringify(data) });
}

export function cancelarChamado(id, data) {
  return request(`/chamados/${id}/cancelar`, { method: 'POST', body: JSON.stringify(data) });
}
