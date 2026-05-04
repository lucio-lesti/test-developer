"""
Modulo "contenitore" per gli import comuni.
"""
import sys
import os
import requests
import csv
import shutil

# Widget e layout PyQt6 utilizzati dalle finestre dell'applicazione.
from PyQt6.QtWidgets import (QApplication, QMainWindow, QTableWidget,
                             QTableWidgetItem, QVBoxLayout, QHBoxLayout,
                             QPushButton, QWidget, QMessageBox, QHeaderView,
                             QLabel, QTabWidget, QLineEdit, QFormLayout,
                             QDateEdit, QFileDialog, QFrame,QComboBox)

# Classi core (timer, impostazioni, animazioni, threading).
from PyQt6.QtCore import Qt, QTimer, QSettings, QDate, QPropertyAnimation, QEasingCurve
from PyQt6.QtGui import QFont, QIntValidator
from PyQt6.QtWidgets import QTabBar
from PyQt6.QtCore import QRunnable, pyqtSlot, QThreadPool

# Componenti di rete: usati per il lock single-instance via socket TCP.
from PyQt6.QtNetwork import QTcpServer, QHostAddress