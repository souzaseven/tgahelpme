"""
Monitoramento de URLs do navegador (Windows, Chrome/Edge/Brave/Opera via leitura do arquivo de histórico local).
"""
import os
import shutil
import sqlite3
import time
from datetime import datetime

class BrowserMonitor:
    def __init__(self):
        self.last_visit_time = 0
        self.history_paths = self._get_browser_history_paths()

    def _get_browser_history_paths(self):
        user = os.getlogin()
        base = f"C:/Users/{user}/AppData/Local/"
        browsers = {
            "chrome": base + "Google/Chrome/User Data/Default/History",
            "edge": base + "Microsoft/Edge/User Data/Default/History",
            "brave": base + "BraveSoftware/Brave-Browser/User Data/Default/History",
            "opera": base + "Opera Software/Opera Stable/History"
        }
        return browsers

    def get_recent_urls(self, since=0):
        urls = []
        for name, path in self.history_paths.items():
            if not os.path.exists(path):
                continue
            # Precisa copiar o arquivo pois o navegador pode estar usando
            tmp_path = path + ".tmp"
            try:
                shutil.copy2(path, tmp_path)
                conn = sqlite3.connect(tmp_path)
                cursor = conn.cursor()
                cursor.execute("SELECT url, title, last_visit_time FROM urls WHERE last_visit_time > ? ORDER BY last_visit_time DESC LIMIT 10", (since,))
                for url, title, visit_time in cursor.fetchall():
                    urls.append({
                        "browser": name,
                        "url": url,
                        "title": title,
                        "visit_time": visit_time
                    })
                conn.close()
                os.remove(tmp_path)
            except Exception as e:
                pass
        return urls
