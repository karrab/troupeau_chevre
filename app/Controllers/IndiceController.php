<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\KeysetPaginator;
use App\Core\Ref;
use App\Services\FormulaEngine;
use App\Services\FormulaException;
use App\Services\IndicatorService;
use App\Services\PdfExporter;

final class IndiceController extends Controller
{
    public const CIBLES = ['troupeau' => 'Troupeau', 'espece' => 'Espèce', 'race' => 'Race', 'animal' => 'Animal'];

    // ------------------------------------------------------------------
    //  Résultats enregistrés
    // ------------------------------------------------------------------
    public function index(): void
    {
        [$q, $f] = $this->query();
        $q['limit'] = $this->pageSize();
        $q['cursor'] = input_str('cursor', 500) ?: null;
        $page = KeysetPaginator::paginate($q);
        $total = KeysetPaginator::count($q['from'], $q['where'], $q['params'], 'indic_res');

        // Synthèse pluriannuelle (tableau croisé indices × années) pour la cible choisie
        $synthese = null;
        if ($f['cible_type'] !== 'animal') {
            $cid = $f['cible_type'] === 'troupeau' ? 0 : (int) $f['cible_id'];
            $rows = Cache::remember("synth:{$f['cible_type']}:$cid", fn() => Database::all(
                "SELECT r.indicateur_id, r.annee, r.valeur, r.niveau, r.interpretation, i.nom, i.unite, i.decimales
                   FROM indicateur_resultats r JOIN indicateurs i ON i.id = r.indicateur_id
                  WHERE r.cible_type = ? AND r.cible_id = ? AND r.annee > 0
                  ORDER BY i.ordre, r.annee", [$f['cible_type'], $cid]), 300, 'indic_res');
            $synthese = ['annees' => [], 'lignes' => []];
            foreach ($rows as $r) {
                $synthese['annees'][$r['annee']] = true;
                $synthese['lignes'][$r['indicateur_id']]['ind'] = $r;
                $synthese['lignes'][$r['indicateur_id']]['val'][$r['annee']] = $r;
            }
            $synthese['annees'] = array_keys($synthese['annees']);
            sort($synthese['annees']);
            $synthese['annees'] = array_slice($synthese['annees'], -6);
        }
        $this->render('indices/index', [
            'page' => $page, 'total' => $total, 'f' => $f, 'synthese' => $synthese,
            'indicateurs' => IndicatorService::indicateurs(null, false), 'especes' => Ref::especes(), 'races' => Ref::races(),
            'annees' => $this->anneesDisponibles(),
        ]);
    }

    public function exportPdf(): void
    {
        [$q, $f] = $this->query();
        $rows = (function () use ($q) {
            foreach (KeysetPaginator::iterate($q, $this->maxExport()) as $r) {
                yield [
                    $r['nom'], $r['cible_libelle'], $r['annee'] ? $r['annee'] : 'Carrière',
                    $r['valeur'] === null ? 'N/C' : fnum($r['valeur'], (int) $r['decimales']) . ' ' . $r['unite'],
                    ['bon' => 'Bon', 'moyen' => 'Moyen', 'faible' => 'Faible', 'na' => 'N/C'][$r['niveau']],
                    $r['interpretation'], fdate($r['calcule_le'], true),
                ];
            }
        })();
        PdfExporter::liste('Indices calculés', ['Indice', 'Cible', 'Année', 'Valeur', 'Niveau', 'Interprétation', 'Calculé le'], $rows, [
            'Cible' => self::CIBLES[$f['cible_type']] ?? '', 'Année' => $f['annee'] === 0 ? 'Carrière' : $f['annee'],
            'Niveau' => $f['niveau'], 'Animal' => $f['animal'],
        ], 'landscape', input_str('mode') !== 'imprimer');
    }

    private function query(): array
    {
        $f = [
            'cible_type' => array_key_exists(input_str('cible_type'), self::CIBLES) ? input_str('cible_type') : 'troupeau',
            'cible_id' => input_int('cible_id'),
            'annee' => input_int('annee'),
            'indicateur_id' => input_int('indicateur_id'),
            'niveau' => in_array(input_str('niveau'), ['bon', 'moyen', 'faible', 'na'], true) ? input_str('niveau') : '',
            'animal' => input_str('animal', 40),
        ];
        $w = ['r.cible_type = :ct'];
        $p = ['ct' => $f['cible_type']];
        if ($f['cible_type'] === 'troupeau') {
            $w[] = 'r.cible_id = 0';
        } elseif ($f['cible_id'] && $f['cible_type'] !== 'animal') {
            $w[] = 'r.cible_id = :cid';
            $p['cid'] = $f['cible_id'];
        }
        if ($f['annee'] !== null) { $w[] = 'r.annee = :an'; $p['an'] = $f['annee']; }
        if ($f['indicateur_id']) { $w[] = 'r.indicateur_id = :ind'; $p['ind'] = $f['indicateur_id']; }
        if ($f['niveau']) { $w[] = 'r.niveau = :niv'; $p['niv'] = $f['niveau']; }
        if ($f['cible_type'] === 'animal' && $f['animal'] !== '') {
            $w[] = 'an.numero_identification LIKE :num';
            $p['num'] = addcslashes($f['animal'], '%_\\') . '%';
        }
        $select = "r.*, i.nom, i.unite, i.decimales, i.code,
                   CASE r.cible_type WHEN 'troupeau' THEN 'Troupeau' WHEN 'espece' THEN es.libelle WHEN 'race' THEN ra.libelle
                        ELSE CONCAT(an.numero_identification, ' ', an.nom) END cible_libelle";
        $from = "indicateur_resultats r JOIN indicateurs i ON i.id = r.indicateur_id
                 LEFT JOIN especes es ON r.cible_type = 'espece' AND es.id = r.cible_id
                 LEFT JOIN races ra ON r.cible_type = 'race' AND ra.id = r.cible_id
                 LEFT JOIN animaux an ON r.cible_type = 'animal' AND an.id = r.cible_id";
        return [[
            'select' => $select, 'from' => $from, 'where' => $w, 'params' => $p,
            'sort' => ['col' => 'r.id', 'key' => 'id', 'dir' => 'DESC'], 'id' => ['col' => 'r.id', 'key' => 'id'],
        ], $f];
    }

    private function anneesDisponibles(): array
    {
        $min = (int) (Database::value('SELECT YEAR(MIN(date_naissance)) FROM animaux WHERE supprime = 0') ?: date('Y'));
        return range((int) date('Y'), max($min, (int) date('Y') - 30));
    }

    // ------------------------------------------------------------------
    //  Lancement du calcul
    // ------------------------------------------------------------------
    public function calculer(): void
    {
        if ($this->isPost()) {
            @set_time_limit(600);
            $portee = input_str('portee') === 'animal' ? 'animal' : 'annee';
            try {
                if ($portee === 'annee') {
                    $de = input_int('annee_de', (int) date('Y'));
                    $a = input_int('annee_a', (int) date('Y'));
                    [$de, $a] = [min($de, $a), max($de, $a)];
                    $annees = range(max(1990, $de), min((int) date('Y'), $a));
                    $niveaux = array_values(array_intersect((array) ($_POST['niveaux'] ?? []), ['troupeau', 'espece', 'race'])) ?: ['troupeau'];
                    $s = IndicatorService::calculerAnnees($annees, $niveaux);
                    $msg = sprintf('%d résultat(s) calculé(s) pour %d cible(s)/année en %ss (%d non calculable(s)).', $s['resultats'], $s['cibles'], $s['duree'], $s['non_calculables']);
                    Audit::log('calcul_indices', 'indicateur', null, "Calcul des indices annuels " . implode(',', $annees) . ' (' . implode(',', $niveaux) . ')', null, $s);
                    flash('success', $msg);
                    redirect(url('indices', ['cible_type' => $niveaux[0], 'annee' => end($annees)]));
                } else {
                    $annee = input_int('annee_animal', 0);
                    $filtre = array_filter([
                        'animal_id' => input_int('animal_id'),
                        'espece_id' => input_int('espece_id'),
                        'tous' => input_int('tous') === 1,
                    ]);
                    $s = IndicatorService::calculerAnimaux($annee, $filtre);
                    Audit::log('calcul_indices', 'indicateur', $filtre['animal_id'] ?? null, 'Calcul des indices par animal (' . ($annee ?: 'carrière') . ')', null, $s);
                    flash('success', sprintf('%d résultat(s) pour %d animal(aux) en %ss (%d non calculable(s)).', $s['resultats'], $s['animaux'], $s['duree'], $s['non_calculables']));
                    if (!empty($filtre['animal_id'])) {
                        redirect(url('animaux/voir', ['id' => $filtre['animal_id'], 'onglet' => 'indices']));
                    }
                    redirect(url('indices', ['cible_type' => 'animal', 'annee' => $annee]));
                }
            } catch (FormulaException $e) {
                flash('danger', 'Erreur dans une formule : ' . $e->getMessage());
            }
        }
        $this->render('indices/calculer', ['especes' => Ref::especes(), 'annees' => $this->anneesDisponibles()]);
    }

    // ------------------------------------------------------------------
    //  Paramétrage des formules
    // ------------------------------------------------------------------
    public function formules(): void
    {
        $historique = Database::all(
            'SELECT h.*, i.nom, u.nom_complet FROM indicateur_formules_historique h JOIN indicateurs i ON i.id = h.indicateur_id
               LEFT JOIN utilisateurs u ON u.id = h.modifie_par ORDER BY h.id DESC LIMIT 30');
        $this->render('indices/formules', [
            'indicateurs' => IndicatorService::indicateurs(null, false), 'historique' => $historique,
        ]);
    }

    public function editFormule(): void
    {
        $id = input_int('id');
        $ind = $id ? Database::one('SELECT * FROM indicateurs WHERE id = ?', [$id]) : null;
        if ($id && !$ind) {
            flash('danger', 'Indice introuvable.');
            redirect(url('indices/formules'));
        }
        $errors = [];
        $v = $ind ?? ['portee' => input_str('portee') === 'animal' ? 'animal' : 'annee', 'sens' => 'haut', 'decimales' => 2, 'actif' => 1, 'ordre' => 100];
        if ($this->isPost()) {
            $d = [
                'code' => strtoupper(input_str('code', 40)), 'nom' => input_str('nom', 120), 'description' => input_str('description', 500) ?: null,
                'portee' => input_str('portee') === 'animal' ? 'animal' : 'annee',
                'sexe_cible' => in_array(input_str('sexe_cible'), ['M', 'F'], true) ? input_str('sexe_cible') : null,
                'formule' => input_str('formule', 1000), 'unite' => input_str('unite', 20) ?: null,
                'decimales' => max(0, min(4, (int) input_int('decimales', 2))),
                'sens' => in_array(input_str('sens'), ['haut', 'bas', 'intervalle'], true) ? input_str('sens') : 'haut',
                'seuil_bas' => is_numeric(str_replace(',', '.', input_str('seuil_bas'))) ? str_replace(',', '.', input_str('seuil_bas')) : null,
                'seuil_haut' => is_numeric(str_replace(',', '.', input_str('seuil_haut'))) ? str_replace(',', '.', input_str('seuil_haut')) : null,
                'texte_bon' => input_str('texte_bon') ?: null, 'texte_moyen' => input_str('texte_moyen') ?: null, 'texte_faible' => input_str('texte_faible') ?: null,
                'actif' => input_int('actif') ? 1 : 0, 'ordre' => (int) input_int('ordre', 100),
            ];
            if ($d['portee'] === 'annee') $d['sexe_cible'] = null;
            if (!preg_match('/^[A-Z][A-Z0-9_]{1,39}$/', $d['code'])) $errors['code'] = 'Code : majuscules, chiffres et _ (ex : TAUX_SEVRAGE).';
            elseif (Database::value('SELECT id FROM indicateurs WHERE code = ? AND id <> ?', [$d['code'], $id ?? 0])) $errors['code'] = 'Code déjà utilisé.';
            if ($d['nom'] === '') $errors['nom'] = 'Nom obligatoire.';
            if ($err = FormulaEngine::validate($d['formule'], array_keys(IndicatorService::varsFor($d['portee'])))) $errors['formule'] = $err;
            if ($d['seuil_bas'] !== null && $d['seuil_haut'] !== null && (float) $d['seuil_bas'] > (float) $d['seuil_haut']) {
                $errors['seuil_bas'] = 'Le seuil bas doit être inférieur ou égal au seuil haut.';
            }
            if (!$errors) {
                Database::transaction(function () use ($d, $ind, &$id) {
                    if ($ind) {
                        [$avant, $apres] = Audit::diff($ind, $d);
                        Database::update('indicateurs', $d, (int) $ind['id']);
                        if ($ind['formule'] !== $d['formule']) {
                            Database::insert('indicateur_formules_historique', [
                                'indicateur_id' => $ind['id'], 'ancienne_formule' => $ind['formule'],
                                'nouvelle_formule' => $d['formule'], 'modifie_par' => Auth::id(),
                            ]);
                        }
                        Audit::log('modification', 'indicateur', (int) $ind['id'], 'Modification de l\'indice ' . $d['code'], $avant, $apres);
                    } else {
                        $id = Database::insert('indicateurs', $d);
                        Database::insert('indicateur_formules_historique', [
                            'indicateur_id' => $id, 'ancienne_formule' => null, 'nouvelle_formule' => $d['formule'], 'modifie_par' => Auth::id(),
                        ]);
                        Audit::log('creation', 'indicateur', $id, 'Création de l\'indice ' . $d['code'], null, $d);
                    }
                });
                Cache::flushGroup('indic');
                flash('success', 'Indice enregistré. Relancez le calcul pour mettre à jour les résultats.');
                redirect(url('indices/formules'));
            }
            $v = array_merge($v, $d);
            flash('danger', 'Veuillez corriger les erreurs.');
        }
        $this->render('indices/formule', [
            'ind' => $ind, 'v' => $v, 'errors' => $errors,
            'varsAnimal' => IndicatorService::ANIMAL_VARS, 'varsAnnee' => IndicatorService::ANNEE_VARS,
            'fonctions' => FormulaEngine::FUNCTIONS, 'especes' => Ref::especes(),
        ]);
    }

    public function deleteFormule(): void
    {
        $ind = Database::one('SELECT * FROM indicateurs WHERE id = ?', [(int) input_int('id', 0)]);
        if ($ind) {
            Database::run('DELETE FROM indicateurs WHERE id = ?', [$ind['id']]);
            Audit::log('suppression', 'indicateur', (int) $ind['id'], "Suppression de l'indice " . $ind['code'], $ind, null);
            Cache::flushGroup('indic');
            Cache::flushGroup('indic_res');
            flash('success', 'Indice supprimé (et ses résultats).');
        }
        redirect(url('indices/formules'));
    }

    /** Test AJAX d'une formule sur un cas réel, sans enregistrer. */
    public function tester(): void
    {
        $portee = input_str('portee') === 'animal' ? 'animal' : 'annee';
        $formule = input_str('formule', 1000);
        if ($err = FormulaEngine::validate($formule, array_keys(IndicatorService::varsFor($portee)))) {
            $this->json(['ok' => false, 'erreur' => $err]);
        }
        try {
            if ($portee === 'animal') {
                $num = input_str('animal', 40);
                $aid = (int) Database::value('SELECT id FROM animaux WHERE numero_identification = ? AND supprime = 0', [$num]);
                if (!$aid) $this->json(['ok' => false, 'erreur' => "Animal « $num » introuvable."]);
                $res = IndicatorService::tester($formule, 'animal', (int) input_int('annee', 0), 'animal', $aid);
            } else {
                $ct = in_array(input_str('cible_type'), ['troupeau', 'espece'], true) ? input_str('cible_type') : 'troupeau';
                $res = IndicatorService::tester($formule, 'annee', (int) input_int('annee', (int) date('Y')), $ct, (int) input_int('cible_id', 0));
            }
        } catch (FormulaException $e) {
            $this->json(['ok' => false, 'erreur' => $e->getMessage()]);
        }
        $ind = ['seuil_bas' => input_str('seuil_bas') !== '' ? (float) str_replace(',', '.', input_str('seuil_bas')) : null,
                'seuil_haut' => input_str('seuil_haut') !== '' ? (float) str_replace(',', '.', input_str('seuil_haut')) : null,
                'sens' => input_str('sens') ?: 'haut', 'decimales' => (int) input_int('decimales', 2), 'unite' => input_str('unite'),
                'nom' => input_str('nom') ?: 'Indice', 'texte_bon' => input_str('texte_bon'), 'texte_moyen' => input_str('texte_moyen'),
                'texte_faible' => input_str('texte_faible')];
        [$niveau, $txt] = IndicatorService::interpret($ind, $res['valeur'], $res['variables']);
        $this->json(['ok' => true, 'valeur' => $res['valeur'], 'niveau' => $niveau, 'interpretation' => $txt, 'variables' => $res['variables']]);
    }
}
