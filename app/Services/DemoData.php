<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Ref;

/**
 * Génère un troupeau de démonstration réaliste sur plusieurs années
 * (utilisé par l'installateur web et par database/demo.php).
 */
final class DemoData
{
    public static function generer(int $nbMeres = 120): string
    {
        mt_srand(42);
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        $racesParEspece = [];
        foreach (Ref::races() as $r) $racesParEspece[$r['espece_id']][] = (int) $r['id'];
        $cat = [];
        foreach (Ref::categories() as $c) $cat[$c['code']] = (int) $c['id'];

        $num = 0;
        $mk = function (array $d) use (&$num) {
            $num++;
            $prefix = $d['espece_id'] === 1 ? 'CP' : 'OV';
            $d['numero_identification'] ??= sprintf('%s%05d', $prefix, $num);
            $d += ['nom' => '', 'origine' => 'naissance', 'statut' => 'actif', 'cree_par' => 1];
            $d['date_entree'] ??= $d['date_naissance'];
            $d['categorie_id'] = Ref::autoCategorie($d['espece_id'], $d['sexe'], $d['date_naissance'], (bool) ($d['castre'] ?? 0), $d['date_sortie'] ?? null);
            return Database::insert('animaux', $d);
        };
        $rand = fn(float $a, float $b) => $a + mt_rand() / mt_getrandmax() * ($b - $a);
        $prenoms = ['Blanche', 'Noisette', 'Perle', 'Câline', 'Biscotte', 'Cannelle', 'Étoile', 'Fleur', 'Gazelle', 'Jasmine', 'Lilas', 'Mimosa', 'Olive', 'Praline', 'Safran', 'Tulipe', 'Vanille', 'Zita'];

        $start = (int) date('Y') - 3;
        $males = [];
        $meres = [];
        foreach ([1, 2] as $esp) {
            for ($i = 0; $i < 4; $i++) {
                $males[$esp][] = $mk(['espece_id' => $esp, 'race_id' => $racesParEspece[$esp][array_rand($racesParEspece[$esp])], 'sexe' => 'M',
                    'nom' => ($esp === 1 ? 'Bouc ' : 'Bélier ') . ($i + 1), 'date_naissance' => sprintf('%d-%02d-%02d', $start - 3, mt_rand(1, 12), mt_rand(1, 28)),
                    'origine' => 'achat', 'date_entree' => sprintf('%d-01-15', $start - 1), 'prix_achat' => mt_rand(2500, 6000)]);
            }
            for ($i = 0; $i < intdiv($nbMeres, 2); $i++) {
                $meres[$esp][] = $mk(['espece_id' => $esp, 'race_id' => $racesParEspece[$esp][array_rand($racesParEspece[$esp])], 'sexe' => 'F',
                    'nom' => $prenoms[array_rand($prenoms)], 'date_naissance' => sprintf('%d-%02d-%02d', $start - mt_rand(2, 5), mt_rand(1, 12), mt_rand(1, 28)),
                    'poids_naissance' => round($rand(2.5, 4), 2), 'origine' => 'achat', 'date_entree' => sprintf('%d-01-10', $start - 1), 'prix_achat' => mt_rand(1200, 2500)]);
            }
        }
        // Un mouton castré pour l'exemple
        $mk(['espece_id' => 2, 'sexe' => 'M', 'castre' => 1, 'nom' => 'Mouton', 'date_naissance' => ($start - 2) . '-03-01', 'origine' => 'achat', 'date_entree' => ($start - 1) . '-02-01']);

        $today = date('Y-m-d');
        for ($annee = $start; $annee <= (int) date('Y'); $annee++) {
            foreach ([1, 2] as $esp) {
                foreach ($meres[$esp] as $mid) {
                    $statut = Database::value('SELECT statut FROM animaux WHERE id = ?', [$mid]);
                    if ($statut !== 'actif') continue;
                    $saillie = sprintf('%d-%02d-%02d', $annee - 1, mt_rand(8, 10), mt_rand(1, 28));
                    $male = $males[$esp][array_rand($males[$esp])];
                    $mb = date('Y-m-d', strtotime("$saillie +" . mt_rand(145, 155) . ' days'));
                    $fertile = mt_rand(1, 100) <= 88;
                    $resultat = !$fertile ? 'vide' : ($mb > $today ? 'gestante' : 'mise_bas');
                    $avort = $fertile && $mb <= $today && mt_rand(1, 100) <= 4;
                    if ($avort) $resultat = 'avortement';
                    Database::insert('reproductions', ['animal_id' => $mid, 'male_id' => $male, 'date_saillie' => $saillie, 'methode' => 'naturelle',
                        'date_prevue_mise_bas' => date('Y-m-d', strtotime("$saillie +150 days")), 'resultat' => $resultat, 'cree_par' => 1]);
                    if (!$fertile || $mb > $today) continue;
                    if ($avort) {
                        Database::insert('mises_bas', ['animal_id' => $mid, 'date_mise_bas' => date('Y-m-d', strtotime("$saillie +100 days")), 'issue' => 'avortement', 'nb_nes' => 0, 'nb_vivants' => 0, 'cree_par' => 1]);
                        continue;
                    }
                    $nes = $esp === 1 ? [1, 1, 2, 2, 2, 3][mt_rand(0, 5)] : [1, 1, 1, 2, 2][mt_rand(0, 4)];
                    $viv = $nes - (mt_rand(1, 100) <= 6 ? 1 : 0);
                    $sexes = [];
                    for ($k = 0; $k < $viv; $k++) $sexes[] = mt_rand(0, 1) ? 'M' : 'F';
                    $nbM = count(array_filter($sexes, fn($s) => $s === 'M'));
                    Database::insert('mises_bas', ['animal_id' => $mid, 'date_mise_bas' => $mb, 'issue' => mt_rand(1, 10) === 1 ? 'difficile' : 'normale',
                        'nb_nes' => $nes, 'nb_vivants' => $viv, 'nb_males' => $nbM, 'nb_femelles' => $viv - $nbM, 'cree_par' => 1]);
                    foreach ($sexes as $sx) {
                        $pn = round($rand(2.2, 4.2), 2);
                        $mort = mt_rand(1, 100) <= 7;
                        $dSortie = null;
                        $st = 'actif';
                        if ($mort) { $st = 'mort'; $dSortie = date('Y-m-d', strtotime("$mb +" . mt_rand(1, 80) . ' days')); }
                        elseif ($sx === 'M' && date('Y-m-d', strtotime("$mb +150 days")) < $today && mt_rand(1, 100) <= 80) { $st = 'vendu'; $dSortie = date('Y-m-d', strtotime("$mb +" . mt_rand(120, 200) . ' days')); }
                        if ($dSortie && $dSortie > $today) { $st = 'actif'; $dSortie = null; }
                        $jid = $mk(['espece_id' => $esp, 'race_id' => Database::value('SELECT race_id FROM animaux WHERE id = ?', [$mid]), 'sexe' => $sx,
                            'date_naissance' => $mb, 'poids_naissance' => $pn, 'mere_id' => $mid, 'pere_id' => $male, 'statut' => $st, 'date_sortie' => $dSortie,
                            'motif_sortie' => $st === 'mort' ? 'Mortalité néonatale' : ($st === 'vendu' ? 'Vente boucherie' : null),
                            'prix_vente' => $st === 'vendu' ? mt_rand(900, 1800) : null]);
                        $gmq = $rand(0.09, 0.19);
                        foreach ([30 => 'controle', 90 => 'sevrage', 150 => 'controle'] as $j => $type) {
                            $d = date('Y-m-d', strtotime("$mb +$j days"));
                            if ($d > $today || ($dSortie && $d > $dSortie)) break;
                            Database::insert('pesees', ['animal_id' => $jid, 'date_pesee' => $d, 'poids_kg' => round($pn + $gmq * $j, 2), 'type_pesee' => $type, 'cree_par' => 1]);
                        }
                    }
                    if ($esp === 1) {
                        for ($m = 1; $m <= 8; $m++) {
                            $d = date('Y-m-d', strtotime("$mb +" . ($m * 30) . ' days'));
                            if ($d > $today) break;
                            $q = max(0.3, round($rand(1.2, 3.2) * (1 - $m * 0.06), 2));
                            Database::insert('productions_lait', ['animal_id' => $mid, 'date_controle' => $d, 'quantite_litres' => $q, 'taux_mg' => round($rand(30, 45), 1), 'taux_mp' => round($rand(28, 36), 1), 'cree_par' => 1]);
                        }
                    }
                }
                // Soins annuels du troupeau
                foreach (array_merge($meres[$esp], $males[$esp]) as $aid) {
                    foreach ([['vaccination', 'Enterotoxémie', 15], ['vermifugation', 'Ivermectine', 8]] as [$type, $prod, $cout]) {
                        $d = sprintf('%d-%02d-%02d', $annee, mt_rand(2, 10), mt_rand(1, 28));
                        if ($d > $today) continue;
                        Database::insert('soins', ['animal_id' => $aid, 'date_soin' => $d, 'type_soin' => $type, 'produit' => $prod, 'cout' => $cout, 'veterinaire' => 'Dr Vétérinaire', 'cree_par' => 1]);
                    }
                }
            }
            // Quelques réformes de mères âgées
            foreach (array_slice($meres[1], 0, 2) as $mid) {
                if ($annee === (int) date('Y') - 1) {
                    Database::run("UPDATE animaux SET statut = 'reforme', date_sortie = ?, motif_sortie = 'Réforme (âge)', prix_vente = 1500 WHERE id = ? AND statut = 'actif'", ["$annee-11-15", $mid]);
                }
            }
        }
        Database::run("INSERT INTO animal_historique (animal_id, date_evenement, type, titre, utilisateur_id) SELECT id, date_entree, 'creation', CONCAT('Enregistrement (démo) ', numero_identification), 1 FROM animaux");
        $pdo->commit();
        return 'Démo : ' . Database::value('SELECT COUNT(*) FROM animaux') . ' animaux, ' . Database::value('SELECT COUNT(*) FROM pesees') . ' pesées, '
            . Database::value('SELECT COUNT(*) FROM mises_bas') . " mises bas générés.";
    }
}
