<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Settings;

/**
 * Calcul, interprétation et enregistrement des indices.
 *
 * - Portée "animal" : variables calculées par animal (carrière complète ou une année).
 * - Portée "annee"  : variables calculées par année pour le troupeau, chaque espèce ou chaque race.
 *
 * Les variables sont agrégées en SQL par lots (requêtes GROUP BY sur des index
 * (animal_id, date)), puis injectées dans les formules paramétrables.
 */
final class IndicatorService
{
    /** Variables disponibles pour les indices par animal. */
    public const ANIMAL_VARS = [
        'age_jours'              => 'Âge en jours (à la fin de la période ou à la sortie)',
        'age_mois'               => 'Âge en mois',
        'est_femelle'            => '1 si femelle, 0 sinon',
        'poids_naissance'        => 'Poids de naissance (kg)',
        'poids_premier'          => 'Premier poids de la période (kg) — poids de naissance en carrière',
        'poids_actuel'           => 'Dernier poids de la période (kg)',
        'jours_entre_pesees'     => 'Jours entre premier et dernier poids',
        'nb_pesees'              => 'Nombre de pesées',
        'poids_sevrage'          => 'Poids à la pesée de sevrage (kg)',
        'age_sevrage_jours'      => 'Âge à la pesée de sevrage (jours)',
        'nb_saillies'            => 'Nombre de saillies / inséminations',
        'nb_mises_bas'           => 'Nombre de mises bas (hors avortements)',
        'nb_avortements'         => "Nombre d'avortements",
        'nb_nes'                 => 'Nombre total de nés',
        'nb_nes_vivants'         => 'Nombre de nés vivants',
        'nb_morts_nes'           => 'Nombre de morts-nés',
        'intervalle_moyen_mb'    => 'Intervalle moyen entre mises bas (jours)',
        'age_premiere_mb_jours'  => 'Âge à la première mise bas (jours)',
        'nb_descendants'         => 'Nombre de descendants enregistrés',
        'nb_descendants_vivants' => 'Descendants non morts',
        'lait_total'             => 'Somme des contrôles laitiers (L)',
        'nb_controles_lait'      => 'Nombre de contrôles laitiers',
        'lait_moyen_controle'    => 'Lait moyen par contrôle (L/j)',
        'jours_lactation'        => 'Jours entre premier et dernier contrôle (+1)',
        'nb_soins'               => 'Nombre de soins',
        'cout_sanitaire'         => 'Coût total des soins',
        'prix_achat'             => "Prix d'achat",
        'prix_vente'             => 'Prix de vente',
    ];

    /** Variables disponibles pour les indices annuels (troupeau / espèce / race). */
    public const ANNEE_VARS = [
        'nb_jours'                 => "Nombre de jours de l'année",
        'effectif_debut'           => 'Effectif présent au 1er janvier',
        'effectif_fin'             => 'Effectif présent au 31 décembre',
        'effectif_moyen'           => '(effectif début + effectif fin) / 2',
        'nb_femelles_adultes'      => "Femelles adultes présentes au 1er janvier (âge ≥ âge adulte)",
        'nb_males_adultes'         => 'Mâles adultes présents au 1er janvier',
        'nb_saillies'              => "Saillies dont la mise bas était attendue dans l'année",
        'nb_femelles_saillies'     => "Femelles saillies dont la mise bas était attendue dans l'année",
        'nb_mises_bas'             => "Mises bas de l'année (hors avortements)",
        'nb_femelles_mises_bas'    => "Femelles ayant mis bas dans l'année",
        'nb_avortements'           => "Avortements de l'année",
        'nb_nes'                   => 'Total des nés',
        'nb_nes_vivants'           => 'Nés vivants',
        'nb_morts_nes'             => 'Morts-nés',
        'nb_naissances_enregistrees' => "Jeunes nés dans l'année enregistrés comme animaux",
        'nb_morts_avant_sevrage'   => "Jeunes nés dans l'année et morts avant l'âge de sevrage",
        'nb_sevres'                => 'Nés vivants - morts avant sevrage',
        'nb_morts'                 => "Morts dans l'année",
        'nb_ventes'                => "Ventes dans l'année",
        'nb_reformes'              => "Réformes dans l'année",
        'nb_perdus'                => "Animaux perdus dans l'année",
        'nb_achats'                => "Achats dans l'année",
        'lait_total'               => 'Somme des contrôles laitiers (L)',
        'nb_femelles_traites'      => 'Femelles contrôlées',
        'lait_moyen_controle'      => 'Lait moyen par contrôle (L/j)',
        'poids_moyen_naissance'    => "Poids moyen de naissance des jeunes de l'année (kg)",
        'poids_moyen_sevrage'      => 'Poids moyen aux pesées de sevrage (kg)',
        'age_moyen_sevrage'        => 'Âge moyen aux pesées de sevrage (jours)',
        'nb_soins'                 => 'Nombre de soins',
        'cout_sanitaire'           => 'Coût total des soins',
        'chiffre_ventes'           => 'Total des prix de vente',
        'cout_achats'              => "Total des prix d'achat",
    ];

