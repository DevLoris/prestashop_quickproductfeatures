{**
 * @author    Loris Pinna <https://lorispinna.com>
 * @copyright Loris Pinna
 * @license   Commercial
 *}
<div id="fqpf-app"
     data-ajax-url="{$fqpf_ajax_url|escape:'html':'UTF-8'}"
     data-can-edit="{if $fqpf_can_edit}1{else}0{/if}">

  {* ---------------------------------------------------------------- *}
  <div class="panel">
    <div class="panel-heading"><i class="icon-list-ul"></i> 1. Caractéristique &amp; valeur</div>
    <div class="row">
      <div class="col-md-4">
        <label for="fqpf-feature">Caractéristique</label>
        <select id="fqpf-feature" class="form-control">
          <option value="">— Choisir —</option>
          {foreach $fqpf_features as $f}
            <option value="{$f.id_feature|intval}">{$f.name|escape:'html':'UTF-8'}</option>
          {/foreach}
        </select>
        <p class="help-block" id="fqpf-feature-stats"></p>
      </div>
      <div class="col-md-4">
        <label for="fqpf-value">Valeur</label>
        <select id="fqpf-value" class="form-control" disabled>
          <option value="">— Choisir une caractéristique —</option>
        </select>
        <input type="text" id="fqpf-value-filter" class="form-control input-sm fqpf-mt5" placeholder="Filtrer les valeurs…" disabled>
      </div>
      <div class="col-md-4">
        <label>Portée du retrait</label>
        <div class="radio"><label><input type="radio" name="fqpf-scope" value="value" checked> Uniquement cette valeur</label></div>
        <div class="radio"><label><input type="radio" name="fqpf-scope" value="feature"> Toute la caractéristique (toutes valeurs, y compris personnalisées)</label></div>
      </div>
    </div>
  </div>

  {* ---------------------------------------------------------------- *}
  <div class="panel">
    <div class="panel-heading">
      <i class="icon-cubes"></i> 2. Sélection des produits
      <span class="badge fqpf-badge-sel" id="fqpf-sel-count-head">0 sélectionné</span>
    </div>

    <div class="row fqpf-filters">
      <div class="col-md-3">
        <label>Recherche</label>
        <input type="text" id="fqpf-q" class="form-control" placeholder="Nom, référence, EAN, ID…">
      </div>
      <div class="col-md-3">
        <label>Catégorie</label>
        <select id="fqpf-category" class="form-control">
          <option value="">Toutes</option>
          {foreach $fqpf_categories as $c}
            <option value="{$c.id_category|intval}">{$c.label|escape:'html':'UTF-8'}</option>
          {/foreach}
        </select>
        <div class="checkbox fqpf-mt0"><label><input type="checkbox" id="fqpf-subcats" checked> Inclure les sous-catégories</label></div>
      </div>
      <div class="col-md-2">
        <label>Marque</label>
        <select id="fqpf-manufacturer" class="form-control">
          <option value="">Toutes</option>
          <option value="0">(Sans marque)</option>
          {foreach $fqpf_manufacturers as $m}
            <option value="{$m.id_manufacturer|intval}">{$m.name|escape:'html':'UTF-8'}</option>
          {/foreach}
        </select>
      </div>
      <div class="col-md-1">
        <label>Statut</label>
        <select id="fqpf-active" class="form-control">
          <option value="">Tous</option>
          <option value="1">Actifs</option>
          <option value="0">Inactifs</option>
        </select>
      </div>
      <div class="col-md-3">
        <label>Filtre caractéristique</label>
        <select id="fqpf-feature-filter" class="form-control">
          <option value="">Indifférent</option>
          <option value="has_value">Ont la valeur choisie</option>
          <option value="not_value">N'ont pas la valeur choisie</option>
          <option value="has_feature">Ont la caractéristique (n'importe quelle valeur)</option>
          <option value="no_feature">N'ont pas la caractéristique</option>
        </select>
        <div class="checkbox fqpf-mt0"><label><input type="checkbox" id="fqpf-only-selected"> Afficher uniquement la sélection</label></div>
      </div>
    </div>

    <div class="fqpf-paste">
      <a href="#" id="fqpf-paste-toggle"><i class="icon-paste"></i> Coller une liste d'IDs ou de références…</a>
      <div id="fqpf-paste-box" style="display:none">
        <textarea id="fqpf-paste-input" class="form-control" rows="4" placeholder="Une valeur par ligne, ou séparées par virgule / espace / point-virgule"></textarea>
        <div class="fqpf-paste-actions">
          <label class="radio-inline"><input type="radio" name="fqpf-paste-type" value="id" checked> IDs produits</label>
          <label class="radio-inline"><input type="radio" name="fqpf-paste-type" value="reference"> Références (produit ou déclinaison)</label>
          <button type="button" class="btn btn-default btn-sm" id="fqpf-paste-add"><i class="icon-plus"></i> Ajouter à la sélection</button>
          <button type="button" class="btn btn-default btn-sm" id="fqpf-paste-remove"><i class="icon-minus"></i> Retirer de la sélection</button>
        </div>
        <div id="fqpf-paste-result" class="help-block"></div>
      </div>
    </div>

    <div class="fqpf-toolbar clearfix">
      <div class="pull-left">
        <button type="button" class="btn btn-default btn-sm" id="fqpf-select-page">Cocher la page</button>
        <button type="button" class="btn btn-default btn-sm" id="fqpf-unselect-page">Décocher la page</button>
        <button type="button" class="btn btn-default btn-sm" id="fqpf-select-all">Sélectionner tous les résultats (<span id="fqpf-total-2">0</span>)</button>
        <button type="button" class="btn btn-default btn-sm" id="fqpf-unselect-all-results">Désélectionner tous les résultats</button>
        <button type="button" class="btn btn-link btn-sm text-danger" id="fqpf-clear">Vider la sélection</button>
      </div>
      <div class="pull-right form-inline">
        <select id="fqpf-sort" class="form-control input-sm">
          <option value="id_desc">ID ↓</option>
          <option value="id_asc">ID ↑</option>
          <option value="name_asc">Nom A→Z</option>
          <option value="name_desc">Nom Z→A</option>
          <option value="ref_asc">Référence</option>
        </select>
        <select id="fqpf-limit" class="form-control input-sm">
          <option value="20">20</option>
          <option value="50" selected>50</option>
          <option value="100">100</option>
          <option value="300">300</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-condensed fqpf-table">
        <thead>
          <tr>
            <th class="fqpf-col-check"><input type="checkbox" id="fqpf-check-page"></th>
            <th class="fqpf-col-img"></th>
            <th>ID</th>
            <th>Référence</th>
            <th>Nom</th>
            <th>Statut</th>
            <th>Valeur(s) actuelle(s) <span id="fqpf-col-feature-name" class="text-muted"></span></th>
            <th></th>
          </tr>
        </thead>
        <tbody id="fqpf-tbody">
          <tr><td colspan="8" class="text-center text-muted">Chargement…</td></tr>
        </tbody>
      </table>
    </div>

    <div class="fqpf-pagination clearfix">
      <div class="pull-left text-muted"><span id="fqpf-total">0</span> produit(s) trouvé(s)</div>
      <div class="pull-right">
        <button type="button" class="btn btn-default btn-sm" id="fqpf-prev"><i class="icon-chevron-left"></i></button>
        Page <input type="number" id="fqpf-page" min="1" value="1" class="form-control input-sm fqpf-page-input"> / <span id="fqpf-pages">1</span>
        <button type="button" class="btn btn-default btn-sm" id="fqpf-next"><i class="icon-chevron-right"></i></button>
      </div>
    </div>
  </div>

  {* ---------------------------------------------------------------- *}
  <div class="panel">
    <div class="panel-heading"><i class="icon-cogs"></i> 3. Action</div>

    {if !$fqpf_can_edit}
      <div class="alert alert-warning">Votre profil n'a pas les droits de modification sur cet onglet.</div>
    {/if}

    <div class="row">
      <div class="col-md-6">
        <h4>Ajouter la valeur</h4>
        <div class="radio"><label><input type="radio" name="fqpf-mode" value="replace" checked> <strong>Remplacer</strong> les valeurs existantes de cette caractéristique</label></div>
        <div class="radio"><label><input type="radio" name="fqpf-mode" value="append"> <strong>Ajouter</strong> en plus des valeurs existantes (multi-valeurs)</label></div>
        <button type="button" class="btn btn-primary fqpf-action" data-op="add" data-target="selection">
          <i class="icon-plus-sign"></i> Ajouter aux produits sélectionnés (<span class="fqpf-sel-count">0</span>)
        </button>
      </div>
      <div class="col-md-6">
        <h4>Retirer <small id="fqpf-scope-label">la valeur</small></h4>
        <p class="text-muted">La portée (valeur seule ou caractéristique entière) se règle dans le bloc 1.</p>
        <button type="button" class="btn btn-warning fqpf-action" data-op="remove" data-target="selection">
          <i class="icon-minus-sign"></i> Retirer des produits sélectionnés (<span class="fqpf-sel-count">0</span>)
        </button>
        <button type="button" class="btn btn-danger fqpf-action" data-op="remove" data-target="all">
          <i class="icon-trash"></i> Retirer de TOUS les produits
        </button>
      </div>
    </div>

    <hr>
    <div class="checkbox">
      <label>
        <input type="checkbox" id="fqpf-hooks">
        Déclencher les hooks <code>actionProductSave</code> / <code>actionProductUpdate</code> pour chaque produit
        <span class="text-muted">(plus lent — utile si un module de flux/synchro doit être notifié)</span>
      </label>
    </div>

    <div id="fqpf-progress-wrap" style="display:none">
      <div class="progress">
        <div class="progress-bar progress-bar-striped active" id="fqpf-progress" role="progressbar" style="width:0%">0%</div>
      </div>
    </div>
    <div id="fqpf-log"></div>
  </div>

  <p class="text-muted text-right"><small>flaveo_quickproductfeatures v{$fqpf_version|escape:'html':'UTF-8'} — <a href="https://lorispinna.com" target="_blank" rel="noopener">Loris Pinna</a></small></p>
</div>
