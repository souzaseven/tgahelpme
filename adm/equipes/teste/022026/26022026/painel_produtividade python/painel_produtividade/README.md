# 📊 Painel Produtividade Evolux

Painel web em Python/Flask para consulta de produtividade de operadores e filas da plataforma Evolux.

---

## 📁 Estrutura

```
painel_produtividade/
├── app.py                  ← Servidor Flask (rotas)
├── config.py               ← Token e URL da API
├── requirements.txt        ← Dependências Python
├── services/
│   └── evolux_service.py   ← Comunicação com API Evolux
└── templates/
    └── index.html          ← Interface do painel
```

---

## 🚀 Como rodar

### 1. Instalar dependências

```bash
pip install -r requirements.txt
```

### 2. Iniciar o servidor

```bash
python app.py
```

### 3. Acessar no navegador

```
http://localhost:5000
```

---

## ⚙️ Configurações (config.py)

| Variável   | Descrição                      |
|------------|-------------------------------|
| `BASE_URL` | URL base da API Evolux         |
| `TOKEN`    | Token de autenticação          |
| `TIMEOUT`  | Timeout das requisições (seg.) |

---

## 🔌 Endpoints da API usados

| Endpoint                            | Descrição                     |
|-------------------------------------|-------------------------------|
| `/api/v1/report/agents_performance` | Produtividade de operadores   |
| `/api/v1/queues/{id}`               | Dados de uma fila             |
| `/api/v1/queues`                    | Lista de filas (filtro)       |

---

## 🔎 Filtros disponíveis no painel

- **Data Inicial / Final** — período da consulta (horário de Brasília)
- **Filas** — IDs separados por vírgula (ex: `108,85`) ou `all`
- **Agente** — ID específico ou `all`

---

## 📡 Endpoint JSON interno

Para integrar com outras ferramentas ou fazer refresh automático:

```
GET /api/performance?start_date=...&end_date=...&queue_ids=all&agent_ids=all
```
