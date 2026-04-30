<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
  abrirChamado,
  cancelarChamado,
  criarPrioridade,
  criarSetor,
  finalizarChamado,
  iniciarChamado,
  listarChamados,
  listarPrioridades,
  listarSetores,
  obterIndicadores,
  salvarSolucaoChamado,
} from './services/api';

const appName = import.meta.env.VITE_APP_NAME ?? 'Sistema de Controle de Atendimentos';

const loading = ref(false);
const feedback = reactive({
  show: false,
  type: 'success',
  text: '',
});

const setores = ref([]);
const prioridades = ref([]);
const chamados = ref([]);
const metaChamados = reactive({
  page: 1,
  limit: 10,
  total: 0,
  total_pages: 0,
  order_by: 'data_abertura',
  order_dir: 'DESC',
});
const indicadores = reactive({
  total_aberto: 0,
  total_em_atendimento: 0,
  total_atrasados: 0,
});
const solucaoPorChamado = reactive({});
const motivoCancelamentoPorChamado = reactive({});
const temaEscuro = ref(false);
const detalheModal = reactive({
  show: false,
  chamado: null,
  solucao: '',
  motivo: '',
});
const confirmModal = reactive({
  show: false,
  title: '',
  text: '',
  loading: false,
  action: null,
});

const filtros = reactive({
  status: '',
  setor_id: '',
  prioridade_id: '',
});

const novoSetor = reactive({ nome: '' });
const novaPrioridade = reactive({ descricao: '', tempo_estimado_horas: 1 });
const novoChamado = reactive({
  setor_id: '',
  prioridade_id: '',
  descricao_problema: '',
});

const loadingState = reactive({
  setor: false,
  prioridade: false,
  chamado: false,
  filtro: false,
  checkin: {},
  checkout: {},
  cancelar: {},
  solucao: {},
});

const semChamados = computed(() => !loading.value && chamados.value.length === 0);

let feedbackTimer = null;

function mostrarFeedback(texto, tipo = 'success') {
  if (feedbackTimer) clearTimeout(feedbackTimer);
  feedback.show = true;
  feedback.type = tipo;
  feedback.text = texto;
  feedbackTimer = setTimeout(() => {
    feedback.show = false;
  }, 2800);
}

function aplicarTema() {
  document.documentElement.setAttribute('data-theme', temaEscuro.value ? 'dark' : 'light');
  localStorage.setItem('tema-atendimento', temaEscuro.value ? 'dark' : 'light');
}

function alternarTema() {
  temaEscuro.value = !temaEscuro.value;
  aplicarTema();
}

function abrirConfirmacao(title, text, action) {
  confirmModal.show = true;
  confirmModal.title = title;
  confirmModal.text = text;
  confirmModal.action = action;
}

function abrirDetalheChamado(chamado) {
  detalheModal.show = true;
  detalheModal.chamado = { ...chamado };
  detalheModal.solucao = chamado.solucao_breve ?? '';
  detalheModal.motivo = '';
}

function fecharDetalheChamado() {
  detalheModal.show = false;
  detalheModal.chamado = null;
  detalheModal.solucao = '';
  detalheModal.motivo = '';
}

function fecharConfirmacao() {
  if (confirmModal.loading) return;
  confirmModal.show = false;
  confirmModal.title = '';
  confirmModal.text = '';
  confirmModal.action = null;
}

async function confirmarAcao() {
  if (!confirmModal.action) return;
  confirmModal.loading = true;
  try {
    await confirmModal.action();
    confirmModal.show = false;
  } finally {
    confirmModal.loading = false;
    confirmModal.action = null;
  }
}

async function carregarDados() {
  loading.value = true;
  try {
    const [setoresData, prioridadesData, chamadosResposta, indicadoresData] = await Promise.all([
      listarSetores(),
      listarPrioridades(),
      listarChamados({
        status: filtros.status,
        setor_id: filtros.setor_id ? Number(filtros.setor_id) : undefined,
        prioridade_id: filtros.prioridade_id ? Number(filtros.prioridade_id) : undefined,
        page: metaChamados.page,
        limit: metaChamados.limit,
        order_by: metaChamados.order_by,
        order_dir: metaChamados.order_dir,
      }),
      obterIndicadores(),
    ]);
    setores.value = setoresData;
    prioridades.value = prioridadesData;
    chamados.value = chamadosResposta.data ?? [];
    Object.assign(metaChamados, chamadosResposta.meta ?? {});
    Object.assign(indicadores, indicadoresData ?? {});
    if (detalheModal.show && detalheModal.chamado) {
      const atualizado = chamados.value.find((item) => item.id === detalheModal.chamado.id);
      if (atualizado) {
        detalheModal.chamado = { ...atualizado };
      }
    }
  } catch (e) {
    mostrarFeedback(e.message, 'error');
  } finally {
    loading.value = false;
    loadingState.filtro = false;
  }
}

