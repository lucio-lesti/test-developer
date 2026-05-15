
"""
Classe che si interfaccia con le API HTTP verso il backend  PHP :
elenco, lettura singola, creazione, modifica, eliminazione e importazione CSV
"""

from classes.common_lib import *


class _CsvUploadSignals(QObject):
    """Segnali emessi dal worker di upload CSV."""
    finished = pyqtSignal(object)
    error = pyqtSignal(str)


class _CsvUploadWorker(QRunnable):
    """Esegue l'upload del CSV in un thread separato del QThreadPool
    in modo da non bloccare la UI durante la richiesta HTTP.
    """

    def __init__(self, url, headers, file_path):
        super().__init__()
        self.url = url
        self.headers = headers
        self.file_path = file_path
        self.signals = _CsvUploadSignals()

    @pyqtSlot()
    def run(self):
        try:
            with open(self.file_path, 'rb') as fh:
                files = {'file': (os.path.basename(self.file_path), fh, 'text/csv')}
                res = requests.post(self.url, headers=self.headers, files=files, timeout=30)
            res.raise_for_status()
            body = res.json() if res.content else {}
            self.signals.finished.emit(body)
        except Exception as e:
            self.signals.error.emit(str(e))


class ActionData:

    def __init__(self, gui):
        self.gui = gui

    def _persons_url(self, pid=None, suffix=None):
        """Compone l'URL completo verso l'endpoint "persons", sia in creazione, cancellazione, modifica
        """
        base = str(self.gui.settings.value("WS_CONFIG/url_persons"))
        if pid is not None:
            return f"{base}/{pid}"
        if suffix:
            return f"{base}/{suffix}"
        return base


    # ----- Lettura: elenco e singolo record -----
    def fetch_data(self):
        """Carica l'elenco persone dal backend e popola il datatable.
        Aggiorna anche la label di riepilogo "Totale record".
        """
        headers = {
            "X-API-Token": str(self.gui.settings.value("WS_CONFIG/api_token")),
            "Accept": "application/json"
        }        
        try:
            res = requests.get(self._persons_url(), headers=headers,timeout=5)
            res.raise_for_status()
            items = res.json()
            items = items.get('data', items) if isinstance(items, dict) else items

            self.gui.table.setRowCount(0)
            self.gui.lbl_count.setText(f"Totale record: {len(items)}")
            for i, item in enumerate(items):
                self.gui.table.insertRow(i)
                # Colonne 0..3: ID, Nome, Cognome, Email.
                self.gui.table.setItem(i, 0, QTableWidgetItem(str(item.get('id', ''))))
                self.gui.table.setItem(i, 1, QTableWidgetItem(str(item.get('first_name') or '')))
                self.gui.table.setItem(i, 2, QTableWidgetItem(str(item.get('last_name') or '')))
                self.gui.table.setItem(i, 3, QTableWidgetItem(str(item.get('email') or '')))

                # Colonna 4: badge "Ruolo" (fallback "ospite" se non valorizzato).
                role = item.get('role') or 'ospite'
                badge = QLabel(str(role)); badge.setAlignment(Qt.AlignmentFlag.AlignCenter)
                badge.setStyleSheet("background-color: #17a2b8; color: white; border-radius: 10px;")
                self.gui.table.setCellWidget(i, 4, badge)

                # Colonna 5: pulsanti "Azioni" (Modifica / Visualizza / Elimina).
                # Usiamo un widget contenitore con QHBoxLayout per allinearli orizzontalmente.
                actions_widget = QWidget()
                actions_layout = QHBoxLayout(actions_widget)
                actions_layout.setContentsMargins(2, 2, 2, 2)
                actions_layout.setSpacing(4)

                # NOTA: il default-arg nelle lambda (`it=item`, `pid=item.get('id')`)
                # serve a "catturare per valore" la riga corrente
                btn_edit = QPushButton("✏️")
                btn_edit.setStyleSheet("background-color: #ffc107; color: white; border: none; border-radius: 3px;")
                btn_edit.clicked.connect(lambda checked, it=item: self.gui.action_gui.open_edit_person_tab(it))

                btn_view = QPushButton("👁")
                btn_view.setStyleSheet("background-color: #17a2b8; color: white; border: none; border-radius: 3px;")
                btn_view.clicked.connect(lambda checked, it=item: self.gui.action_gui.open_view_person_tab(it))

                btn_del = QPushButton("🗑")
                btn_del.setStyleSheet("background-color: #dc3545; color: white; border: none; border-radius: 3px;")
                btn_del.clicked.connect(lambda checked, pid=item.get('id'): self.delete(pid))

                actions_layout.addWidget(btn_edit)
                actions_layout.addWidget(btn_view)
                actions_layout.addWidget(btn_del)
                self.gui.table.setCellWidget(i, 5, actions_widget)
                
        except Exception as e:
            print(f"Errore Connessione /  Recupero dati: {e}")
            self.gui.lbl_count.setText("Totale record: 0")
            QMessageBox.critical(self.gui, "Errore Connessione", "Errore Connessione /  Recupero dati " + str(e))


    def get_person(self, pid):
        """Recupera una singola persona via `GET /persons/{pid}`.
        Usato dal form di Modifica/Visualizzazione
        """
        token = str(self.gui.settings.value("WS_CONFIG/api_token"))
        headers = {
            "X-API-Token": str(self.gui.settings.value("WS_CONFIG/api_token")),
            "Accept": "application/json"
        }         
        try:
            res = requests.get(self._persons_url(pid=pid), headers=headers,timeout=5)
            res.raise_for_status()
            body = res.json()
            # Gestisce sia risposte "wrapped" {"data": {...}} sia oggetti diretti.
            return body.get('data', body) if isinstance(body, dict) else body
        except Exception as e:
            QMessageBox.critical(self.gui, "Errore", f"Caricamento persona fallito: {e}")
            return None


    # ----- Eliminazione -----
    def delete(self, pid):
        """Elimina la persona con id `pid` dopo conferma utente.
        Esegue sul backend `DELETE /persons/{pid}`. Al successo ricarica l'elenco in tabella.
        """
        headers = {
            "X-API-Token": str(self.gui.settings.value("WS_CONFIG/api_token")),
            "Accept": "application/json"
        }           
        if QMessageBox.question(self.gui, "Conferma Cancellazione", f"Cancellare record con ID {pid}?") == QMessageBox.StandardButton.Yes:
            try:
                requests.delete(self._persons_url(pid=pid), headers=headers,timeout=5).raise_for_status()
                self.fetch_data()
            except Exception as e:
                QMessageBox.critical(self.gui, "Errore", str(e))


    # ----- Creazione / Aggiornamento -----
    def save(self, fname, lname, email, dob, phone, role, mode='add', pid=None):
        """Salva la persona via POST (creazione) o PUT (modifica).
        - `mode='add'` (default) ⇒ `POST /persons`
        - `mode='edit'` con `pid` valorizzato ⇒ `PUT /persons/{pid}`
        """
        headers = {
            "X-API-Token": str(self.gui.settings.value("WS_CONFIG/api_token")),
            "Accept": "application/json"
        }   
        payload = {
            'first_name':    fname.text().strip(),
            'last_name':     lname.text().strip(),
            'email':         email.text().strip(),
            'role':          role.currentData(),
            'date_of_birth': dob.date().toString('yyyy-MM-dd'),
            'phone_number':  phone.text().strip() or None,
            'notes':         None,
        }
        
        try:
            if mode == 'edit' and pid is not None:
                res = requests.put(self._persons_url(pid=pid), headers=headers,json=payload, timeout=5)
            else:
                res = requests.post(self._persons_url(),headers=headers, json=payload, timeout=5)
            res.raise_for_status()
            QMessageBox.information(self.gui, "Operazione di salvataggio riuscita", "Elemento salvato con successo!")
            self.gui.action_gui.close_tab(self.gui.tabs.currentIndex())
            self.fetch_data()
        except requests.HTTPError as he:
            status = he.response.status_code if he.response is not None else '?'
            QMessageBox.critical(self.gui, "Errore di Salvataggio", f"Salvataggio fallito ({status}): {he}")
        except Exception as e:
            QMessageBox.critical(self.gui, "Errore di Salvataggio", str(e))



    #Importazione CSV
    def import_csv(self):
        """Importa un file CSV inviandolo al backend come multipart/form-data.
        Esegue l'upload in un thread separato e mostra un loader modale
        finche' il backend non risponde.
        """
        headers = {
            "X-API-Token": str(self.gui.settings.value("WS_CONFIG/api_token")),
            "Accept": "application/json"
        }
        file_path, _ = QFileDialog.getOpenFileName(self.gui, "Seleziona CSV", "", "File CSV (*.csv)")
        if not file_path:
            return

        # Loader modale indeterminato (range 0..0 = barra "busy").
        progress = QProgressDialog("Importazione CSV in corso...", None, 0, 0, self.gui)
        progress.setWindowTitle("Importazione")
        progress.setWindowModality(Qt.WindowModality.ApplicationModal)
        progress.setCancelButton(None)
        progress.setMinimumDuration(0)
        progress.setAutoClose(False)
        progress.setAutoReset(False)
        progress.show()

        worker = _CsvUploadWorker(self._persons_url(suffix='import'), headers, file_path)
        worker.signals.finished.connect(lambda body: self._on_import_finished(progress, body))
        worker.signals.error.connect(lambda err: self._on_import_error(progress, err))
        QThreadPool.globalInstance().start(worker)


    def _on_import_finished(self, progress, body):
        """Callback al termine dell'upload CSV: chiude il loader e mostra il riepilogo."""
        progress.close()
        stats = body.get('data', {}) if isinstance(body, dict) else {}
        total   = stats.get('total', 0)
        valid   = stats.get('valid', 0)
        invalid = stats.get('invalid', 0)
        msg = f"Importazione: totale={total}, validi={valid}, non validi={invalid}"
        if invalid:
            QMessageBox.warning(self.gui, "Importazione Completata", msg)
        else:
            QMessageBox.information(self.gui, "Importazione Riuscita", msg)
        self.fetch_data()


    def _on_import_error(self, progress, err):
        """Callback in caso di errore durante l'upload CSV."""
        progress.close()
        QMessageBox.critical(self.gui, "Errore di Importazione", f"Caricamento fallito: {err}")
