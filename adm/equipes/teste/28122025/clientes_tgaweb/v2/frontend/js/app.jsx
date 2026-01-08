const { useEffect, useMemo, useRef, useState } = React;

/* ===================================================
   FETCH PADRÃO
=================================================== */
function apiFetch(url, options = {}) {
  return fetch(url, {
    credentials: "same-origin",
    ...options,
    headers: {
      "Content-Type": "application/json",
      ...(options.headers || {})
    }
  });
}

/* ===================================================
   TOAST
=================================================== */
function Toast({ items, onClose }) {
  return (
    <div className="toast">
      {items.map(t => (
        <div key={t.id} className={"toastItem " + (t.type === "err" ? "err" : "ok")}>
          <div style={{ display: "flex", justifyContent: "space-between", gap: 10 }}>
            <div>{t.msg}</div>
            <button className="btn ghost" onClick={() => onClose(t.id)}>✕</button>
          </div>
        </div>
      ))}
    </div>
  );
}

/* ===================================================
   MODAL
=================================================== */
function Modal({ open, title, children, onClose }) {
  if (!open) return null;
  return (
    <div className="modalBack" onClick={onClose}>
      <div className="modal" onClick={e => e.stopPropagation()}>
        <div className="modalHeader">
          <h2>{title}</h2>
          <button className="btn" onClick={onClose}>Fechar</button>
        </div>
        <div className="modalBody">{children}</div>
      </div>
    </div>
  );
}

/* ===================================================
   HELPERS
=================================================== */
function formatDate(dt) {
  if (!dt) return "-";
  try {
    return new Date(dt.replace(" ", "T")).toLocaleString("pt-BR");
  } catch {
    return dt;
  }
}

