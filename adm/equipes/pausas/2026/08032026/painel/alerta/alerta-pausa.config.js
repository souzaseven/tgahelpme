// ============================================================
// alerta-pausa.config.js
// Configurações do alerta de pausa
// ============================================================

window.ALERTA_PAUSA_CONFIG = {

    // ---------------------------------
    // Regras gerais
    // ---------------------------------
    LIMITE_MINUTOS: 20,
    INTERVALO_VERIFICACAO_MS: 10_000, // 10s
    CLASSE_ALERTA: "alerta-pausa-excedida",

    // ---------------------------------
    // WhatsApp / alerta progressivo
    // ---------------------------------
    tempoExibirBotao: 18 * 60 + 57, // 18:57
    tempoAutoEnvio:   19 * 60,      // 19:00

    // ---------------------------------
    // Execução
    // ---------------------------------
    intervaloCheck: 5000, // 5s
    debug: true
};
