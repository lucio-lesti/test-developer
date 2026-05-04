<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$cb = new \DI\ContainerBuilder();
(require __DIR__ . '/../../app/settings.php')($cb);
$container = $cb->build();

$tokenSettings = (array) $container
    ->get(\App\Application\Settings\SettingsInterface::class)
    ->get('token');

$apiToken    = bin2hex(sodium_crypto_generichash((string) $tokenSettings['secret']));
$tokenHeader = (string) ($tokenSettings['header'] ?? 'X-API-Token');
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="api-token" content="<?= htmlspecialchars($apiToken, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="api-token-header" content="<?= htmlspecialchars($tokenHeader, ENT_QUOTES, 'UTF-8') ?>">
  <title>AdminLTE | Gestione Personale</title>

  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/toastr/toastr.min.css">
  <link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">

  <style>
    .field-error { color: #dc3545; font-size: .85em; margin-top: .25rem; display: none; }
    .form-control.is-invalid ~ .field-error { display: block; }
    .nav-tabs .close-tab { margin-left: .5rem; cursor: pointer; opacity: .55; }
    .nav-tabs .close-tab:hover { opacity: 1; }
    .api-bar { background: #343a40; color: #fff; padding: .4rem 1rem; }
    .api-bar input { background: #495057; color: #fff; border: 0; }
    table.dataTable thead tr.column-filters input { width: 100%; padding: 3px; box-sizing: border-box; font-weight: normal; }
    table.dataTable thead tr.column-filters th { padding: 4px; }
    .dt-actions .btn { margin-right: 2px; }

    .csv-overlay {
      position: fixed; inset: 0; z-index: 1090;
      background: rgba(33,37,41,.55);
      display: flex; align-items: center; justify-content: center;
    }
    .csv-overlay-box {
      background: #fff; border-radius: .5rem;
      padding: 1.25rem 1.75rem;
      display: flex; align-items: center; gap: 1rem;
      box-shadow: 0 4px 16px rgba(0,0,0,.25);
    }
    .csv-spinner {
      width: 36px; height: 36px;
      border: 4px solid #e9ecef;
      border-top-color: #007bff;
      border-radius: 50%;
      animation: csv-spin .8s linear infinite;
    }
    @keyframes csv-spin { to { transform: rotate(360deg); } }
    .csv-overlay-text { color: #343a40; font-weight: 500; }
  </style>
</head>
<body class="hold-transition sidebar-mini">

<div id="csvOverlay" class="csv-overlay d-none" role="status" aria-live="polite" aria-hidden="true">
  <div class="csv-overlay-box">
    <div class="csv-spinner"></div>
    <div class="csv-overlay-text">Caricamento CSV in corso…</div>
  </div>
</div>

<div class="wrapper">

  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="/" class="nav-link">Home</a>
      </li>
    </ul>
  </nav>

  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="#" class="brand-link">
      <span class="brand-text font-weight-light"><b>Backend</b> Trial</span>
    </a>
    <div class="sidebar">
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
          <li class="nav-header">PERSONE</li>
          <li class="nav-item">
            <a href="#" class="nav-link active" data-action="show-list">
              <i class="nav-icon fas fa-users"></i>
              <p>Elenco Persone</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content-wrapper">

    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1>Gestione Personale</h1></div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card card-primary card-outline card-tabs">
          <div class="card-header p-0 pt-1 border-bottom-0">
            <ul class="nav nav-tabs" id="personTabs" role="tablist">
              <li class="nav-item">
                <a class="nav-link active" id="tab-list" data-toggle="pill" href="#pane-list" role="tab" aria-controls="pane-list" aria-selected="true">
                  <i class="fas fa-list mr-1"></i> Elenco
                </a>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <div class="tab-content" id="personTabsContent">

              <div class="tab-pane fade show active" id="pane-list" role="tabpanel" aria-labelledby="tab-list">
                <div class="d-flex mb-3">
                  <button class="btn btn-success btn-sm mr-2" id="btnCreate">
                    <i class="fas fa-user-plus mr-1"></i> Nuova
                  </button>
                  <button class="btn btn-info btn-sm mr-2" id="btnUpload">
                    <i class="fas fa-file-upload mr-1"></i> Importa CSV
                  </button>
                  <input type="file" id="csvFile" accept=".csv,text/csv,text/plain" class="d-none">
                  <button class="btn btn-secondary btn-sm ml-auto" id="btnReload">
                    <i class="fas fa-sync mr-1"></i> Aggiorna
                  </button>
                </div>

                <table id="personsTable" class="table table-bordered table-striped table-hover w-100">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nome</th>
                      <th>Cognome</th>
                      <th>Email</th>
                      <th>Ruolo</th>
                      <th>Data di Nascita</th>
                      <th>Telefono</th>
                      <th class="text-center" style="min-width:140px">Azioni</th>
                    </tr>
                    <tr class="column-filters">
                      <th><input type="text" placeholder="Filtra ID"></th>
                      <th><input type="text" placeholder="Filtra nome"></th>
                      <th><input type="text" placeholder="Filtra cognome"></th>
                      <th><input type="text" placeholder="Filtra email"></th>
                      <th><input type="text" placeholder="Filtra ruolo"></th>
                      <th><input type="text" placeholder="Filtra data nascita"></th>
                      <th><input type="text" placeholder="Filtra telefono"></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <footer class="main-footer">
    <strong>Prova di Ingegneria Backend AdminLTE</strong>
  </footer>
</div>

<template id="tplPersonForm">
  <form class="person-form" novalidate>
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Nome <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="first_name" maxlength="100">
        <div class="field-error" data-error-for="first_name"></div>
      </div>
      <div class="form-group col-md-6">
        <label>Cognome <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="last_name" maxlength="100">
        <div class="field-error" data-error-for="last_name"></div>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Email <span class="text-danger">*</span></label>
        <input type="email" class="form-control" name="email" maxlength="255">
        <div class="field-error" data-error-for="email"></div>
      </div>
      <div class="form-group col-md-3">
        <label>Ruolo</label>
        <select class="form-control" name="role">
          <option value="">— nessuno —</option>
          <option value="admin">amministratore</option>
          <option value="user">utente</option>
          <option value="moderator">moderatore</option>
          <option value="guest">ospite</option>
        </select>
        <div class="field-error" data-error-for="role"></div>
      </div>
      <div class="form-group col-md-3">
        <label>Data di nascita</label>
        <input type="date" class="form-control" name="date_of_birth">
        <div class="field-error" data-error-for="date_of_birth"></div>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Numero di telefono</label>
        <input type="text" class="form-control" name="phone_number" maxlength="20" placeholder="+39 333 1234567">
        <div class="field-error" data-error-for="phone_number"></div>
      </div>
    </div>
    <div class="form-group">
      <label>Note</label>
      <textarea class="form-control" name="notes" rows="3" maxlength="1000"></textarea>
      <div class="field-error" data-error-for="notes"></div>
    </div>
    <div class="form-actions text-right">
      <button type="button" class="btn btn-default btn-cancel">Chiudi</button>
      <button type="submit" class="btn btn-primary btn-submit">
        <i class="fas fa-save mr-1"></i> <span class="submit-label">Salva</span>
      </button>
    </div>
  </form>
</template>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="plugins/toastr/toastr.min.js"></script>
<script src="plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>

<script src="assets/js/persons.js"></script>

</body>
</html>
