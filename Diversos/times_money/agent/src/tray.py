"""
Integração do agente com a bandeja do sistema (Windows) usando pystray.
"""
import threading
from pystray import Icon, Menu, MenuItem
from PIL import Image, ImageDraw

class TrayIcon:
    def __init__(self, agent):
        self.agent = agent
        self.icon = Icon("TimesMoneyAgent", self._create_image(), "Times Money Agent", menu=Menu(
            MenuItem("Sair", self.quit)
        ))
        self.thread = threading.Thread(target=self.icon.run, daemon=True)

    def _create_image(self):
        # Cria um ícone simples (círculo verde)
        img = Image.new('RGB', (64, 64), color=(0, 128, 0))
        d = ImageDraw.Draw(img)
        d.ellipse((16, 16, 48, 48), fill=(0, 255, 0))
        return img

    def start(self):
        self.thread.start()

    def quit(self, icon, item):
        self.agent.running = False
        self.icon.stop()
