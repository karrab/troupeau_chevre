-- =====================================================================
--  Système d'information - Gestion de troupeau caprin / ovin
--  MySQL 8 / MariaDB 10.5+  -  Moteur InnoDB, utf8mb4
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Sécurité : utilisateurs, rôles, permissions
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
  id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(50)  NOT NULL,
  libelle       VARCHAR(100) NOT NULL,
  description   VARCHAR(255) NULL,
  systeme       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = rôle non supprimable',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS permissions;
CREATE TABLE permissions (
  id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code      VARCHAR(60)  NOT NULL,
  module    VARCHAR(40)  NOT NULL,
  libelle   VARCHAR(150) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_code (code),
  KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS role_permissions;
CREATE TABLE role_permissions (
  role_id       SMALLINT UNSIGNED NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_rp_permission (permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS utilisateurs;
CREATE TABLE utilisateurs (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id            SMALLINT UNSIGNED NOT NULL,
  login              VARCHAR(60)  NOT NULL,
  nom_complet        VARCHAR(150) NOT NULL,
  email              VARCHAR(190) NULL,
  mot_de_passe       VARCHAR(255) NOT NULL,
  actif              TINYINT(1)   NOT NULL DEFAULT 1,
  doit_changer_mdp   TINYINT(1)   NOT NULL DEFAULT 0,
  derniere_connexion DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_utilisateurs_login (login),
  UNIQUE KEY uq_utilisateurs_email (email),
  KEY idx_utilisateurs_role (role_id),
  KEY idx_utilisateurs_actif_nom (actif, nom_complet, id),
  CONSTRAINT fk_utilisateurs_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Traçabilité : journal d'audit (toutes les actions)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS journal_audit;
CREATE TABLE journal_audit (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NULL,
  login         VARCHAR(60)  NULL,
  action        VARCHAR(40)  NOT NULL COMMENT 'creation, modification, suppression, connexion, export...',
  entite        VARCHAR(50)  NOT NULL,
  entite_id     BIGINT UNSIGNED NULL,
  description   VARCHAR(255) NULL,
  anciennes_valeurs JSON NULL,
  nouvelles_valeurs JSON NULL,
  ip            VARCHAR(45)  NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at, id),
  KEY idx_audit_user (utilisateur_id, id),
  KEY idx_audit_entite (entite, entite_id, id),
  KEY idx_audit_action (action, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Paramètres
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS parametres;
CREATE TABLE parametres (
  cle        VARCHAR(60)  NOT NULL,
  valeur     VARCHAR(500) NULL,
  type       ENUM('texte','entier','decimal','booleen') NOT NULL DEFAULT 'texte',
  groupe     VARCHAR(40)  NOT NULL DEFAULT 'general',
  libelle    VARCHAR(150) NOT NULL,
  aide       VARCHAR(255) NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (cle),
  KEY idx_param_groupe (groupe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Référentiels : espèces, races, catégories
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS especes;
CREATE TABLE especes (
  id       TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code     VARCHAR(20) NOT NULL,
  libelle  VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_especes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS races;
CREATE TABLE races (
  id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  espece_id TINYINT UNSIGNED NOT NULL,
  libelle   VARCHAR(80) NOT NULL,
  actif     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_races (espece_id, libelle),
  CONSTRAINT fk_races_espece FOREIGN KEY (espece_id) REFERENCES especes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
  id        TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  espece_id TINYINT UNSIGNED NOT NULL,
  code      VARCHAR(20) NOT NULL,
  libelle   VARCHAR(60) NOT NULL,
  sexe      ENUM('M','F') NOT NULL,
  adulte    TINYINT(1) NOT NULL DEFAULT 1,
  castre    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_code (code),
  KEY idx_categories_espece (espece_id, sexe, adulte),
  CONSTRAINT fk_categories_espece FOREIGN KEY (espece_id) REFERENCES especes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Animaux (fiche animal)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS animaux;
CREATE TABLE animaux (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero_identification VARCHAR(40)  NOT NULL COMMENT 'Boucle / puce',
  nom                   VARCHAR(80)  NOT NULL DEFAULT '',
  espece_id             TINYINT UNSIGNED NOT NULL,
  race_id               SMALLINT UNSIGNED NULL,
  categorie_id          TINYINT UNSIGNED NOT NULL,
  sexe                  ENUM('M','F') NOT NULL,
  castre                TINYINT(1) NOT NULL DEFAULT 0,
  date_naissance        DATE NOT NULL,
  date_naissance_estimee TINYINT(1) NOT NULL DEFAULT 0,
  poids_naissance       DECIMAL(6,2) NULL,
  mere_id               INT UNSIGNED NULL,
  pere_id               INT UNSIGNED NULL,
  origine               ENUM('naissance','achat','don','autre') NOT NULL DEFAULT 'naissance',
  date_entree           DATE NOT NULL,
  prix_achat            DECIMAL(10,2) NULL,
  couleur               VARCHAR(60) NULL,
  statut                ENUM('actif','vendu','mort','reforme','perdu') NOT NULL DEFAULT 'actif',
  date_sortie           DATE NULL,
  motif_sortie          VARCHAR(255) NULL,
  prix_vente            DECIMAL(10,2) NULL,
  observations          TEXT NULL,
  supprime              TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Suppression logique',
  cree_par              INT UNSIGNED NULL,
  modifie_par           INT UNSIGNED NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_animaux_numero (numero_identification),
  -- Index pensés pour la pagination keyset (colonne de tri + id) et les filtres
  KEY idx_animaux_liste      (supprime, statut, id),
  KEY idx_animaux_espece     (supprime, espece_id, statut, id),
  KEY idx_animaux_categorie  (supprime, categorie_id, id),
  KEY idx_animaux_naissance  (supprime, date_naissance, id),
  KEY idx_animaux_nom        (supprime, nom, id),
  KEY idx_animaux_numero_tri (supprime, numero_identification, id),
  KEY idx_animaux_sortie     (statut, date_sortie),
  KEY idx_animaux_entree     (date_entree),
  KEY idx_animaux_race       (race_id),
  KEY idx_animaux_mere       (mere_id),
  KEY idx_animaux_pere       (pere_id),
  FULLTEXT KEY ft_animaux    (nom, observations),
  CONSTRAINT fk_animaux_espece    FOREIGN KEY (espece_id)    REFERENCES especes(id),
  CONSTRAINT fk_animaux_race      FOREIGN KEY (race_id)      REFERENCES races(id) ON DELETE SET NULL,
  CONSTRAINT fk_animaux_categorie FOREIGN KEY (categorie_id) REFERENCES categories(id),
  CONSTRAINT fk_animaux_mere      FOREIGN KEY (mere_id)      REFERENCES animaux(id) ON DELETE SET NULL,
  CONSTRAINT fk_animaux_pere      FOREIGN KEY (pere_id)      REFERENCES animaux(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique (chronologie) de chaque animal
DROP TABLE IF EXISTS animal_historique;
CREATE TABLE animal_historique (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  animal_id      INT UNSIGNED NOT NULL,
  date_evenement DATE NOT NULL,
  type           VARCHAR(30)  NOT NULL,
  titre          VARCHAR(200) NOT NULL,
  details        JSON NULL,
  utilisateur_id INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_hist_animal (animal_id, date_evenement, id),
  KEY idx_hist_type (type, id),
  CONSTRAINT fk_hist_animal FOREIGN KEY (animal_id) REFERENCES animaux(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Événements zootechniques
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS pesees;
CREATE TABLE pesees (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  animal_id   INT UNSIGNED NOT NULL,
  date_pesee  DATE NOT NULL,
  poids_kg    DECIMAL(6,2) NOT NULL,
  type_pesee  ENUM('naissance','controle','sevrage','vente','autre') NOT NULL DEFAULT 'controle',
  note        VARCHAR(255) NULL,
  cree_par    INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pesees_animal (animal_id, date_pesee, id),
  KEY idx_pesees_date (date_pesee, id),
  KEY idx_pesees_type_date (type_pesee, date_pesee),
  CONSTRAINT fk_pesees_animal FOREIGN KEY (animal_id) REFERENCES animaux(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS reproductions;
CREATE TABLE reproductions (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  animal_id            INT UNSIGNED NOT NULL COMMENT 'Femelle',
  male_id              INT UNSIGNED NULL,
  date_saillie         DATE NOT NULL,
  methode              ENUM('naturelle','insemination','lutte_libre') NOT NULL DEFAULT 'naturelle',
  date_prevue_mise_bas DATE NULL,
  resultat             ENUM('en_attente','gestante','vide','avortement','mise_bas') NOT NULL DEFAULT 'en_attente',
  note                 VARCHAR(255) NULL,
  cree_par             INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_repro_animal (animal_id, date_saillie, id),
  KEY idx_repro_male (male_id, date_saillie),
  KEY idx_repro_date (date_saillie, id),
  KEY idx_repro_prevue (resultat, date_prevue_mise_bas),
  CONSTRAINT fk_repro_animal FOREIGN KEY (animal_id) REFERENCES animaux(id) ON DELETE CASCADE,
  CONSTRAINT fk_repro_male   FOREIGN KEY (male_id)   REFERENCES animaux(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS mises_bas;
CREATE TABLE mises_bas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  animal_id     INT UNSIGNED NOT NULL COMMENT 'Mère',
  date_mise_bas DATE NOT NULL,
  issue         ENUM('normale','difficile','cesarienne','avortement') NOT NULL DEFAULT 'normale',
  nb_nes        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  nb_vivants    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  nb_males      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  nb_femelles   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  note          VARCHAR(255) NULL,
  cree_par      INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mb_animal (animal_id, date_mise_bas, id),
  KEY idx_mb_date (date_mise_bas, id),
  CONSTRAINT fk_mb_animal FOREIGN KEY (animal_id) REFERENCES animaux(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS productions_lait;
CREATE TABLE productions_lait (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  animal_id        INT UNSIGNED NOT NULL,
  date_controle    DATE NOT NULL,
  quantite_litres  DECIMAL(6,2) NOT NULL,
  taux_mg          DECIMAL(5,2) NULL COMMENT 'Matière grasse g/L',
  taux_mp          DECIMAL(5,2) NULL COMMENT 'Matière protéique g/L',
  note             VARCHAR(255) NULL,
  cree_par         INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lait_animal (animal_id, date_controle, id),
  KEY idx_lait_date (date_controle, id),
  CONSTRAINT fk_lait_animal FOREIGN KEY (animal_id) REFERENCES animaux(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS soins;
CREATE TABLE soins (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  animal_id           INT UNSIGNED NOT NULL,
  date_soin           DATE NOT NULL,
  type_soin           ENUM('vaccination','vermifugation','traitement','parage','chirurgie','autre') NOT NULL,
  produit             VARCHAR(120) NULL,
  dose                VARCHAR(60) NULL,
  veterinaire         VARCHAR(120) NULL,
  cout                DECIMAL(10,2) NULL,
  delai_attente_jours SMALLINT UNSIGNED NULL,
  note                VARCHAR(255) NULL,
  cree_par            INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_soins_animal (animal_id, date_soin, id),
  KEY idx_soins_date (date_soin, id),
  KEY idx_soins_type (type_soin, date_soin),
  CONSTRAINT fk_soins_animal FOREIGN KEY (animal_id) REFERENCES animaux(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Indices / indicateurs (formules paramétrables)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS indicateurs;
CREATE TABLE indicateurs (
  id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(40)  NOT NULL,
  nom           VARCHAR(120) NOT NULL,
  description   VARCHAR(500) NULL,
  portee        ENUM('animal','annee') NOT NULL COMMENT 'animal = par animal, annee = troupeau/espèce/race par année',
  sexe_cible    ENUM('M','F') NULL COMMENT 'Restreint les indices par animal à un sexe',
  formule       VARCHAR(1000) NOT NULL,
  unite         VARCHAR(20)  NULL,
  decimales     TINYINT UNSIGNED NOT NULL DEFAULT 2,
  sens          ENUM('haut','bas','intervalle') NOT NULL DEFAULT 'haut' COMMENT 'haut = plus élevé est meilleur',
  seuil_bas     DECIMAL(12,4) NULL,
  seuil_haut    DECIMAL(12,4) NULL,
  texte_bon     VARCHAR(255) NULL,
  texte_moyen   VARCHAR(255) NULL,
  texte_faible  VARCHAR(255) NULL,
  actif         TINYINT(1) NOT NULL DEFAULT 1,
  ordre         SMALLINT NOT NULL DEFAULT 0,
  updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_indicateurs_code (code),
  KEY idx_indicateurs_portee (portee, actif, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS indicateur_formules_historique;
CREATE TABLE indicateur_formules_historique (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  indicateur_id   SMALLINT UNSIGNED NOT NULL,
  ancienne_formule VARCHAR(1000) NULL,
  nouvelle_formule VARCHAR(1000) NOT NULL,
  modifie_par     INT UNSIGNED NULL,
  modifie_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ifh_indic (indicateur_id, id),
  CONSTRAINT fk_ifh_indic FOREIGN KEY (indicateur_id) REFERENCES indicateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Résultats enregistrés : un résultat par (indice, cible, année). annee = 0 => carrière complète
DROP TABLE IF EXISTS indicateur_resultats;
CREATE TABLE indicateur_resultats (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  indicateur_id  SMALLINT UNSIGNED NOT NULL,
  cible_type     ENUM('animal','troupeau','espece','race') NOT NULL,
  cible_id       INT UNSIGNED NOT NULL DEFAULT 0,
  annee          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  valeur         DECIMAL(16,4) NULL,
  niveau         ENUM('bon','moyen','faible','na') NOT NULL DEFAULT 'na',
  interpretation VARCHAR(500) NULL,
  formule        VARCHAR(1000) NOT NULL,
  variables      JSON NULL,
  calcule_par    INT UNSIGNED NULL,
  calcule_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_resultat (indicateur_id, cible_type, cible_id, annee),
  KEY idx_res_cible (cible_type, cible_id, annee, indicateur_id),
  KEY idx_res_annee (annee, cible_type, indicateur_id),
  KEY idx_res_niveau (niveau, id),
  CONSTRAINT fk_res_indic FOREIGN KEY (indicateur_id) REFERENCES indicateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
