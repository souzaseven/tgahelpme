"""
Quallit Manager — reconstrução da interface.

Espelha a stack do sistema de referência (tga.qloudme.com.br, "Quallit Manager"),
que é uma aplicação Flask com renderização server-side e templates Jinja2:

    Browser
      -> HTML/CSS/JS  (templates/ + static/)
      -> Flask        (este arquivo)
      -> sessão / autenticação (mock em memória)
      -> dados        (data/mock.json)

As telas internas foram reconstruídas a partir de screenshots fornecidos pelo
operador do sistema. TODOS os dados operacionais (nomes de clientes, CNPJs,
e-mails, usuários AD, IPs e hostnames internos) foram substituídos por valores
FICTÍCIOS em data/mock.json. As ações destrutivas (parar serviço, desativar
usuário, limpar perfil, resetar senha) são apenas demonstrativas — não fazem nada.

Como executar:
    pip install -r requirements.txt
    python app.py
    # abre em http://localhost:5000  (redireciona para /login)

Usuário de demonstração: admin / admin
"""

import json
import os
import re
from datetime import datetime
from functools import wraps
from pathlib import Path
from urllib.parse import urlparse

from flask import (
    Flask,
    flash,
    redirect,
    render_template,
    request,
    session,
    url_for,
)
from markupsafe import Markup, escape
from werkzeug.security import check_password_hash, generate_password_hash

BASE_DIR = Path(__file__).parent

app = Flask(__name__)
# Em produção defina SECRET_KEY no ambiente; o valor abaixo serve só para dev.
app.secret_key = os.environ.get("SECRET_KEY", "dev-only-nao-use-em-producao")

# Store de usuários de demonstração. Troque por um banco/serviço real depois.
USERS = {
    "admin": generate_password_hash(os.environ.get("DEMO_PASSWORD", "admin")),
}

# Mensagens equivalentes às do sistema de referência.
MSG_LOGIN_REQUERIDO = "Por favor, faça login para acessar esta página."
MSG_CREDENCIAL_INVALIDA = "Usuário ou senha inválidos."

# Menu lateral. `icon` = nome do glifo Bootstrap Icons (bi-<icon>).
MENU = [
    {"slug": "dashboard", "label": "Dashboard", "icon": "speedometer2"},
    {"slug": "clientes", "label": "Clientes", "icon": "people"},
    {"slug": "usuarios-ad", "label": "Usuários AD", "icon": "person-vcard"},
    {"slug": "servicos-windows", "label": "Serviços Windows", "icon": "gear-wide-connected"},
    {"slug": "limpeza-perfil", "label": "Limpeza de Perfil", "icon": "eraser"},
    {"slug": "cloudbackup", "label": "CloudBackup", "icon": "cloud-arrow-up"},
    {"slug": "usuarios-conectados", "label": "Usuários Conectados", "icon": "display"},
    {"slug": "acesso-servidor-tga", "label": "Acesso Servidor TGA", "icon": "hdd-stack"},
    {"slug": "datacenter-qloudme", "label": "Datacenter Qloudme", "icon": "server"},
    {"slug": "manutencao-firebird", "label": "Manutenção Firebird", "icon": "database-gear"},
    {"slug": "versionamento", "label": "Versionamento", "icon": "code-square"},
    {"slug": "relatorios", "label": "Relatórios", "icon": "file-earmark-bar-graph"},
    {"slug": "seguranca-2fa", "label": "Segurança 2FA", "icon": "shield-lock"},
    {"slug": "auditoria-logins", "label": "Auditoria de Logins", "icon": "clipboard-check"},
]
MENU_BY_SLUG = {item["slug"]: item for item in MENU}
MENU_BY_SLUG["resetar-senhas"] = {
    "slug": "resetar-senhas", "label": "Resetar Senhas", "icon": "key",
}

# Slugs do menu que já têm tela dedicada (o resto cai no placeholder /secao).
IMPLEMENTED_ROUTES = {
    "dashboard": "dashboard",
    "clientes": "clientes",
    "usuarios-ad": "usuarios_ad",
    "servicos-windows": "servicos_windows",
    "limpeza-perfil": "limpeza_perfil",
    "usuarios-conectados": "usuarios_conectados",
    "acesso-servidor-tga": "acesso_servidor_tga",
    "datacenter-qloudme": "datacenter_qloudme",
    "versionamento": "versionamento",
    "relatorios": "relatorios",
    "auditoria-logins": "auditoria_logins",
}


def load_mock():
    """Carrega os dados de exibição (KPIs, gráfico, tabelas...) do JSON."""
    return json.loads((BASE_DIR / "data" / "mock.json").read_text(encoding="utf-8"))


def _next_seguro():
    """Retorna o parâmetro ?next apenas se for um caminho relativo local.

    Evita open redirect — o sistema original repassa ?next sem validar; aqui
    só aceitamos algo como "/dashboard".
    """
    alvo = request.values.get("next", "")
    partes = urlparse(alvo)
    if alvo.startswith("/") and not partes.scheme and not partes.netloc:
        return alvo
    return None


