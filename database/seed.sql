-- =====================================================================
--  Données initiales
-- =====================================================================
SET NAMES utf8mb4;

-- Rôles --------------------------------------------------------------
INSERT INTO roles (id, code, libelle, description, systeme) VALUES
 (1, 'admin',       'Administrateur', 'Accès complet au système', 1),
 (2, 'gestionnaire','Gestionnaire',   'Gestion complète du troupeau et des indices', 0),
 (3, 'technicien',  'Technicien / Vétérinaire', 'Saisie des événements zootechniques et sanitaires', 0),
 (4, 'consultant',  'Consultant',     'Consultation et export uniquement', 0);

-- Permissions --------------------------------------------------------
INSERT INTO permissions (code, module, libelle) VALUES
 ('animaux.voir',          'animaux',     'Consulter les animaux et leurs fiches'),
 ('animaux.creer',         'animaux',     'Ajouter un animal'),
 ('animaux.modifier',      'animaux',     'Modifier un animal'),
 ('animaux.supprimer',     'animaux',     'Supprimer / restaurer un animal'),
 ('animaux.exporter',      'animaux',     'Imprimer et exporter en PDF'),
 ('evenements.voir',       'evenements',  'Consulter pesées, reproductions, mises bas, lait, soins'),
 ('evenements.creer',      'evenements',  'Saisir un événement'),
 ('evenements.modifier',   'evenements',  'Modifier un événement'),
 ('evenements.supprimer',  'evenements',  'Supprimer un événement'),
 ('indices.voir',          'indices',     'Consulter les indices calculés'),
 ('indices.calculer',      'indices',     'Lancer le calcul des indices'),
 ('indices.formules',      'indices',     'Modifier les formules et seuils des indices'),
 ('utilisateurs.gerer',    'securite',    'Gérer les utilisateurs'),
 ('roles.gerer',           'securite',    'Gérer les rôles et permissions'),
 ('journal.voir',          'securite',    'Consulter le journal des traces'),
 ('parametres.gerer',      'parametres',  'Modifier les paramètres et référentiels'),
 ('systeme.cache',         'parametres',  'Consulter / vider les caches OPcache & APCu');

-- Administrateur : toutes les permissions
INSERT INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions;
-- Gestionnaire
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 2, id FROM permissions WHERE module IN ('animaux','evenements','indices') OR code IN ('journal.voir','parametres.gerer');
-- Technicien
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 3, id FROM permissions WHERE code IN ('animaux.voir','animaux.creer','animaux.modifier','animaux.exporter',
    'evenements.voir','evenements.creer','evenements.modifier','indices.voir');
-- Consultant
INSERT INTO role_permissions (role_id, permission_id)
  SELECT 4, id FROM permissions WHERE code IN ('animaux.voir','animaux.exporter','evenements.voir','indices.voir');

-- Administrateur par défaut : admin / Admin@123 (changement obligatoire à la 1re connexion)
INSERT INTO utilisateurs (role_id, login, nom_complet, email, mot_de_passe, actif, doit_changer_mdp) VALUES
 (1, 'admin', 'Administrateur', NULL, '$2y$10$nyZ/ZqeOudPuYANw13Ot8ekXSUvtsnvGV.vRXttWUgILojZ9qkEdS', 1, 1);

-- Paramètres ---------------------------------------------------------
INSERT INTO parametres (cle, valeur, type, groupe, libelle, aide) VALUES
 ('nom_exploitation',     'Mon exploitation', 'texte',  'general',  'Nom de l''exploitation', 'Affiché sur les impressions PDF'),
 ('adresse_exploitation', '',                 'texte',  'general',  'Adresse', NULL),
 ('telephone_exploitation','',                'texte',  'general',  'Téléphone', NULL),
 ('devise',               'DH',               'texte',  'general',  'Devise', 'Symbole monétaire'),
 ('taille_page',          '25',               'entier', 'affichage','Lignes par page', 'Pagination keyset des listes'),
 ('max_export_pdf',       '2000',             'entier', 'affichage','Lignes max. par export PDF', NULL),
 ('age_adulte_mois',      '12',               'entier', 'zootechnie','Âge adulte (mois)', 'Sert à déterminer la catégorie automatique et les femelles reproductrices'),
 ('age_sevrage_jours',    '90',               'entier', 'zootechnie','Âge au sevrage (jours)', 'Sert aux taux de sevrage et mortalité avant sevrage'),
 ('duree_gestation_jours','150',              'entier', 'zootechnie','Durée de gestation (jours)', 'Calcule la date prévue de mise bas'),
 ('cache_ttl',            '300',              'entier', 'systeme',  'Durée du cache APCu (secondes)', NULL);

