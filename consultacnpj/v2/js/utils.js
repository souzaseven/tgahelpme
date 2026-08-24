/**
 * utils.js
 * Normalização, máscara, validação e formatação de dados.
 * Mantido desacoplado de regras rígidas de formato do CNPJ para
 * permitir compatibilidade futura com o novo padrão alfanumérico
 * em discussão para o CNPJ.
 */

const Utils = (() => {

  /**
   * Remove qualquer caractere que não seja letra ou número.
   * Não assume que o CNPJ é puramente numérico, preparando o
   * terreno para o futuro formato alfanumérico.
   */
  function normalizeCnpj(value) {
    if (!value) return "";
    return value
      .toString()
      .trim()
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, "");
  }

  /**
   * Validação "frouxa": apenas garante que, após normalizar,
   * sobrou uma sequência de 14 caracteres alfanuméricos.
   * Não valida dígito verificador aqui de forma rígida para não
   * quebrar compatibilidade com futuras mudanças de formato —
   * a validação definitiva é sempre a resposta da API.
   */
  function isPlausibleCnpj(value) {
    const normalized = normalizeCnpj(value);
    return /^[A-Z0-9]{14}$/.test(normalized);
  }

  /**
   * Tenta encontrar um CNPJ dentro de um texto maior — por exemplo, um
   * trecho colado de um e-mail ou documento como
   * "CNPJ: 12.345.678/0001-90 - Empresa X Ltda". Retorna o CNPJ já
   * normalizado (14 dígitos) ou null se nenhum padrão for encontrado.
   * Cobre apenas o formato numérico clássico: dentro de texto livre não
   * há como distinguir com segurança um futuro CNPJ alfanumérico de
   * qualquer outra sequência de letras e números do próprio texto.
   */
  const CNPJ_LOOSE_PATTERN = /\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}/;

  function extractCnpjFromText(text) {
    if (!text) return null;
    const match = String(text).match(CNPJ_LOOSE_PATTERN);
    if (!match) return null;
    const normalized = normalizeCnpj(match[0]);
    return normalized.length === 14 ? normalized : null;
  }

  /**
   * Validação do dígito verificador (módulo 11), aplicada apenas ao
   * formato numérico clássico de 14 dígitos. Se o valor contiver letras
   * (formato alfanumérico em discussão para o futuro do CNPJ), a função
   * não reprova nada aqui — quem decide é a resposta da API — evitando
   * travar a compatibilidade com o novo padrão.
   * Objetivo: dar feedback instantâneo para números claramente errados
   * (dígitos trocados, sequências repetidas) sem precisar de uma ida
   * à API só para descobrir isso.
   */
  function calcularDigitoVerificador(digitos, pesos) {
    const soma = digitos.reduce((acc, digito, i) => acc + digito * pesos[i], 0);
    const resto = soma % 11;
    return resto < 2 ? 0 : 11 - resto;
  }

  function isValidCnpjChecksum(value) {
    const normalized = normalizeCnpj(value);
    if (!/^\d{14}$/.test(normalized)) return true; // formato alfanumérico: não valida aqui

    if (/^(\d)\1{13}$/.test(normalized)) return false; // todos os dígitos iguais

    const digitos = normalized.split("").map(Number);
    const pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    const pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    const dv1 = calcularDigitoVerificador(digitos.slice(0, 12), pesos1);
    if (dv1 !== digitos[12]) return false;

    const dv2 = calcularDigitoVerificador(digitos.slice(0, 13), pesos2);
    if (dv2 !== digitos[13]) return false;

    return true;
  }

  /**
   * Aplica máscara visual 00.000.000/0000-00 enquanto o usuário digita.
   * Funciona de forma incremental (não trava se o usuário apagar).
   */
  function maskCnpj(value) {
    const digits = normalizeCnpj(value).slice(0, 14);
    let out = "";
    for (let i = 0; i < digits.length; i++) {
      const ch = digits[i];
      if (i === 2 || i === 5) out += ".";
      if (i === 8) out += "/";
      if (i === 12) out += "-";
      out += ch;
    }
    return out;
  }

  /** Formata CNPJ normalizado (14 chars) em máscara, para exibição. */
  function formatCnpjDisplay(normalized) {
    if (!normalized) return "—";
    if (normalized.length !== 14) return normalized;
    return maskCnpj(normalized);
  }

  function formatCurrencyBRL(value) {
    if (value === null || value === undefined || value === "") return null;
    const num = Number(value);
    if (Number.isNaN(num)) return null;
    return num.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function formatDateBR(isoDate) {
    if (!isoDate) return null;
    // aceita "YYYY-MM-DD" ou similar
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(isoDate);
    if (!match) return isoDate;
    const [, y, m, d] = match;
    return `${d}/${m}/${y}`;
  }

  function formatPhone(ddd, number) {
    if (!number) return null;
    const digits = String(number).replace(/\D/g, "");
    if (!digits) return null;
    if (digits.length <= 8) return digits;
    const dddPart = digits.slice(0, 2);
    const rest = digits.slice(2);
    if (rest.length === 9) {
      return `(${dddPart}) ${rest.slice(0, 5)}-${rest.slice(5)}`;
    }
    return `(${dddPart}) ${rest.slice(0, 4)}-${rest.slice(4)}`;
  }

  function formatCep(cep) {
    if (!cep) return null;
    const digits = String(cep).replace(/\D/g, "");
    if (digits.length !== 8) return cep;
    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
  }

  /** Retorna texto amigável ou "Não informado" para valores vazios/nulos. */
  function orNotInformed(value) {
    if (value === null || value === undefined) return "Não informado";
    if (typeof value === "string" && value.trim() === "") return "Não informado";
    return value;
  }

  function isEmptyValue(value) {
    return value === null || value === undefined || (typeof value === "string" && value.trim() === "");
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  return {
    normalizeCnpj,
    isPlausibleCnpj,
    extractCnpjFromText,
    isValidCnpjChecksum,
    maskCnpj,
    formatCnpjDisplay,
    formatCurrencyBRL,
    formatDateBR,
    formatPhone,
    formatCep,
    orNotInformed,
    isEmptyValue,
    escapeHtml,
  };

})();
