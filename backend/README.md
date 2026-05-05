# Backend Take-Home — Sicurezza, Validazione e Problem Solving

Backend sviluppato con PHP 8.x, framework "Slim 4"
Ho scelto questo framework per la sua leggerezza.

PROBLEMATICHE AFFRONTATE:
A1 — Analisi di sicurezza	SECURITY_NOTES.md
A2 — CRUD risorsa Person (+ PATCH) con validazione rigorosa	PersonController.php, src/Application/Validation/
A3 — Importazione CSV per Person (MIME sniffing, limite dimensione, storage UUID, validazione per riga)	PersonController.php (metodo import)

## Requisiti

- **PHP 7.4 or 8.x** (tested on 8.2)
- Estensioni: `pdo`, `pdo_sqlite` (always required by tests), `pdo_mysql` (optional, only for the MySQL runtime path), `fileinfo`
- Composer

####
## Installazione Via Docker
L'Ambiente PHP + Mysql 8.x viene installato tramite file docker presente nella root del progetto.
Inoltre viene lanciato lo script in automatico per la migrazione della tabella "persons" nel DB
lanciare i seguenti comandi:

docker-compose up -d --build
docker-compose exec app composer install --working-dir=backend #per installare le dipendenze di Slim PHP framework



## Installazione MANUALE
Posizionarsi nella cartella backend e lanciare da CLI
composer install
 
***Database**
MySQL 8.x  

```bash
# 1. creazione database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Eseguire la migrazione sul DB
DB_DRIVER=mysql DB_HOST=localhost DB_NAME=test DB_USER=root DB_PASS=root \
  php bin/migrate.php


Per lanciare il server eseguire il comando da CLI: 
php -S localhost:8050 -t public

Il server starta su `http://localhost:8050`.

### APPLICAZIONE WEB
L'applicazione web si trova all'indirizzo "http://localhost:8050/web" 



### Variabili d'Ambiente

| Variable             | Default        | Notes                                                       |
|----------------------|----------------|-------------------------------------------------------------|
| `APP_ENV`            | `prod`         | `dev` enables stack traces in HTTP responses                |
| `DB_DRIVER`          | `mysql`        | `mysql` or `sqlite`                                         |
| `DB_HOST`            | `localhost`    | MySQL only                                                  |
| `DB_NAME`            | `test`         | MySQL only                                                  |
| `DB_USER`            | `root`         | MySQL only                                                  |
| `DB_PASS`            | _(empty)_      | MySQL only                                                  |
| `DB_PATH`            | `var/db.sqlite`| SQLite only; use `:memory:` for ephemeral                   |


#### Campi tabella "persons"

| Field           | Required | Rules                                                          |
|-----------------|----------|----------------------------------------------------------------|
| `first_name`    | yes      | trimmed string, 1–100 chars                                    |
| `last_name`     | yes      | trimmed string, 1–100 chars                                    |
| `email`         | yes      | RFC valid, ≤ 255 chars, lowercased, unique                     |
| `date_of_birth` | no       | ISO-8601 (`YYYY-MM-DD`), valid calendar date, not in the future |
| `phone_number`  | no       | E.164-compatible, ≤ 20 chars                                   |
| `role`          | no       | one of `admin`, `user`, `moderator`, `guest`                   |
| `notes`         | no       | ≤ 1000 chars                                                   |


### Metodi "Problem solving"
#Metodi usati per la gestione delle email duplicate negli inserimenti 

| Method | Path                              | Body                                                      |
|--------|-----------------------------------|-----------------------------------------------------------|
| `POST` | `/problem-solving/duplicates`     | `{ "emails": ["…", …] }`                                  |
| `POST` | `/problem-solving/events`         | `{ "events": [{"user_id": 10, "event": "login"}, …] }`    |
| `POST` | `/problem-solving/tree`           | `{ "tree": {…}, "operation": "leaves|depth|path", "target": "NodeName" }` |