-- Espèces, catégories, races ------------------------------------------
INSERT INTO especes (id, code, libelle) VALUES (1, 'caprin', 'Caprin'), (2, 'ovin', 'Ovin');

INSERT INTO categories (espece_id, code, libelle, sexe, adulte, castre) VALUES
 (1, 'chevre',      'Chèvre',      'F', 1, 0),
 (1, 'bouc',        'Bouc',        'M', 1, 0),
 (1, 'bouc_castre', 'Bouc castré', 'M', 1, 1),
 (1, 'chevrette',   'Chevrette',   'F', 0, 0),
 (1, 'chevreau',    'Chevreau',    'M', 0, 0),
 (2, 'brebis',      'Brebis',      'F', 1, 0),
 (2, 'belier',      'Bélier',      'M', 1, 0),
 (2, 'mouton',      'Mouton (castré)', 'M', 1, 1),
 (2, 'agnelle',     'Agnelle',     'F', 0, 0),
 (2, 'agneau',      'Agneau',      'M', 0, 0);

INSERT INTO races (espece_id, libelle) VALUES
 (1, 'Draa'), (1, 'Noire de l''Atlas'), (1, 'Barcha'), (1, 'Alpine'), (1, 'Saanen'), (1, 'Murciana'), (1, 'Croisée caprine'),
 (2, 'Sardi'), (2, 'Timahdite'), (2, 'Béni Guil'), (2, 'D''man'), (2, 'Boujaâd'), (2, 'Ouled Djellal'), (2, 'Croisée ovine');

