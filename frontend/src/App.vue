<script setup>
import { onMounted, reactive, ref } from 'vue';
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
} from './services/api';

const loading = ref(false);
const erro = ref('');
const mensagem = ref('');

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
const solucaoPorChamado = reactive({});
const motivoCancelamentoPorChamado = reactive({});

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

function limparFeedback() {
  erro.value = '';
  mensagem.value = '';
}

async function carregarDados() {
  loading.value = true;
  limparFeedback();
  try {
    const [setoresData, prioridadesData, chamadosResposta] = await Promise.all([
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
    ]);
    setores.value = setoresData;
    prioridades.value = prioridadesData;
    chamados.value = chamadosResposta.data ?? [];
    Object.assign(metaChamados, chamadosResposta.meta ?? {});
  } catch (e) {
    erro.value = e.message;
  } finally {
    loading.value = false;
  }
}

function aplicarFiltros() {
  metaChamados.page = 1;
  carregarDados();
}

async function salvarSetor() {
  limparFeedback();
  try {
    await criarSetor({ nome: novoSetor.nome });
    novoSetor.nome = '';
    mensagem.value = 'Setor cadastrado com sucesso.';
    await carregarDados();
  } catch (e) {
    erro.value = e.message;
  }
}

async function salvarPrioridade() {
  limparFeedback();
  try {
    await criarPrioridade({
      descricao: novaPrioridade.descricao,
      tempo_estimado_horas: Number(novaPrioridade.tempo_estimado_horas),
    });
    novaPrioridade.descricao = '';
    novaPrioridade.tempo_estimado_horas = 1;
    mensagem.value = 'Prioridade cadastrada com sucesso.';
    await carregarDados();
  } catch (e) {
    erro.value = e.message;
  }
}

async function salvarChamado() {
  limparFeedback();
  try {
    await abrirChamado({
      setor_id: Number(novoChamado.setor_id),
      prioridade_id: Number(novoChamado.prioridade_id),
      descricao_problema: novoChamado.descricao_problema,
    });
    novoChamado.setor_id = '';
    novoChamado.prioridade_id = '';
    novoChamado.descricao_problema = '';
    mensagem.value = 'Chamado aberto com sucesso.';
    await carregarDados();
  } catch (e) {
    erro.value = e.message;
  }
}

async function fazerCheckIn(id) {
  limparFeedback();
  try {
    await iniciarChamado(id);
    mensagem.value = 'Check-in realizado.';
    await carregarDados();
  } catch (e) {
    erro.value = e.message;
  }
}

async function fazerCheckOut(id) {
  limparFeedback();
  try {
    const solucao = (solucaoPorChamado[id] ?? '').trim();
    await finalizarChamado(id, { solucao_breve: solucao });
    solucaoPorChamado[id] = '';
    mensagem.value = 'Check-out realizado.';
    await carregarDados();
  } catch (e) {
    erro.value = e.message;
  }
}

async function cancelar(id) {
  limparFeedback();
  try {
    const motivo = (motivoCancelamentoPorChamado[id] ?? '').trim();
    await cancelarChamado(id, { motivo_cancelamento: motivo });
    motivoCancelamentoPorChamado[id] = '';
    mensagem.value = 'Chamado cancelado com sucesso.';
    await carregarDados();
  } catch (e) {
    erro.value = e.message;
  }
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

onMounted(() => {
  carregarDados();
});
</script>

<template>
  <main class="container">
    <h1>Sistema de Controle de Atendimentos</h1>

    <p v-if="loading">Carregando...</p>
    <p v-if="erro" class="erro">{{ erro }}</p>
    <p v-if="mensagem" class="sucesso">{{ mensagem }}</p>

    <section class="grid">
      <form class="card" @submit.prevent="salvarSetor">
        <h2>Cadastro de Setor</h2>
        <input v-model="novoSetor.nome" type="text" placeholder="Nome do setor" required />
        <button type="submit">Salvar Setor</button>
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
        <button type="submit">Salvar Prioridade</button>
      </form>
    </section>

    <form class="card" @submit.prevent="salvarChamado">
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
      <button type="submit">Abrir Chamado</button>
    </form>

    <section class="card">
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
        <button type="button" @click="aplicarFiltros">Aplicar filtros</button>
        <button type="button" @click="limparFiltros">Limpar</button>
      </div>

      <div class="tabela-wrapper">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Setor</th>
              <th>Prioridade</th>
              <th>Status</th>
              <th>SLA (h)</th>
              <th>Abertura</th>
              <th>Início</th>
              <th>Finalização</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="chamado in chamados" :key="chamado.id">
              <td>{{ chamado.id }}</td>
              <td>{{ chamado.setor_nome }}</td>
              <td>{{ chamado.prioridade_descricao }}</td>
              <td>{{ chamado.status }}</td>
              <td>{{ chamado.sla_horas }}</td>
              <td>{{ formatarData(chamado.data_abertura) }}</td>
              <td>{{ formatarData(chamado.data_inicio_atendimento) }}</td>
              <td>{{ formatarData(chamado.data_finalizacao) }}</td>
              <td class="acoes">
                <button
                  type="button"
                  @click="fazerCheckIn(chamado.id)"
                  :disabled="chamado.status !== 'Aberto'"
                >
                  Check-in
                </button>
                <input
                  v-model="solucaoPorChamado[chamado.id]"
                  type="text"
                  placeholder="Solução breve"
                  :disabled="chamado.status !== 'Em Atendimento'"
                />
                <button
                  type="button"
                  @click="fazerCheckOut(chamado.id)"
                  :disabled="chamado.status !== 'Em Atendimento'"
                >
                  Check-out
                </button>
                <input
                  v-model="motivoCancelamentoPorChamado[chamado.id]"
                  type="text"
                  placeholder="Motivo cancelamento"
                  :disabled="!['Aberto', 'Em Atendimento'].includes(chamado.status)"
                />
                <button
                  type="button"
                  @click="cancelar(chamado.id)"
                  :disabled="!['Aberto', 'Em Atendimento'].includes(chamado.status)"
                >
                  Cancelar
                </button>
              </td>
            </tr>
            <tr v-if="!chamados.length">
              <td colspan="9">Nenhum chamado cadastrado.</td>
            </tr>
          </tbody>
        </table>
      </div>
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
  </main>
</template>
