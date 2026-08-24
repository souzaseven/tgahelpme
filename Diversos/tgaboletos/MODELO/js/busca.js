/**
 * busca.js
 * ------------------------------------------------------------------
 * Índice de busca simples (client-side, sem backend). Varre nomes de
 * bancos, campos do Portador e erros catalogados, devolvendo trechos
 * que "batem" com o termo digitado. Usado pela caixa de busca do
 * dashboard e pela página "Documentação".
 * ------------------------------------------------------------------
 */
window.CentralBoletos = window.CentralBoletos || {};

(function () {
  // Faixa Unicode dos sinais diacríticos combinantes (0x0300–0x036F),
  // construída via código de ponto para evitar problemas de encoding
  // de caracteres combinantes literais dentro do arquivo-fonte.
  var DIACRITICOS = new RegExp(
    "[" + String.fromCharCode(0x0300) + "-" + String.fromCharCode(0x036f) + "]",
    "g"
  );
  function normalizar(str) {
    return (str || "")
      .toString()
      .normalize("NFD")
      .replace(DIACRITICOS, "")
      .toLowerCase();
  }

  function construirIndice() {
    const { bancos, portadorCamposBase, baseConhecimentoGeral } = window.CentralBoletos;
    const indice = [];

    bancos.forEach((banco) => {
      indice.push({ tipo: "Banco", titulo: banco.nome, contexto: banco.resumo, href: `#/banco/${banco.id}` });
      (banco.erros || []).forEach((e) => {
        indice.push({ tipo: "Erro", titulo: `${e.codigo} — ${e.titulo} (${banco.nome})`, contexto: e.causas.join("; "), href: `#/banco/${banco.id}#erros` });
      });
      (banco.integracaoApi && banco.integracaoApi.campos || []).forEach((c) => {
        indice.push({ tipo: "Campo de API", titulo: `${c.nome} (${banco.nome})`, contexto: c.paraQueServe, href: `#/banco/${banco.id}#integracao-api` });
      });
    });

    ["abaDadosCedente", "abaInstrucoesBanco", "abaImpressao", "abaRemessaRetorno"].forEach((abaKey) => {
      const aba = portadorCamposBase[abaKey];
      (aba.campos || []).forEach((c) => {
        indice.push({ tipo: "Campo do Portador", titulo: c.nome, contexto: `${aba.titulo}: ${c.paraQueServe}`, href: "#/portador" });
      });
    });

    (baseConhecimentoGeral || []).forEach((item) => {
      indice.push({ tipo: "Base de Conhecimento", titulo: item.erro, contexto: `${item.causa} ${item.solucao}`, href: "#/erros" });
    });

    return indice;
  }

  let indiceCache = null;
  function buscar(termo) {
    if (!indiceCache) indiceCache = construirIndice();
    const alvo = normalizar(termo).trim();
    if (!alvo) return [];
    return indiceCache.filter((item) => {
      return normalizar(item.titulo).includes(alvo) || normalizar(item.contexto).includes(alvo) || normalizar(item.tipo).includes(alvo);
    });
  }

  window.CentralBoletos.busca = { buscar, normalizar };
})();
