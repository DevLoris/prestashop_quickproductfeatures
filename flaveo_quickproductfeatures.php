<?php
/**
 * Flaveo - Quick Product Features
 *
 * Ajout / retrait en masse d'une valeur de caractéristique sur les produits.
 *
 * @author    Loris Pinna <https://lorispinna.com>
 * @copyright Loris Pinna
 * @license   Commercial
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Flaveo_Quickproductfeatures extends Module
{
    const ADMIN_CONTROLLER = 'AdminFlaveoQuickProductFeatures';

    public function __construct()
    {
        $this->name = 'flaveo_quickproductfeatures';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Loris Pinna';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.7.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Flaveo - Caractéristiques produits en masse');
        $this->description = $this->l('Ajoutez ou retirez rapidement une valeur de caractéristique sur une sélection de produits, ou sur tout le catalogue.');
        $this->confirmUninstall = $this->l('Désinstaller le module ? Aucune caractéristique produit ne sera modifiée.');
    }

    public function install()
    {
        return parent::install() && $this->installTab();
    }

    public function uninstall()
    {
        return $this->uninstallTab() && parent::uninstall();
    }

    /**
     * Bouton "Configurer" => redirection vers l'outil.
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(self::ADMIN_CONTROLLER));
    }

    protected function installTab()
    {
        if ((int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = self::ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->active = 1;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');
        $tab->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Caractéristiques en masse';
        }

        return (bool) $tab->add();
    }

    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        if (!$idTab) {
            return true;
        }
        $tab = new Tab($idTab);

        return (bool) $tab->delete();
    }
}
