$(function () {
  toastr.options = { positionClass: 'toast-top-right', timeOut: 4000, progressBar: true };

  const apiToken    = $('meta[name="api-token"]').attr('content') || '';
  const tokenHeader = $('meta[name="api-token-header"]').attr('content') || 'X-API-Token';

  $.ajaxSetup({
    beforeSend: function (xhr) {
      if (apiToken) xhr.setRequestHeader(tokenHeader, apiToken);
    }
  });

  const STORAGE_KEY = 'persons_api_base';
  let apiBase = localStorage.getItem(STORAGE_KEY) || 'http://localhost:8050';
  $('#apiBase').val(apiBase);
  $('#apiHost').text(apiBase);

  $('#applyApiBase').on('click', function () {
    apiBase = ($('#apiBase').val() || '').replace(/\/+$/, '');
    localStorage.setItem(STORAGE_KEY, apiBase);
    $('#apiHost').text(apiBase);
    reloadTable();
    toastr.info('URL API aggiornato.');
  });

  function api(path) { return apiBase.replace(/\/+$/, '') + path; }

  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function actionsHtml(id) {
    return '<div class="dt-actions text-center">'
      + '<button class="btn btn-info btn-xs" data-act="view" data-id="' + id + '" title="Visualizza"><i class="fas fa-eye"></i></button> '
      + '<button class="btn btn-warning btn-xs" data-act="edit" data-id="' + id + '" title="Modifica"><i class="fas fa-pen"></i></button> '
      + '<button class="btn btn-danger btn-xs" data-act="delete" data-id="' + id + '" title="Elimina"><i class="fas fa-trash"></i></button>'
      + '</div>';
  }

  let table = null;

  function initTable() {
    table = $('#personsTable').DataTable({
      data: [],
      responsive: true,
      autoWidth: false,
      order: [[0, 'asc']],
      columns: [
        { data: 'id' },
        { data: 'first_name' },
        { data: 'last_name' },
        { data: 'email' },
        { data: 'role', defaultContent: '' },
        { data: 'date_of_birth', defaultContent: '' },
        { data: 'phone_number', defaultContent: '' },
        {
          data: 'id',
          orderable: false,
          searchable: false,
          render: function (id) { return actionsHtml(id); }
        }
      ],
      orderCellsTop: true,
      initComplete: function () {
        const apiInst = this.api();
        apiInst.columns().every(function (idx) {
          const column = this;
          $('#personsTable thead tr.column-filters th').eq(idx).find('input').on('keyup change clear', function () {
            if (column.search() !== this.value) {
              column.search(this.value).draw();
            }
          });
        });
      }
    });

    $('#personsTable tbody').on('click', 'button[data-act]', function () {
      const id = $(this).data('id');
      const act = $(this).data('act');
      if (act === 'view')   openPersonTab('view', id);
      if (act === 'edit')   openPersonTab('edit', id);
      if (act === 'delete') deletePerson(id);
    });
  }

  function reloadTable() {
    return $.ajax({ url: api('/persons'), method: 'GET', dataType: 'json' })
      .done(function (resp) {
        const rows = (resp && resp.data) ? resp.data : [];
        table.clear().rows.add(rows).draw();
      })
      .fail(function (xhr) {
        toastr.error('Caricamento persone fallito. ' + (xhr.statusText || ''));
      });
  }

  function deletePerson(id) {
    Swal.fire({
      title: 'Eliminare persona #' + id + '?',
      text: 'Questa azione non può essere annullata.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Elimina',
      cancelButtonText: 'Annulla',
      confirmButtonColor: '#dc3545'
    }).then(function (result) {
      if (!result.isConfirmed) return;
      $.ajax({ url: api('/persons/' + id), method: 'DELETE' })
        .done(function () {
          toastr.success('Persona #' + id + ' eliminata.');
          closePersonTabs(id);
          reloadTable();
        })
        .fail(function (xhr) {
          if (xhr.status === 404) toastr.warning('Persona non trovata.');
          else toastr.error('Eliminazione fallita.');
        });
    });
  }

  // ----- Tab dinamici ----
  let dynamicSeq = 0;

  function buildFormPane(paneId, mode) {
    const tpl = document.getElementById('tplPersonForm').content.cloneNode(true);
    const form = tpl.querySelector('form');
    form.dataset.mode = mode;
    if (mode === 'view') {
      form.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = true; });
      form.querySelector('.btn-submit').classList.add('d-none');
      form.querySelector('.btn-cancel').textContent = 'Chiudi';
    } else {
      form.querySelector('.submit-label').textContent = (mode === 'edit') ? 'Aggiorna' : 'Crea';
    }
    const wrapper = document.createElement('div');
    wrapper.className = 'tab-pane fade';
    wrapper.id = paneId;
    wrapper.setAttribute('role', 'tabpanel');
    wrapper.appendChild(tpl);
    return wrapper;
  }

  function appendTab(label, paneId, icon) {
    const tabId = 'tab-' + paneId;
    const li = $(
      '<li class="nav-item">'
      + '<a class="nav-link" id="' + tabId + '" data-toggle="pill" href="#' + paneId + '" role="tab" aria-controls="' + paneId + '" aria-selected="false">'
      +   '<i class="fas ' + icon + ' mr-1"></i>' + escapeHtml(label)
      +   ' <span class="close-tab" title="Close">&times;</span>'
      + '</a>'
      + '</li>'
    );
    $('#personTabs').append(li);
    li.find('.close-tab').on('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      closeTab(paneId);
    });
    return tabId;
  }

  function activateTab(tabId) {
    $('#' + tabId).tab('show');
  }

  function closeTab(paneId) {
    const tabId = 'tab-' + paneId;
    const wasActive = $('#' + tabId).hasClass('active');
    $('#' + tabId).closest('li').remove();
    $('#' + paneId).remove();
    if (wasActive) $('#tab-list').tab('show');
  }

  function closePersonTabs(id) {
    ['view-' + id, 'edit-' + id].forEach(function (suffix) {
      const paneId = 'pane-' + suffix;
      if (document.getElementById(paneId)) closeTab(paneId);
    });
  }

  function fillForm(form, person) {
    ['first_name','last_name','email','role','date_of_birth','phone_number','notes'].forEach(function (k) {
      const el = form.querySelector('[name="' + k + '"]');
      if (el) el.value = person[k] === null || person[k] === undefined ? '' : person[k];
    });
  }

  function readForm(form) {
    const data = {};
    ['first_name','last_name','email','role','date_of_birth','phone_number','notes'].forEach(function (k) {
      const el = form.querySelector('[name="' + k + '"]');
      const v = el ? el.value.trim() : '';
      if (k === 'role' || k === 'date_of_birth' || k === 'phone_number' || k === 'notes') {
        data[k] = v === '' ? null : v;
      } else {
        data[k] = v;
      }
    });
    return data;
  }

  function clearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('.field-error').forEach(function (el) { el.textContent = ''; });
  }

  function applyErrors(form, fieldsErrors) {
    clearErrors(form);
    Object.keys(fieldsErrors || {}).forEach(function (field) {
      const input = form.querySelector('[name="' + field + '"]');
      const target = form.querySelector('[data-error-for="' + field + '"]');
      const messages = (fieldsErrors[field] || []).join(' ');
      if (input) input.classList.add('is-invalid');
      if (target) target.textContent = messages;
    });
  }

  function openPersonTab(mode, id) {
    let paneId;
    if (mode === 'create') {
      dynamicSeq += 1;
      paneId = 'pane-create-' + dynamicSeq;
    } else {
      paneId = 'pane-' + mode + '-' + id;
      if (document.getElementById(paneId)) {
        activateTab('tab-' + paneId);
        return;
      }
    }

    const pane = buildFormPane(paneId, mode);
    document.getElementById('personTabsContent').appendChild(pane);

    let label, icon;
    if (mode === 'create')      { label = 'Crea'; icon = 'fa-user-plus'; }
    else if (mode === 'view')   { label = 'Visualizza #' + id; icon = 'fa-eye'; }
    else                        { label = 'Modifica #' + id; icon = 'fa-pen'; }

    const tabId = appendTab(label, paneId, icon);
    activateTab(tabId);

    const form = pane.querySelector('form');

    form.querySelector('.btn-cancel').addEventListener('click', function () {
      closeTab(paneId);
    });

    if (mode === 'view' || mode === 'edit') {
      $.ajax({ url: api('/persons/' + id), method: 'GET', dataType: 'json' })
        .done(function (resp) {
          if (resp && resp.data) fillForm(form, resp.data);
          else { toastr.error('Persona non trovata.'); closeTab(paneId); }
        })
        .fail(function (xhr) {
          toastr.error(xhr.status === 404 ? 'Persona non trovata.' : 'Caricamento persona fallito.');
          closeTab(paneId);
        });
    }

    if (mode === 'view') return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const payload = readForm(form);
      const isEdit = (mode === 'edit');
      const url = isEdit ? api('/persons/' + id) : api('/persons');
      const method = isEdit ? 'PUT' : 'POST';

      $.ajax({
        url: url, method: method, contentType: 'application/json',
        data: JSON.stringify(payload), dataType: 'json'
      })
      .done(function () {
        toastr.success(isEdit ? ('Persona #' + id + ' aggiornata.') : 'Persona creata.');
        closeTab(paneId);
        reloadTable();
      })
      .fail(function (xhr) {
        if (xhr.status === 422) {
          const body = xhr.responseJSON || {};
          const fields = (body.data && body.data.fields) || {};
          applyErrors(form, fields);
          toastr.warning('Correggi i campi evidenziati.');
        } else if (xhr.status === 409) {
          applyErrors(form, { email: ['Email già esistente.'] });
          toastr.warning('Email duplicata.');
        } else {
          toastr.error('Salvataggio fallito (' + xhr.status + ').');
        }
      });
    });
  }

  // ----- Caricamento CSV -----
  $('#btnUpload').on('click', function () { $('#csvFile').trigger('click'); });

  function showCsvOverlay(show) {
    const $o = $('#csvOverlay');
    $o.toggleClass('d-none', !show).attr('aria-hidden', show ? 'false' : 'true');
    $('#btnUpload, #btnCreate, #btnReload').prop('disabled', show);
  }

  const CSV_MAX_BYTES = 5 * 1024 * 1024;
  const CSV_ALLOWED_MIMES = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', ''];

  function validateCsvFile(file) {
    const name = (file.name || '').toLowerCase();
    if (!name.endsWith('.csv')) {
      return 'Sono ammessi solo file .csv.';
    }
    if (CSV_ALLOWED_MIMES.indexOf(file.type || '') === -1) {
      return 'Tipo file MIME "' + file.type + '" non è un CSV.';
    }
    if (file.size > CSV_MAX_BYTES) {
      const mb = (file.size / 1024 / 1024).toFixed(2);
      return 'File troppo grande (' + mb + ' MB). Massimo ' + (CSV_MAX_BYTES / 1024 / 1024) + ' MB.';
    }
    if (file.size === 0) {
      return 'File vuoto.';
    }
    return null;
  }

  $('#csvFile').on('change', function () {
    const file = this.files && this.files[0];
    if (!file) return;

    const error = validateCsvFile(file);
    if (error) {
      toastr.error(error);
      $('#csvFile').val('');
      return;
    }

    const fd = new FormData();
    fd.append('file', file);

    showCsvOverlay(true);

    $.ajax({
      url: api('/persons/import'), method: 'POST',
      data: fd, processData: false, contentType: false, dataType: 'json'
    })
    .done(function (resp) {
      const s = (resp && resp.data) || {};
      const msg = 'Importazione: totale=' + (s.total || 0)
        + ', validi=' + (s.valid || 0)
        + ', non validi=' + (s.invalid || 0);
      if ((s.invalid || 0) > 0) toastr.warning(msg);
      else toastr.success(msg);
      reloadTable();
    })
    .fail(function (xhr) {
      const body = xhr.responseJSON || {};
      const fields = (body.data && body.data.fields) || {};
      const reason = fields.file ? fields.file.join(' ') : (body.error && body.error.description) || 'Caricamento fallito.';
      toastr.error(reason);
    })
    .always(function () {
      $('#csvFile').val('');
      showCsvOverlay(false);
    });
  });

  // ----- Barra strumenti -----

  $('#btnCreate').on('click', function () { openPersonTab('create'); });
  $('#btnReload').on('click', function () { reloadTable(); });

  $('.nav-sidebar [data-action]').on('click', function (e) {
    e.preventDefault();
    const act = $(this).data('action');
    if (act === 'show-list')   $('#tab-list').tab('show');
    if (act === 'open-create') openPersonTab('create');
    if (act === 'open-upload') $('#btnUpload').trigger('click');
  });

  initTable();
  reloadTable();
});