### Architettura Backend
src/
├── Application/
│   ├── Actions/            
│   │   ├── Person/          # Classe per HTTP endpoint  
│   │   └── ProblemSolving/  # Classe per gestione duplicati email etc.
│   ├── Validation/         # Gestione validazione
│   ├── Handlers/           # HTTP error handler
│   ├── Middleware/
│   └── Settings/
└── Infrastructure/
    └── Persistence/Person/ # PdoPersonRepository 


### Architettura Web Application
public/
├── web/         
├── assets/
│   ├── js/            
│   │   ├── person.js # Logica Javascript
│   ├── index.php   # Pagina PHP/Html su template AdminLTE / framework "bootstrap"

##Riassunto:
- Una classe Action = un endpoint, 
La classe  `PersonController` è la classe action/controller deputata agli endpoint.

- Il livello Domain non ha dipendenze da framework: le entità e le interfacce del repository sono in puro PHP.

- Un singolo `PdoPersonRepository` funziona sia con MySQL che (in futuro) con SQLite tramite SQL portatile

- La validazione risiede in `App\Application\Validation\PersonValidator


### Test con PHP Unit

La cartella tests/ è organizzata in modo speculare alla cartella src/, separando i test di logica pura (Domain) dai test di integrazione API (Application).  

##Test dell'Applicazione (API e Middleware 
Questi test verificano che gli endpoint HTTP rispondano correttamente.

-ActionTest.php: Test base per le azioni generiche.  

-PersonActionTest.php: Verifica il CRUD delle persone (lista, creazione, aggiornamento, eliminazione).  

-ImportPersonsActionTest.php: Testa specificamente il caricamento dei file CSV.  

-ProblemSolvingActionsTest.php: Verifica le API relative agli algoritmi (duplicati, eventi, albero).  

-ApiTokenMiddlewareTest.php: Verifica che il sistema di sicurezza X-API-Token blocchi gli accessi non autorizzati e accetti quelli corretti.  

-PersonValidatorTest.php: Testa le regole di validazione dei campi (email, date, ruoli).  

##Test "Logica di Business"##

-PersonTest.php: Verifica l'integrità dell'entità Person.  

-DuplicateEmailFinderTest.php
 
-UserTest.php
 

###Struttura Gerarchica dei Test
 

tests/
├── Application/ (Integrazione API e Middleware)
│   ├── Actions/
│   │   ├── ActionTest.php (Logica base delle azioni)
│   │   ├── Person/
│   │   │   ├── ImportPersonsActionTest.php (Test importazione CSV)
│   │   │   └── PersonActionTest.php (CRUD completo Persone)
│   │   └── ProblemSolving/
│   │       └── ProblemSolvingActionsTest.php (Endpoint algoritmi)
│   ├── Middleware/
│   │   └── ApiTokenMiddlewareTest.php (Sicurezza X-API-Token)
│   └── Validation/
│       └── PersonValidatorTest.php (Regole validazione dati)
├── Domain/ (Logica di Business Pura)
│   ├── Person/
│   │   └── PersonTest.php (Integrità entità Person)
│   ├── ProblemSolving/
│   │   ├── DuplicateEmailFinderTest.php (Algoritmo duplicati)
│   │   ├── EventGrouperTest.php (Algoritmo raggruppamento eventi)
│   │   └── TreeTraverserTest.php (Algoritmo albero ricorsivo)
├── TestCase.php (Classe base di configurazione)
└── bootstrap.php 


##Come Eseguire i test###

#Per debuggare il Token di sicurezza:
php vendor/bin/phpunit tests/Application/Middleware/ApiTokenMiddlewareTest.php

###Per testare il caricamento CSV
php vendor/bin/phpunit tests/Application/Actions/Person/ImportPersonsActionTest.php
  
##Per testare l'algoritmo delle email duplicate:**
php vendor/bin/phpunit tests/Domain/ProblemSolving/DuplicateEmailFinderTest.php

### Test Problem Solving
php vendor/bin/phpunit tests/Domain/ProblemSolving/

###Test Creazione persona
php vendor/bin/phpunit --filter testPersonIsCreatedSuccessfully tests/Application/Actions/Person/PersonActionTest.php

php vendor\bin\phpunit --testdox tests\Application\Validation\PersonValidatorTest.php