/* ===================================================
   APP PRINCIPAL
=================================================== */
function App() {

  const [loading, setLoading] = useState(false);

  /* ===============================
     FILTROS
  =============================== */
  const [q, setQ] = useState("");
  const [versao, setVersao] = useState("");
  const [firebirdFiltro, setFirebirdFiltro] = useState("");
  const [firebirdLivre, setFirebirdLivre] = useState("");

  /* ===============================
     PAGINAÇÃO / DADOS
  =============================== */
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [total, setTotal] = useState(0);
  const [items, setItems] = useState([]);

  /* ===============================
     MODAL / FORM
  =============================== */
  const [openForm, setOpenForm] = useState(false);
  const [edit, setEdit] = useState(null);

  /* ===============================
     TOAST
  =============================== */
  const [toasts, setToasts] = useState([]);
  const toastId = useRef(1);

  function pushToast(msg, type = "ok") {
    const id = toastId.current++;
    setToasts(prev => [{ id, msg, type }, ...prev].slice(0, 4));
    setTimeout(() => {
      setToasts(prev => prev.filter(x => x.id !== id));
    }, 4200);
  }

  /* ===============================
     BUSCAR CLIENTES
  =============================== */
  async function fetchClientes() {

    const firebirdFinal =
      firebirdFiltro === "outro" ? firebirdLivre : firebirdFiltro;

    const params = new URLSearchParams({
      q,
      versao,
      firebird: firebirdFinal,
      page: String(page),
      perPage: String(perPage)
    });

    try {
      setLoading(true);
      const r = await apiFetch(`backend/clientes.php?${params.toString()}`);
      const j = await r.json();

      if (!j.success) {
        pushToast(j.error || "Erro ao listar.", "err");
        return;
      }

      setItems(j.items || []);
      setTotal(j.total || 0);

    } catch {
      pushToast("Erro ao consultar.", "err");
    } finally {
      setLoading(false);
    }
  }

  /* ===============================
     EFFECTS
  =============================== */
  useEffect(() => {
    const t = setTimeout(() => setPage(1), 300);
    return () => clearTimeout(t);
  }, [q, versao, firebirdFiltro, firebirdLivre]);

  useEffect(() => {
    fetchClientes();
  }, [page, perPage, q, versao, firebirdFiltro, firebirdLivre]);

  const totalPages = useMemo(
    () => Math.max(1, Math.ceil(total / perPage)),
    [total, perPage]
  );

  /* ===============================
     CRUD
  =============================== */
  function openNew() {
    setEdit({
      id: 0,
      codigotga: "",
      nome_empresa: "",
      cnpj: "",
      versao: "",
      firebird: "",
      info_adicional: "",
      qntusuarios: 0,
      senhapadrao: "tga@1234"
    });
    setOpenForm(true);
  }

  function openEdit(row) {
    setEdit({ ...row });
    setOpenForm(true);
  }

  async function saveForm() {
    if (!edit) return;

    const payload = {
      ...edit,
      qntusuarios: Number(edit.qntusuarios || 0)
    };

    if (!payload.codigotga || !payload.nome_empresa || !payload.cnpj) {
      pushToast("Código TGA, Empresa e CNPJ são obrigatórios.", "err");
      return;
    }

    try {
      const r = await apiFetch("backend/clientes.php", {
        method: payload.id ? "PUT" : "POST",
        body: JSON.stringify(payload)
      });

      const j = await r.json();
      if (!j.success) {
        pushToast(j.error || "Erro ao salvar.", "err");
        return;
      }

      pushToast(payload.id ? "Atualizado com sucesso." : "Cadastrado com sucesso.");
      setOpenForm(false);
      setEdit(null);
      fetchClientes();

    } catch {
      pushToast("Erro ao salvar.", "err");
    }
  }

  async function removeRow(row) {
    if (!confirm(`Excluir "${row.nome_empresa}"?`)) return;

    try {
      const r = await apiFetch(`backend/clientes.php?id=${row.id}`, {
        method: "DELETE"
      });
      const j = await r.json();

      if (!j.success) {
        pushToast(j.error || "Erro ao excluir.", "err");
        return;
      }

      pushToast("Excluído com sucesso.");
      fetchClientes();

    } catch {
      pushToast("Erro ao excluir.", "err");
    }
  }

  /* ===============================
     RENDER
  =============================== */
  return (
    <div className="container">

      <div className="header">
        <div className="title">
          <h1>Clientes TGA Web — Cadastros</h1>
          <p>Painel administrativo</p>
        </div>
        <button className="btn primary" onClick={openNew}>+ Novo Cliente</button>
      </div>

      <div className="card">

        {/* ================= FILTROS ================= */}
        <div className="toolbar">
          <input className="input" value={q}
            onChange={e => setQ(e.target.value)}
            placeholder="Buscar..." />

          <input className="input" value={versao}
            onChange={e => setVersao(e.target.value)}
            placeholder="Versão" />

          <select className="select"
            value={firebirdFiltro}
            onChange={e => setFirebirdFiltro(e.target.value)}>
            <option value="">Firebird (todos)</option>
            <option value="2.5">Firebird 2.5</option>
            <option value="5.0">Firebird 5.0</option>
            <option value="outro">Outro</option>
          </select>

          {firebirdFiltro === "outro" && (
            <input
              className="input"
              placeholder="Versão Firebird"
              value={firebirdLivre}
              onChange={e => setFirebirdLivre(e.target.value)}
            />
          )}

          <select className="select" value={perPage}
            onChange={e => { setPage(1); setPerPage(Number(e.target.value)); }}>
            <option value={10}>10 / pág</option>
            <option value={20}>20 / pág</option>
            <option value={50}>50 / pág</option>
            <option value={100}>100 / pág</option>
          </select>

          <button className="btn"
            onClick={() => {
              setQ(""); setVersao("");
              setFirebirdFiltro(""); setFirebirdLivre("");
              setPage(1);
            }}>
            Limpar
          </button>

          <span style={{ marginLeft: "auto" }}>
            Total: <b>{total}</b>
          </span>
        </div>

        {/* ================= TABELA ================= */}
        <div className="tableWrap">
          <table>
            <thead>
              <tr>
                <th>Código</th>
                <th>Empresa</th>
                <th>CNPJ</th>
                <th>Versão</th>
                <th>Firebird</th>
                <th>Info</th>
                <th>Usuários</th>
                <th>Atualizado</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              {items.length === 0 ? (
                <tr>
                  <td colSpan="9">{loading ? "Carregando..." : "Nenhum registro."}</td>
                </tr>
              ) : items.map(row => (
                <tr key={row.id}>
                  <td>{row.codigotga}</td>
                  <td>{row.nome_empresa}</td>
                  <td>{row.cnpj}</td>
                  <td>{row.versao || "-"}</td>
                  <td>{row.firebird || "-"}</td>
                  <td>{row.info_adicional || "-"}</td>
                  <td>{row.qntusuarios}</td>
                  <td>{formatDate(row.updated_at)}</td>
                  <td>
                    <button className="btn" onClick={() => openEdit(row)}>Editar</button>
                    <button className="btn danger" onClick={() => removeRow(row)}>Excluir</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* ================= PAGINAÇÃO ================= */}
        <div className="footerBar">
          Página {page} de {totalPages}
          <div>
            <button className="btn" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>◀</button>
            <button className="btn" disabled={page >= totalPages} onClick={() => setPage(p => p + 1)}>▶</button>
          </div>
        </div>
      </div>

      {/* ================= MODAL ================= */}
      <Modal open={openForm}
        title={edit?.id ? "Editar cliente" : "Novo cliente"}
        onClose={() => { setOpenForm(false); setEdit(null); }}>

        {edit && (
          <>
            <div className="grid">

              <input className="input field" placeholder="Código TGA"
                value={edit.codigotga}
                onChange={e => setEdit(s => ({ ...s, codigotga: e.target.value }))} />

              <input className="input field" placeholder="Empresa"
                value={edit.nome_empresa}
                onChange={e => setEdit(s => ({ ...s, nome_empresa: e.target.value }))} />

              <input className="input field" placeholder="CNPJ"
                value={edit.cnpj}
                onChange={e => setEdit(s => ({ ...s, cnpj: e.target.value }))} />

              <input className="input field" placeholder="Versão"
                value={edit.versao}
                onChange={e => setEdit(s => ({ ...s, versao: e.target.value }))} />

              <input className="input field" placeholder="Firebird"
                value={edit.firebird}
                onChange={e => setEdit(s => ({ ...s, firebird: e.target.value }))} />

              <input className="input field" type="number" placeholder="Usuários"
                value={edit.qntusuarios}
                onChange={e => setEdit(s => ({ ...s, qntusuarios: e.target.value }))} />

              {/* INFO ADICIONAL */}
              <select className="select"
                value={edit.info_adicional === "MIGRADO" ? "MIGRADO" : "outro"}
                onChange={e => {
                  if (e.target.value === "MIGRADO") {
                    setEdit(s => ({ ...s, info_adicional: "MIGRADO" }));
                  } else {
                    setEdit(s => ({ ...s, info_adicional: "" }));
                  }
                }}>
                <option value="outro">Info livre</option>
                <option value="MIGRADO">MIGRADO</option>
              </select>

              {edit.info_adicional !== "MIGRADO" && (
                <input className="input field" placeholder="Info adicional"
                  value={edit.info_adicional}
                  onChange={e => setEdit(s => ({ ...s, info_adicional: e.target.value }))} />
              )}

            </div>

            <div style={{ display: "flex", justifyContent: "flex-end", gap: 10 }}>
              <button className="btn" onClick={() => setOpenForm(false)}>Cancelar</button>
              <button className="btn primary" onClick={saveForm}>Salvar</button>
            </div>
          </>
        )}
      </Modal>

      <Toast items={toasts} onClose={id => setToasts(t => t.filter(x => x.id !== id))} />
    </div>
  );
}

/* ===================================================
   INIT
=================================================== */
ReactDOM.createRoot(document.getElementById("app")).render(<App />);