    public static function varsFor(string $portee): array
    {
        return $portee === 'animal' ? self::ANIMAL_VARS : self::ANNEE_VARS;
    }

    public static function indicateurs(?string $portee = null, bool $actifsSeulement = true): array
    {
        $all = Cache::remember('indicateurs', fn() => Database::all('SELECT * FROM indicateurs ORDER BY portee, ordre, id'), 3600, 'indic');
        return array_values(array_filter($all, fn($i) =>
            ($portee === null || $i['portee'] === $portee) && (!$actifsSeulement || (int) $i['actif'] === 1)));
    }

    // ==================================================================
    //  Interprétation
    // ==================================================================
    public static function interpret(array $ind, ?float $v, array $usedVars = []): array
    {
        if ($v === null) {
            $missing = array_keys(array_filter($usedVars, fn($x) => $x === null));
            $txt = 'Non calculable : ' . ($missing ? 'données manquantes (' . implode(', ', $missing) . ')' : 'division par zéro ou valeur indéfinie') . '.';
            return ['na', $txt];
        }
        $lo = $ind['seuil_bas'] !== null ? (float) $ind['seuil_bas'] : null;
        $hi = $ind['seuil_haut'] !== null ? (float) $ind['seuil_haut'] : null;
        $niveau = 'moyen';
        if ($lo !== null || $hi !== null) {
            switch ($ind['sens']) {
                case 'haut':
                    $niveau = ($hi !== null && $v >= $hi) ? 'bon' : (($lo === null || $v >= $lo) ? 'moyen' : 'faible');
                    if ($hi === null && $lo !== null && $v >= $lo) $niveau = 'bon';
                    break;
                case 'bas':
                    $niveau = ($lo !== null && $v <= $lo) ? 'bon' : (($hi === null || $v <= $hi) ? 'moyen' : 'faible');
                    if ($lo === null && $hi !== null && $v <= $hi) $niveau = 'bon';
                    break;
                case 'intervalle':
                    $niveau = (($lo === null || $v >= $lo) && ($hi === null || $v <= $hi)) ? 'bon' : 'faible';
                    break;
            }
        }
        $tpl = $ind['texte_' . $niveau] ?: match ($niveau) {
            'bon' => '{nom} : bon résultat ({valeur} {unite}).',
            'moyen' => '{nom} : résultat moyen ({valeur} {unite}).',
            default => '{nom} : résultat insuffisant ({valeur} {unite}).',
        };
        $txt = strtr($tpl, [
            '{valeur}' => number_format($v, (int) $ind['decimales'], ',', ' '),
            '{unite}'  => (string) $ind['unite'],
            '{nom}'    => $ind['nom'],
        ]);
        return [$niveau, trim($txt)];
    }

    // ==================================================================
    //  Calculs
    // ==================================================================

