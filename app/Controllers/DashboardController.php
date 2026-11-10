<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache;
use App\Core\Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        // Statistiques du tableau de bord mises en cache 60 s (APCu)
        $stats = Cache::remember('dashboard', function () {
            $parCategorie = Database::all(
                "SELECT e.libelle espece, c.libelle categorie, COUNT(*) n
                   FROM animaux a JOIN categories c ON c.id = a.categorie_id JOIN especes e ON e.id = a.espece_id
                  WHERE a.supprime = 0 AND a.statut = 'actif'
                  GROUP BY e.id, c.id ORDER BY e.id, n DESC"
            );
            $totaux = Database::one(
                "SELECT COUNT(*) total, SUM(sexe = 'F') femelles, SUM(sexe = 'M') males,
                        SUM(espece_id = 1) caprins, SUM(espece_id = 2) ovins
                   FROM animaux WHERE supprime = 0 AND statut = 'actif'"
            );
            $mbPrevues = Database::all(
                "SELECT r.id, r.date_prevue_mise_bas, a.id animal_id, a.numero_identification, a.nom
                   FROM reproductions r JOIN animaux a ON a.id = r.animal_id
                  WHERE r.resultat IN ('en_attente', 'gestante') AND a.statut = 'actif' AND a.supprime = 0
                    AND r.date_prevue_mise_bas BETWEEN CURDATE() - INTERVAL 7 DAY AND CURDATE() + INTERVAL 30 DAY
                  ORDER BY r.date_prevue_mise_bas LIMIT 10"
            );
            $annee = (int) date('Y');
            $mouvements = Database::one(
                "SELECT
                   (SELECT COALESCE(SUM(nb_vivants), 0) FROM mises_bas WHERE date_mise_bas >= MAKEDATE(?, 1)) naissances,
                   (SELECT COUNT(*) FROM animaux WHERE statut = 'mort'  AND date_sortie >= MAKEDATE(?, 1) AND supprime = 0) morts,
                   (SELECT COUNT(*) FROM animaux WHERE statut = 'vendu' AND date_sortie >= MAKEDATE(?, 1) AND supprime = 0) ventes",
                [$annee, $annee, $annee]
            );
            $evolution = Database::all(
                "SELECT DATE_FORMAT(date_mise_bas, '%Y-%m') mois, SUM(nb_nes) nes, SUM(nb_vivants) vivants
                   FROM mises_bas WHERE date_mise_bas >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01')
                  GROUP BY mois ORDER BY mois"
            );
            $indices = Database::all(
                "SELECT i.nom, i.unite, i.decimales, r.valeur, r.niveau, r.interpretation, r.annee
                   FROM indicateur_resultats r JOIN indicateurs i ON i.id = r.indicateur_id
                  WHERE r.cible_type = 'troupeau' AND r.annee = (SELECT MAX(annee) FROM indicateur_resultats WHERE cible_type = 'troupeau')
                  ORDER BY i.ordre LIMIT 8"
            );
            return compact('parCategorie', 'totaux', 'mbPrevues', 'mouvements', 'evolution', 'indices');
        }, 60, 'dashboard');

        $this->render('dashboard/index', $stats);
    }
}
