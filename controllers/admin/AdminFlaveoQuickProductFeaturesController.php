<?php
/**
 * @author    Loris Pinna <https://lorispinna.com>
 * @copyright Loris Pinna
 * @license   Commercial
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminFlaveoQuickProductFeaturesController extends ModuleAdminController
{
    /** Nombre max d'IDs traités par appel AJAX */
    const MAX_IDS_PER_CALL = 1000;

    /** Nombre max d'IDs renvoyés par "sélectionner tous les résultats" */
    const MAX_SELECT_ALL = 100000;

    /** @var Flaveo_Quickproductfeatures */
    public $module;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = 'Caractéristiques en masse';
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css?v=' . $this->module->version);
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js?v=' . $this->module->version);
    }

    public function initToolbar()
    {
        parent::initToolbar();
        $this->toolbar_btn = [];
    }

    /**
     * Page principale (pas de liste classique : on rend notre propre template).
     */
    public function renderList()
    {
        $idLang = (int) $this->context->language->id;

        $this->context->smarty->assign([
            'fqpf_ajax_url' => $this->context->link->getAdminLink($this->controller_name),
            'fqpf_features' => Feature::getFeatures($idLang),
            'fqpf_categories' => $this->getCategoriesFlat(),
            'fqpf_manufacturers' => Manufacturer::getManufacturers(false, $idLang, false),
            'fqpf_can_edit' => (bool) $this->access('edit'),
            'fqpf_version' => $this->module->version,
        ]);

        return $this->context->smarty->fetch(
            $this->module->getLocalPath() . 'views/templates/admin/main.tpl'
        );
    }

    /* ------------------------------------------------------------------ */
    /* AJAX                                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Valeurs d'une caractéristique + nombre de produits par valeur.
     */
    public function ajaxProcessGetValues()
    {
        $idFeature = (int) Tools::getValue('id_feature');
        if (!$this->featureExists($idFeature)) {
            $this->fqpfJsonError('Caractéristique introuvable.');
        }
        $idLang = (int) $this->context->language->id;

        $values = Db::getInstance()->executeS(
            'SELECT fv.`id_feature_value`, fvl.`value`,
                (SELECT COUNT(DISTINCT fp.`id_product`) FROM `' . _DB_PREFIX_ . 'feature_product` fp
                 WHERE fp.`id_feature_value` = fv.`id_feature_value`) AS nb_products
             FROM `' . _DB_PREFIX_ . 'feature_value` fv
             LEFT JOIN `' . _DB_PREFIX_ . 'feature_value_lang` fvl
                ON fvl.`id_feature_value` = fv.`id_feature_value` AND fvl.`id_lang` = ' . $idLang . '
             WHERE fv.`id_feature` = ' . $idFeature . ' AND (fv.`custom` = 0 OR fv.`custom` IS NULL)
             ORDER BY fvl.`value` ASC'
        );

        $nbFeature = (int) Db::getInstance()->getValue(
            'SELECT COUNT(DISTINCT `id_product`) FROM `' . _DB_PREFIX_ . 'feature_product`
             WHERE `id_feature` = ' . $idFeature
        );

        $nbCustom = (int) Db::getInstance()->getValue(
            'SELECT COUNT(DISTINCT fp.`id_product`) FROM `' . _DB_PREFIX_ . 'feature_product` fp
             INNER JOIN `' . _DB_PREFIX_ . 'feature_value` fv ON fv.`id_feature_value` = fp.`id_feature_value`
             WHERE fp.`id_feature` = ' . $idFeature . ' AND fv.`custom` = 1'
        );

        $this->fqpfJsonOk([
            'values' => $values ?: [],
            'nb_products_feature' => $nbFeature,
            'nb_products_custom' => $nbCustom,
        ]);
    }

    /**
     * Liste paginée des produits selon les filtres.
     */
    public function ajaxProcessSearchProducts()
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $idFeature = (int) Tools::getValue('id_feature');

        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = (int) Tools::getValue('limit', 50);
        if (!in_array($limit, [20, 50, 100, 300], true)) {
            $limit = 50;
        }

        $sortMap = [
            'id_desc' => 'p.`id_product` DESC',
            'id_asc' => 'p.`id_product` ASC',
            'name_asc' => 'pl.`name` ASC',
            'name_desc' => 'pl.`name` DESC',
            'ref_asc' => 'p.`reference` ASC',
        ];
        $sort = (string) Tools::getValue('sort', 'id_desc');
        $orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : $sortMap['id_desc'];

        $where = $this->buildProductWhere();
        $from = $this->buildProductFrom();

        $total = (int) Db::getInstance()->getValue('SELECT COUNT(*) ' . $from . ' WHERE ' . $where);

        $currentValuesSql = '\'\'';
        if ($idFeature) {
            $currentValuesSql = '(SELECT GROUP_CONCAT(
                    CONCAT(IFNULL(fvl.`value`, CONCAT(\'#\', fp.`id_feature_value`)), IF(fv.`custom` = 1, \' (perso)\', \'\'))
                    ORDER BY fvl.`value` SEPARATOR \' | \')
                FROM `' . _DB_PREFIX_ . 'feature_product` fp
                INNER JOIN `' . _DB_PREFIX_ . 'feature_value` fv ON fv.`id_feature_value` = fp.`id_feature_value`
                LEFT JOIN `' . _DB_PREFIX_ . 'feature_value_lang` fvl
                    ON fvl.`id_feature_value` = fp.`id_feature_value` AND fvl.`id_lang` = ' . $idLang . '
                WHERE fp.`id_product` = p.`id_product` AND fp.`id_feature` = ' . $idFeature . ')';
        }

        $rows = Db::getInstance()->executeS(
            'SELECT p.`id_product`, p.`reference`, pl.`name`, pl.`link_rewrite`, ps.`active`,
                    img.`id_image`, ' . $currentValuesSql . ' AS current_values
             ' . $from . '
             LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` img
                ON img.`id_product` = p.`id_product` AND img.`cover` = 1 AND img.`id_shop` = ' . $idShop . '
             WHERE ' . $where . '
             ORDER BY ' . $orderBy . '
             LIMIT ' . (int) (($page - 1) * $limit) . ', ' . (int) $limit
        );

        $imageType = $this->getSmallImageType();
        $editBase = $this->context->link->getAdminLink('AdminProducts');
        $products = [];
        foreach ($rows ?: [] as $row) {
            $thumb = '';
            if ($row['id_image'] && $imageType) {
                $thumb = $this->context->link->getImageLink(
                    $row['link_rewrite'] ?: 'product',
                    (int) $row['id_product'] . '-' . (int) $row['id_image'],
                    $imageType
                );
            }
            $products[] = [
                'id_product' => (int) $row['id_product'],
                'reference' => (string) $row['reference'],
                'name' => (string) $row['name'],
                'active' => (int) $row['active'],
                'thumb' => $thumb,
                'current_values' => (string) $row['current_values'],
                'edit_url' => $editBase . '&id_product=' . (int) $row['id_product'] . '&updateproduct',
            ];
        }

        $this->fqpfJsonOk([
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
            'products' => $products,
        ]);
    }

    /**
     * Tous les IDs correspondant aux filtres courants (pour "tout sélectionner").
     */
    public function ajaxProcessSearchIds()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT p.`id_product` ' . $this->buildProductFrom() . '
             WHERE ' . $this->buildProductWhere() . '
             ORDER BY p.`id_product` ASC
             LIMIT ' . (int) self::MAX_SELECT_ALL
        );

        $this->fqpfJsonOk(['ids' => array_map('intval', array_column($rows ?: [], 'id_product'))]);
    }

    /**
     * IDs des produits possédant la valeur (ou la caractéristique) => "retirer de tous".
     */
    public function ajaxProcessIdsWithValue()
    {
        $idFeature = (int) Tools::getValue('id_feature');
        $idFeatureValue = (int) Tools::getValue('id_feature_value');
        $scope = Tools::getValue('scope') === 'feature' ? 'feature' : 'value';

        if (!$this->featureExists($idFeature)) {
            $this->fqpfJsonError('Caractéristique introuvable.');
        }
        if ($scope === 'value' && !$this->valueBelongsToFeature($idFeatureValue, $idFeature)) {
            $this->fqpfJsonError('Valeur introuvable pour cette caractéristique.');
        }

        $sql = 'SELECT DISTINCT `id_product` FROM `' . _DB_PREFIX_ . 'feature_product`
                WHERE `id_feature` = ' . $idFeature;
        if ($scope === 'value') {
            $sql .= ' AND `id_feature_value` = ' . $idFeatureValue;
        }
        $rows = Db::getInstance()->executeS($sql . ' ORDER BY `id_product` ASC');

        $this->fqpfJsonOk(['ids' => array_map('intval', array_column($rows ?: [], 'id_product'))]);
    }

    /**
     * Résout une liste collée d'IDs ou de références en IDs produits.
     */
    public function ajaxProcessResolveIds()
    {
        $raw = (string) Tools::getValue('raw');
        $type = Tools::getValue('type') === 'reference' ? 'reference' : 'id';

        $tokens = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_unique(array_map('trim', $tokens ?: [])));
        if (!$tokens) {
            $this->fqpfJsonError('Aucune valeur saisie.');
        }
        if (count($tokens) > 20000) {
            $this->fqpfJsonError('Trop de valeurs (20 000 max par collage).');
        }

        $found = [];
        $matchedTokens = [];

        if ($type === 'id') {
            $ints = array_filter(array_map('intval', $tokens));
            if ($ints) {
                $rows = Db::getInstance()->executeS(
                    'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product`
                     WHERE `id_product` IN (' . implode(',', $ints) . ')'
                );
                foreach ($rows ?: [] as $r) {
                    $found[(int) $r['id_product']] = true;
                    $matchedTokens[(string) (int) $r['id_product']] = true;
                }
            }
        } else {
            $quoted = [];
            foreach ($tokens as $t) {
                $quoted[] = '\'' . pSQL($t) . '\'';
            }
            $in = implode(',', $quoted);
            $rows = Db::getInstance()->executeS(
                'SELECT `id_product`, `reference` FROM `' . _DB_PREFIX_ . 'product` WHERE `reference` IN (' . $in . ')
                 UNION
                 SELECT `id_product`, `reference` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `reference` IN (' . $in . ')'
            );
            foreach ($rows ?: [] as $r) {
                $found[(int) $r['id_product']] = true;
                $matchedTokens[Tools::strtolower((string) $r['reference'])] = true;
            }
        }

        $notFound = [];
        foreach ($tokens as $t) {
            $key = $type === 'id' ? (string) (int) $t : Tools::strtolower($t);
            if (!isset($matchedTokens[$key])) {
                $notFound[] = $t;
            }
        }

        $this->fqpfJsonOk([
            'ids' => array_map('intval', array_keys($found)),
            'not_found' => array_slice($notFound, 0, 200),
            'nb_not_found' => count($notFound),
        ]);
    }

    /**
     * Applique un lot : ajout ou retrait.
     */
    public function ajaxProcessApply()
    {
        $this->assertCanEdit();

        $op = (string) Tools::getValue('op');
        $idFeature = (int) Tools::getValue('id_feature');
        $idFeatureValue = (int) Tools::getValue('id_feature_value');
        $scope = Tools::getValue('scope') === 'feature' ? 'feature' : 'value';
        $mode = Tools::getValue('mode') === 'append' ? 'append' : 'replace';
        $runHooks = (bool) Tools::getValue('hooks');
        $ids = $this->parseIds(Tools::getValue('ids'));

        if (!in_array($op, ['add', 'remove'], true)) {
            $this->fqpfJsonError('Opération inconnue.');
        }
        if (!$ids) {
            $this->fqpfJsonError('Aucun produit dans ce lot.');
        }
        if (count($ids) > self::MAX_IDS_PER_CALL) {
            $this->fqpfJsonError('Lot trop gros.');
        }
        if (!$this->featureExists($idFeature)) {
            $this->fqpfJsonError('Caractéristique introuvable.');
        }
        if (($op === 'add' || $scope === 'value') && !$this->valueBelongsToFeature($idFeatureValue, $idFeature)) {
            $this->fqpfJsonError('Valeur introuvable pour cette caractéristique.');
        }

        $db = Db::getInstance();
        $table = '`' . _DB_PREFIX_ . 'feature_product`';
        $in = implode(',', $ids);
        $affected = 0;

        if ($op === 'add') {
            if ($mode === 'replace') {
                $db->execute(
                    'DELETE FROM ' . $table . '
                     WHERE `id_feature` = ' . $idFeature . '
                       AND `id_feature_value` != ' . $idFeatureValue . '
                       AND `id_product` IN (' . $in . ')'
                );
            }
            $db->execute(
                'INSERT IGNORE INTO ' . $table . ' (`id_feature`, `id_product`, `id_feature_value`)
                 SELECT ' . $idFeature . ', p.`id_product`, ' . $idFeatureValue . '
                 FROM `' . _DB_PREFIX_ . 'product` p
                 WHERE p.`id_product` IN (' . $in . ')'
            );
            $affected = (int) $db->Affected_Rows();
        } else {
            $sql = 'DELETE FROM ' . $table . '
                    WHERE `id_feature` = ' . $idFeature . '
                      AND `id_product` IN (' . $in . ')';
            if ($scope === 'value') {
                $sql .= ' AND `id_feature_value` = ' . $idFeatureValue;
            }
            $db->execute($sql);
            $affected = (int) $db->Affected_Rows();
        }

        $this->cleanOrphanCustomValues($idFeature);
        $this->touchProducts($ids);

        if ($runHooks) {
            foreach ($ids as $idProduct) {
                $product = new Product($idProduct);
                if (!Validate::isLoadedObject($product)) {
                    continue;
                }
                Hook::exec('actionProductSave', ['id_product' => $idProduct, 'product' => $product]);
                Hook::exec('actionProductUpdate', ['id_product' => $idProduct, 'product' => $product]);
            }
        }

        $this->fqpfJsonOk(['processed' => count($ids), 'affected' => $affected]);
    }

    /**
     * Appelé une fois en fin de traitement : invalidation des caches.
     */
    public function ajaxProcessFinalize()
    {
        $this->assertCanEdit();

        $faceted = Module::getInstanceByName('ps_facetedsearch');
        if ($faceted && Module::isEnabled('ps_facetedsearch')
            && method_exists($faceted, 'invalidateLayeredFilterBlockCache')) {
            $faceted->invalidateLayeredFilterBlockCache();
        }

        $this->fqpfJsonOk([]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    protected function buildProductFrom()
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        return 'FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                ON ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . $idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $idLang . ' AND pl.`id_shop` = ' . $idShop;
    }

    protected function buildProductWhere()
    {
        $where = ['1'];

        $q = trim((string) Tools::getValue('q'));
        if ($q !== '') {
            $like = pSQL(addcslashes($q, '%_'));
            $cond = 'pl.`name` LIKE \'%' . $like . '%\'
                OR p.`reference` LIKE \'%' . $like . '%\'
                OR p.`ean13` LIKE \'%' . $like . '%\'
                OR p.`supplier_reference` LIKE \'%' . $like . '%\'';
            if (ctype_digit($q)) {
                $cond .= ' OR p.`id_product` = ' . (int) $q;
            }
            $where[] = '(' . $cond . ')';
        }

        $idCategory = (int) Tools::getValue('id_category');
        if ($idCategory) {
            if (Tools::getValue('subcats')) {
                $cat = Db::getInstance()->getRow(
                    'SELECT `nleft`, `nright` FROM `' . _DB_PREFIX_ . 'category` WHERE `id_category` = ' . $idCategory
                );
                if ($cat) {
                    $where[] = 'EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` cp
                        INNER JOIN `' . _DB_PREFIX_ . 'category` c ON c.`id_category` = cp.`id_category`
                        WHERE cp.`id_product` = p.`id_product`
                          AND c.`nleft` >= ' . (int) $cat['nleft'] . ' AND c.`nright` <= ' . (int) $cat['nright'] . ')';
                }
            } else {
                $where[] = 'EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` cp
                    WHERE cp.`id_product` = p.`id_product` AND cp.`id_category` = ' . $idCategory . ')';
            }
        }

        $idManufacturer = Tools::getValue('id_manufacturer');
        if ($idManufacturer !== false && $idManufacturer !== '') {
            $where[] = 'p.`id_manufacturer` = ' . (int) $idManufacturer;
        }

        $active = Tools::getValue('active');
        if ($active === '0' || $active === '1') {
            $where[] = 'ps.`active` = ' . (int) $active;
        }

        $idFeature = (int) Tools::getValue('id_feature');
        $idFeatureValue = (int) Tools::getValue('id_feature_value');
        $featureFilter = (string) Tools::getValue('feature_filter');
        if ($idFeature && $featureFilter !== '') {
            $fpBase = 'SELECT 1 FROM `' . _DB_PREFIX_ . 'feature_product` fpf
                WHERE fpf.`id_product` = p.`id_product` AND fpf.`id_feature` = ' . $idFeature;
            switch ($featureFilter) {
                case 'has_value':
                    if ($idFeatureValue) {
                        $where[] = 'EXISTS (' . $fpBase . ' AND fpf.`id_feature_value` = ' . $idFeatureValue . ')';
                    }
                    break;
                case 'not_value':
                    if ($idFeatureValue) {
                        $where[] = 'NOT EXISTS (' . $fpBase . ' AND fpf.`id_feature_value` = ' . $idFeatureValue . ')';
                    }
                    break;
                case 'has_feature':
                    $where[] = 'EXISTS (' . $fpBase . ')';
                    break;
                case 'no_feature':
                    $where[] = 'NOT EXISTS (' . $fpBase . ')';
                    break;
            }
        }

        $onlyIds = $this->parseIds(Tools::getValue('only_ids'));
        if (Tools::getValue('only_selected')) {
            $where[] = $onlyIds ? 'p.`id_product` IN (' . implode(',', $onlyIds) . ')' : '0';
        }

        return implode(' AND ', $where);
    }

    /**
     * Supprime les valeurs personnalisées (custom=1) de la caractéristique qui ne sont plus liées à aucun produit.
     */
    protected function cleanOrphanCustomValues($idFeature)
    {
        $orphans = Db::getInstance()->executeS(
            'SELECT fv.`id_feature_value` FROM `' . _DB_PREFIX_ . 'feature_value` fv
             LEFT JOIN `' . _DB_PREFIX_ . 'feature_product` fp ON fp.`id_feature_value` = fv.`id_feature_value`
             WHERE fv.`id_feature` = ' . (int) $idFeature . ' AND fv.`custom` = 1 AND fp.`id_feature_value` IS NULL'
        );
        $ids = array_map('intval', array_column($orphans ?: [], 'id_feature_value'));
        if (!$ids) {
            return;
        }
        $in = implode(',', $ids);
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'feature_value_lang` WHERE `id_feature_value` IN (' . $in . ')');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'feature_value` WHERE `id_feature_value` IN (' . $in . ')');
    }

    /**
     * Met à jour date_upd (flux / synchros) et marque les produits à réindexer pour la recherche.
     */
    protected function touchProducts(array $ids)
    {
        $in = implode(',', array_map('intval', $ids));
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'product` SET `indexed` = 0, `date_upd` = NOW() WHERE `id_product` IN (' . $in . ')'
        );
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'product_shop` SET `indexed` = 0, `date_upd` = NOW() WHERE `id_product` IN (' . $in . ')'
        );
    }

    protected function parseIds($raw)
    {
        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }
        $ids = array_map('intval', explode(',', (string) $raw));

        return array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));
    }

    protected function featureExists($idFeature)
    {
        return $idFeature > 0 && (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'feature` WHERE `id_feature` = ' . (int) $idFeature
        );
    }

    protected function valueBelongsToFeature($idFeatureValue, $idFeature)
    {
        return $idFeatureValue > 0 && (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'feature_value`
             WHERE `id_feature_value` = ' . (int) $idFeatureValue . ' AND `id_feature` = ' . (int) $idFeature
        );
    }

    protected function getCategoriesFlat()
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        $rows = Db::getInstance()->executeS(
            'SELECT c.`id_category`, c.`level_depth`, cl.`name`
             FROM `' . _DB_PREFIX_ . 'category` c
             INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . $idShop . '
             LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                ON cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . $idLang . ' AND cl.`id_shop` = ' . $idShop . '
             WHERE c.`level_depth` > 0
             ORDER BY c.`nleft` ASC'
        );

        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[] = [
                'id_category' => (int) $r['id_category'],
                'label' => str_repeat('— ', max(0, (int) $r['level_depth'] - 1)) . $r['name'],
            ];
        }

        return $out;
    }

    protected function getSmallImageType()
    {
        foreach (['small', 'cart', 'home'] as $base) {
            $name = ImageType::getFormattedName($base);
            if ($name && ImageType::typeAlreadyExists($name)) {
                return $name;
            }
        }

        return '';
    }

    protected function assertCanEdit()
    {
        if (!$this->access('edit')) {
            $this->fqpfJsonError('Vous n\'avez pas les droits de modification.');
        }
    }

    protected function fqpfJsonOk(array $data)
    {
        $this->fqpfJsonOut(array_merge(['success' => true], $data));
    }

    protected function fqpfJsonError($message)
    {
        $this->fqpfJsonOut(['success' => false, 'error' => $message]);
    }

    protected function fqpfJsonOut(array $data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
