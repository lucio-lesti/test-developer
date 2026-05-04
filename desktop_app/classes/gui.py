"""
Finestra principale dell'applicazione (`Gui`).

Costruisce l'intera interfaccia ispirata ad AdminLTE:
  - barra superiore (navbar) con bottone "toggle" per la sidebar,
  - sidebar con i pulsanti di navigazione "Elenco" e "Aggiungi",
  - area centrale a schede (`QTabWidget`) dove vengono aperti elenco/form ecc..
"""

from classes.common_lib import *
from classes.action_data import *
from classes.action_gui import *


class Gui(QMainWindow):
    

    def __init__(self):
        super().__init__()
        # Caricamento foglio di stile QSS dalla cartella assets/ del progetto.
        qss_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "assets/style.qss")
        if os.path.exists(qss_path):
            with open(qss_path, "r") as f:
                self.setStyleSheet(f.read())

        self.action_data = ActionData(self)
        self.action_gui = ActionGui(self)


    def init_gui(self):
        """Inizializza tutti i widget della finestra e collega i relativi segnali/slot(action dei componenti) di QT..
        """

        # 1. Configurazione finestra
        self.setWindowTitle("Prova Backend -  Desktop")
        self.resize(1200, 800)

        self.main_container = QWidget()
        self.setCentralWidget(self.main_container)
        self.root_layout = QVBoxLayout(self.main_container)
        self.root_layout.setContentsMargins(0, 0, 0, 0)
        self.root_layout.setSpacing(0)

        self.navbar = QFrame()
        self.navbar.setObjectName("navbar")
        self.navbar.setFixedHeight(50)
        nav_layout = QHBoxLayout(self.navbar)

        self.btn_toggle = QPushButton("≡")
        self.btn_toggle.setObjectName("btn_toggle")
        self.btn_toggle.setFixedSize(40, 35)

        nav_layout.addWidget(self.btn_toggle)
        nav_layout.addWidget(QLabel("Home"))
        nav_layout.addStretch()
        self.root_layout.addWidget(self.navbar)

        # --- AREA CENTRALE (Sidebar + Schede) ---
        self.middle_section = QWidget()
        self.middle_layout = QHBoxLayout(self.middle_section)
        self.middle_layout.setContentsMargins(0, 0, 0, 0)
        self.middle_layout.setSpacing(0)


        # Sidebar laterale
        self.sidebar = QFrame()
        self.sidebar.setObjectName("sidebar")
        self.sidebar.setFixedWidth(230)
        self.sidebar_layout = QVBoxLayout(self.sidebar)

        self.logo_label = QLabel("Prova Backend")
        self.logo_label.setObjectName("logo_label")
        self.sidebar_layout.addWidget(self.logo_label)

        self.btn_list = QPushButton(" 👥  Elenco")
        self.btn_list.setObjectName("active_menu")
        self.btn_add = QPushButton(" ➕  Aggiungi")

        for btn in [self.btn_list, self.btn_add]:
            btn.setCursor(Qt.CursorShape.PointingHandCursor)
            self.sidebar_layout.addWidget(btn)

        self.sidebar_layout.addStretch()

        # SISTEMA A TAB: l'elenco e' fisso (scheda 0), modifica/visualizzazione
        self.tabs = QTabWidget()
        self.tabs.setObjectName("pages_area")
        self.tabs.setTabsClosable(True)
        self.tabs.setMovable(True)
        self.tabs.tabCloseRequested.connect(self.action_gui.close_tab)

        self.middle_layout.addWidget(self.sidebar)
        self.middle_layout.addWidget(self.tabs)
        self.root_layout.addWidget(self.middle_section)

        # --- FOOTER ---
        self.footer = QFrame()
        self.footer.setObjectName("footer")
        self.footer.setFixedHeight(40)
        footer_layout = QHBoxLayout(self.footer)
        footer_layout.addWidget(QLabel("Backend Gestione personale"))
        self.root_layout.addWidget(self.footer)


        # 3. Inizializzazione contenuti
        self.init_list_page() 

        # Rimuove il pulsante di chiusura sulla scheda Elenco
        self.tabs.tabBar().setTabButton(0, QTabBar.ButtonPosition.RightSide, None)


        # CollegO i segnali / slot ai pulsanti
        self.btn_toggle.clicked.connect(self.action_gui.toggle_menu)
        self.btn_list.clicked.connect(self.action_gui.switch_to_list)
        self.btn_add.clicked.connect(self.action_gui.open_add_person_tab)

        # Caricamento dati al primo avvio
        QTimer.singleShot(100, self.action_data.fetch_data)



    # --- COSTRUZIONE PAGINA ELENCO (scheda 0) ---
    def init_list_page(self):
        """Costruisce la scheda principale "Elenco": tabella + pulsanti
        Aggiorna/Importa CSV + label di riepilogo conteggio record."""
        page = QWidget()
        layout = QVBoxLayout(page)
        layout.setContentsMargins(25, 25, 25, 25)
        layout.addWidget(QLabel("<h1 style='font-size: 28px;'>Gestione Personale</h1>"))

        card = QFrame()
        card.setObjectName("card")
        card_layout = QVBoxLayout(card)

        header_layout = QHBoxLayout()
        header_layout.addWidget(QLabel("<b>Numero elementi</b>"))

        self.btn_refresh = QPushButton(" ↻ Aggiorna")
        self.btn_refresh.setObjectName("btn_green")
        self.btn_refresh.clicked.connect(self.action_data.fetch_data)

        self.btn_csv = QPushButton(" 📤 Importa CSV")
        self.btn_csv.setObjectName("btn_blue_sm")
        self.btn_csv.clicked.connect(self.action_data.import_csv)

        header_layout.addStretch()
        header_layout.addWidget(self.btn_csv)
        header_layout.addWidget(self.btn_refresh)
        card_layout.addLayout(header_layout)

        self.table = QTableWidget()
        self.table.setColumnCount(6)
        self.table.setHorizontalHeaderLabels(["ID", "Nome", "Cognome", "Email", "Ruolo", "Azioni"])
        self.table.horizontalHeader().setSectionResizeMode(QHeaderView.ResizeMode.Stretch)
        self.table.verticalHeader().setVisible(False)
        card_layout.addWidget(self.table)

        # Riga di riepilogo: totale record visualizzati nella tabella
        footer_table_layout = QHBoxLayout()
        footer_table_layout.addStretch()
        self.lbl_count = QLabel("Totale record: 0")
        self.lbl_count.setObjectName("lbl_count")
        footer_table_layout.addWidget(self.lbl_count)
        card_layout.addLayout(footer_table_layout)

        layout.addWidget(card)
        layout.addStretch()
        self.tabs.addTab(page, "👥 Elenco")
