import json
import argparse
import os
from collections import defaultdict

# Assegnazione statica dei percorsi file
FILES_MAP = {
    "B1": "data_b1.json",
    "B2": "data_b2.json",
    "B3": "data_b3.json"
}

def find_duplicate_emails(emails):
    """B1: Identifica duplicati dopo normalizzazione."""
    seen = set()
    duplicates = set()
    for email in emails:
        if not isinstance(email, str): continue
        normalized = email.strip().lower()
        if normalized in seen:
            duplicates.add(normalized)
        else:
            seen.add(normalized)
    return sorted(list(duplicates))

def group_user_events(events):
    """B2: Raggruppa eventi per user_id."""
    user_map = defaultdict(lambda: {"events": [], "count": 0, "last_event": None})
    for entry in events:
        if not isinstance(entry, dict): continue
        uid = entry.get("user_id")
        event_name = entry.get("event")
        if uid is None: continue
        user_map[uid]["events"].append(event_name)
        user_map[uid]["count"] += 1
        user_map[uid]["last_event"] = event_name
    return dict(sorted(user_map.items()))

def get_max_depth(node):
    """B3: Calcola profondità massima."""
    if not node or not node.get("children"): return 1
    return 1 + max(get_max_depth(child) for child in node["children"])

def get_leaf_nodes(node):
    """B3: Recupera i nomi dei nodi foglia."""
    if not node or not node.get("children"):
        return [node.get("name")]
    leaves = []
    for child in node["children"]:
        leaves.extend(get_leaf_nodes(child))
    return leaves

def load_json_static(task):
    """Carica il file JSON corrispondente al task specificato con gestione errori."""
    filepath = FILES_MAP.get(task)
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except FileNotFoundError:
        print(f"ERRORE: Il file statico '{filepath}' non è presente nella cartella.")
    except json.JSONDecodeError:
        print(f"ERRORE: Il file '{filepath}' contiene JSON non valido.")
    except Exception as e:
        print(f"ERRORE IMPREVISTO: {e}")
    return None

def main():
    parser = argparse.ArgumentParser(description="CLI Algoritmi con JSON statici")
    parser.add_argument("task", choices=["B1", "B2", "B3"], help="Esercizio da eseguire")
    args = parser.parse_args()

    data = load_json_static(args.task)
    if data is None: return

    if args.task == "B1":
        result = find_duplicate_emails(data)
        print(f"Output B1 (Duplicati): {result}")
    elif args.task == "B2":
        result = group_user_events(data)
        print("Output B2 (Aggregati):", json.dumps(result, indent=2))
    elif args.task == "B3":
        print(f"Output B3: Profondità = {get_max_depth(data)}, Foglie = {get_leaf_nodes(data)}")

if __name__ == "__main__":
    main()