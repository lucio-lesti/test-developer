"""
Script principale di avvio dell'interfaccia Desktop
"""

from check_qt_requirements import *
check_requirements()

from classes.common_lib import *
from classes.networking import *
from classes.gui import *


def init_config(win):
    
    """Carica le impostazioni da `client.ini`
    """
    ini_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "client.ini")
    win.settings = QSettings(ini_path, QSettings.Format.IniFormat)


if __name__ == "__main__":
    app = QApplication(sys.argv)
    app.setFont(QFont("Segoe UI", 10))

    #Verifica se la porta 9999 e' gia' occupata, se libera esegue il run dell'app.
    if is_another_instance_running(9999):
        msg = QMessageBox()
        msg.setWindowTitle("Gestione personale")
        msg.setText("L'applicazione è già in esecuzione o la porta 9999 e' gia occupata")
        msg.setIcon(QMessageBox.Icon.Warning)
        msg.exec()
        sys.exit(0)
        
    win = Gui()
    init_config(win)
    win.init_gui()
    win.show()
    sys.exit(app.exec())
