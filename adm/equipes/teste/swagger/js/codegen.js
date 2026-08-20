/**
 * codegen.js
 * Gera exemplos de código (JavaScript/fetch, PHP/cURL, cURL de terminal) a
 * partir da última requisição executada — puramente formatação de texto,
 * não faz nenhuma chamada de rede.
 *
 * Regra de segurança (igual ao Request Viewer): a senha NUNCA aparece, nem
 * sob pedido explícito. O token só aparece completo se o usuário marcar a
 * opção "Incluir token real" — por padrão vem como placeholder.
 */

const TgaCodegen = {
  TOKEN_PLACEHOLDER: 'Bearer SEU_TOKEN_AQUI',

  sanitizeBody(body) {
    if (!body || typeof body !== 'object') return body;
    const clone = { ...body };
    if ('password' in clone) clone.password = '••••••••';
    return clone;
  },

  sanitizeHeaders(headers, includeToken) {
    const clone = { ...(headers || {}) };
    if (clone.authorization && !includeToken) {
      clone.authorization = this.TOKEN_PLACEHOLDER;
    }
    return clone;
  },

  generate(request, lang, includeToken) {
    const headers = this.sanitizeHeaders(request.headers, includeToken);
    const body = this.sanitizeBody(request.body);

    if (lang === 'php') return this.php(request, headers, body);
    if (lang === 'curl') return this.curl(request, headers, body);
    return this.javascript(request, headers, body);
  },

  // ---------------- JavaScript (fetch) ----------------

  javascript(request, headers, body) {
    const lines = [];
    lines.push(`fetch(${JSON.stringify(request.url)}, {`);
    lines.push(`  method: ${JSON.stringify(request.method)},`);
    lines.push(`  headers: ${this.indentBlock(headers, 2)},`);
    if (body) {
      lines.push(`  body: JSON.stringify(${this.indentBlock(body, 2)}),`);
    }
    lines.push('})');
    lines.push('  .then((res) => res.json())');
    lines.push('  .then((data) => console.log(data))');
    lines.push('  .catch((err) => console.error(err));');
    return lines.join('\n');
  },

  // ---------------- PHP (cURL) ----------------

  php(request, headers, body) {
    const lines = ['<?php', '', '$ch = curl_init();', ''];
    lines.push(`curl_setopt($ch, CURLOPT_URL, ${this.phpString(request.url)});`);
    lines.push('curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);');
    lines.push(`curl_setopt($ch, CURLOPT_CUSTOMREQUEST, ${this.phpString(request.method)});`);

    const headerEntries = Object.entries(headers).map(([k, v]) => `    ${this.phpString(`${k}: ${v}`)}`);
    if (headerEntries.length) {
      lines.push('curl_setopt($ch, CURLOPT_HTTPHEADER, [');
      lines.push(headerEntries.join(',\n'));
      lines.push(']);');
    }

    if (body) {
      lines.push('');
      lines.push(`$data = ${this.phpArrayLiteral(body, 0)};`);
      lines.push('curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));');
    }

    lines.push('', '$response = curl_exec($ch);', 'curl_close($ch);', '', 'echo $response;');
    return lines.join('\n');
  },

  phpString(s) {
    return `"${String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
  },

  phpArrayLiteral(obj, indent) {
    const pad = '    '.repeat(indent + 1);
    const closePad = '    '.repeat(indent);
    const entries = Object.entries(obj).map(([k, v]) => {
      let val;
      if (v === null || v === undefined) val = 'null';
      else if (typeof v === 'object') val = this.phpArrayLiteral(v, indent + 1);
      else if (typeof v === 'number' || typeof v === 'boolean') val = String(v);
      else val = this.phpString(v);
      return `${pad}${this.phpString(k)} => ${val}`;
    });
    return `[\n${entries.join(',\n')}\n${closePad}]`;
  },

  // ---------------- cURL (terminal) ----------------

  curl(request, headers, body) {
    const lines = [`curl -X ${request.method} ${this.shellQuote(request.url)} \\`];
    Object.entries(headers).forEach(([k, v]) => {
      lines.push(`  -H ${this.shellQuote(`${k}: ${v}`)} \\`);
    });

    if (body) {
      lines.push(`  -d ${this.shellQuote(JSON.stringify(body))}`);
    } else {
      lines[lines.length - 1] = lines[lines.length - 1].replace(/ \\$/, '');
    }
    return lines.join('\n');
  },

  shellQuote(s) {
    return `'${String(s).replace(/'/g, `'\\''`)}'`;
  },

  // ---------------- Util ----------------

  indentBlock(obj, spaces) {
    const json = JSON.stringify(obj, null, 2);
    const pad = ' '.repeat(spaces);
    return json.split('\n').map((line, i) => (i === 0 ? line : pad + line)).join('\n');
  },
};
