# Flaveo – Quick Product Features

> Module PrestaShop pour ajouter ou retirer en masse une valeur de caractéristique sur vos produits.
> PrestaShop module to bulk add or remove a feature value on your products.

![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7.7%2B-df0067)
![PHP](https://img.shields.io/badge/PHP-7.1%2B-777bb4)

**[🇫🇷 Français](#-français) · [🇬🇧 English](#-english)**

---

## 🇫🇷 Français

### Présentation

Dans PrestaShop, une caractéristique (Couleur, Matière, Dimensions…) s'assigne produit par produit. Sur un gros catalogue, c'est long et on se trompe facilement.

**Flaveo – Quick Product Features** ajoute une page **Catalogue > Caractéristiques en masse** qui permet de :

- **choisir une caractéristique et l'une de ses valeurs** ;
- **sélectionner des produits en masse** (filtres, sélection de tous les résultats, collage d'IDs ou de références) ;
- **ajouter** la valeur à la sélection, en remplaçant la valeur existante ou en l'ajoutant en plus ;
- **retirer** la valeur, ou la caractéristique entière, de la sélection ou de **tous les produits** du catalogue.

### Fonctionnalités

#### 1. Caractéristique & valeur
- Liste des caractéristiques de la boutique.
- Liste des valeurs avec le **nombre de produits** qui ont chacune, et un champ pour filtrer les valeurs.
- Statistiques : nombre de produits qui ont la caractéristique, dont ceux avec une valeur personnalisée.
- Portée du retrait :
  - **uniquement cette valeur**,
  - **toute la caractéristique** (toutes valeurs, y compris personnalisées).

#### 2. Sélection des produits
- Filtres :
  - recherche par nom, référence, EAN, référence fournisseur ou ID ;
  - catégorie, avec ou sans sous-catégories ;
  - marque, y compris « sans marque » ;
  - statut actif ou inactif ;
  - filtre sur la caractéristique choisie : ont la valeur / n'ont pas la valeur / ont la caractéristique / n'ont pas la caractéristique.
- Tableau paginé (20 à 300 lignes, tri par ID, nom ou référence) avec miniature, statut, **valeur(s) actuelle(s)** de la caractéristique et lien vers la fiche produit.
- Sélection en masse :
  - cocher ou décocher la page, ou un clic sur la ligne ;
  - **sélectionner ou désélectionner tous les résultats filtrés** (jusqu'à 100 000) ;
  - **coller une liste d'IDs ou de références** (produit ou déclinaison), avec la liste des valeurs introuvables ;
  - afficher uniquement la sélection, ou la vider.
- La sélection est conservée quand on change de page ou de filtre, ce qui permet de cumuler plusieurs recherches.

#### 3. Actions
| Action | Description |
|---|---|
| **Ajouter (Remplacer)** | Assigne la valeur et retire les autres valeurs de cette caractéristique sur les produits sélectionnés. |
| **Ajouter (En plus)** | Assigne la valeur en conservant les valeurs existantes (multi-valeurs). |
| **Retirer de la sélection** | Retire la valeur, ou la caractéristique entière, des produits sélectionnés. |
| **Retirer de TOUS les produits** | Retire la valeur, ou la caractéristique, de tout le catalogue. Une confirmation indique le nombre de produits concernés. |

- Chaque action demande une confirmation avant de s'exécuter.
- Traitement **par lots AJAX** (500 produits par requête) avec barre de progression, pour éviter les timeouts.
- Journal des opérations avec le nombre d'associations créées ou supprimées.

### Ce que fait le module en base

- Il agit uniquement sur la table `ps_feature_product`. Les produits ne sont pas réenregistrés un par un, d'où la rapidité.
- Les **valeurs personnalisées** devenues orphelines (liées à aucun produit) sont supprimées.
- `date_upd` est mis à jour sur les produits modifiés, pour que les modules de flux (Google Merchant Center, Channable…) détectent le changement.
- Les produits sont marqués **à réindexer** (`indexed = 0`) pour la recherche.
- Le cache des filtres de **ps_facetedsearch** est vidé en fin de traitement.
- **Option** : déclencher `actionProductSave` / `actionProductUpdate` pour chaque produit, si un module tiers doit être notifié. C'est plus lent (lots de 50).

> ℹ️ La recherche du front ne prend en compte les changements qu'au prochain réindexage (**Préférences > Rechercher**, ou la tâche cron d'indexation).

### Compatibilité

- PrestaShop **1.7.7.0 et supérieur** (développé pour la 1.7.7.3, fonctionne aussi en 1.7.8)
- PHP **7.1+**
- Multiboutique : la liste et les filtres utilisent le contexte de boutique courant. Les caractéristiques sont communes à toutes les boutiques, comme dans PrestaShop.

### Installation

1. Téléchargez le zip du module, ou clonez ce dépôt dans `modules/flaveo_quickproductfeatures`.
2. Dans le back-office : **Modules > Module Manager > Installer un module**, puis envoyez le zip.
3. Ouvrez **Catalogue > Caractéristiques en masse**. Le bouton « Configurer » du module y mène aussi.

La désinstallation supprime uniquement l'onglet d'administration. **Aucune donnée produit n'est modifiée.**

### Utilisation rapide

1. Choisissez la **caractéristique**, puis la **valeur**.
2. Filtrez les produits, puis cochez-les, ou cliquez sur **Sélectionner tous les résultats**, ou collez une liste d'IDs ou de références.
3. Cliquez sur **Ajouter aux produits sélectionnés** ou **Retirer des produits sélectionnés**.
4. Pour nettoyer une valeur sur tout le catalogue : choisissez la portée, puis **Retirer de TOUS les produits**.

> 💡 Astuce : utilisez le filtre « N'ont pas la caractéristique » puis « Sélectionner tous les résultats » pour compléter rapidement les fiches incomplètes.

### Droits

- La consultation nécessite le droit **lecture** sur l'onglet.
- Les modifications nécessitent le droit **modification**. Sans lui, les boutons d'action sont désactivés et refusés côté serveur.

### ⚠️ Avertissement

Les retraits sont **irréversibles**. Faites une sauvegarde de la base, au moins de la table `ps_feature_product`, avant une opération sur tout le catalogue.

---

## 🇬🇧 English

### Overview

In PrestaShop, features (Color, Material, Dimensions…) are assigned product by product. On a large catalog this is slow and error-prone.

**Flaveo – Quick Product Features** adds a **Catalog > Caractéristiques en masse** page (bulk features) that lets you:

- **pick a feature and one of its values**;
- **bulk-select products** (filters, select all results, paste IDs or references);
- **add** the value to the selection, either replacing the existing value or adding it alongside;
- **remove** the value, or the whole feature, from the selection or from **every product** in the catalog.

> The back-office UI is in French.

### Features

#### 1. Feature & value
- List of the shop's features.
- List of values with the **number of products** using each, plus a text filter for the values.
- Stats: number of products with the feature, including how many have a custom value.
- Removal scope:
  - **this value only**,
  - **the whole feature** (all values, custom ones included).

#### 2. Product selection
- Filters:
  - search by name, reference, EAN, supplier reference or ID;
  - category, with or without subcategories;
  - manufacturer, including "no manufacturer";
  - active or inactive status;
  - filter on the selected feature: has the value / doesn't have the value / has the feature / doesn't have the feature.
- Paginated grid (20 to 300 rows, sort by ID, name or reference) with thumbnail, status, **current value(s)** of the feature and a link to the product page.
- Bulk selection:
  - check or uncheck the page, or click a row;
  - **select or unselect all filtered results** (up to 100,000);
  - **paste a list of IDs or references** (product or combination), with a list of the values that weren't found;
  - show only the selection, or clear it.
- The selection is kept across pages and filter changes, so you can combine several searches.

#### 3. Actions
| Action | Description |
|---|---|
| **Add (Replace)** | Assigns the value and removes the feature's other values from the selected products. |
| **Add (Append)** | Assigns the value and keeps the existing values (multi-value). |
| **Remove from selection** | Removes the value, or the whole feature, from the selected products. |
| **Remove from ALL products** | Removes the value, or the feature, from the whole catalog. A confirmation shows how many products are affected. |

- Every action asks for confirmation before running.
- **AJAX batch processing** (500 products per request) with a progress bar, to avoid timeouts.
- Operation log showing how many associations were created or removed.

### What happens in the database

- The module only works on the `ps_feature_product` table. Products are not re-saved one by one, which keeps it fast.
- Orphan **custom values** (linked to no product) are deleted.
- `date_upd` is updated on the modified products, so feed modules (Google Merchant Center, Channable…) pick up the change.
- Products are flagged **for search re-indexing** (`indexed = 0`).
- The **ps_facetedsearch** filter cache is cleared when processing ends.
- **Option**: trigger `actionProductSave` / `actionProductUpdate` for each product, if a third-party module needs to be notified. This is slower (batches of 50).

> ℹ️ Front-office search only reflects the changes after the next re-index (**Shop Parameters > Search**, or the indexing cron job).

### Compatibility

- PrestaShop **1.7.7.0 and above** (built for 1.7.7.3, also works on 1.7.8)
- PHP **7.1+**
- Multistore: the grid and filters use the current shop context. Features are shared by all shops, as in PrestaShop.

### Installation

1. Download the module zip, or clone this repository into `modules/flaveo_quickproductfeatures`.
2. In the back office: **Modules > Module Manager > Upload a module**, then upload the zip.
3. Open **Catalog > Caractéristiques en masse**. The module's "Configure" button also leads there.

Uninstalling only removes the admin tab. **No product data is modified.**

### Quick start

1. Pick the **feature**, then the **value**.
2. Filter the products, then check them, or click **Select all results**, or paste a list of IDs or references.
3. Click **Add to selected products** or **Remove from selected products**.
4. To clean up a value across the whole catalog: choose the scope, then **Remove from ALL products**.

> 💡 Tip: use the "doesn't have the feature" filter, then "Select all results", to quickly fill in incomplete products.

### Permissions

- Viewing requires the **view** permission on the tab.
- Changes require the **edit** permission. Without it, the action buttons are disabled and rejected server-side.

### ⚠️ Warning

Removals are **irreversible**. Back up your database, at least the `ps_feature_product` table, before running an operation on the whole catalog.

---

## Structure

```
flaveo_quickproductfeatures/
├── flaveo_quickproductfeatures.php        # Classe du module / module class (install, tab)
├── controllers/admin/
│   └── AdminFlaveoQuickProductFeaturesController.php  # Page + endpoints AJAX
├── views/
│   ├── templates/admin/main.tpl           # Interface
│   ├── js/admin.js                        # Sélection, lots AJAX / selection, AJAX batches
│   └── css/admin.css
└── logo.png
```

## Auteur / Author

**Loris Pinna** — [lorispinna.com](https://lorispinna.com)