    /**
     * Calcule et enregistre les indices par animal.
     * @param int $annee 0 = carrière complète
     * @param array $filtre ['animal_id' => int] ou ['espece_id' => int] ; ['tous' => true] inclut les sortis
     */
    public static function calculerAnimaux(int $annee, array $filtre = []): array
    {
        $t0 = microtime(true);
        $inds = self::indicateurs('animal');
        $stats = ['animaux' => 0, 'resultats' => 0, 'non_calculables' => 0];
        if (!$inds) {
            return $stats + ['duree' => 0];
        }
        $parsed = [];
        foreach ($inds as $i) {
            $parsed[$i['id']] = FormulaEngine::parse($i['formule']);
        }

        $where = ['supprime = 0'];
        $params = [];
        if (!empty($filtre['animal_id'])) {
            $where[] = 'id = :aid';
            $params['aid'] = (int) $filtre['animal_id'];
        } else {
            if (empty($filtre['tous'])) {
                $where[] = "statut = 'actif'";
            }
            if (!empty($filtre['espece_id'])) {
                $where[] = 'espece_id = :esp';
                $params['esp'] = (int) $filtre['espece_id'];
            }
        }
        if ($annee > 0) {
            $where[] = 'date_naissance <= :fin';
            $params['fin'] = "$annee-12-31";
        }

        $lastId = 0;
        do {
            // Parcours keyset des animaux par lots de 500
            $rows = Database::all(
                'SELECT id FROM animaux WHERE ' . implode(' AND ', $where) . ' AND id > :last ORDER BY id LIMIT 500',
                $params + ['last' => $lastId]
            );
            if (!$rows) break;
            $ids = array_map('intval', array_column($rows, 'id'));
            $lastId = end($ids);
            $vars = self::animalVariables($ids, $annee);

            $batch = [];
            foreach ($vars as $aid => $v) {
                $stats['animaux']++;
                foreach ($inds as $ind) {
                    if ($ind['sexe_cible'] && $ind['sexe_cible'] !== $v['_sexe']) {
                        continue;
                    }
                    $batch[] = self::buildResult($ind, $parsed[$ind['id']], $v, 'animal', $aid, $annee, $stats);
                }
            }
            self::saveResults($batch);
        } while (count($rows) === 500);

        Cache::flushGroup('indic_res');
        return $stats + ['duree' => round(microtime(true) - $t0, 2)];
    }

    /**
     * Calcule et enregistre les indices annuels.
     * @param int[] $annees
     * @param string[] $niveaux parmi troupeau, espece, race
     */
    public static function calculerAnnees(array $annees, array $niveaux = ['troupeau', 'espece']): array
    {
        $t0 = microtime(true);
        $inds = self::indicateurs('annee');
        $stats = ['cibles' => 0, 'resultats' => 0, 'non_calculables' => 0];
        $parsed = [];
        foreach ($inds as $i) {
            $parsed[$i['id']] = FormulaEngine::parse($i['formule']);
        }
        $cibles = [];
        if (in_array('troupeau', $niveaux, true)) {
            $cibles[] = ['troupeau', 0];
        }
        if (in_array('espece', $niveaux, true)) {
            foreach (Database::all('SELECT id FROM especes') as $e) $cibles[] = ['espece', (int) $e['id']];
        }
        if (in_array('race', $niveaux, true)) {
            foreach (Database::all('SELECT DISTINCT race_id FROM animaux WHERE race_id IS NOT NULL AND supprime = 0') as $r) {
                $cibles[] = ['race', (int) $r['race_id']];
            }
        }
        foreach ($annees as $annee) {
            $batch = [];
            foreach ($cibles as [$type, $id]) {
                $v = self::anneeVariables((int) $annee, $type, $id);
                $stats['cibles']++;
                foreach ($inds as $ind) {
                    $batch[] = self::buildResult($ind, $parsed[$ind['id']], $v, $type, $id, (int) $annee, $stats);
                }
            }
            self::saveResults($batch);
        }
        Cache::flushGroup('indic_res');
        return $stats + ['duree' => round(microtime(true) - $t0, 2)];
    }

    /** Évalue une formule sur un contexte (test depuis les paramètres) sans enregistrer. */
    public static function tester(string $formule, string $portee, int $annee, string $cibleType, int $cibleId): array
    {
        $vars = $portee === 'animal'
            ? (self::animalVariables([$cibleId], $annee)[$cibleId] ?? null)
            : self::anneeVariables($annee, $cibleType, $cibleId);
        if ($vars === null) {
            throw new FormulaException('Animal introuvable.');
        }
        $ast = FormulaEngine::parse($formule);
        $used = array_intersect_key($vars, array_flip(FormulaEngine::variables($ast)));
        return ['valeur' => FormulaEngine::evaluate($ast, $vars), 'variables' => $used];
    }