function aplicarFiltros() {
  loadingState.filtro = true;
  metaChamados.page = 1;
  carregarDados();
}

async function salvarSetor() {
  const nome = novoSetor.nome.trim();
  if (!nome) {
    mostrarFeedback('Informe o nome do setor.', 'error');
    return;
  }
  loadingState.setor = true;
  try {
    await criarSetor({ nome });
    novoSetor.nome = '';
    mostrarFeedback('Setor cadastrado com sucesso.');
    await carregarDados();
  } catch (e) {
    mostrarFeedback(e.message, 'error');
  } finally {
    loadingState.setor = false;
  }
}

async function salvarPrioridade() {
  const descricao = novaPrioridade.descricao.trim();
  const tempo = Number(novaPrioridade.tempo_estimado_horas);
  if (!descricao) {
    mostrarFeedback('Informe a descrição da prioridade.', 'error');
    return;
  }
  if (!Number.isFinite(tempo) || tempo < 1) {
    mostrarFeedback('Tempo estimado deve ser maior que zero.', 'error');
    return;
  }
  loadingState.prioridade = true;
  try {
    await criarPrioridade({
      descricao,
      tempo_estimado_horas: tempo,
    });
    novaPrioridade.descricao = '';
    novaPrioridade.tempo_estimado_horas = 1;
    mostrarFeedback('Prioridade cadastrada com sucesso.');
    await carregarDados();
  } catch (e) {
    mostrarFeedback(e.message, 'error');
  } finally {
    loadingState.prioridade = false;
  }
}

async function salvarChamado() {
  if (!novoChamado.setor_id || !novoChamado.prioridade_id) {
    mostrarFeedback('Selecione setor e prioridade.', 'error');
    return;
  }
  const descricao = novoChamado.descricao_problema.trim();
  if (descricao.length < 5) {
    mostrarFeedback('A descrição do problema deve ter ao menos 5 caracteres.', 'error');
    return;
  }
  loadingState.chamado = true;
  try {
    await abrirChamado({
      setor_id: Number(novoChamado.setor_id),
      prioridade_id: Number(novoChamado.prioridade_id),
      descricao_problema: descricao,
    });
    novoChamado.setor_id = '';
    novoChamado.prioridade_id = '';
    novoChamado.descricao_problema = '';
    mostrarFeedback('Chamado aberto com sucesso.');
    await carregarDados();
  } catch (e) {
    mostrarFeedback(e.message, 'error');
  } finally {
    loadingState.chamado = false;
  }
}

async function fazerCheckIn(id) {
  loadingState.checkin[id] = true;
  try {
    await iniciarChamado(id);
    mostrarFeedback('Chamado iniciado com sucesso.');
    await carregarDados();
  } catch (e) {
    mostrarFeedback(e.message, 'error');
  } finally {
    loadingState.checkin[id] = false;
  }
}

async function salvarSolucao(id, textoSolucao) {
  const solucao = (textoSolucao ?? '').trim();
  if (solucao.length < 5) {
    mostrarFeedback('A solução deve ter ao menos 5 caracteres.', 'error');
    return;
  }
  loadingState.solucao[id] = true;
  try {
    await salvarSolucaoChamado(id, { solucao_breve: solucao });
    mostrarFeedback('Solução salva com sucesso.');
    await carregarDados();
  } catch (e) {
    mostrarFeedback(e.message, 'error');
  } finally {
    loadingState.solucao[id] = false;
  }
}

async function fazerCheckOut(id) {
  const executar = async () => {
    const solucaoFonte = detalheModal.show && detalheModal.chamado?.id === id
      ? detalheModal.solucao
      : solucaoPorChamado[id];
    const solucao = (solucaoFonte ?? '').trim();
    if (solucao.length < 5) {
      mostrarFeedback('Descreva a solução com ao menos 5 caracteres.', 'error');
      return;
    }
    loadingState.checkout[id] = true;
    await finalizarChamado(id, { solucao_breve: solucao });
    solucaoPorChamado[id] = '';
    mostrarFeedback('Finalizado com sucesso.');
    await carregarDados();
  };
  abrirConfirmacao('Finalizar chamado', 'Deseja realmente finalizar este chamado?', async () => {
    try {
      await executar();
    } catch (e) {
      mostrarFeedback(e.message, 'error');
    } finally {
      loadingState.checkout[id] = false;
    }
  });
}