-- Indices par année (troupeau / espèce / race) ------------------------
INSERT INTO indicateurs (code, nom, description, portee, sexe_cible, formule, unite, decimales, sens, seuil_bas, seuil_haut, texte_bon, texte_moyen, texte_faible, ordre) VALUES
 ('FERTILITE', 'Taux de fertilité', 'Femelles ayant mis bas / femelles saillies', 'annee', NULL,
  'div(nb_femelles_mises_bas, nb_femelles_saillies) * 100', '%', 1, 'haut', 80, 90,
  'Fertilité excellente ({valeur} {unite}) : la conduite de la reproduction est maîtrisée.',
  'Fertilité moyenne ({valeur} {unite}) : vérifier l''état corporel à la lutte et la fertilité des mâles.',
  'Fertilité faible ({valeur} {unite}) : rechercher des causes alimentaires, sanitaires ou un défaut des mâles.', 10),
 ('PROLIFICITE', 'Prolificité', 'Nombre de nés / nombre de mises bas', 'annee', NULL,
  'div(nb_nes, nb_mises_bas)', 'nés/MB', 2, 'haut', 1.4, 1.8,
  'Bonne prolificité ({valeur} {unite}).', 'Prolificité moyenne ({valeur} {unite}) : améliorer le flushing avant la lutte.',
  'Prolificité faible ({valeur} {unite}) : revoir la sélection et l''alimentation des reproductrices.', 20),
 ('FECONDITE', 'Taux de fécondité', 'Nés / femelles adultes présentes en début d''année', 'annee', NULL,
  'div(nb_nes, nb_femelles_adultes) * 100', '%', 1, 'haut', 120, 160,
  'Fécondité élevée ({valeur} {unite}).', 'Fécondité moyenne ({valeur} {unite}).', 'Fécondité faible ({valeur} {unite}) : trop de femelles improductives.', 30),
 ('MORTINATALITE', 'Taux de mortinatalité', 'Morts-nés / nés', 'annee', NULL,
  'div(nb_morts_nes, nb_nes) * 100', '%', 1, 'bas', 3, 8,
  'Mortinatalité maîtrisée ({valeur} {unite}).', 'Mortinatalité à surveiller ({valeur} {unite}).', 'Mortinatalité élevée ({valeur} {unite}) : surveiller les mises bas et l''alimentation en fin de gestation.', 40),
 ('MORT_AV_SEVRAGE', 'Mortalité avant sevrage', 'Jeunes morts avant l''âge de sevrage / nés vivants', 'annee', NULL,
  'div(nb_morts_avant_sevrage, nb_nes_vivants) * 100', '%', 1, 'bas', 5, 10,
  'Mortalité des jeunes faible ({valeur} {unite}).', 'Mortalité des jeunes moyenne ({valeur} {unite}) : vérifier la prise colostrale.', 'Mortalité des jeunes élevée ({valeur} {unite}) : revoir l''hygiène, le colostrum et la prévention sanitaire.', 50),
 ('TAUX_SEVRAGE', 'Taux de sevrage', 'Jeunes sevrés / nés vivants', 'annee', NULL,
  'div(nb_sevres, nb_nes_vivants) * 100', '%', 1, 'haut', 85, 95,
  'Taux de sevrage très bon ({valeur} {unite}).', 'Taux de sevrage moyen ({valeur} {unite}).', 'Taux de sevrage faible ({valeur} {unite}).', 60),
 ('PRODUCTIVITE_NUM', 'Productivité numérique', 'Jeunes sevrés / femelles adultes', 'annee', NULL,
  'div(nb_sevres, nb_femelles_adultes) * 100', '%', 1, 'haut', 110, 150,
  'Productivité numérique élevée ({valeur} {unite}).', 'Productivité numérique moyenne ({valeur} {unite}).', 'Productivité numérique faible ({valeur} {unite}) : agir sur fertilité, prolificité et mortalité.', 70),
 ('TAUX_AVORTEMENT', 'Taux d''avortement', 'Avortements / femelles saillies', 'annee', NULL,
  'div(nb_avortements, nb_femelles_saillies) * 100', '%', 1, 'bas', 3, 8,
  'Avortements rares ({valeur} {unite}).', 'Avortements à surveiller ({valeur} {unite}).', 'Taux d''avortement préoccupant ({valeur} {unite}) : faire un diagnostic (brucellose, chlamydiose, fièvre Q...).', 80),
 ('MORTALITE_GLOBALE', 'Mortalité globale', 'Morts / effectif moyen', 'annee', NULL,
  'div(nb_morts, effectif_moyen) * 100', '%', 1, 'bas', 5, 10,
  'Mortalité globale faible ({valeur} {unite}).', 'Mortalité globale moyenne ({valeur} {unite}).', 'Mortalité globale élevée ({valeur} {unite}).', 90),
 ('TAUX_REFORME', 'Taux de réforme / renouvellement', '(Réformes + ventes) / effectif de début d''année', 'annee', NULL,
  'div(nb_reformes + nb_ventes, effectif_debut) * 100', '%', 1, 'intervalle', 15, 30,
  'Taux de réforme équilibré ({valeur} {unite}).', NULL, 'Taux de réforme hors de la plage recommandée 15-30 % ({valeur} {unite}).', 100),
 ('CROIT_NET', 'Croît net du troupeau', '(Effectif fin - effectif début) / effectif début', 'annee', NULL,
  'div(effectif_fin - effectif_debut, effectif_debut) * 100', '%', 1, 'intervalle', 0, 20,
  'Croissance du troupeau maîtrisée ({valeur} {unite}).', NULL, 'Évolution de l''effectif à analyser ({valeur} {unite}).', 110),
 ('GMQ_JEUNES', 'GMQ moyen naissance-sevrage', 'Gain moyen quotidien des jeunes (g/j)', 'annee', NULL,
  '(poids_moyen_sevrage - poids_moyen_naissance) / age_moyen_sevrage * 1000', 'g/j', 0, 'haut', 100, 150,
  'Croissance des jeunes très bonne ({valeur} {unite}).', 'Croissance des jeunes moyenne ({valeur} {unite}).', 'Croissance des jeunes faible ({valeur} {unite}) : revoir l''alimentation lactée et la complémentation.', 120),
 ('LAIT_MOYEN_JOUR', 'Lait moyen par contrôle', 'Quantité moyenne de lait par contrôle (L/j)', 'annee', NULL,
  'lait_moyen_controle', 'L/j', 2, 'haut', 1.5, 2.5,
  'Bonne production laitière ({valeur} {unite}).', 'Production laitière moyenne ({valeur} {unite}).', 'Production laitière faible ({valeur} {unite}).', 130),
 ('COUT_SANIT_TETE', 'Coût sanitaire par tête', 'Coût des soins / effectif moyen', 'annee', NULL,
  'div(cout_sanitaire, effectif_moyen)', 'DH', 2, 'bas', 50, 100,
  'Coût sanitaire maîtrisé ({valeur} {unite}/tête).', 'Coût sanitaire moyen ({valeur} {unite}/tête).', 'Coût sanitaire élevé ({valeur} {unite}/tête).', 140);

