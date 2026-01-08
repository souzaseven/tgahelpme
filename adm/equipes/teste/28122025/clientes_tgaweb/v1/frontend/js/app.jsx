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
          <div style={{display:"flex",justifyContent:"space-between",gap:10}}>
            <div>{t.msg}</div>
            <button
              className="btn ghost"
              onClick={() => onClose(t.id)}
              style={{padding:"6px 10px"}}
            >
              ✕
            </button>
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
   APP PRINCIPAL (SEM LOGIN)
=================================================== */
function App() {

  /* ===============================
     ESTADO
  =============================== */
  const [loading, setLoading] = useState(false);

  // filtros
  const [q, setQ] = useState("");
  const [versao, setVersao] = useState("");
  const [firebird, setFirebird] = useState("");

  // paginação
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [total, setTotal] = useState(0);

  // dados
  const [items, setItems] = useState([]);

  // modal CRUD
  const [openForm, setOpenForm] = useState(false);
  const [edit, setEdit] = useState(null);

  // toast
  const [toasts, setToasts] = useState([]);
  const toastId = useRef(1);

  /* ===============================
     TOAST
  =============================== */
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
    const params = new URLSearchParams({
      q,
      versao,
      firebird,
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

  // debounce busca
  useEffect(() => {
    const t = setTimeout(() => setPage(1), 250);
    return () => clearTimeout(t);
  }, [q, versao, firebird]);

  useEffect(() => {
    fetchClientes();
  }, [page, perPage, q, versao, firebird]);

  /* ===============================
     PAGINAÇÃO
  =============================== */
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
      id: edit.id,
      codigotga: edit.codigotga.trim(),
      nome_empresa: edit.nome_empresa.trim(),
      cnpj: edit.cnpj.trim(),
      versao: edit.versao.trim(),
      firebird: edit.firebird.trim(),
      info_adicional: edit.info_adicional.trim(),
      qntusuarios: Number(edit.qntusuarios || 0),
      senhapadrao: edit.senhapadrao.trim()
    };

    if (!payload.codigotga || !payload.nome_empresa || !payload.cnpj) {
      pushToast("Código TGA, Empresa e CNPJ são obrigatórios.", "err");
      return;
    }

    const method = payload.id ? "PUT" : "POST";

    try {
      const r = await apiFetch("backend/clientes.php", {
        method,
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
    if (!row?.id) return;
    if (!confirm(`Excluir "${row.nome_empresa}" (${row.codigotga})?`)) return;

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

      if (items.length === 1 && page > 1) {
        setPage(p => p - 1);
      } else {
        fetchClientes();
      }

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
          <p>Painel administrativo (React + PHP + MySQL)</p>
        </div>
        <button className="btn primary" onClick={openNew}>
          + Novo Cliente
        </button>
      </div>

      <div className="card">

        <div className="toolbar">
          <input
            className="input"
            value={q}
            onChange={e => setQ(e.target.value)}
            placeholder="Buscar (código, empresa, CNPJ, info)"
          />
          <input
            className="input"
            value={versao}
            onChange={e => setVersao(e.target.value)}
            placeholder="Versão (ex: 25.12)"
          />
          <input
            className="input"
            value={firebird}
            onChange={e => setFirebird(e.target.value)}
            placeholder="Firebird (ex: 2.5 / 5.0)"
          />

          <select
            className="select"
            value={perPage}
            onChange={e => {
              setPage(1);
              setPerPage(Number(e.target.value));
            }}
          >
            <option value={10}>10 / pág</option>
            <option value={20}>20 / pág</option>
            <option value={50}>50 / pág</option>
            <option value={100}>100 / pág</option>
          </select>

          <button
            className="btn"
            onClick={() => {
              setQ("");
              setVersao("");
              setFirebird("");
              setPage(1);
            }}
          >
            Limpar filtros
          </button>

          <span style={{marginLeft:"auto", color:"var(--muted)"}}>
            Total: <b style={{color:"var(--text)"}}>{total}</b>
          </span>
        </div>

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
                <th>Senha</th>
                <th>Atualizado</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              {items.length === 0 ? (
                <tr>
                  <td colSpan="10" style={{color:"var(--muted)"}}>
                    {loading ? "Carregando..." : "Nenhum registro encontrado."}
                  </td>
                </tr>
              ) : items.map(row => (
                <tr key={row.id}>
                  <td><span className="badge">{row.codigotga}</span></td>
                  <td>{row.nome_empresa}</td>
                  <td>{row.cnpj}</td>
                  <td>{row.versao || "-"}</td>
                  <td>{row.firebird || "-"}</td>
                  <td>{row.info_adicional || "-"}</td>
                  <td>{row.qntusuarios ?? 0}</td>
                  <td><span className="badge">••••••••</span></td>
                  <td>{formatDate(row.updated_at)}</td>
                  <td>
                    <div className="actions">
                      <button className="btn" onClick={() => openEdit(row)}>Editar</button>
                      <button className="btn danger" onClick={() => removeRow(row)}>Excluir</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="footerBar">
          <div>
            Página <b>{page}</b> de <b>{totalPages}</b>
          </div>
          <div style={{display:"flex", gap:10}}>
            <button className="btn" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>◀</button>
            <button className="btn" disabled={page >= totalPages} onClick={() => setPage(p => p + 1)}>▶</button>
          </div>
        </div>
      </div>

      <Modal
        open={openForm}
        title={edit?.id ? "Editar cliente" : "Novo cliente"}
        onClose={() => { setOpenForm(false); setEdit(null); }}
      >
        {!edit ? null : (
          <>
            <div className="grid">
              <div>
                <label className="label">Código TGA *</label>
                <input className="input field" value={edit.codigotga}
                  onChange={e => setEdit(s => ({ ...s, codigotga: e.target.value }))} />
              </div>

              <div>
                <label className="label">Empresa *</label>
                <input className="input field" value={edit.nome_empresa}
                  onChange={e => setEdit(s => ({ ...s, nome_empresa: e.target.value }))} />
              </div>

              <div>
                <label className="label">CNPJ *</label>
                <input className="input field" value={edit.cnpj}
                  onChange={e => setEdit(s => ({ ...s, cnpj: e.target.value }))} />
              </div>

              <div>
                <label className="label">Versão</label>
                <input className="input field" value={edit.versao}
                  onChange={e => setEdit(s => ({ ...s, versao: e.target.value }))} />
              </div>

              <div>
                <label className="label">Firebird</label>
                <input className="input field" value={edit.firebird}
                  onChange={e => setEdit(s => ({ ...s, firebird: e.target.value }))} />
              </div>

              <div>
                <label className="label">Usuários</label>
                <input className="input field" type="number" value={edit.qntusuarios}
                  onChange={e => setEdit(s => ({ ...s, qntusuarios: e.target.value }))} />
              </div>

              <div style={{gridColumn:"1 / -1"}}>
                <label className="label">Info adicional</label>
                <input className="input field" value={edit.info_adicional}
                  onChange={e => setEdit(s => ({ ...s, info_adicional: e.target.value }))} />
              </div>

              <div style={{gridColumn:"1 / -1"}}>
                <label className="label">Senha padrão</label>
                <input className="input field" value={edit.senhapadrao}
                  onChange={e => setEdit(s => ({ ...s, senhapadrao: e.target.value }))} />
              </div>
            </div>

            <div style={{display:"flex", gap:10, marginTop:14, justifyContent:"flex-end"}}>
              <button className="btn" onClick={() => { setOpenForm(false); setEdit(null); }}>Cancelar</button>
              <button className="btn primary" onClick={saveForm}>Salvar</button>
            </div>
          </>
        )}
      </Modal>

      <Toast
        items={toasts}
        onClose={(id) => setToasts(t => t.filter(x => x.id !== id))}
      />
    </div>
  );
}

/* ===================================================
   INIT
=================================================== */
ReactDOM.createRoot(document.getElementById("app")).render(<App />);
