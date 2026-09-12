"""
Monitoramento de janelas, processos, screenshots e inatividade (Windows).
"""

import time
import win32gui
import win32process
import psutil
from PIL import ImageGrab
import ctypes
import threading
import json
import os
from pynput import mouse, keyboard


class Monitor:
    def __init__(self, metrics_path=None):
        self.last_input_time = self.get_idle_duration()
        self.metrics_path = metrics_path or os.path.abspath(os.path.join(os.path.dirname(__file__), '../../logs/metrics.json'))
        self.metrics = {
            'clicks': 0,
            'keystrokes': 0,
            'words': 0,
            'app_times': {},  # {app: total_seconds}
            'last_window': None,
            'last_switch_time': time.time()
        }
        self._load_metrics()
        self._start_listeners()

    def get_active_window(self):
        hwnd = win32gui.GetForegroundWindow()
        pid = win32process.GetWindowThreadProcessId(hwnd)
        process_id = pid[-1]
        try:
            process = psutil.Process(process_id)
            process_name = process.name()
        except Exception:
            process_name = None
        window_title = win32gui.GetWindowText(hwnd)
        return {'process_name': process_name, 'window_title': window_title}
    def _on_click(self, x, y, button, pressed):
        if pressed:
            self.metrics['clicks'] += 1
            self._save_metrics()

    def _on_press(self, key):
        self.metrics['keystrokes'] += 1
        try:
            if hasattr(key, 'char') and key.char and key.char.isspace():
                self.metrics['words'] += 1
        except Exception:
            pass
        self._save_metrics()

    def _start_listeners(self):
        mouse_listener = mouse.Listener(on_click=self._on_click)
        keyboard_listener = keyboard.Listener(on_press=self._on_press)
        mouse_listener.daemon = True
        keyboard_listener.daemon = True
        mouse_listener.start()
        keyboard_listener.start()

    def _save_metrics(self):
        os.makedirs(os.path.dirname(self.metrics_path), exist_ok=True)
        with open(self.metrics_path, 'w', encoding='utf-8') as f:
            json.dump(self.metrics, f)

    def _load_metrics(self):
        if os.path.exists(self.metrics_path):
            try:
                with open(self.metrics_path, 'r', encoding='utf-8') as f:
                    self.metrics = json.load(f)
            except Exception:
                pass

    def update_app_time(self):
        win = self.get_active_window()
        app = win['process_name'] or 'Desconhecido'
        now = time.time()
        if self.metrics['last_window']:
            last_app = self.metrics['last_window']
            delta = now - self.metrics['last_switch_time']
            self.metrics['app_times'][last_app] = self.metrics['app_times'].get(last_app, 0) + delta
        self.metrics['last_window'] = app
        self.metrics['last_switch_time'] = now
        self._save_metrics()

    def get_metrics(self):
        self._load_metrics()
        return self.metrics

    def capture_screenshot(self, path):
        img = ImageGrab.grab()
        img.save(path)

    def get_idle_duration(self):
        class LASTINPUTINFO(ctypes.Structure):
            _fields_ = [('cbSize', ctypes.c_uint), ('dwTime', ctypes.c_uint)]
        lii = LASTINPUTINFO()
        lii.cbSize = ctypes.sizeof(LASTINPUTINFO)
        ctypes.windll.user32.GetLastInputInfo(ctypes.byref(lii))
        millis = ctypes.windll.kernel32.GetTickCount() - lii.dwTime
        return millis / 1000.0

    def is_inactive(self, threshold=300):
        idle = self.get_idle_duration()
        return idle >= threshold
