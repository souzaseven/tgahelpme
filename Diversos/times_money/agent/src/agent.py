"""
Módulo principal do agente desktop.
"""
import time
import threading




from .monitor import Monitor
from .uploader import Uploader
from .browser_monitor import BrowserMonitor
from .config import load_config

import os
import datetime
from .tray import TrayIcon


class Agent:
    def __init__(self):
        self.running = True
        config = load_config()
        self.monitor = Monitor()
        self.uploader = Uploader(api_url=config["api_url"], token=config["token"])
        self.screenshot_interval = 300  # 5 minutos
        self.last_screenshot = time.time()
        self.screenshot_dir = os.path.join(os.getcwd(), "screenshots")
        os.makedirs(self.screenshot_dir, exist_ok=True)
        self.browser_monitor = BrowserMonitor()
        self.last_url_check = 0
        self.last_url_time = 0

    def run(self):
        print("Agente iniciado. Rodando na bandeja do sistema.")
        tray = TrayIcon(self)
        tray.start()
        try:
            while self.running:
                event = {}
                # 1. Monitorar janela/processo ativo
                win_info = self.monitor.get_active_window()
                event["window"] = win_info

                # 2. Detectar inatividade
                event["inactive"] = self.monitor.is_inactive(threshold=300)

                # 3. Capturar screenshot a cada 5 min
                now = time.time()
                screenshot_path = None
                if now - self.last_screenshot >= self.screenshot_interval:
                    dt = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
                    screenshot_path = os.path.join(self.screenshot_dir, f"screenshot_{dt}.png")
                    self.monitor.capture_screenshot(screenshot_path)
                    event["screenshot_path"] = screenshot_path
                    self.last_screenshot = now

                # 4. Monitorar URLs do navegador a cada 30s
                if time.time() - self.last_url_check > 30:
                    urls = self.browser_monitor.get_recent_urls(since=self.last_url_time)
                    if urls:
                        event["recent_urls"] = urls
                        # Atualiza o último timestamp de visita
                        self.last_url_time = max([u["visit_time"] for u in urls] + [self.last_url_time])
                    self.last_url_check = time.time()

                # 5. Enviar dados para API
                self.uploader.send(event)

                time.sleep(5)
        except KeyboardInterrupt:
            print("Encerrando agente...")
            self.running = False
