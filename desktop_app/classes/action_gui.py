"""
Controller dell'interfaccia (`ActionGui`).

Concentra tutte le "Action" sulla GUI: apertura/chiusura schede,
costruzione del form persona (in modalita' Aggiungi/Modifica/Visualizza),
animazione del menu laterale e gestione dello stile dei bottoni.

Non effettua chiamate HTTP.
"""

from classes.common_lib import *
import re

# Regex usata per la validazione lato client del campo Email.
EMAIL_RE = re.compile(r"^[^\s@]+@[^\s@]+\.[^\s@]+$")
# Stile applicato ai widget non validi per evidenziarli all'utente.
INVALID_BORDER = "border: 1px solid #dc3545;"


class ActionGui:
    

    def __init__(self, gui):
        self.gui = gui

    # ----- Punti di ingresso pubblici per le tre modalita' del form -----

    def open_add_person_tab(self):
        """Apre una nuova scheda con il form vuoto per creare una persona."""
        self._open_person_tab(mode="add")

    def open_edit_person_tab(self, item):
        """Apre una scheda di modifica precompilata con i dati presi dal server """
        self._open_person_tab(mode="edit", item=item)

    def open_view_person_tab(self, item):
        """Apre una scheda in sola lettura con i dati presi dal server """
        self._open_person_tab(mode="view", item=item)


    def _open_person_tab(self, mode="add", item=None):
        """Metodo privato per la costruzione effettiva della scheda .
        """
        pid = item.get("id", "") if item else ""
        titles = {
            "add":  "➕ Aggiungi",
            "edit": f"✏️ Modifica #{pid}",
            "view": f"👁 Visualizza  #{pid}",
        }
        title = titles[mode]

        # Se la scheda esiste gia' la porto in primo piano senza ricrearla.
        for i in range(self.gui.tabs.count()):
            if self.gui.tabs.tabText(i) == title:
                self.gui.tabs.setCurrentIndex(i)
                self.switch_to_btn_style(self.gui.btn_add)
                return

        # Costruzione layout della scheda
        page = QWidget()
        layout = QVBoxLayout(page)
        layout.setContentsMargins(25, 25, 25, 25)

        card = QFrame()
        card.setObjectName("card")
        form = QFormLayout(card)

        # Campi del form
        fname = QLineEdit(); 
        lname = QLineEdit(); 
        email = QLineEdit()
        role_combo = QComboBox()
        role_combo.addItem("amministratore", "admin")
        role_combo.addItem("utente", "user")
        role_combo.addItem("moderatore", "moderator")
        role_combo.addItem("ospite", "guest")
        
        dob = QDateEdit(calendarPopup=True, date=QDate.currentDate())
        phone = QLineEdit()
        phone.setValidator(QIntValidator())  # Telefono: solo cifre

        # In modifica/visualizzazione recupero il record completo dal server
        full = self.gui.action_data.get_person(pid) if mode in ("edit", "view") and pid != "" else item
        if full:
            fname.setText(str(full.get("first_name") or ""))
            lname.setText(str(full.get("last_name") or ""))
            email.setText(str(full.get("email") or ""))
            dob_str = full.get("date_of_birth")
            role = str(full.get("role") or "")
            index = role_combo.findData(role)
            if index >= 0:
                role_combo.setCurrentIndex(index)
            else:
                role_combo.setCurrentIndex(4) # Default to 'none' if empty            
            if dob_str:
                # Conversione stringa "yyyy-MM-dd"
                d = QDate.fromString(str(dob_str), "yyyy-MM-dd")
                if d.isValid():
                    dob.setDate(d)
            phone.setText(str(full.get("phone_number") or ""))

        form.addRow("Nome:", fname)
        form.addRow("Cognome:", lname)
        form.addRow("Email:", email)
        form.addRow("Ruolo:", role_combo)
        form.addRow("Data di Nascita:", dob)
        form.addRow("Telefono:", phone)

        #sola lettura
        if mode == "view":
            for w in (fname, lname, email, dob, phone):
                w.setReadOnly(True)
        else:
            save_pid = pid if mode == "edit" else None


            def on_save_clicked():
                """Validazione client-side del form prima della chiamata HTTP.

                Verifica i campi obbligatori (Nome/Cognome/Email) e il
                formato dell'email; in caso di errori evidenzia i campi
                con bordo rosso e mostra un messaggio riassuntivo.
                """
                errors = []
                invalid = []
                # Pulizia bordi rossi precedenti
                for w in (fname, lname, email):
                    w.setStyleSheet("")

                if not fname.text().strip():
                    errors.append("Nome è obbligatorio.")
                    invalid.append(fname)
                if not lname.text().strip():
                    errors.append("Cognome è obbligatorio.")
                    invalid.append(lname)

                e = email.text().strip()
                if not e:
                    errors.append("Email è obbligatoria.")
                    invalid.append(email)
                elif not EMAIL_RE.match(e):
                    errors.append("Email non valida.")
                    invalid.append(email)

                for w in invalid:
                    w.setStyleSheet(INVALID_BORDER)

                if errors:
                    QMessageBox.warning(self.gui, "Validazione", "\n".join(errors))
                    return

                # Validazione superata: delego al controller dati.
                self.gui.action_data.save(fname, lname, email, dob, phone, role_combo,mode, save_pid)

            btn_save = QPushButton("💾 Salva")
            btn_save.setObjectName("btn_green")
            btn_save.clicked.connect(on_save_clicked)
            form.addRow("", btn_save)

        layout.addWidget(card)
        layout.addStretch()

        # Aggiungo la scheda al QTabWidget e la attivo.
        idx = self.gui.tabs.addTab(page, title)
        self.gui.tabs.setCurrentIndex(idx)
        self.switch_to_btn_style(self.gui.btn_add)


    def close_tab(self, index):
        #Chiude la scheda / tab 
        if index != 0:
            widget = self.gui.tabs.widget(index)
            if widget:
                widget.deleteLater()
            self.gui.tabs.removeTab(index)
            if self.gui.tabs.currentIndex() == 0:
                self.switch_to_btn_style(self.gui.btn_list)

    
    def switch_to_list(self):
        #Porta in primo piano la scheda Elenco
        self.gui.tabs.setCurrentIndex(0)
        self.switch_to_btn_style(self.gui.btn_list)


    def switch_to_btn_style(self, button):
        self.gui.btn_list.setObjectName("")
        self.gui.btn_add.setObjectName("")
        button.setObjectName("active_menu")
        self.gui.setStyleSheet(self.gui.styleSheet())

    
    def toggle_menu(self):
        #Espande/comprime la sidebar con animazione (230px ↔ 60px).
        
        width = self.gui.sidebar.width()
        new_width = 60 if width == 230 else 230
        self.anim = QPropertyAnimation(self.gui.sidebar, b"minimumWidth")
        self.anim.setDuration(250)
        self.anim.setEndValue(new_width)
        self.anim.setEasingCurve(QEasingCurve.Type.InOutQuart)
        self.anim_m = QPropertyAnimation(self.gui.sidebar, b"maximumWidth")
        self.anim_m.setDuration(250)
        self.anim_m.setEndValue(new_width)
        self.anim_m.start()
        self.anim.start()