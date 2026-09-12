
from flask import Flask, render_template, jsonify
import os

app = Flask(__name__)

# Rota para métricas em tempo real
@app.route('/api/metrics')
def api_metrics():
    metrics_path = os.path.abspath(os.path.join(os.path.dirname(__file__), '../logs/metrics.json'))
    metrics = {
        'clicks': 0,
        'keystrokes': 0,
        'words': 0,
        'app_times': {}
    }
    if os.path.exists(metrics_path):
        try:
            import json
            with open(metrics_path, 'r', encoding='utf-8') as f:
                metrics = json.load(f)
        except Exception:
            pass
    return jsonify(metrics)

@app.route('/')
def dashboard():
    # Contagem real de screenshots
    screenshots_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), '../screenshots'))
    screenshots = [f for f in os.listdir(screenshots_dir) if f.endswith('.png')] if os.path.exists(screenshots_dir) else []

    # Busca de logs
    logs_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), '../logs'))
    logs = [f for f in os.listdir(logs_dir) if f.endswith('.log')] if os.path.exists(logs_dir) else []

    # Lê métricas reais do agente
    metrics_path = os.path.abspath(os.path.join(os.path.dirname(__file__), '../logs/metrics.json'))
    metrics = {
        'clicks': 0,
        'keystrokes': 0,
        'words': 0,
        'app_times': {}
    }
    if os.path.exists(metrics_path):
        try:
            import json
            with open(metrics_path, 'r', encoding='utf-8') as f:
                metrics = json.load(f)
        except Exception:
            pass

    # Exemplo de uploads pendentes (ajuste conforme implementação real)
    uploads_pendentes = 0  # Substitua por leitura real se houver

    # Exemplo de agentes ativos (ajuste conforme implementação real)
    agentes_ativos = 1  # Substitua por leitura real se houver

    resumo = {
        'agentes_ativos': agentes_ativos,
        'uploads_pendentes': uploads_pendentes,
        'screenshots': len(screenshots),
        'logs_monitoramento': len(logs)
    }
    return render_template('dashboard.html', resumo=resumo, screenshots=screenshots, logs=logs, metrics=metrics)

if __name__ == '__main__':
    app.run(debug=True)
