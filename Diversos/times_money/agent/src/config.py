import os
import json

CONFIG_PATH = os.path.join(os.path.dirname(os.path.dirname(__file__)), "config.json")

def load_config():
    config = {}
    # 1. Tenta carregar do arquivo config.json
    if os.path.exists(CONFIG_PATH):
        with open(CONFIG_PATH, "r", encoding="utf-8") as f:
            config = json.load(f)
    # 2. Sobrescreve por variáveis de ambiente, se existirem
    config["api_url"] = os.environ.get("AGENT_API_URL", config.get("api_url", ""))
    config["token"] = os.environ.get("AGENT_TOKEN", config.get("token", ""))
    return config
