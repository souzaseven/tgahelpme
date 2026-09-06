@echo off
REM Inicia o servidor de desenvolvimento. Duplo clique ou "run" no terminal.
cd /d "%~dp0"

if not exist ".venv\Scripts\python.exe" (
  echo [erro] Ambiente virtual nao encontrado. Rode primeiro:
  echo     py -m venv .venv ^&^& .venv\Scripts\python.exe -m pip install -r requirements.txt
  pause
  exit /b 1
)

if not exist ".env" copy ".env.example" ".env" >nul

".venv\Scripts\python.exe" -m uvicorn app.main:app --reload --host 127.0.0.1 --port 8000
pause
