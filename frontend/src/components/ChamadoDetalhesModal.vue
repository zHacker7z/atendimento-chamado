<script setup>
import { computed, onMounted } from "vue";

onMounted(() => {
  console.log("ChamadoDetalhesModal montado");
});

const props = defineProps({
  chamado: {
    type: Object,
    required: true,
  },
  solucao: {
    type: String,
    default: "",
  },
  motivo: {
    type: String,
    default: "",
  },
  loadingState: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits([
  "close",
  "update:solucao",
  "update:motivo",
  "salvar-solucao",
  "iniciar-atendimento",
  "finalizar",
  "cancelar",
]);

// Calcula porcentagem de SLA consumido
const slaPercentual = computed(() => {
  if (!props.chamado) return 0;
  const slaHoras = Number(props.chamado.sla_horas) || 0;
  const tempoEstimado = Number(props.chamado.tempo_estimado_horas) || 1;
  return Math.min(Math.round((slaHoras / tempoEstimado) * 100), 100);
});

// Calcula se está atrasado
const slaAtrasado = computed(() => {
  if (!props.chamado) return false;
  return (
    Number(props.chamado.sla_horas) > Number(props.chamado.tempo_estimado_horas)
  );
});

// Formata data para exibição
function formatarData(data) {
  if (!data) return "— pendente";
  const d = new Date(data);
  const dia = String(d.getDate()).padStart(2, "0");
  const mes = String(d.getMonth() + 1).padStart(2, "0");
  const hora = String(d.getHours()).padStart(2, "0");
  const min = String(d.getMinutes()).padStart(2, "0");
  return `${dia}/${mes} · ${hora}:${min}`;
}

// Formata duração em horas
function formatarDuracao(horas) {
  const totalMinutos = Math.max(Math.round(Number(horas) * 60), 0);
  const h = Math.floor(totalMinutos / 60);
  const m = totalMinutos % 60;
  return `${h}h ${String(m).padStart(2, "0")}m`;
}

// Classe do badge de status
function classeStatusBadge(status) {
  if (status === "Aberto") return "bg-blue-950 text-blue-400 border-blue-900";
  if (status === "Em Atendimento")
    return "bg-amber-950 text-amber-500 border-amber-900";
  if (status === "Finalizado")
    return "bg-green-950 text-green-400 border-green-900";
  return "bg-red-950 text-red-400 border-red-900";
}

// Classe do badge de prioridade
function classePrioridadeBadge(prioridade) {
  if (prioridade === "Crítica") return "bg-red-950 text-red-400 border-red-900";
  if (prioridade === "Alta")
    return "bg-amber-950 text-amber-400 border-amber-900";
  if (prioridade === "Média")
    return "bg-yellow-950 text-yellow-300 border-yellow-900";
  if (prioridade === "Baixa")
    return "bg-green-950 text-green-400 border-green-900";
  return "bg-slate-950 text-slate-300 border-slate-900";
}

// Classe do badge de setor
function classeSetorBadge() {
  return "bg-blue-950 text-blue-400 border-blue-900";
}

// Computed para verificar se pode cancelar
const canCancel = computed(() => {
  return (
    ["Aberto", "Em Atendimento"].includes(props.chamado.status) &&
    !props.loadingState.cancelar?.[props.chamado.id]
  );
});
</script>

<template>
  <div
    class="fixed z-[9999] flex items-center justify-center p-6 backdrop-blur-sm"
    style="
      position: fixed;
      inset: 0;
      width: 100vw;
      min-height: 100vh;
      background: rgba(0, 0, 0, 0.8);
    "
    @click.self="emit('close')"
  >
    <div
      class="relative w-full rounded-[16px] overflow-hidden shadow-2xl"
      style="
        max-width: 672px;
        background-color: #181c27;
        border: 1px solid #2a2f42;
      "
    >
      <!-- Botão de Fechar Absoluto -->
      <button
        @click="emit('close')"
        class="absolute top-4 right-4 left-auto w-8 h-8 rounded-lg border-0 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-slate-200 flex items-center justify-center transition-all text-lg z-10"
        style="top: 1rem; right: 1rem; left: auto"
      >
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path
            d="M1 1l14 14M15 1L1 15"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
          />
        </svg>
      </button>

      <!-- Header -->
      <div
        class="px-6 py-5 border-b flex items-start justify-between gap-3"
        style="border-color: #1f2437"
      >
        <div class="flex flex-col gap-1.5">
          <span
            class="text-xs font-medium tracking-widest flex items-center gap-1.5"
            style="
              color: #6b7fad;
              font-family: &quot;JetBrains Mono&quot;, monospace;
              letter-spacing: 0.04em;
            "
          >
            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
            CHAMADO #{{ chamado.id }}
          </span>
          <h2
            class="text-sm font-semibold leading-snug max-w-xs"
            style="color: #e8ecf5; font-family: &quot;Sora&quot;, sans-serif"
          >
            {{ chamado.descricao_problema }}
          </h2>
        </div>
      </div>

      <!-- Status Bar -->
      <div
        class="px-6 py-3 border-b flex items-center gap-2 flex-wrap"
        style="background-color: #13162090; border-color: #1f2437"
      >
        <span class="text-sm font-medium text-slate-100">
          {{ chamado.status }}
        </span>
        <span class="text-slate-600">·</span>
        <span class="text-sm font-medium text-slate-100">
          {{ chamado.prioridade_descricao || "Sem prioridade" }}
        </span>
        <span class="text-slate-600">·</span>
        <span class="text-sm font-medium text-slate-100">
          {{ chamado.setor_nome }}
        </span>
      </div>

      <!-- Body -->
      <div class="p-6 flex flex-col gap-4">
        <!-- Info Grid Card -->
        <div
          class="rounded-2xl p-4"
          style="background-color: transparent; border: none"
        >
          <div class="grid grid-cols-3 gap-2.5">
            <div
              class="rounded-[10px] p-2.5 flex flex-col gap-0.75"
              style="background-color: #13162090; border: 1px solid #1f2437"
            >
              <span
                class="text-xs font-medium tracking-wider uppercase"
                style="
                  color: #4a5270;
                  font-family: &quot;Sora&quot;, sans-serif;
                  letter-spacing: 0.06em;
                "
                >Abertura</span
              >
              <span
                class="text-xs font-medium"
                style="
                  color: #9ba8c9;
                  font-family: &quot;JetBrains Mono&quot;, monospace;
                "
                >{{ formatarData(chamado.data_abertura) }}</span
              >
            </div>
            <div
              class="rounded-[10px] p-2.5 flex flex-col gap-0.75"
              style="background-color: #13162090; border: 1px solid #1f2437"
            >
              <span
                class="text-xs font-medium tracking-wider uppercase"
                style="
                  color: #4a5270;
                  font-family: &quot;Sora&quot;, sans-serif;
                  letter-spacing: 0.06em;
                "
                >Início</span
              >
              <span
                class="text-xs font-medium"
                style="
                  color: #9ba8c9;
                  font-family: &quot;JetBrains Mono&quot;, monospace;
                "
                >{{
                  chamado.data_inicio_atendimento
                    ? formatarData(chamado.data_inicio_atendimento)
                    : "— pendente"
                }}</span
              >
            </div>
            <div
              class="rounded-[10px] p-2.5 flex flex-col gap-0.75"
              style="background-color: #13162090; border: 1px solid #1f2437"
            >
              <span
                class="text-xs font-medium tracking-wider uppercase"
                style="
                  color: #4a5270;
                  font-family: &quot;Sora&quot;, sans-serif;
                  letter-spacing: 0.06em;
                "
                >Finalização</span
              >
              <span
                class="text-xs font-medium"
                :style="
                  chamado.data_finalizacao
                    ? {
                        color: '#9ba8c9',
                        fontFamily: 'JetBrains Mono, monospace',
                      }
                    : {
                        color: '#4a5270',
                        fontStyle: 'italic',
                        fontFamily: 'Sora, sans-serif',
                        fontSize: '11px',
                      }
                "
                >{{
                  chamado.data_finalizacao
                    ? formatarData(chamado.data_finalizacao)
                    : "— pendente"
                }}</span
              >
            </div>
          </div>
        </div>

        <!-- SLA Bar Card -->
        <div
          class="rounded-2xl p-4"
          style="background-color: transparent; border: none"
        >
          <div
            class="rounded-[10px] p-3.5 flex items-center gap-3"
            style="background-color: #131620; border: 1px solid #1f2437"
          >
            <span
              class="text-xs font-medium whitespace-nowrap"
              style="
                color: #4a5270;
                font-family: &quot;Sora&quot;, sans-serif;
                font-weight: 500;
              "
              >SLA consumido</span
            >
            <div
              class="flex-1 h-1 rounded-full overflow-hidden"
              style="background-color: #1f2437"
            >
              <div
                class="h-full rounded-full transition-all duration-500"
                :style="{
                  width: slaPercentual + '%',
                  background: slaAtrasado
                    ? 'linear-gradient(90deg, #f5a623 0%, #f06060 100%)'
                    : 'linear-gradient(90deg, #10b981 0%, #059669 100%)',
                }"
              ></div>
            </div>
            <span
              class="text-xs font-medium"
              style="
                font-family: &quot;JetBrains Mono&quot;, monospace;
                color: #f5a623;
                white-space: nowrap;
              "
            >
              {{ slaPercentual }}%
            </span>
          </div>
        </div>

        <!-- Solução Card -->
        <div
          class="rounded-2xl p-4"
          style="background-color: transparent; border: none"
        >
          <p
            class="text-xs font-medium tracking-wider uppercase mb-1.5"
            style="
              color: #4a5270;
              font-family: &quot;Sora&quot;, sans-serif;
              letter-spacing: 0.06em;
            "
          >
            Solução aplicada
          </p>
          <textarea
            :value="solucao"
            @input="emit('update:solucao', $event.target.value)"
            rows="3"
            placeholder="Descreva a solução técnica aplicada..."
            :disabled="chamado.status === 'Cancelado'"
            class="w-full rounded-[10px] text-sm px-4 py-3 resize-none transition-all outline-none placeholder:text-slate-600 disabled:opacity-50 disabled:cursor-default"
            style="
              background-color: #131620;
              border: 1px solid #1f2437;
              color: #c5cde0;
              font-family: &quot;Sora&quot;, sans-serif;
              line-height: 1.6;
            "
            @focus="
              $event.target.style.borderColor = '#3b82f6';
              $event.target.style.backgroundColor = '#10131e';
            "
            @blur="
              $event.target.style.borderColor = '#1f2437';
              $event.target.style.backgroundColor = '#131620';
            "
          ></textarea>
        </div>

        <!-- Motivo de Cancelamento Card -->
        <div
          class="rounded-2xl p-4 mb-6"
          style="background-color: transparent; border: none"
        >
          <p
            class="text-xs font-medium tracking-wider uppercase mb-1.5"
            style="
              color: #4a5270;
              font-family: &quot;Sora&quot;, sans-serif;
              letter-spacing: 0.06em;
            "
          >
            Motivo de cancelamento
          </p>
          <input
            type="text"
            :value="motivo"
            @input="emit('update:motivo', $event.target.value)"
            placeholder="Informe o motivo (caso cancele)"
            :disabled="!['Aberto', 'Em Atendimento'].includes(chamado.status)"
            class="w-full rounded-[10px] text-sm px-4 py-3 transition-all outline-none placeholder:text-slate-600 disabled:opacity-50 disabled:cursor-default"
            style="
              background-color: #131620;
              border: 1px solid #1f2437;
              color: #c5cde0;
              font-family: &quot;Sora&quot;, sans-serif;
            "
            @focus="
              $event.target.style.borderColor = '#3b82f6';
              $event.target.style.backgroundColor = '#10131e';
            "
            @blur="
              $event.target.style.borderColor = '#1f2437';
              $event.target.style.backgroundColor = '#131620';
            "
          />
        </div>
      </div>

      <!-- Footer -->
      <div
        class="px-6 py-6 border-t flex items-center justify-between gap-6 flex-wrap"
        style="border-color: #1f2437"
      >
        <div class="flex gap-4">
          <button
            @click="emit('cancelar')"
            :disabled="!canCancel"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg font-medium text-xs transition-all disabled:opacity-50 disabled:cursor-default"
            :style="{
              backgroundColor: canCancel ? 'transparent' : '#1a1820',
              color: canCancel ? '#f87171' : '#7f6370',
              border: canCancel ? '1px solid #2a1820' : '1px solid #1a1820',
              fontFamily: 'Sora, sans-serif',
              fontWeight: 500,
            }"
            @mouseenter="
              if (canCancel) {
                $event.target.style.backgroundColor = '#2a1820';
                $event.target.style.color = '#f87171';
              }
            "
            @mouseleave="
              if (canCancel) {
                $event.target.style.backgroundColor = 'transparent';
                $event.target.style.color = '#f87171';
              }
            "
          >
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path
                d="M1 1l10 10M11 1L1 11"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
              />
            </svg>
            {{
              loadingState.cancelar?.[chamado.id] ? "Cancelando..." : "Cancelar"
            }}
          </button>
        </div>
        <div class="flex gap-4">
          <button
            @click="emit('salvar-solucao')"
            :disabled="
              loadingState.solucao?.[chamado.id] ||
              chamado.status === 'Cancelado'
            "
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg font-medium text-xs transition-all disabled:opacity-50 disabled:cursor-default"
            style="
              background-color: #1a2040;
              color: #60a5fa;
              border: 1px solid #1f2d52;
              font-family: &quot;Sora&quot;, sans-serif;
              font-weight: 500;
            "
            @mouseenter="
              $event.target.style.backgroundColor = '#1f2a55';
              $event.target.style.borderColor = '#2a3a70';
            "
            @mouseleave="
              $event.target.style.backgroundColor = '#1a2040';
              $event.target.style.borderColor = '#1f2d52';
            "
          >
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path
                d="M1 6l3.5 3.5L11 2"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            {{
              loadingState.solucao?.[chamado.id]
                ? "Salvando..."
                : "Salvar solução"
            }}
          </button>
          <button
            @click="emit('iniciar-atendimento')"
            :disabled="
              chamado.status !== 'Aberto' || loadingState.checkin?.[chamado.id]
            "
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg font-medium text-xs transition-all disabled:opacity-50 disabled:cursor-default"
            style="
              background-color: #1a2040;
              color: #60a5fa;
              border: 1px solid #1f2d52;
              font-family: &quot;Sora&quot;, sans-serif;
              font-weight: 500;
            "
            @mouseenter="
              $event.target.style.backgroundColor = '#1f2a55';
              $event.target.style.borderColor = '#2a3a70';
            "
            @mouseleave="
              $event.target.style.backgroundColor = '#1a2040';
              $event.target.style.borderColor = '#1f2d52';
            "
          >
            {{
              loadingState.checkin?.[chamado.id]
                ? "Iniciando..."
                : "Iniciar atendimento"
            }}
          </button>
          <button
            @click="emit('finalizar')"
            :disabled="
              chamado.status !== 'Em Atendimento' ||
              loadingState.checkout?.[chamado.id]
            "
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg font-medium text-xs transition-all disabled:opacity-50 disabled:cursor-default"
            style="
              background-color: #064e2e;
              color: #4ade80;
              border: 1px solid #0d5c38;
              font-family: &quot;Sora&quot;, sans-serif;
              font-weight: 500;
            "
            @mouseenter="$event.target.style.backgroundColor = '#075e38'"
            @mouseleave="$event.target.style.backgroundColor = '#064e2e'"
          >
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path
                d="M1 6l3.5 3.5L11 2"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            {{
              loadingState.checkout?.[chamado.id]
                ? "Finalizando..."
                : "Finalizar"
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
