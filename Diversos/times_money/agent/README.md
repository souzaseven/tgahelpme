# Agente Desktop - Times Money Clone

## Funcionalidades implementadas
- Monitoramento de janela/processo ativo
- Captura de screenshots periódicas (a cada 5 minutos)
- Detecção de inatividade (sem mouse/teclado por 5 minutos)
- Monitoramento de URLs do navegador (Chrome/Edge/Brave/Opera)
- Envio real de dados para API (com fallback mock em caso de erro)
- Execução discreta na bandeja do sistema (tray)

## Como configurar
1. Edite o arquivo `config.json` na raiz da pasta `agent`:
   ```json
   {
     "api_url": "http://SEU_BACKEND/api/events",
     "token": "SEU_TOKEN_AQUI"
   }
   ```
   Ou defina as variáveis de ambiente `AGENT_API_URL` e `AGENT_TOKEN`.

## Como executar
1. Instale as dependências:
   ```bash
   pip install -r requirements.txt
   ```
2. Execute o agente:
   ```bash
   python main.py
   ```
   O agente ficará rodando na bandeja do sistema (ícone próximo ao relógio do Windows). Para encerrar, clique com o botão direito no ícone e escolha "Sair".

## Como empacotar como executável (Windows)
1. Instale o PyInstaller:
   ```bash
   pip install pyinstaller
   ```
2. Gere o executável:
   ```bash
   pyinstaller --onefile --noconsole main.py
   ```
   O executável estará em `dist/main.exe`.

-
- Autenticação real por token

- Suporte a macOS
- Instalação automática como serviço
- Configuração via arquivo/env
