/**
 * @author    Loris Pinna <https://lorispinna.com>
 * @copyright Loris Pinna
 * @license   Commercial
 */
(function ($) {
  'use strict';

  $(function () {
    var $app = $('#fqpf-app');
    if (!$app.length) {
      return;
    }

    var ajaxUrl = $app.data('ajax-url');
    var canEdit = String($app.data('can-edit')) === '1';

    var state = {
      selected: new Set(),
      page: 1,
      pages: 1,
      total: 0,
      pageIds: [],
      values: [],
      busy: false
    };

    // ------------------------------------------------------------------
    // Utils
    // ------------------------------------------------------------------
    function call(action, data) {
      return $.ajax({
        url: ajaxUrl + '&ajax=1&action=' + encodeURIComponent(action),
        type: 'POST',
        dataType: 'json',
        data: data || {}
      }).then(function (res) {
        if (!res || !res.success) {
          return $.Deferred().reject((res && res.error) || 'Erreur inconnue').promise();
        }
        return res;
      }, function (xhr) {
        var msg = 'Erreur HTTP ' + xhr.status;
        if (xhr.responseText && xhr.responseText.length < 500) {
          msg += ' : ' + $('<div>').html(xhr.responseText).text();
        }
        return $.Deferred().reject(msg).promise();
      });
    }

    function esc(str) {
      return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function debounce(fn, wait) {
      var t;
      return function () {
        var args = arguments, ctx = this;
        clearTimeout(t);
        t = setTimeout(function () { fn.apply(ctx, args); }, wait);
      };
    }

    function chunk(arr, size) {
      var out = [];
      for (var i = 0; i < arr.length; i += size) {
        out.push(arr.slice(i, i + size));
      }
      return out;
    }

    function log(msg, type) {
      var cls = type === 'error' ? 'alert-danger' : (type === 'warning' ? 'alert-warning' : 'alert-success');
      $('#fqpf-log').prepend('<div class="alert ' + cls + ' fqpf-log-line">' + msg + '</div>');
    }

    function featureId() { return parseInt($('#fqpf-feature').val(), 10) || 0; }
    function valueId() { return parseInt($('#fqpf-value').val(), 10) || 0; }
    function scope() { return $('input[name="fqpf-scope"]:checked').val(); }
    function mode() { return $('input[name="fqpf-mode"]:checked').val(); }
    function featureName() { return featureId() ? $('#fqpf-feature option:selected').text() : ''; }
    function valueName() {
      var id = valueId();
      for (var i = 0; i < state.values.length; i++) {
        if (parseInt(state.values[i].id_feature_value, 10) === id) {
          return state.values[i].value;
        }
      }
      return '';
    }

    // ------------------------------------------------------------------
    // Caractéristique / valeurs
    // ------------------------------------------------------------------
    function renderValueOptions() {
      var filter = ($('#fqpf-value-filter').val() || '').toLowerCase();
      var current = $('#fqpf-value').val();
      var html = '<option value="">— Choisir une valeur —</option>';
      state.values.forEach(function (v) {
        if (filter && String(v.value).toLowerCase().indexOf(filter) === -1 && String(v.id_feature_value) !== current) {
          return;
        }
        html += '<option value="' + parseInt(v.id_feature_value, 10) + '">' +
          esc(v.value) + ' (' + parseInt(v.nb_products, 10) + ' produit' + (v.nb_products > 1 ? 's' : '') + ')</option>';
      });
      $('#fqpf-value').html(html).val(current);
    }

    function loadValues() {
      var id = featureId();
      state.values = [];
      $('#fqpf-feature-stats').text('');
      $('#fqpf-col-feature-name').text(id ? '(' + featureName() + ')' : '');

      if (!id) {
        $('#fqpf-value').html('<option value="">— Choisir une caractéristique —</option>').prop('disabled', true);
        $('#fqpf-value-filter').val('').prop('disabled', true);
        return $.Deferred().resolve().promise();
      }

      $('#fqpf-value').prop('disabled', true);
      return call('getValues', { id_feature: id }).then(function (res) {
        state.values = res.values;
        renderValueOptions();
        $('#fqpf-value, #fqpf-value-filter').prop('disabled', false);
        var txt = res.nb_products_feature + ' produit(s) ont cette caractéristique';
        if (res.nb_products_custom) {
          txt += ' — dont ' + res.nb_products_custom + ' avec une valeur personnalisée';
        }
        $('#fqpf-feature-stats').text(txt);
      }, function (err) {
        log(esc(err), 'error');
      });
    }

    // ------------------------------------------------------------------
    // Produits
    // ------------------------------------------------------------------
    function filters() {
      var data = {
        q: $('#fqpf-q').val(),
        id_category: $('#fqpf-category').val(),
        subcats: $('#fqpf-subcats').is(':checked') ? 1 : 0,
        id_manufacturer: $('#fqpf-manufacturer').val(),
        active: $('#fqpf-active').val(),
        id_feature: featureId(),
        id_feature_value: valueId(),
        feature_filter: $('#fqpf-feature-filter').val()
      };
      if ($('#fqpf-only-selected').is(':checked')) {
        data.only_selected = 1;
        data.only_ids = Array.from(state.selected).join(',');
      }
      return data;
    }

    function loadProducts() {
      var data = filters();
      data.page = state.page;
      data.limit = $('#fqpf-limit').val();
      data.sort = $('#fqpf-sort').val();

      $('#fqpf-tbody').addClass('fqpf-loading');
      return call('searchProducts', data).then(function (res) {
        state.total = res.total;
        state.pages = res.pages;
        state.page = res.page;
        state.pageIds = res.products.map(function (p) { return p.id_product; });
        renderProducts(res.products);
      }, function (err) {
        $('#fqpf-tbody').html('<tr><td colspan="8" class="text-danger">' + esc(err) + '</td></tr>');
      }).always(function () {
        $('#fqpf-tbody').removeClass('fqpf-loading');
      });
    }

    function renderProducts(products) {
      var html = '';
      if (!products.length) {
        html = '<tr><td colspan="8" class="text-center text-muted">Aucun produit.</td></tr>';
      }
      products.forEach(function (p) {
        var checked = state.selected.has(p.id_product);
        html += '<tr data-id="' + p.id_product + '" class="' + (checked ? 'fqpf-row-selected' : '') + '">' +
          '<td class="fqpf-col-check"><input type="checkbox" class="fqpf-check" value="' + p.id_product + '"' + (checked ? ' checked' : '') + '></td>' +
          '<td class="fqpf-col-img">' + (p.thumb ? '<img src="' + esc(p.thumb) + '" alt="" loading="lazy">' : '') + '</td>' +
          '<td>' + p.id_product + '</td>' +
          '<td>' + esc(p.reference) + '</td>' +
          '<td>' + esc(p.name) + '</td>' +
          '<td>' + (p.active ? '<span class="label label-success">Actif</span>' : '<span class="label label-default">Inactif</span>') + '</td>' +
          '<td>' + (p.current_values ? esc(p.current_values) : '<span class="text-muted">—</span>') + '</td>' +
          '<td class="text-right"><a href="' + esc(p.edit_url) + '" target="_blank" class="btn btn-default btn-xs" title="Éditer"><i class="icon-pencil"></i></a></td>' +
          '</tr>';
      });
      $('#fqpf-tbody').html(html);
      $('#fqpf-total, #fqpf-total-2').text(state.total);
      $('#fqpf-pages').text(state.pages);
      $('#fqpf-page').val(state.page).attr('max', state.pages);
      $('#fqpf-prev').prop('disabled', state.page <= 1);
      $('#fqpf-next').prop('disabled', state.page >= state.pages);
      refreshSelectionUi();
    }

    function refreshSelectionUi() {
      var n = state.selected.size;
      $('.fqpf-sel-count').text(n);
      $('#fqpf-sel-count-head').text(n + ' sélectionné' + (n > 1 ? 's' : ''));
      var allOnPage = state.pageIds.length > 0 && state.pageIds.every(function (id) { return state.selected.has(id); });
      $('#fqpf-check-page').prop('checked', allOnPage);
    }

    function setSelected(ids, on) {
      ids.forEach(function (id) {
        id = parseInt(id, 10);
        if (!id) { return; }
        if (on) { state.selected.add(id); } else { state.selected.delete(id); }
      });
      $('#fqpf-tbody tr[data-id]').each(function () {
        var id = parseInt($(this).data('id'), 10);
        var c = state.selected.has(id);
        $(this).toggleClass('fqpf-row-selected', c).find('.fqpf-check').prop('checked', c);
      });
      refreshSelectionUi();
    }

    var reloadFirstPage = function () {
      state.page = 1;
      loadProducts();
    };

    // ------------------------------------------------------------------
    // Traitement par lots
    // ------------------------------------------------------------------
    function setBusy(b) {
      state.busy = b;
      $('.fqpf-action').prop('disabled', b || !canEdit);
      $('#fqpf-progress-wrap').toggle(b);
    }

    function progress(done, total) {
      var pct = total ? Math.round(done * 100 / total) : 100;
      $('#fqpf-progress').css('width', pct + '%').text(pct + '% (' + done + ' / ' + total + ')');
    }

    function runBatches(op, ids) {
      var hooks = $('#fqpf-hooks').is(':checked');
      var batches = chunk(ids, hooks ? 50 : 500);
      var done = 0;
      var affected = 0;
      var base = {
        op: op,
        id_feature: featureId(),
        id_feature_value: valueId(),
        scope: scope(),
        mode: mode(),
        hooks: hooks ? 1 : 0
      };

      setBusy(true);
      progress(0, ids.length);

      var chain = $.Deferred().resolve().promise();
      batches.forEach(function (batch) {
        chain = chain.then(function () {
          return call('apply', $.extend({}, base, { ids: batch.join(',') })).then(function (res) {
            done += batch.length;
            affected += res.affected;
            progress(done, ids.length);
          });
        });
      });

      return chain.then(function () {
        return call('finalize');
      }).then(function () {
        return { processed: done, affected: affected };
      }).always(function () {
        setBusy(false);
      });
    }

    function describeTarget(op) {
      var f = '« ' + esc(featureName()) + ' »';
      if (op === 'remove' && scope() === 'feature') {
        return 'la caractéristique ' + f + ' (toutes valeurs)';
      }
      return 'la valeur « ' + esc(valueName()) + ' » de ' + f;
    }

    function plainText(html) {
      return $('<div>').html(html).text();
    }

    function onAction() {
      if (state.busy || !canEdit) { return; }
      var op = $(this).data('op');
      var target = $(this).data('target');

      if (!featureId()) {
        log('Choisissez une caractéristique.', 'warning');
        return;
      }
      if ((op === 'add' || scope() === 'value') && !valueId()) {
        log('Choisissez une valeur.', 'warning');
        return;
      }

      var label = describeTarget(op);

      if (target === 'all') {
        call('idsWithValue', { id_feature: featureId(), id_feature_value: valueId(), scope: scope() }).then(function (res) {
          if (!res.ids.length) {
            log('Aucun produit ne possède ' + label + '.', 'warning');
            return;
          }
          var msg = 'Retirer ' + plainText(label) + ' de TOUS les produits ?\n\n' + res.ids.length + ' produit(s) concerné(s). Cette action est irréversible.';
          if (!window.confirm(msg)) { return; }
          runBatches('remove', res.ids).then(function (r) {
            log('Retrait terminé : ' + label + ' — ' + r.affected + ' association(s) supprimée(s) sur ' + r.processed + ' produit(s).');
            afterRun();
          }, function (err) { log(esc(err), 'error'); afterRun(); });
        }, function (err) { log(esc(err), 'error'); });
        return;
      }

      var ids = Array.from(state.selected);
      if (!ids.length) {
        log('Aucun produit sélectionné.', 'warning');
        return;
      }

      var verb = op === 'add'
        ? 'Ajouter ' + plainText(label) + ' à ' + ids.length + ' produit(s)' + (mode() === 'replace' ? ' en remplaçant les valeurs existantes' : ' en plus des valeurs existantes')
        : 'Retirer ' + plainText(label) + ' de ' + ids.length + ' produit(s)';
      if (!window.confirm(verb + ' ?')) { return; }

      runBatches(op, ids).then(function (r) {
        var what = op === 'add' ? 'association(s) créée(s)' : 'association(s) supprimée(s)';
        log((op === 'add' ? 'Ajout' : 'Retrait') + ' terminé : ' + label + ' — ' + r.affected + ' ' + what + ' sur ' + r.processed + ' produit(s).');
        afterRun();
      }, function (err) { log(esc(err), 'error'); afterRun(); });
    }

    function afterRun() {
      var keep = valueId();
      loadValues().then(function () {
        $('#fqpf-value').val(keep);
        loadProducts();
      });
    }

    // ------------------------------------------------------------------
    // Événements
    // ------------------------------------------------------------------
    $('#fqpf-feature').on('change', function () {
      $('#fqpf-value-filter').val('');
      loadValues().then(reloadFirstPage);
    });
    $('#fqpf-value').on('change', function () {
      if ($('#fqpf-feature-filter').val() === 'has_value' || $('#fqpf-feature-filter').val() === 'not_value') {
        reloadFirstPage();
      }
    });
    $('#fqpf-value-filter').on('input', debounce(renderValueOptions, 150));
    $('input[name="fqpf-scope"]').on('change', function () {
      $('#fqpf-scope-label').text(scope() === 'feature' ? 'toute la caractéristique' : 'la valeur');
    });

    $('#fqpf-q').on('input', debounce(reloadFirstPage, 350));
    $('#fqpf-category, #fqpf-subcats, #fqpf-manufacturer, #fqpf-active, #fqpf-feature-filter, #fqpf-only-selected, #fqpf-limit, #fqpf-sort')
      .on('change', reloadFirstPage);

    $('#fqpf-prev').on('click', function () { if (state.page > 1) { state.page--; loadProducts(); } });
    $('#fqpf-next').on('click', function () { if (state.page < state.pages) { state.page++; loadProducts(); } });
    $('#fqpf-page').on('change', function () {
      var p = parseInt($(this).val(), 10) || 1;
      state.page = Math.min(Math.max(1, p), state.pages);
      loadProducts();
    });

    $('#fqpf-tbody').on('change', '.fqpf-check', function () {
      setSelected([$(this).val()], $(this).is(':checked'));
    });
    $('#fqpf-tbody').on('click', 'td:not(.fqpf-col-check):not(:last-child)', function () {
      var $cb = $(this).closest('tr').find('.fqpf-check');
      if ($cb.length) { $cb.prop('checked', !$cb.prop('checked')).trigger('change'); }
    });
    $('#fqpf-check-page').on('change', function () { setSelected(state.pageIds, $(this).is(':checked')); });
    $('#fqpf-select-page').on('click', function () { setSelected(state.pageIds, true); });
    $('#fqpf-unselect-page').on('click', function () { setSelected(state.pageIds, false); });
    $('#fqpf-clear').on('click', function () {
      state.selected.clear();
      setSelected([], true);
      if ($('#fqpf-only-selected').is(':checked')) { reloadFirstPage(); }
    });

    function allResults(on) {
      var $btns = $('#fqpf-select-all, #fqpf-unselect-all-results').prop('disabled', true);
      call('searchIds', filters()).then(function (res) {
        setSelected(res.ids, on);
        log(res.ids.length + ' produit(s) ' + (on ? 'ajouté(s) à' : 'retiré(s) de') + ' la sélection.');
      }, function (err) { log(esc(err), 'error'); }).always(function () { $btns.prop('disabled', false); });
    }
    $('#fqpf-select-all').on('click', function () { allResults(true); });
    $('#fqpf-unselect-all-results').on('click', function () { allResults(false); });

    $('#fqpf-paste-toggle').on('click', function (e) {
      e.preventDefault();
      $('#fqpf-paste-box').slideToggle(150);
    });
    function paste(on) {
      var raw = $('#fqpf-paste-input').val();
      call('resolveIds', { raw: raw, type: $('input[name="fqpf-paste-type"]:checked').val() }).then(function (res) {
        setSelected(res.ids, on);
        var txt = res.ids.length + ' produit(s) ' + (on ? 'ajouté(s) à' : 'retiré(s) de') + ' la sélection.';
        if (res.nb_not_found) {
          txt += ' ' + res.nb_not_found + ' introuvable(s) : ' + esc(res.not_found.join(', ')) + (res.nb_not_found > res.not_found.length ? '…' : '');
        }
        $('#fqpf-paste-result').html(txt);
        if ($('#fqpf-only-selected').is(':checked')) { reloadFirstPage(); }
      }, function (err) { $('#fqpf-paste-result').html('<span class="text-danger">' + esc(err) + '</span>'); });
    }
    $('#fqpf-paste-add').on('click', function () { paste(true); });
    $('#fqpf-paste-remove').on('click', function () { paste(false); });

    $('.fqpf-action').on('click', onAction).prop('disabled', !canEdit);

    // Init
    loadProducts();
  });
})(jQuery);
