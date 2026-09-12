"""
Uploader mock para envio de dados à API.
"""
import requests
import json

class Uploader:
    def __init__(self, api_url="http://localhost:8000/api/events", token="demo-token"):
        self.api_url = api_url
        self.token = token

    def send(self, data):
        headers = {"Authorization": f"Bearer {self.token}"}
        try:
            resp = requests.post(self.api_url, json=data, headers=headers, timeout=10)
            if resp.status_code == 200:
                print("[OK] Dados enviados para API.")
            else:
                print(f"[ERRO] Falha ao enviar dados: {resp.status_code} - {resp.text}")
        except Exception as e:
            print(f"[MOCK] Erro ao enviar para API, exibindo dados localmente: {e}")
            print(json.dumps(data, indent=2))