async function cancelar(id) {
  const executar = async () => {
    const motivoFonte = detalheModal.show && detalheModal.chamado?.id === id
      ? detalheModal.motivo
      : motivoCancelamentoPorChamado[id];
    const motivo = (motivoFonte ?? '').trim();
    if (motivo.length < 5) {
      mostrarFeedback('Informe um motivo de cancelamento com ao menos 5 caracteres.', 'error');
      return;
    }
    loadingState.cancelar[id] = true;
    await cancelarChamado(id, { motivo_cancelamento: motivo });
    motivoCancelamentoPorChamado[id] = '';
    mostrarFeedback('Chamado cancelado com sucesso.');
    await carregarDados();
  };
  abrirConfirmacao('Cancelar chamado', 'Deseja realmente cancelar este chamado?', async () => {
    try {
      await executar();
    } catch (e) {
      mostrarFeedback(e.message, 'error');
    } finally {
      loadingState.cancelar[id] = false;
    }
  });
}

function limparFiltros() {
  filtros.status = '';
  filtros.setor_id = '';
  filtros.prioridade_id = '';
  metaChamados.page = 1;
  metaChamados.order_by = 'data_abertura';
  metaChamados.order_dir = 'DESC';
  carregarDados();
}

function paginaAnterior() {
  if (metaChamados.page <= 1) return;
  metaChamados.page -= 1;
  carregarDados();
}

function proximaPagina() {
  if (metaChamados.page >= metaChamados.total_pages) return;
  metaChamados.page += 1;
  carregarDados();
}

function formatarData(data) {
  if (!data) return '-';
  return new Date(data).toLocaleString('pt-BR');
}

function classeStatus(status) {
  if (status === 'Aberto') return 'status-aberto';
  if (status === 'Em Atendimento') return 'status-atendimento';
  if (status === 'Finalizado') return 'status-finalizado';
  return 'status-cancelado';
}

function slaAtrasado(chamado) {
  return Number(chamado.sla_horas) > Number(chamado.tempo_estimado_horas);
}

function formatarDuracaoHoras(valorHoras) {
  const totalMinutos = Math.max(Math.round(Number(valorHoras) * 60), 0);
  const horas = Math.floor(totalMinutos / 60);
  const minutos = totalMinutos % 60;
  return `${horas}h ${String(minutos).padStart(2, '0')}m`;
}

onMounted(() => {
  temaEscuro.value = localStorage.getItem('tema-atendimento') === 'dark';
  aplicarTema();
  carregarDados();
});
</script>

