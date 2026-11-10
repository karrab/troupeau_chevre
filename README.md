# Troupeau SI — Gestion de troupeau caprin & ovin

Système d'information complet (PHP 8 / MySQL InnoDB / Bootstrap 5) pour gérer un troupeau de
**chèvres, boucs, chevreaux / chevrettes, brebis, béliers, moutons, agneaux / agnelles**.

## Fonctionnalités

| Module | Contenu |
|---|---|
| **Fiche animal** | Identification, espèce, race, catégorie (automatique selon âge / sexe / castration), filiation mère/père, origine, statut (actif, vendu, mort, réformé, perdu), courbe de poids, descendants, indices, historique chronologique. Boutons **Modifier, Supprimer (corbeille + restauration), Imprimer, PDF**. |
| **Événements** | Pesées, reproductions (date de mise bas prévue calculée), mises bas, contrôles laitiers, soins / traitements. Liste globale filtrable, saisie depuis la fiche, modification, suppression, export PDF. |
| **Recherche avancée** | N° / nom (préfixe indexé), plein texte (FULLTEXT sur nom + observations), espèce, race, catégorie, sexe, statut, origine, dates de naissance, âge min / max, mère, père, tri, corbeille. |
| **Indices** | Calcul, **interprétation** (bon / moyen / faible + texte) et **enregistrement** des indices **par année** (troupeau, espèce, race) et **par animal** (carrière ou année). Synthèse pluriannuelle avec tendances. |
| **Paramétrage des formules** | Chaque indice a une formule modifiable (Paramètres → Formules) : variables, fonctions, seuils, sens, textes d'interprétation, test en direct sur des données réelles, historique des modifications de formules. |
| **Utilisateurs & permissions** | Comptes, rôles (Administrateur, Gestionnaire, Technicien, Consultant + rôles personnalisés), matrice rôles × permissions, mot de passe fort, changement obligatoire, limitation des tentatives de connexion. |
| **Traces** | Journal d'audit de toutes les actions (connexions, créations, modifications avec valeurs avant/après, suppressions, exports, calculs), filtrable et exportable en PDF. |
| **Exports** | Toutes les listes (animaux, événements, indices, journal, utilisateurs) s'exportent en PDF en respectant les filtres. |

## Performances

- **OPcache** : cache du bytecode + préchargement (`config/preload.php`), réglages dans `docs/php-performance.ini`.
- **APCu** (`app/Core/Cache.php`) : permissions par rôle, paramètres, référentiels, formules compilées (AST),
  compteurs de liste, tableau de bord, limitation des tentatives de connexion. Invalidation par groupes versionnés.
  Repli automatique sur un cache de requête si APCu est absent.
- **MySQL InnoDB bien indexé** : index composites `(filtre…, colonne_de_tri, id)` alignés sur les requêtes, index
  `(animal_id, date)` sur chaque table d'événements, FULLTEXT, clés étrangères. Réglages dans `docs/mysql-performance.cnf`.
- **Pagination keyset** (`app/Core/KeysetPaginator.php`) : `WHERE (tri, id) < (:v, :id) ORDER BY tri, id LIMIT n+1`,
  sans `OFFSET` — coût constant quelle que soit la page. Les exports PDF parcourent aussi les données par lots keyset.
- Calcul des indices par animal **par lots de 500** avec agrégats SQL `GROUP BY` et upsert multi-lignes
  (`INSERT … ON DUPLICATE KEY UPDATE`).

## Installation

Prérequis : PHP ≥ 8.1 (pdo_mysql, mbstring ; opcache et apcu recommandés), MySQL 8 ou MariaDB ≥ 10.5, Composer.

```bash
composer install --no-dev -o
```

```bash
cp config/config.local.example.php config/config.local.php
```

Renseigner les accès MySQL dans `config/config.local.php`, puis :

```bash
php database/install.php
```

Pour ajouter un troupeau de démonstration (~800 animaux sur 4 ans) :

```bash
php database/install.php --demo
```

La racine web doit pointer sur `public/` (sinon le `.htaccess` racine redirige vers `public/`).
Pour un test local :

```bash
php -S localhost:8000 -t public
```

Connexion initiale : **admin / Admin@123** — un nouveau mot de passe est exigé à la première connexion.

> Sans Dompdf (`composer install` non exécuté), les boutons PDF ouvrent une page optimisée pour l'impression
> et la boîte « Imprimer / Enregistrer en PDF » du navigateur.

## Formules des indices

Syntaxe : `+ - * / % ^ ( )`, comparaisons `< <= > >= == !=`, `&& ||`, fonctions
`div, pct, min, max, moy, round, floor, ceil, abs, sqrt, log, si/if, coalesce`.
Une variable manquante ou une division par zéro donne « non calculable » (jamais d'erreur). Aucun `eval()` :
les formules sont analysées par un parseur dédié (`app/Services/FormulaEngine.php`).

Exemples livrés :

| Indice | Formule |
|---|---|
| Fertilité | `div(nb_femelles_mises_bas, nb_femelles_saillies) * 100` |
| Prolificité | `div(nb_nes, nb_mises_bas)` |
| Productivité numérique | `div(nb_sevres, nb_femelles_adultes) * 100` |
| GMQ naissance-sevrage (animal) | `(poids_sevrage - poids_naissance) / age_sevrage_jours * 1000` |
| Intervalle entre mises bas | `intervalle_moyen_mb` |

La liste complète des variables est affichée dans l'éditeur de formule. Les paramètres zootechniques
(âge adulte, âge au sevrage, durée de gestation) se règlent dans **Paramètres**.

## Arborescence

```
app/Core          Database (PDO), Cache (APCu), Auth (RBAC), Audit, KeysetPaginator, Csrf, View, Settings, Ref
app/Services      FormulaEngine, IndicatorService, EvenementTypes, Historique, PdfExporter
app/Controllers   Animaux, Événements, Indices, Utilisateurs, Rôles, Journal, Paramètres, Auth, Tableau de bord
views/            Gabarits Bootstrap 5 + gabarits PDF
database/         schema.sql, seed.sql, install.php, demo.php
public/           index.php (contrôleur frontal), assets
docs/             réglages PHP (OPcache/APCu) et MySQL
```

## Sécurité

Requêtes préparées partout, jetons CSRF sur tous les POST, échappement HTML systématique,
`password_hash`, sessions durcies (cookie HttpOnly / SameSite, régénération d'ID, expiration),
limitation des tentatives de connexion (APCu), contrôle de permission sur chaque route,
suppression logique des animaux (historique conservé).