def login_required(view):
    @wraps(view)
    def wrapper(*args, **kwargs):
        if not session.get("user"):
            flash(MSG_LOGIN_REQUERIDO, "info")
            return redirect(url_for("login", next=request.path))
        return view(*args, **kwargs)

    return wrapper


@app.context_processor
def inject_layout():
    """Disponibiliza o menu, o usuário logado e o resolvedor de URL do menu."""

    def nav_url(slug):
        endpoint = IMPLEMENTED_ROUTES.get(slug)
        return url_for(endpoint) if endpoint else url_for("secao", slug=slug)

    return {
        "menu": MENU,
        "current_user": session.get("user"),
        "current_role": "Gerente",
        "nav_url": nav_url,
    }


@app.template_filter("contadores")
def contadores(texto):
    """Destaca números em textos de scan ("0 novo(s)", "3 erro(s)"...).

    Escapa o texto primeiro; só então injeta os <span>.
    """
    seguro = str(escape(texto))

    def repl(m):
        n = int(m.group(1))
        palavra = m.group(0)
        if n == 0:
            classe = "ok"
        elif "arquivo" in palavra:
            classe = "num"
        else:
            classe = "warn"
        return f'<span class="{classe}">{palavra}</span>'

    seguro = re.sub(r"(\d+)\s(?:arquivo|novo|alterado|erro)\(s\)", repl, seguro)
    return Markup(seguro)


@app.get("/")
def index():
    return redirect(url_for("dashboard"))


@app.route("/login", methods=["GET", "POST"])
def login():
    if session.get("user"):
        return redirect(_next_seguro() or url_for("dashboard"))

    if request.method == "POST":
        usuario = request.form.get("username", "").strip()
        senha = request.form.get("password", "")
        hash_salvo = USERS.get(usuario)
        if hash_salvo and check_password_hash(hash_salvo, senha):
            session["user"] = usuario
            return redirect(_next_seguro() or url_for("dashboard"))
        flash(MSG_CREDENCIAL_INVALIDA, "error")

    return render_template("login.html", next_url=_next_seguro())


@app.post("/logout")
def logout():
    session.clear()
    return redirect(url_for("login"))


@app.get("/dashboard")
@login_required
def dashboard():
    return render_template("dashboard.html", data=load_mock(), active="dashboard")


@app.get("/clientes")
@login_required
def clientes():
    return render_template("clientes.html", clientes=load_mock()["clientes"], active="clientes")


@app.get("/usuarios-ad")
@login_required
def usuarios_ad():
    return render_template(
        "usuarios_ad.html", usuarios=load_mock()["usuarios_ad"], active="usuarios-ad"
    )


@app.get("/servicos-windows")
@login_required
def servicos_windows():
    dados = load_mock()["servicos_windows"]
    return render_template(
        "servicos_windows.html",
        servicos=dados["servicos"],
        nota=dados["nota"],
        active="servicos-windows",
    )


@app.get("/limpeza-perfil")
@login_required
def limpeza_perfil():
    return render_template(
        "limpeza_perfil.html", info=load_mock()["limpeza_perfil"], active="limpeza-perfil"
    )


@app.get("/usuarios-conectados")
@login_required
def usuarios_conectados():
    dados = load_mock()["sessoes"]
    pico = max((s["sessoes"] for s in dados["servidores"]), default=1)
    # Escala das barras contra uma capacidade plausível por servidor (não só o
    # pico), para não exagerar diferenças pequenas entre 28 e 31 sessões.
    escala = max(pico, 40)
    return render_template(
        "usuarios_conectados.html",
        sessoes=dados,
        maximo=escala,
        atualizado_em=datetime.now().strftime("%H:%M:%S"),
        active="usuarios-conectados",
    )


@app.get("/acesso-servidor-tga")
@login_required
def acesso_servidor_tga():
    return render_template(
        "rdp.html", servidores=load_mock()["rdp"], active="acesso-servidor-tga"
    )


@app.get("/datacenter-qloudme")
@login_required
def datacenter_qloudme():
    dados = load_mock()["datacenter"]
    return render_template(
        "datacenter.html",
        servidores=dados["servidores"],
        nota=dados["nota"],
        active="datacenter-qloudme",
    )


@app.get("/versionamento")
@login_required
def versionamento():
    return render_template(
        "versionamento.html", v=load_mock()["versionamento"], active="versionamento"
    )


@app.get("/relatorios")
@login_required
def relatorios():
    return render_template(
        "relatorios.html", rel=load_mock()["relatorios"], active="relatorios"
    )


@app.get("/auditoria-logins")
@login_required
def auditoria_logins():
    return render_template(
        "auditoria_logins.html", aud=load_mock()["auditoria_logins"], active="auditoria-logins"
    )


@app.get("/secao/<slug>")
@login_required
def secao(slug):
    item = MENU_BY_SLUG.get(slug)
    if not item:
        return render_template("secao.html", label=slug, icon="folder", active=slug), 404
    return render_template("secao.html", label=item["label"], icon=item["icon"], active=slug)


if __name__ == "__main__":
    app.run(debug=True, port=5000)