<template>
  <main class="container">
    <header class="page-header">
      <h1>{{ appName }}</h1>
      <p>Controle de SLA, status e fluxo de atendimento em um único painel.</p>
      <button type="button" class="theme-toggle" @click="alternarTema">
        <span aria-hidden="true">{{ temaEscuro ? '☀️' : '🌙' }}</span>
        {{ temaEscuro ? 'Modo claro' : 'Modo escuro' }}
      </button>
    </header>

    <div v-if="feedback.show" class="toast" :class="feedback.type === 'error' ? 'toast-erro' : 'toast-sucesso'">
      {{ feedback.text }}
    </div>

    <section class="kpis">
      <article class="kpi-card">
        <h3>Abertos</h3>
        <strong>{{ indicadores.total_aberto }}</strong>
      </article>
      <article class="kpi-card">
        <h3>Em atendimento</h3>
        <strong>{{ indicadores.total_em_atendimento }}</strong>
      </article>
      <article class="kpi-card">
        <h3>Atrasados</h3>
        <strong class="kpi-alert">{{ indicadores.total_atrasados }}</strong>
      </article>
    </section>

    <section class="grid section-space">
      <form class="card" @submit.prevent="salvarSetor">
        <h2>Cadastro de Setor</h2>
        <input v-model="novoSetor.nome" type="text" placeholder="Nome do setor" required />
        <button type="submit" :disabled="loadingState.setor">
          {{ loadingState.setor ? 'Salvando...' : 'Salvar Setor' }}
        </button>
      </form>

      <form class="card" @submit.prevent="salvarPrioridade">
        <h2>Cadastro de Prioridade</h2>
        <input v-model="novaPrioridade.descricao" type="text" placeholder="Descrição" required />
        <input
          v-model.number="novaPrioridade.tempo_estimado_horas"
          type="number"
          min="1"
          placeholder="Tempo estimado (h)"
          required
        />
        <button type="submit" :disabled="loadingState.prioridade">
          {{ loadingState.prioridade ? 'Salvando...' : 'Salvar Prioridade' }}
        </button>
      </form>
    </section>

    <form class="card section-space" @submit.prevent="salvarChamado">
      <h2>Abertura de Chamado</h2>
      <select v-model="novoChamado.setor_id" required>
        <option value="">Selecione o setor</option>
        <option v-for="setor in setores" :key="setor.id" :value="setor.id">
          {{ setor.nome }}
        </option>
      </select>

      <select v-model="novoChamado.prioridade_id" required>
        <option value="">Selecione a prioridade</option>
        <option v-for="prioridade in prioridades" :key="prioridade.id" :value="prioridade.id">
          {{ prioridade.descricao }} ({{ prioridade.tempo_estimado_horas }}h)
        </option>
      </select>

      <textarea
        v-model="novoChamado.descricao_problema"
        placeholder="Descrição do problema"
        required
      />
      <button type="submit" :disabled="loadingState.chamado">
        {{ loadingState.chamado ? 'Abrindo...' : 'Abrir Chamado' }}
      </button>
    </form>

    <section class="card section-space">
      <h2>Chamados</h2>
      <div class="filtros">
        <select v-model="filtros.status">
          <option value="">Todos os status</option>
          <option value="Aberto">Aberto</option>
          <option value="Em Atendimento">Em Atendimento</option>
          <option value="Finalizado">Finalizado</option>
          <option value="Cancelado">Cancelado</option>
        </select>
        <select v-model="filtros.setor_id">
          <option value="">Todos os setores</option>
          <option v-for="setor in setores" :key="setor.id" :value="setor.id">
            {{ setor.nome }}
          </option>
        </select>
        <select v-model="filtros.prioridade_id">
          <option value="">Todas as prioridades</option>
          <option v-for="prioridade in prioridades" :key="prioridade.id" :value="prioridade.id">
            {{ prioridade.descricao }}
          </option>
        </select>
        <select v-model="metaChamados.order_by">
          <option value="data_abertura">Ordenar: Data abertura</option>
          <option value="id">Ordenar: ID</option>
          <option value="status">Ordenar: Status</option>
          <option value="setor">Ordenar: Setor</option>
          <option value="prioridade">Ordenar: Prioridade</option>
        </select>
        <select v-model="metaChamados.order_dir">
          <option value="DESC">Descendente</option>
          <option value="ASC">Ascendente</option>
        </select>
        <select v-model.number="metaChamados.limit">
          <option :value="5">5 por página</option>
          <option :value="10">10 por página</option>
          <option :value="20">20 por página</option>
          <option :value="50">50 por página</option>
        </select>
        <button type="button" @click="aplicarFiltros" :disabled="loadingState.filtro">
          {{ loadingState.filtro ? 'Carregando...' : 'Aplicar filtros' }}
        </button>
        <button type="button" @click="limparFiltros">Limpar</button>
      </div>

      <div class="table-hint">Dê double-click em uma linha para abrir os detalhes e ações.</div>
      <table class="chamados-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Descrição</th>
              <th>Setor/Prioridade</th>
              <th>Status</th>
              <th>SLA (h)</th>
              <th>Abertura</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="chamado in chamados"
              :key="chamado.id"
              class="row-clickable"
              @dblclick="abrirDetalheChamado(chamado)"
            >
              <td>{{ chamado.id }}</td>
              <td class="col-descricao">{{ chamado.descricao_problema }}</td>
              <td>{{ chamado.setor_nome }} / {{ chamado.prioridade_descricao }}</td>
              <td>
                <span class="status-chip" :class="classeStatus(chamado.status)">
                  {{ chamado.status }}
                </span>
              </td>
              <td>
                <span :class="slaAtrasado(chamado) ? 'sla-atrasado' : 'sla-ok'">
                  {{ formatarDuracaoHoras(chamado.sla_horas) }} de
                  {{ formatarDuracaoHoras(chamado.tempo_estimado_horas) }}
                  <small v-if="slaAtrasado(chamado)"> (atrasado)</small>
                  <small v-else> (dentro do prazo)</small>
                </span>
              </td>
              <td>{{ formatarData(chamado.data_abertura) }}</td>
            </tr>
            <tr v-if="semChamados">
              <td colspan="6">Nenhum chamado cadastrado.</td>
            </tr>
          </tbody>
      </table>
      <div class="paginacao">
        <button type="button" @click="paginaAnterior" :disabled="metaChamados.page <= 1">Anterior</button>
        <span>Página {{ metaChamados.page }} de {{ Math.max(metaChamados.total_pages, 1) }}</span>
        <span>Total: {{ metaChamados.total }}</span>
        <button
          type="button"
          @click="proximaPagina"
          :disabled="metaChamados.page >= metaChamados.total_pages"
        >
          Próxima
        </button>
      </div>
    </section>

    <div v-if="detalheModal.show && detalheModal.chamado" class="modal-backdrop">
      <div class="modal-card modal-large">
        <h3>Chamado #{{ detalheModal.chamado.id }}</h3>
        <p><strong>Problema:</strong> {{ detalheModal.chamado.descricao_problema }}</p>
        <p>
          <strong>Status:</strong>
          <span class="status-chip" :class="classeStatus(detalheModal.chamado.status)">
            {{ detalheModal.chamado.status }}
          </span>
        </p>
        <p><strong>Setor:</strong> {{ detalheModal.chamado.setor_nome }}</p>
        <p><strong>Prioridade:</strong> {{ detalheModal.chamado.prioridade_descricao }}</p>
        <p><strong>Abertura:</strong> {{ formatarData(detalheModal.chamado.data_abertura) }}</p>
        <p><strong>Início:</strong> {{ formatarData(detalheModal.chamado.data_inicio_atendimento) }}</p>
        <p><strong>Finalização:</strong> {{ formatarData(detalheModal.chamado.data_finalizacao) }}</p>

        <label>Solução</label>
        <textarea
          v-model="detalheModal.solucao"
          placeholder="Descreva a solução do chamado"
          :disabled="detalheModal.chamado.status === 'Cancelado'"
        />

        <label>Motivo de cancelamento</label>
        <input
          v-model="detalheModal.motivo"
          type="text"
          placeholder="Informe o motivo (caso cancele)"
          :disabled="!['Aberto', 'Em Atendimento'].includes(detalheModal.chamado.status)"
        />

        <div class="modal-actions">
          <button type="button" class="btn-secondary" @click="fecharDetalheChamado">Fechar</button>
          <button
            type="button"
            @click="salvarSolucao(detalheModal.chamado.id, detalheModal.solucao)"
            :disabled="loadingState.solucao[detalheModal.chamado.id] || detalheModal.chamado.status === 'Cancelado'"
          >
            {{ loadingState.solucao[detalheModal.chamado.id] ? 'Salvando...' : 'Salvar solução' }}
          </button>
          <button
            type="button"
            @click="fazerCheckIn(detalheModal.chamado.id)"
            :disabled="detalheModal.chamado.status !== 'Aberto' || loadingState.checkin[detalheModal.chamado.id]"
          >
            {{ loadingState.checkin[detalheModal.chamado.id] ? 'Iniciando...' : 'Iniciar atendimento' }}
          </button>
          <button
            type="button"
            @click="fazerCheckOut(detalheModal.chamado.id)"
            :disabled="detalheModal.chamado.status !== 'Em Atendimento' || loadingState.checkout[detalheModal.chamado.id]"
          >
            {{ loadingState.checkout[detalheModal.chamado.id] ? 'Finalizando...' : 'Finalizar' }}
          </button>
          <button
            type="button"
            @click="cancelar(detalheModal.chamado.id)"
            :disabled="!['Aberto', 'Em Atendimento'].includes(detalheModal.chamado.status) || loadingState.cancelar[detalheModal.chamado.id]"
          >
            {{ loadingState.cancelar[detalheModal.chamado.id] ? 'Cancelando...' : 'Cancelar' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="confirmModal.show" class="modal-backdrop">
      <div class="modal-card">
        <h3>{{ confirmModal.title }}</h3>
        <p>{{ confirmModal.text }}</p>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" @click="fecharConfirmacao">Voltar</button>
          <button type="button" @click="confirmarAcao" :disabled="confirmModal.loading">
            {{ confirmModal.loading ? 'Processando...' : 'Confirmar' }}
          </button>
        </div>
      </div>
    </div>
  </main>
</template>
