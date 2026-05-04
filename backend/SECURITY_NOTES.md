# NOTE SULLA SICUREZZA — Backend Person Management

Questo documento descrive l'analisi di sicurezza e le contromisure implementate nel modulo "Person" e negli endpoint di Problem-Solving.

## 1. Architettura e Ambito
Tutte le operazioni relative alle persone (CRUD e Importazione CSV) sono centralizzate in un unico controller:
- Classe: `App\Application\Actions\Person\PersonController`

## 2. Autenticazione tramite API Token
L'accesso a tutte le rotte è protetto dal middleware 'ApiTokenMiddleware'. 
- Requisito: Ogni richiesta deve includere l'header 'X-API-Token'[cite: 1, 2].
- Meccanismo: Il server non memorizza il token in chiaro. Il valore ricevuto viene verificato tramite un hash crittografico (sodium_crypto_generichash) rispetto al segreto configurato[cite: 1, 2].
- Risposta di errore: In caso di token mancante o errato, il server restituisce '401 Unauthorized', bloccando l'accesso prima che venga eseguita qualsiasi logica[cite: 4].

## 3. Mitigazioni contro le Minacce comuni
A. SQL Injection:
- Utilizzo esclusivo di PDO con prepared statements e segnaposti nominativi[cite: 3, 5].
- Le colonne per l'ordinamento (sort) sono verificate contro una "allow-list" definita nel repository; i valori non riconosciuti vengono ignorati[cite: 5].

B. Mass Assignment (Inserimento di campi non autorizzati):
- Il 'PersonValidator' definisce esplicitamente i campi scrivibili. Ogni chiave extra inviata nel JSON viene scartata automaticamente[cite: 3, 5].

C. Validazione Rigorosa:
- Controlli su campi obbligatori, formati email (RFC), date (ISO-8601) e lunghezze massime delle stringhe[cite: 5].
- Il ruolo ('role') è limitato a una lista chiusa: admin, user, moderator, guest[cite: 1, 5].

D. Sicurezza dell'Importazione CSV:
- MIME Sniffing: Il tipo di file viene verificato tramite "magic bytes" (finfo_buffer), ignorando l'estensione dichiarata dall'utente[cite: 5].
- Gestione File: I file caricati vengono rinominati con UUID casuali e salvati fuori dalla root web per prevenire attacchi di path traversal[cite: 3, 5].
- Validazione Righe: Ogni riga del CSV viene validata singolarmente usando le stesse regole delle API standard[cite: 3, 5].
(*)NOTE:Nella root del progetto c'è un template "test_import_persons.csv" di come dovrebbe essere un csv per essere importato

## 4. Altro
- Normalizzazione Email: Tutte le email sono convertite in minuscolo e trimmate per prevenire duplicati con casing differente[cite: 5].
