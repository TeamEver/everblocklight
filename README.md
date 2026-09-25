# Ever Block Light

Version allégée du module PrestaShop **Ever Block** (Team Ever, v9.0.6, licence AFL 3.0) : on conserve le socle — blocs HTML sur les hooks, shortcodes — et on retire tout le surplus (FAQ, pages, contenus produit, page builders, outils d'administration).

- Nom technique : `everblocklight`
- Compatibilité déclarée : PrestaShop 8.0+ (`ps_versions_compliancy`), PHP 8.1+ (`composer.json`)
- Auteur : Griiv
- Licence : AFL 3.0 (voir `LICENSE.md`)
- Œuvre dérivée d'[Ever Block](https://www.team-ever.com/) 9.0.6 (© 2019-2025 Team Ever), modifiée par Griiv en 2026. Les mentions de copyright et de licence d'origine sont conservées dans chaque fichier, conformément à l'article 6 de l'AFL 3.0.

## Fonctionnalités conservées

### Blocs HTML
- Création illimitée de blocs HTML multilingues, positionnés sur n'importe quel hook `display*`.
- Ciblage : page d'accueil uniquement, catégories, produits d'une catégorie, marques, fournisseurs, catégories CMS, groupes clients, appareil (mobile / tablette / desktop), dates de début et de fin.
- Mise en forme : conteneur, classes CSS / Bootstrap, attributs `data-*`, couleur de fond, code personnalisé.
- Affichage en **modale** (délai d'apparition, fréquence d'affichage via cookie).
- SEO / performance : obfuscation des liens, lazyload des images, cache de rendu par bloc.
- Actions en masse (activer, désactiver, dupliquer, supprimer) et aperçu front du bloc depuis l'admin.

### Hooks
- Liste et gestion des hooks display depuis l'admin (création de hooks personnalisés).

### Shortcodes
- Shortcodes personnalisés (onglet « Shortcodes ») + documentation intégrée dans l'admin.
- Shortcodes intégrés :
  - **Contenu** : `[alert]`, `[video]`, `[everblocklight id]` (inclusion d’un autre bloc), `[cms]`, `[evercms]`, `[widget]`, `{hook h='...'}`, `[shop_logo]`, `[llorem]`, `[everimg]`
  - **Produits** : `[product]`, `[product_image]`, `[category]`, `[manufacturer]`, `[brands]`, `[subcategories]`, `[productfeature]`, `[productfeaturevalue]`, `[last-products]`, `[recently_viewed]`, `[promo-products]`, `[best-sales]`, `[categorybestsales]`, `[brandbestsales]`, `[featurebestsales]`, `[featurevaluebestsales]`, `[products_by_tag]`, `[low_stock]`, `[random_product]`, `[linkedproducts]`, `[accessories]`, `[crosselling]`
  - **Panier / client** : `[evercart]`, `[cart_total]`, `[cart_quantity]`, `[everaddtocart]`, `[newsletter_form]`, `[entity_firstname]`, `[entity_lastname]`, `[entity_gender]`…
  - **Formulaires** : formulaire de contact (`[evercontactform_open]` … `[evercontact type="…"]` … `[evercontactform_close]`), `[nativecontact]`
  - **Intégrations externes** : `[everinstagram]`, `[googlereviews]`, `[wordpress-posts]`, `[storelocator]`, `[evermap]`, `[everstore]`, `[qcdacf]`, `[displayQcdSvg]`
- Les shortcodes sont rendus dans toute la page front (`actionOutputHTMLBefore`) et dans les e-mails (`actionEmailAddAfterContent`).

La liste détaillée des paramètres est disponible dans l'admin : *Ever Block Light > Shortcode documentation*.

### Déclencher une modale depuis un bouton
Dans le contenu d'un bloc : `<a href="#" data-everclickmodal="ID_DU_BLOC">…</a>` ou `<a href="#" data-evercms="ID_CMS">…</a>`.

### Configuration
- Réglages : chargement du CSS front, script d'obfuscation, TinyMCE, nombre de paragraphes/phrases pour `[llorem]`.
- Meta (Instagram), WordPress (API REST), Google (Places / avis, Maps / store locator, icône de marqueur), horaires exceptionnels par magasin.
- Outils : CSS / JS personnalisés, liens CSS / JS externes, scripts d'en-tête, vidage du cache du module.
- Tâches cron sécurisées (URL avec jeton) : `refreshtokens`, `fetchinstagramimages`, `fetchwordpressposts`.

### Console
```bash
php bin/console everblocklight:tools:execute --list
```
Actions : `refreshtokens`, `fetchinstagramimages`, `fetchwordpressposts`, `checkdatabase`, `clearcache`.

## Fonctionnalités retirées (par rapport à Ever Block 9.0.6)
- FAQ (admin, pages front, liaison produits, shortcodes `[everfaq]` / `[everfaq_product]`)
- Pages / guides (admin, contrôleurs front, routes)
- Contenus produit : onglets, flags (dont « épuisé » et caractéristiques), modales fichiers produit, onglet global, import xlsx
- Intégrations Pretty Blocks et QCD Page Builder, jeux (roue de la fortune, etc.)
- Outils d'administration : mise à jour automatique depuis GitHub, traduction Google, sauvegarde / restauration, génération de produits factices, purge des logs, suppression des langues, migration d'URL, recherche / remplacement en base, import / export xlsx
- Étape supplémentaire du tunnel de commande (formulaire `[everorderform]`, affichage sur confirmation de commande, factures, bons de livraison et admin commande)
- Mot de passe de maintenance, connexion en tant que client, « hack » Google Shopping
- Nettoyage automatique des fichiers PHP hors liste blanche (`allowed_files.php`)

## Identité technique
| Élément | Valeur |
| --- | --- |
| Dossier / classe principale | `everblocklight` / `Everblocklight` |
| Namespace PHP | `Everblocklight\Tools\` (PSR-4 → `src/`) |
| Tables | `PREFIX_everblocklight`, `PREFIX_everblocklight_lang`, `PREFIX_everblocklight_shortcode`, `PREFIX_everblocklight_shortcode_lang` (utf8mb4) |
| Clés de configuration | préfixe `EVERBLOCKLIGHT_` (toutes supprimées à la désinstallation) |
| Onglets admin | `AdminEverBlockLight*` |
| Routes admin | `admin_everblocklight_*` |
| Domaine de traduction | `Modules.Everblocklight.*` |

## Points d'attention
- **Pas de reprise des données d'Ever Block** : les blocs et shortcodes existants de l'original ne sont pas migrés automatiquement.
- **Coexistence avec Ever Block** : les deux modules peuvent être installés en même temps (tables, configuration, namespace et routes distincts). En front, certains attributs génériques (`data-everclickmodal`, `data-evercms`, obfuscation `data-ob`) sont écoutés par les deux scripts : éviter d'activer les deux modules sur la même boutique en production.
- Le fichier `views/js/everblock-loader.js` référencé par Ever Block 9.0.6 était absent de la source fournie ; Ever Block Light charge directement `views/js/everblocklight.js`.

## Développement et tests

L'outillage de test est isolé dans `tests/` (son propre `composer.json`, `tests/vendor/` ignoré par git) : le `vendor/` du module livré en production ne contient jamais PHPUnit ni PHPStan.

```bash
cd tests
composer install
vendor/bin/phpunit                      # tests unitaires + tests d'intégrité
_PS_ROOT_DIR_=/chemin/vers/prestashop vendor/bin/phpstan analyse -c phpstan.neon
```

- `tests/Unit` : moteur de shortcodes, outils SEO (obfuscation, lazyload), horaires / jours fériés, entités, cache (les classes du cœur PrestaShop sont remplacées par des stubs, `tests/stubs`).
- `tests/Integrity` : cohérence du module (templates référencés présents, routes ↔ actions, préfixe `EVERBLOCKLIGHT_` des clés de configuration, tables install/uninstall, absence d'identifiants Ever Block ou de fonctionnalités retirées).
- PHPStan (niveau 3) s'exécute contre les sources réelles de PrestaShop (`_PS_ROOT_DIR_` : clone du dépôt officiel + `composer install --no-dev`).

La CI GitHub Actions (`.github/workflows/ci.yml`) joue à chaque push / pull request et chaque lundi :
lint PHP 8.1 → 8.4, syntaxe JS, PHPUnit PHP 8.1 → 8.4, PHPStan contre PrestaShop 8.2.8 et 9.1.5 (+ branche 9.2.x en informatif), test d'installation réel, puis produit l'archive `everblocklight.zip` sans l'outillage de développement.

Le test d'installation (`tests/install/run.sh <tag image>`, job `install`) démarre les images Docker officielles `prestashop/prestashop` (8.2 / PHP 8.1 et 9.1 / PHP 8.4) avec MySQL 8.0, installe le module en ligne de commande, vérifie tables / hooks / onglets / routes / conteneur Symfony, enregistre un bloc et un shortcode via les repositories du module, contrôle leur rendu sur la page d'accueil (sans erreur PHP), puis désinstalle et vérifie le nettoyage. Il se lance aussi en local : `tests/install/run.sh 9.1-8.4`.
