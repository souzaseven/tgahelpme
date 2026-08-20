/**
 * jwt.js
 * Decodifica só a parte pública (payload) de um JWT — sem verificar
 * assinatura, isso é responsabilidade exclusiva do servidor. Serve apenas
 * para exibir informações não sensíveis ao usuário, como a expiração
 * (`exp`), sem precisar esperar um 401 para descobrir que o token venceu.
 *
 * O token do login da TGA tem formato JWT (confirmado: começa com
 * "eyJhbGciOiJIUzI1NiIs...", header padrão `{"alg":"HS256","typ":"JWT"}`),
 * mas isso é um detalhe de implementação da API que pode mudar — por isso
 * todo o módulo falha graciosamente (retorna null) se o token não for um
 * JWT válido, em vez de quebrar a interface.
 */

const TgaJwt = {
  /** Decodifica o payload (claims) de um JWT. Retorna null se não for um JWT válido. */
  decode(token) {
    if (typeof token !== 'string') return null;
    const parts = token.split('.');
    if (parts.length !== 3) return null;

    try {
      const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
      const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
      const binary = atob(padded);
      const json = decodeURIComponent(
        binary.split('').map(c => '%' + c.charCodeAt(0).toString(16).padStart(2, '0')).join('')
      );
      return JSON.parse(json);
    } catch (e) {
      return null;
    }
  },

  /** Retorna a data de expiração (claim `exp`, em segundos) como Date, ou null. */
  getExpiration(token) {
    const payload = this.decode(token);
    if (!payload || typeof payload.exp !== 'number') return null;
    return new Date(payload.exp * 1000);
  },
};
