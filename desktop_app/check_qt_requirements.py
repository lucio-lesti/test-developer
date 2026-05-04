"""
Verifica preliminare dei pacchetti Python richiesti dall'applicazione.
"""

import importlib

# Elenco dei pacchetti che l'applicazione richiede a runtime.
REQUIRED_PKGS = ["PyQt6", "requests"]


def check_requirements():
    """Controlla che le librerie di base in REQUIRED_PKGS siano installate.
    """
    missing = []
    for pkg in REQUIRED_PKGS:
        try:
            importlib.import_module(pkg)
        except ImportError:
            missing.append(pkg)

    if missing:
        print(f"ATTENZIONE - Librerie mancanti trovate: {missing}. ")
        try:
            # Si usa tkinter come UI di fallback perche' PyQt potrebbe non essere installato.
            import tkinter as tk
            from tkinter import messagebox

            root = tk.Tk()
            root.withdraw()  # Nasconde la finestra principale di tkinter
            messagebox.showinfo(
                "Moba Manager - Setup",
                f"Attenzione, mancano le seguenti librerie: {', '.join(missing)}\n\n"
                "Lanciare  lo script di installazione moba_qt_install.cmd"
            )
            root.destroy()
        except ImportError as e:
            print("Errore - tkinter non trovato nella tua installazione " + str(e))

        exit(0)
