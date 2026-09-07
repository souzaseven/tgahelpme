/*
 * dom.js — criacao de elementos SEM innerHTML.
 * ------------------------------------------------------------------
 * Regra de seguranca do master prompt: nunca element.innerHTML com
 * dado do usuario. Aqui so existe textContent / createTextNode.
 */

/**
 * el("button", { class: "x", text: "Ok", onClick: fn, "aria-label": "..." }, [filhos])
 *  - "text"      -> textContent (seguro)
 *  - "class"     -> className
 *  - "dataset"   -> Object.assign(node.dataset, ...)
 *  - "onX"       -> addEventListener("x", fn)
 *  - true        -> atributo booleano presente
 *  - false/null  -> atributo omitido
 *  - resto       -> setAttribute(k, String(v))
 */
export function el(tag, props = {}, children = []) {
  const node = document.createElement(tag);

  for (const key of Object.keys(props || {})) {
    const value = props[key];
    if (key === "class") {
      node.className = value;
    } else if (key === "text") {
      node.textContent = value == null ? "" : String(value);
    } else if (key === "html") {
      throw new Error("[TGAW] innerHTML nao e permitido. Use 'text' ou filhos.");
    } else if (key === "dataset" && value && typeof value === "object") {
      Object.assign(node.dataset, value);
    } else if (key.startsWith("on") && typeof value === "function") {
      node.addEventListener(key.slice(2).toLowerCase(), value);
    } else if (value === true) {
      node.setAttribute(key, "");
    } else if (value === false || value == null) {
      /* omite */
    } else {
      node.setAttribute(key, String(value));
    }
  }

  const list = Array.isArray(children) ? children : [children];
  for (const child of list) {
    if (child == null || child === false) continue;
    node.appendChild(typeof child === "object" ? child : document.createTextNode(String(child)));
  }
  return node;
}

/** Remove todos os filhos de um no. */
export function clear(node) {
  while (node.firstChild) node.removeChild(node.firstChild);
}

/**
 * Converte marcacao do WhatsApp (*negrito* _italico_ ~tachado~ `codigo`)
 * em nós DOM reais (sem innerHTML). Retorna um DocumentFragment.
 */
export function waMarkupNodes(text) {
  const frag = document.createDocumentFragment();
  const s = String(text == null ? "" : text);
  const re = /(\*[^*\n]+\*|_[^_\n]+_|~[^~\n]+~|`[^`\n]+`)/g;
  let last = 0;
  let m;
  while ((m = re.exec(s))) {
    if (m.index > last) frag.appendChild(document.createTextNode(s.slice(last, m.index)));
    const tk = m[0];
    const tag = tk[0] === "*" ? "strong" : tk[0] === "_" ? "em" : tk[0] === "~" ? "s" : "code";
    const node = document.createElement(tag);
    node.textContent = tk.slice(1, -1);
    frag.appendChild(node);
    last = re.lastIndex;
  }
  if (last < s.length) frag.appendChild(document.createTextNode(s.slice(last)));
  return frag;
}

/** Icone raio (SVG inline, sem dependencia). */
export function boltIcon() {
  const NS = "http://www.w3.org/2000/svg";
  const svg = document.createElementNS(NS, "svg");
  svg.setAttribute("viewBox", "0 0 24 24");
  svg.setAttribute("aria-hidden", "true");
  const path = document.createElementNS(NS, "path");
  path.setAttribute("d", "M13 2 4.5 13.5H11l-1 8.5 9-12h-6.5z");
  path.setAttribute("fill", "currentColor");
  svg.appendChild(path);
  return svg;
}