    private static function buildResult(array $ind, array $ast, array $vars, string $type, int $id, int $annee, array &$stats): array
    {
        $usedNames = FormulaEngine::variables($ast);
        $used = array_intersect_key($vars, array_flip($usedNames));
        try {
            $val = FormulaEngine::evaluate($ast, $vars);
        } catch (FormulaException) {
            $val = null;
        }
        if ($val !== null) {
            $val = round($val, 4);
        }
        [$niveau, $txt] = self::interpret($ind, $val, $used);
        $stats['resultats']++;
        if ($val === null) $stats['non_calculables']++;
        return [
            (int) $ind['id'], $type, $id, $annee, $val, $niveau, mb_substr($txt, 0, 500),
            $ind['formule'], json_encode($used, JSON_UNESCAPED_UNICODE), Auth::id(),
        ];
    }

    /** INSERT multi-lignes ... ON DUPLICATE KEY UPDATE (upsert idempotent sur la clé unique). */
    private static function saveResults(array $rows): void
    {
        foreach (array_chunk($rows, 300) as $chunk) {
            $ph = [];
            $params = [];
            foreach ($chunk as $r) {
                $ph[] = '(?,?,?,?,?,?,?,?,?,?,NOW())';
                array_push($params, ...$r);
            }
            Database::run(
                'INSERT INTO indicateur_resultats (indicateur_id, cible_type, cible_id, annee, valeur, niveau, interpretation, formule, variables, calcule_par, calcule_le)
                 VALUES ' . implode(',', $ph) . '
                 ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), niveau = VALUES(niveau), interpretation = VALUES(interpretation),
                   formule = VALUES(formule), variables = VALUES(variables), calcule_par = VALUES(calcule_par), calcule_le = NOW()',
                $params
            );
        }
    }

    // ==================================================================
    //  Variables par animal (agrégation par lots)
    // ==================================================================
    public static function animalVariables(array $ids, int $annee = 0): array
    {
        if (!$ids) return [];
        $in = implode(',', array_map('intval', $ids));
        $debut = $annee > 0 ? "$annee-01-01" : null;
        $fin = $annee > 0 ? "$annee-12-31" : null;
        $per = fn(string $col) => $annee > 0 ? " AND $col BETWEEN '$debut' AND '$fin'" : '';
        $today = date('Y-m-d');

        $out = [];
        foreach (Database::all("SELECT id, sexe, date_naissance, poids_naissance, date_sortie, prix_achat, prix_vente FROM animaux WHERE id IN ($in)") as $a) {
            $ref = min(array_filter([$fin ?? $today, $a['date_sortie'], $today]));
            $age = $a['date_naissance'] <= $ref ? (int) ((strtotime($ref) - strtotime($a['date_naissance'])) / 86400) : null;
            $v = array_fill_keys(array_keys(self::ANIMAL_VARS), null);
            $v['_sexe'] = $a['sexe'];
            $v['_naissance'] = $a['date_naissance'];
            $v['age_jours'] = $age;
            $v['age_mois'] = $age !== null ? round($age / 30.44, 1) : null;
            $v['est_femelle'] = $a['sexe'] === 'F' ? 1 : 0;
            $v['poids_naissance'] = $a['poids_naissance'] !== null ? (float) $a['poids_naissance'] : null;
            $v['prix_achat'] = $a['prix_achat'] !== null ? (float) $a['prix_achat'] : null;
            $v['prix_vente'] = $a['prix_vente'] !== null ? (float) $a['prix_vente'] : null;
            foreach (['nb_pesees', 'nb_saillies', 'nb_mises_bas', 'nb_avortements', 'nb_nes', 'nb_nes_vivants', 'nb_morts_nes',
                      'nb_descendants', 'nb_descendants_vivants', 'nb_controles_lait', 'nb_soins'] as $k) {
                $v[$k] = 0;
            }
            $out[(int) $a['id']] = $v;
        }

        // Pesées : premier et dernier poids de la période
        foreach (Database::all(
            "SELECT animal_id, COUNT(*) n, MIN(date_pesee) d1, MAX(date_pesee) d2,
                    SUBSTRING_INDEX(GROUP_CONCAT(poids_kg ORDER BY date_pesee, id), ',', 1) p1,
                    SUBSTRING_INDEX(GROUP_CONCAT(poids_kg ORDER BY date_pesee DESC, id DESC), ',', 1) p2
               FROM pesees WHERE animal_id IN ($in)" . $per('date_pesee') . ' GROUP BY animal_id'
        ) as $r) {
            $v = &$out[(int) $r['animal_id']];
            $v['nb_pesees'] = (int) $r['n'];
            $v['poids_actuel'] = (float) $r['p2'];
            $v['poids_premier'] = (float) $r['p1'];
            $d1 = $r['d1'];
            // En carrière, le poids de naissance sert de premier point
            if ($annee === 0 && $v['poids_naissance'] !== null && $v['_naissance'] < $d1) {
                $v['poids_premier'] = $v['poids_naissance'];
                $d1 = $v['_naissance'];
            }
            $v['jours_entre_pesees'] = (int) ((strtotime($r['d2']) - strtotime($d1)) / 86400);
            unset($v);
        }
        foreach (Database::all(
            "SELECT p.animal_id, SUBSTRING_INDEX(GROUP_CONCAT(p.poids_kg ORDER BY p.date_pesee, p.id), ',', 1) poids,
                    MIN(p.date_pesee) d
               FROM pesees p WHERE p.type_pesee = 'sevrage' AND p.animal_id IN ($in)" . $per('p.date_pesee') . ' GROUP BY p.animal_id'
        ) as $r) {
            $v = &$out[(int) $r['animal_id']];
            $v['poids_sevrage'] = (float) $r['poids'];
            $v['age_sevrage_jours'] = (int) ((strtotime($r['d']) - strtotime($v['_naissance'])) / 86400);
            unset($v);
        }

        foreach (Database::all("SELECT animal_id, COUNT(*) n FROM reproductions WHERE animal_id IN ($in)" . $per('date_saillie') . ' GROUP BY animal_id') as $r) {
            $out[(int) $r['animal_id']]['nb_saillies'] = (int) $r['n'];
        }

        foreach (Database::all(
            "SELECT animal_id,
                    SUM(issue <> 'avortement') nmb, SUM(issue = 'avortement') nav,
                    SUM(nb_nes) nes, SUM(nb_vivants) viv,
                    MIN(CASE WHEN issue <> 'avortement' THEN date_mise_bas END) d1,
                    MAX(CASE WHEN issue <> 'avortement' THEN date_mise_bas END) d2
               FROM mises_bas WHERE animal_id IN ($in)" . $per('date_mise_bas') . ' GROUP BY animal_id'
        ) as $r) {
            $v = &$out[(int) $r['animal_id']];
            $v['nb_mises_bas'] = (int) $r['nmb'];
            $v['nb_avortements'] = (int) $r['nav'];
            $v['nb_nes'] = (int) $r['nes'];
            $v['nb_nes_vivants'] = (int) $r['viv'];
            $v['nb_morts_nes'] = (int) $r['nes'] - (int) $r['viv'];
            // Moyenne des intervalles successifs = (dernière - première) / (n - 1)
            if ((int) $r['nmb'] >= 2) {
                $v['intervalle_moyen_mb'] = round((strtotime($r['d2']) - strtotime($r['d1'])) / 86400 / ((int) $r['nmb'] - 1), 1);
            }
            unset($v);
        }
        // Âge à la première mise bas : toujours sur la carrière
        foreach (Database::all("SELECT animal_id, MIN(date_mise_bas) d FROM mises_bas WHERE issue <> 'avortement' AND animal_id IN ($in) GROUP BY animal_id") as $r) {
            $v = &$out[(int) $r['animal_id']];
            $v['age_premiere_mb_jours'] = (int) ((strtotime($r['d']) - strtotime($v['_naissance'])) / 86400);
            unset($v);
        }

        foreach (['mere_id', 'pere_id'] as $col) {
            foreach (Database::all(
                "SELECT $col pid, COUNT(*) n, SUM(statut <> 'mort') viv FROM animaux
                  WHERE supprime = 0 AND $col IN ($in)" . $per('date_naissance') . " GROUP BY $col"
            ) as $r) {
                $out[(int) $r['pid']]['nb_descendants'] += (int) $r['n'];
                $out[(int) $r['pid']]['nb_descendants_vivants'] += (int) $r['viv'];
            }
        }

        foreach (Database::all(
            "SELECT animal_id, COUNT(*) n, SUM(quantite_litres) tot, AVG(quantite_litres) moy, MIN(date_controle) d1, MAX(date_controle) d2
               FROM productions_lait WHERE animal_id IN ($in)" . $per('date_controle') . ' GROUP BY animal_id'
        ) as $r) {
            $v = &$out[(int) $r['animal_id']];
            $v['nb_controles_lait'] = (int) $r['n'];
            $v['lait_total'] = round((float) $r['tot'], 2);
            $v['lait_moyen_controle'] = round((float) $r['moy'], 3);
            $v['jours_lactation'] = (int) ((strtotime($r['d2']) - strtotime($r['d1'])) / 86400) + 1;
            unset($v);
        }

        foreach (Database::all("SELECT animal_id, COUNT(*) n, SUM(cout) c FROM soins WHERE animal_id IN ($in)" . $per('date_soin') . ' GROUP BY animal_id') as $r) {
            $out[(int) $r['animal_id']]['nb_soins'] = (int) $r['n'];
            $out[(int) $r['animal_id']]['cout_sanitaire'] = $r['c'] !== null ? (float) $r['c'] : 0.0;
        }
        return $out;
    }

    // ==================================================================
    //  Variables annuelles (troupeau / espèce / race)
    // ==================================================================
    public static function anneeVariables(int $annee, string $cibleType = 'troupeau', int $cibleId = 0): array
    {
        $deb = "$annee-01-01";
        $fin = "$annee-12-31";
        $ageAdulte = (int) Settings::get('age_adulte_mois', 12);
        $sevrage = (int) Settings::get('age_sevrage_jours', 90);
        $gest = (int) Settings::get('duree_gestation_jours', 150);
        $limAdulte = date('Y-m-d', strtotime("$deb -$ageAdulte months"));

        $f = 'a.supprime = 0';
        $p = [];
        if ($cibleType === 'espece') {
            $f .= ' AND a.espece_id = :cid';
            $p['cid'] = $cibleId;
        } elseif ($cibleType === 'race') {
            $f .= ' AND a.race_id = :cid';
            $p['cid'] = $cibleId;
        }
        $present = fn(string $d) => "(a.date_entree <= '$d' AND (a.date_sortie IS NULL OR a.date_sortie > '$d'))";

        $v = array_fill_keys(array_keys(self::ANNEE_VARS), null);
        $v['nb_jours'] = (int) date('z', strtotime($fin)) + 1;

        $r = Database::one(
            "SELECT
               SUM({$present($deb)}) eff_deb,
               SUM({$present($fin)}) eff_fin,
               SUM({$present($deb)} AND a.sexe = 'F' AND a.date_naissance <= '$limAdulte') fem_ad,
               SUM({$present($deb)} AND a.sexe = 'M' AND a.date_naissance <= '$limAdulte') mal_ad,
               SUM(a.origine = 'naissance' AND a.date_naissance BETWEEN '$deb' AND '$fin') naiss,
               SUM(a.date_naissance BETWEEN '$deb' AND '$fin' AND a.statut = 'mort' AND DATEDIFF(a.date_sortie, a.date_naissance) <= $sevrage) mort_sev,
               SUM(a.statut = 'mort'    AND a.date_sortie BETWEEN '$deb' AND '$fin') morts,
               SUM(a.statut = 'vendu'   AND a.date_sortie BETWEEN '$deb' AND '$fin') ventes,
               SUM(a.statut = 'reforme' AND a.date_sortie BETWEEN '$deb' AND '$fin') reformes,
               SUM(a.statut = 'perdu'   AND a.date_sortie BETWEEN '$deb' AND '$fin') perdus,
               SUM(a.origine = 'achat'  AND a.date_entree BETWEEN '$deb' AND '$fin') achats,
               SUM(CASE WHEN a.statut = 'vendu' AND a.date_sortie BETWEEN '$deb' AND '$fin' THEN a.prix_vente END) ca,
               SUM(CASE WHEN a.origine = 'achat' AND a.date_entree BETWEEN '$deb' AND '$fin' THEN a.prix_achat END) achats_val,
               AVG(CASE WHEN a.date_naissance BETWEEN '$deb' AND '$fin' THEN a.poids_naissance END) pn
             FROM animaux a WHERE $f",
            $p
        ) ?? [];
        $v['effectif_debut'] = (int) ($r['eff_deb'] ?? 0);
        $v['effectif_fin'] = (int) ($r['eff_fin'] ?? 0);
        $v['effectif_moyen'] = ($v['effectif_debut'] + $v['effectif_fin']) / 2;
        $v['nb_femelles_adultes'] = (int) ($r['fem_ad'] ?? 0);
        $v['nb_males_adultes'] = (int) ($r['mal_ad'] ?? 0);
        $v['nb_naissances_enregistrees'] = (int) ($r['naiss'] ?? 0);
        $v['nb_morts_avant_sevrage'] = (int) ($r['mort_sev'] ?? 0);
        $v['nb_morts'] = (int) ($r['morts'] ?? 0);
        $v['nb_ventes'] = (int) ($r['ventes'] ?? 0);
        $v['nb_reformes'] = (int) ($r['reformes'] ?? 0);
        $v['nb_perdus'] = (int) ($r['perdus'] ?? 0);
        $v['nb_achats'] = (int) ($r['achats'] ?? 0);
        $v['chiffre_ventes'] = (float) ($r['ca'] ?? 0);
        $v['cout_achats'] = (float) ($r['achats_val'] ?? 0);
        $v['poids_moyen_naissance'] = isset($r['pn']) ? round((float) $r['pn'], 3) : null;

        // Saillies dont la mise bas est attendue dans l'année (fenêtre décalée de la gestation)
        $sDeb = date('Y-m-d', strtotime("$deb -$gest days"));
        $sFin = date('Y-m-d', strtotime("$fin -$gest days"));
        $r = Database::one(
            "SELECT COUNT(*) n, COUNT(DISTINCT r.animal_id) f FROM reproductions r JOIN animaux a ON a.id = r.animal_id
              WHERE $f AND r.date_saillie BETWEEN '$sDeb' AND '$sFin'", $p) ?? [];
        $v['nb_saillies'] = (int) ($r['n'] ?? 0);
        $v['nb_femelles_saillies'] = (int) ($r['f'] ?? 0);

        $r = Database::one(
            "SELECT SUM(m.issue <> 'avortement') nmb, COUNT(DISTINCT CASE WHEN m.issue <> 'avortement' THEN m.animal_id END) fmb,
                    SUM(m.issue = 'avortement') av, SUM(m.nb_nes) nes, SUM(m.nb_vivants) viv
               FROM mises_bas m JOIN animaux a ON a.id = m.animal_id
              WHERE $f AND m.date_mise_bas BETWEEN '$deb' AND '$fin'", $p) ?? [];
        $v['nb_mises_bas'] = (int) ($r['nmb'] ?? 0);
        $v['nb_femelles_mises_bas'] = (int) ($r['fmb'] ?? 0);
        $v['nb_avortements'] = (int) ($r['av'] ?? 0);
        $v['nb_nes'] = (int) ($r['nes'] ?? 0);
        $v['nb_nes_vivants'] = (int) ($r['viv'] ?? 0);
        $v['nb_morts_nes'] = $v['nb_nes'] - $v['nb_nes_vivants'];
        $v['nb_sevres'] = max(0, $v['nb_nes_vivants'] - $v['nb_morts_avant_sevrage']);

        $r = Database::one(
            "SELECT SUM(l.quantite_litres) tot, AVG(l.quantite_litres) moy, COUNT(DISTINCT l.animal_id) nf
               FROM productions_lait l JOIN animaux a ON a.id = l.animal_id
              WHERE $f AND l.date_controle BETWEEN '$deb' AND '$fin'", $p) ?? [];
        $v['lait_total'] = (float) ($r['tot'] ?? 0);
        $v['lait_moyen_controle'] = isset($r['moy']) ? round((float) $r['moy'], 3) : null;
        $v['nb_femelles_traites'] = (int) ($r['nf'] ?? 0);

        $r = Database::one(
            "SELECT AVG(pe.poids_kg) pds, AVG(DATEDIFF(pe.date_pesee, a.date_naissance)) age
               FROM pesees pe JOIN animaux a ON a.id = pe.animal_id
              WHERE $f AND pe.type_pesee = 'sevrage' AND pe.date_pesee BETWEEN '$deb' AND '$fin'", $p) ?? [];
        $v['poids_moyen_sevrage'] = isset($r['pds']) ? round((float) $r['pds'], 3) : null;
        $v['age_moyen_sevrage'] = isset($r['age']) ? round((float) $r['age'], 1) : null;

        $r = Database::one(
            "SELECT COUNT(*) n, SUM(s.cout) c FROM soins s JOIN animaux a ON a.id = s.animal_id
              WHERE $f AND s.date_soin BETWEEN '$deb' AND '$fin'", $p) ?? [];
        $v['nb_soins'] = (int) ($r['n'] ?? 0);
        $v['cout_sanitaire'] = (float) ($r['c'] ?? 0);

        return $v;
    }
}