-- Indices par animal --------------------------------------------------
INSERT INTO indicateurs (code, nom, description, portee, sexe_cible, formule, unite, decimales, sens, seuil_bas, seuil_haut, texte_bon, texte_moyen, texte_faible, ordre) VALUES
 ('A_GMQ', 'GMQ (entre 1re et dernière pesée)', 'Gain moyen quotidien sur la période', 'animal', NULL,
  '(poids_actuel - poids_premier) / jours_entre_pesees * 1000', 'g/j', 0, 'haut', 80, 120,
  'Croissance très satisfaisante ({valeur} {unite}).', 'Croissance moyenne ({valeur} {unite}).', 'Croissance insuffisante ({valeur} {unite}).', 10),
 ('A_GMQ_SEVRAGE', 'GMQ naissance-sevrage', 'Gain moyen quotidien entre naissance et sevrage', 'animal', NULL,
  '(poids_sevrage - poids_naissance) / age_sevrage_jours * 1000', 'g/j', 0, 'haut', 100, 150,
  'Bon potentiel de croissance ({valeur} {unite}) : candidat au renouvellement.', 'Croissance avant sevrage moyenne ({valeur} {unite}).', 'Croissance avant sevrage faible ({valeur} {unite}).', 20),
 ('A_PROLIFICITE', 'Prolificité individuelle', 'Nés / mises bas', 'animal', 'F',
  'div(nb_nes, nb_mises_bas)', 'nés/MB', 2, 'haut', 1.4, 1.8,
  'Femelle prolifique ({valeur} {unite}).', 'Prolificité moyenne ({valeur} {unite}).', 'Prolificité faible ({valeur} {unite}).', 30),
 ('A_IVMB', 'Intervalle entre mises bas', 'Moyenne des intervalles entre mises bas successives', 'animal', 'F',
  'intervalle_moyen_mb', 'jours', 0, 'bas', 365, 420,
  'Intervalle optimal ({valeur} {unite}).', 'Intervalle un peu long ({valeur} {unite}).', 'Intervalle trop long ({valeur} {unite}) : femelle à surveiller ou à réformer.', 40),
 ('A_AGE_1ERE_MB', 'Âge à la première mise bas', 'Âge en mois à la première mise bas', 'animal', 'F',
  'age_premiere_mb_jours / 30.44', 'mois', 1, 'bas', 14, 18,
  'Mise à la reproduction précoce ({valeur} {unite}).', 'Âge à la 1re mise bas correct ({valeur} {unite}).', 'Première mise bas tardive ({valeur} {unite}).', 50),
 ('A_LAIT_MOYEN', 'Lait moyen par contrôle', 'Moyenne des contrôles laitiers (L/j)', 'animal', 'F',
  'lait_moyen_controle', 'L/j', 2, 'haut', 1.5, 2.5,
  'Bonne laitière ({valeur} {unite}).', 'Laitière moyenne ({valeur} {unite}).', 'Faible laitière ({valeur} {unite}).', 60),
 ('A_LAIT_LACTATION', 'Production estimée par lactation', 'Lait moyen par contrôle x jours de contrôle / nombre de lactations', 'animal', 'F',
  'lait_moyen_controle * jours_lactation / max(nb_mises_bas, 1)', 'L', 0, 'haut', 300, 500,
  'Production de lactation élevée ({valeur} {unite}).', 'Production de lactation moyenne ({valeur} {unite}).', 'Production de lactation faible ({valeur} {unite}).', 70),
 ('A_SURVIE_PORTEES', 'Survie à la naissance des portées', 'Nés vivants / nés', 'animal', 'F',
  'div(nb_nes_vivants, nb_nes) * 100', '%', 1, 'haut', 90, 97,
  'Excellente survie des portées ({valeur} {unite}).', 'Survie des portées correcte ({valeur} {unite}).', 'Pertes à la naissance importantes ({valeur} {unite}).', 80),
 ('A_SURVIE_DESC', 'Survie des descendants', 'Descendants vivants / descendants', 'animal', 'M',
  'div(nb_descendants_vivants, nb_descendants) * 100', '%', 1, 'haut', 85, 95,
  'Reproducteur aux descendants robustes ({valeur} {unite}).', 'Survie des descendants moyenne ({valeur} {unite}).', 'Survie des descendants faible ({valeur} {unite}).', 90),
 ('A_COUT_SANIT', 'Coût sanitaire de l''animal', 'Somme des coûts des soins', 'animal', NULL,
  'coalesce(cout_sanitaire, 0)', 'DH', 2, 'bas', 50, 150,
  'Coût sanitaire faible ({valeur} {unite}).', 'Coût sanitaire moyen ({valeur} {unite}).', 'Coût sanitaire élevé ({valeur} {unite}) : animal fragile.', 100);
