"""
Gerenciamento de conexão com o banco Firebird.

Como a ferramenta é de uso local/individual (um inspetor de banco), mantemos
UMA conexão ativa por vez em memória (singleton), aberta explicitamente pelo
usuário na tela de conexão e reutilizada por todos os endpoints da API.
"""
from __future__ import annotations

import threading
from dataclasses import dataclass, asdict
from typing import Optional

from firebird.driver import connect as fb_connect, DatabaseError, driver_config


@dataclass
class ConnectionInfo:
    database: str
    host: str = "localhost"
    port: int = 3050
    user: str = "SYSDBA"
    charset: str = "UTF8"
    role: Optional[str] = None

    def as_public_dict(self) -> dict:
        """Retorna os dados de conexão sem a senha, para exibir na UI."""
        data = asdict(self)
        return data


def _abrir_conexao_bruta(
    database: str,
    host: str = "localhost",
    port: int = 3050,
    user: str = "SYSDBA",
    password: str = "",
    charset: str = "UTF8",
    role: Optional[str] = None,
):
    """Abre uma conexão Firebird "crua" (sem passar pelo singleton). Lança ConnectionError traduzido."""
    dsn = f"{host}/{port}:{database}" if host else database
    try:
        return fb_connect(
            dsn,
            user=user,
            password=password,
            charset=charset or "UTF8",
            role=role or None,
        )
    except DatabaseError as exc:
        raise ConnectionError(_traduzir_erro(str(exc))) from exc


def _get_version(con) -> str:
    cur = con.cursor()
    try:
        cur.execute(
            "SELECT rdb$get_context('SYSTEM', 'ENGINE_VERSION') AS version "
            "FROM rdb$database"
        )
        row = cur.fetchone()
        return row[0] if row else "desconhecida"
    finally:
        cur.close()


def test_connection(
    database: str,
    host: str = "localhost",
    port: int = 3050,
    user: str = "SYSDBA",
    password: str = "",
    charset: str = "UTF8",
    role: Optional[str] = None,
) -> str:
    """Abre uma conexão apenas para validar (não interfere na conexão ativa da ferramenta)."""
    con = _abrir_conexao_bruta(
        database, host=host, port=port, user=user, password=password, charset=charset, role=role
    )
    try:
        return _get_version(con)
    finally:
        try:
            con.close()
        except Exception:
            pass


class ConnectionManager:
    """Mantém a conexão Firebird ativa da sessão local da ferramenta."""

    def __init__(self) -> None:
        self._lock = threading.Lock()
        self._con = None
        self._info: Optional[ConnectionInfo] = None

    @property
    def is_connected(self) -> bool:
        return self._con is not None

    @property
    def info(self) -> Optional[ConnectionInfo]:
        return self._info

    def connect(
        self,
        database: str,
        host: str = "localhost",
        port: int = 3050,
        user: str = "SYSDBA",
        password: str = "",
        charset: str = "UTF8",
        role: Optional[str] = None,
    ) -> str:
        """Abre a conexão e retorna a versão do engine Firebird."""
        with self._lock:
            self.close()
            self._con = _abrir_conexao_bruta(
                database, host=host, port=port, user=user, password=password, charset=charset, role=role
            )
            self._info = ConnectionInfo(
                database=database,
                host=host,
                port=port,
                user=user,
                charset=charset,
                role=role,
            )
            return _get_version(self._con)

    def get_connection(self):
        if self._con is None:
            raise ConnectionError("Nenhuma conexão ativa. Conecte-se a um banco primeiro.")
        return self._con

    def close(self) -> None:
        if self._con is not None:
            try:
                self._con.close()
            except Exception:
                pass
        self._con = None
        self._info = None


def _traduzir_erro(mensagem: str) -> str:
    """Traduz mensagens comuns de erro do Firebird para o português."""
    m = mensagem.lower()
    if "unable to complete network request" in m or "connection refused" in m:
        return (
            "Não foi possível conectar ao servidor Firebird. Verifique se o "
            "serviço está rodando e se o servidor/porta estão corretos."
        )
    if "no such file or directory" in m or "file not found" in m or "arquivo" in m:
        return "Banco de dados não encontrado no caminho informado."
    if "your user name and password are not defined" in m or "login" in m:
        return "Usuário ou senha inválidos."
    if "password" in m:
        return "Usuário ou senha inválidos."
    return f"Falha ao conectar: {mensagem}"


# Instância única compartilhada pela aplicação
manager = ConnectionManager()
