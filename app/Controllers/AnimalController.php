<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\KeysetPaginator;
use App\Core\Ref;
use App\Core\Settings;
use App\Services\Historique;
use App\Services\PdfExporter;

final class AnimalController extends Controller
{
    public const STATUTS = ['actif' => 'Actif', 'vendu' => 'Vendu', 'mort' => 'Mort', 'reforme' => 'Réformé', 'perdu' => 'Perdu'];
    public const ORIGINES = ['naissance' => 'Né sur l\'exploitation', 'achat' => 'Achat', 'don' => 'Don', 'autre' => 'Autre'];

    public const LABELS = [
        'numero_identification' => 'N° identification', 'nom' => 'Nom', 'espece_id' => 'Espèce', 'race_id' => 'Race',
        'categorie_id' => 'Catégorie', 'sexe' => 'Sexe', 'castre' => 'Castré', 'date_naissance' => 'Date de naissance',
        'date_naissance_estimee' => 'Naissance estimée', 'poids_naissance' => 'Poids de naissance', 'mere_id' => 'Mère',
        'pere_id' => 'Père', 'origine' => 'Origine', 'date_entree' => "Date d'entrée", 'prix_achat' => "Prix d'achat",
        'couleur' => 'Couleur / robe', 'statut' => 'Statut', 'date_sortie' => 'Date de sortie', 'motif_sortie' => 'Motif de sortie',
        'prix_vente' => 'Prix de vente', 'observations' => 'Observations',
    ];

    private const SORTS = [
        'id'        => ['a.id', 'id', 'Date de saisie'],
        'numero'    => ['a.numero_identification', 'numero_identification', 'N° identification'],
        'nom'       => ['a.nom', 'nom', 'Nom'],
        'naissance' => ['a.date_naissance', 'date_naissance', 'Date de naissance'],
    ];

    // ------------------------------------------------------------------
    //  Liste + recherche avancée (pagination keyset)
    // ------------------------------------------------------------------
    public function index(): void
    {
        [$where, $params, $f] = $this->filters();
        [$sortCol, $sortKey, $dir] = $this->sort();

        $page = KeysetPaginator::paginate([
            'select' => $this->selectSql(),
            'from'   => $this->fromSql(),
            'where'  => $where,
            'params' => $params,
            'sort'   => ['col' => $sortCol, 'key' => $sortKey, 'dir' => $dir],
            'id'     => ['col' => 'a.id', 'key' => 'id'],
            'limit'  => $this->pageSize(),
            'cursor' => input_str('cursor', 500) ?: null,
        ]);
        $total = KeysetPaginator::count('animaux a', $where, $params, 'animaux');

        $this->render('animaux/index', [
            'page' => $page, 'total' => $total, 'f' => $f, 'sorts' => self::SORTS,
            'especes' => Ref::especes(), 'races' => Ref::races(), 'categories' => Ref::categories(),
        ]);
    }

    public function exportPdf(): void
    {
        [$where, $params, $f] = $this->filters();
        [$sortCol, $sortKey, $dir] = $this->sort();
        $rows = KeysetPaginator::iterate([
            'select' => $this->selectSql(), 'from' => $this->fromSql(), 'where' => $where, 'params' => $params,
            'sort' => ['col' => $sortCol, 'key' => $sortKey, 'dir' => $dir], 'id' => ['col' => 'a.id', 'key' => 'id'],
        ], $this->maxExport());

        $lignes = (function () use ($rows) {
            foreach ($rows as $r) {
                yield [
                    $r['numero_identification'], $r['nom'], $r['espece'], $r['race'], $r['categorie'],
                    $r['sexe'] === 'F' ? 'F' : 'M', fdate($r['date_naissance']), age_label($r['date_naissance'], $r['date_sortie']),
                    $r['mere_numero'], $r['pere_numero'], fnum($r['dernier_poids'], 1), self::STATUTS[$r['statut']] ?? $r['statut'],
                ];
            }
        })();
        PdfExporter::liste('Liste des animaux',
            ['N°', 'Nom', 'Espèce', 'Race', 'Catégorie', 'Sexe', 'Naissance', 'Âge', 'Mère', 'Père', 'Poids (kg)', 'Statut'],
            $lignes, $this->filterLabels($f), 'landscape', input_str('mode') !== 'imprimer');
    }

    private function selectSql(): string
    {
        return "a.id, a.numero_identification, a.nom, a.sexe, a.date_naissance, a.statut, a.date_sortie, a.supprime,
                e.libelle espece, r.libelle race, c.libelle categorie, m.numero_identification mere_numero, p.numero_identification pere_numero,
                (SELECT pe.poids_kg FROM pesees pe WHERE pe.animal_id = a.id ORDER BY pe.date_pesee DESC, pe.id DESC LIMIT 1) dernier_poids";
    }

    private function fromSql(): string
    {
        return 'animaux a
                JOIN especes e ON e.id = a.espece_id
                JOIN categories c ON c.id = a.categorie_id
                LEFT JOIN races r ON r.id = a.race_id
                LEFT JOIN animaux m ON m.id = a.mere_id
                LEFT JOIN animaux p ON p.id = a.pere_id';
    }

    private function sort(): array
    {
        $tri = input_str('tri');
        [$col, $key] = self::SORTS[$tri] ?? self::SORTS['id'];
        $dir = strtolower(input_str('dir')) === 'asc' ? 'ASC' : 'DESC';
        return [$col, $key, $dir];
    }

    /** Construit les conditions de la recherche avancée (toutes paramétrées). */
    private function filters(): array
    {
        $f = [
            'q' => input_str('q', 80), 'texte' => input_str('texte', 100),
            'espece_id' => input_int('espece_id'), 'race_id' => input_int('race_id'), 'categorie_id' => input_int('categorie_id'),
            'sexe' => in_array(input_str('sexe'), ['M', 'F'], true) ? input_str('sexe') : '',
            'statut' => input_str('statut') ?: 'actif',
            'origine' => array_key_exists(input_str('origine'), self::ORIGINES) ? input_str('origine') : '',
            'naissance_du' => valid_date(input_str('naissance_du')) ? input_str('naissance_du') : '',
            'naissance_au' => valid_date(input_str('naissance_au')) ? input_str('naissance_au') : '',
            'age_min' => input_int('age_min'), 'age_max' => input_int('age_max'),
            'mere' => input_str('mere', 40), 'pere' => input_str('pere', 40),
            'corbeille' => input_int('corbeille') === 1 && can('animaux.supprimer') ? 1 : 0,
        ];
        $w = ['a.supprime = :sup'];
        $p = ['sup' => $f['corbeille']];

        if ($f['q'] !== '') {
            // Recherche par préfixe : exploite les index (numero) et (nom)
            $w[] = '(a.numero_identification LIKE :q1 OR a.nom LIKE :q2)';
            $like = addcslashes($f['q'], '%_\\') . '%';
            $p['q1'] = $like;
            $p['q2'] = $like;
        }
        if ($f['texte'] !== '') {
            $w[] = 'MATCH(a.nom, a.observations) AGAINST (:ft IN BOOLEAN MODE)';
            $p['ft'] = implode(' ', array_map(fn($t) => '+' . preg_replace('/[^\p{L}\p{N}_-]/u', '', $t) . '*', preg_split('/\s+/', $f['texte'])));
        }
        foreach (['espece_id', 'race_id', 'categorie_id'] as $k) {
            if ($f[$k]) {
                $w[] = "a.$k = :$k";
                $p[$k] = $f[$k];
            }
        }
        if ($f['sexe']) { $w[] = 'a.sexe = :sexe'; $p['sexe'] = $f['sexe']; }
        if ($f['statut'] !== 'tous') {
            if (!isset(self::STATUTS[$f['statut']])) $f['statut'] = 'actif';
            $w[] = 'a.statut = :statut';
            $p['statut'] = $f['statut'];
        }
        if ($f['origine']) { $w[] = 'a.origine = :origine'; $p['origine'] = $f['origine']; }
        if ($f['naissance_du']) { $w[] = 'a.date_naissance >= :nd'; $p['nd'] = $f['naissance_du']; }
        if ($f['naissance_au']) { $w[] = 'a.date_naissance <= :na'; $p['na'] = $f['naissance_au']; }
        if ($f['age_min'] !== null) { $w[] = 'a.date_naissance <= :amin'; $p['amin'] = date('Y-m-d', strtotime("-{$f['age_min']} months")); }
        if ($f['age_max'] !== null) { $w[] = 'a.date_naissance > :amax'; $p['amax'] = date('Y-m-d', strtotime('-' . ($f['age_max'] + 1) . ' months')); }
        if ($f['mere'] !== '') { $w[] = 'a.mere_id = (SELECT id FROM animaux WHERE numero_identification = :mere)'; $p['mere'] = $f['mere']; }
        if ($f['pere'] !== '') { $w[] = 'a.pere_id = (SELECT id FROM animaux WHERE numero_identification = :pere)'; $p['pere'] = $f['pere']; }

        return [$w, $p, $f];
    }

    private function filterLabels(array $f): array
    {
        return [
            'Recherche' => $f['q'], 'Texte' => $f['texte'],
            'Espèce' => $f['espece_id'] ? (Ref::especes()[$f['espece_id']]['libelle'] ?? '') : '',
            'Race' => $f['race_id'] ? (Ref::races()[$f['race_id']]['libelle'] ?? '') : '',
            'Catégorie' => $f['categorie_id'] ? (Ref::categories()[$f['categorie_id']]['libelle'] ?? '') : '',
            'Sexe' => $f['sexe'], 'Statut' => $f['statut'] === 'tous' ? 'Tous' : (self::STATUTS[$f['statut']] ?? ''),
            'Origine' => $f['origine'] ? self::ORIGINES[$f['origine']] : '',
            'Né du' => fdate($f['naissance_du']), 'Né au' => fdate($f['naissance_au']),
            'Âge min (mois)' => $f['age_min'], 'Âge max (mois)' => $f['age_max'], 'Mère' => $f['mere'], 'Père' => $f['pere'],
            'Corbeille' => $f['corbeille'] ? 'Oui' : '',
        ];
    }

    /** Autocomplétion (JSON) pour sélectionner mère / père / animal. */
    public function autocomplete(): void
    {
        $q = input_str('q', 40);
        $sexe = input_str('sexe');
        $where = "supprime = 0 AND (numero_identification LIKE :q1 OR nom LIKE :q2)";
        $params = ['q1' => addcslashes($q, '%_\\') . '%', 'q2' => addcslashes($q, '%_\\') . '%'];
        if (in_array($sexe, ['M', 'F'], true)) {
            $where .= ' AND sexe = :s';
            $params['s'] = $sexe;
        }
        if ($esp = input_int('espece_id')) {
            $where .= ' AND espece_id = :e';
            $params['e'] = $esp;
        }
        $rows = $q === '' ? [] : \App\Core\Database::all(
            "SELECT id, numero_identification, nom, sexe, statut FROM animaux WHERE $where ORDER BY numero_identification LIMIT 15", $params);
        $this->json(['results' => $rows]);
    }

    // ------------------------------------------------------------------
    //  Fiche animal
    // ------------------------------------------------------------------
    public function show(): void
    {
        $data = $this->ficheData((int) input_int('id', 0));
        $this->render('animaux/show', $data);
    }

    public function fichePdf(): void
    {
        $data = $this->ficheData((int) input_int('id', 0));
        PdfExporter::output('animal_fiche', $data, 'Fiche_' . $data['a']['numero_identification'], 'portrait', input_str('mode') !== 'imprimer');
    }

    private function ficheData(int $id): array
    {
        $a = Database::one(
            "SELECT a.*, e.libelle espece, e.code espece_code, r.libelle race, c.libelle categorie,
                    m.numero_identification mere_numero, m.nom mere_nom, p.numero_identification pere_numero, p.nom pere_nom,
                    uc.nom_complet cree_par_nom, um.nom_complet modifie_par_nom
               FROM animaux a
               JOIN especes e ON e.id = a.espece_id JOIN categories c ON c.id = a.categorie_id
               LEFT JOIN races r ON r.id = a.race_id
               LEFT JOIN animaux m ON m.id = a.mere_id LEFT JOIN animaux p ON p.id = a.pere_id
               LEFT JOIN utilisateurs uc ON uc.id = a.cree_par LEFT JOIN utilisateurs um ON um.id = a.modifie_par
              WHERE a.id = ?", [$id]);
        if (!$a || ((int) $a['supprime'] === 1 && !can('animaux.supprimer'))) {
            http_response_code(404);
            $this->render('partials/erreur', ['titre' => 'Animal introuvable', 'message' => "Cet animal n'existe pas ou a été supprimé."]);
            exit;
        }
        $ev = [];
        $ev['pesee'] = Database::all('SELECT * FROM pesees WHERE animal_id = ? ORDER BY date_pesee DESC, id DESC LIMIT 200', [$id]);
        $ev['reproduction'] = Database::all(
            'SELECT r.*, m.numero_identification male_id_numero FROM reproductions r LEFT JOIN animaux m ON m.id = r.male_id
              WHERE r.animal_id = ? ORDER BY r.date_saillie DESC, r.id DESC LIMIT 100', [$id]);
        $ev['mise_bas'] = Database::all('SELECT * FROM mises_bas WHERE animal_id = ? ORDER BY date_mise_bas DESC, id DESC LIMIT 100', [$id]);
        $ev['lait'] = Database::all('SELECT * FROM productions_lait WHERE animal_id = ? ORDER BY date_controle DESC, id DESC LIMIT 200', [$id]);
        $ev['soin'] = Database::all('SELECT * FROM soins WHERE animal_id = ? ORDER BY date_soin DESC, id DESC LIMIT 200', [$id]);
        $descendants = Database::all(
            'SELECT id, numero_identification, nom, sexe, date_naissance, statut FROM animaux
              WHERE supprime = 0 AND (mere_id = ? OR pere_id = ?) ORDER BY date_naissance DESC LIMIT 200', [$id, $id]);
        $historique = Database::all(
            'SELECT h.*, u.nom_complet utilisateur FROM animal_historique h LEFT JOIN utilisateurs u ON u.id = h.utilisateur_id
              WHERE h.animal_id = ? ORDER BY h.date_evenement DESC, h.id DESC LIMIT 300', [$id]);
        $indices = Database::all(
            "SELECT r.*, i.nom, i.unite, i.decimales, i.code FROM indicateur_resultats r JOIN indicateurs i ON i.id = r.indicateur_id
              WHERE r.cible_type = 'animal' AND r.cible_id = ? ORDER BY r.annee, i.ordre", [$id]);
        return ['a' => $a, 'ev' => $ev, 'descendants' => $descendants, 'historique' => $historique, 'indices' => $indices];
    }

    // ------------------------------------------------------------------
    //  Création / modification
    // ------------------------------------------------------------------
    public function create(): void
    {
        $this->form(null);
    }

    public function edit(): void
    {
        $a = Database::one('SELECT * FROM animaux WHERE id = ? AND supprime = 0', [(int) input_int('id', 0)]);
        if (!$a) {
            flash('danger', 'Animal introuvable.');
            redirect(url('animaux'));
        }
        $this->form($a);
    }

    private function form(?array $a): void
    {
        $errors = [];
        $values = $a ?? [
            'espece_id' => input_int('espece_id') ?? 1, 'sexe' => input_str('sexe') ?: 'F', 'origine' => 'naissance', 'statut' => 'actif',
            'date_naissance' => date('Y-m-d'), 'date_entree' => '', 'mere_id' => input_int('mere_id'), 'pere_id' => input_int('pere_id'),
        ];
        if ($this->isPost()) {
            [$data, $errors] = $this->validate($a);
            if (!$errors) {
                $id = $a ? $this->doUpdate($a, $data) : $this->doCreate($data);
                flash('success', $a ? 'Animal modifié.' : 'Animal enregistré.');
                redirect(url('animaux/voir', ['id' => $id]));
            }
            $values = array_merge($values, $_POST);
        }
        // Libellés mère / père pour l'affichage
        foreach (['mere_id' => 'mere_numero', 'pere_id' => 'pere_numero'] as $k => $lbl) {
            $values[$lbl] = !empty($values[$k]) ? Database::value('SELECT numero_identification FROM animaux WHERE id = ?', [(int) $values[$k]]) : '';
        }
        $this->render('animaux/form', [
            'a' => $a, 'v' => $values, 'errors' => $errors,
            'especes' => Ref::especes(), 'races' => Ref::races(), 'categories' => Ref::categories(),
        ]);
    }

    private function validate(?array $old): array
    {
        $e = [];
        $d = [
            'numero_identification' => input_str('numero_identification', 40),
            'nom'                   => input_str('nom', 80),
            'espece_id'             => input_int('espece_id'),
            'race_id'               => input_int('race_id'),
            'categorie_id'          => input_int('categorie_id'),
            'sexe'                  => input_str('sexe'),
            'castre'                => input_int('castre') ? 1 : 0,
            'date_naissance'        => input_str('date_naissance'),
            'date_naissance_estimee'=> input_int('date_naissance_estimee') ? 1 : 0,
            'poids_naissance'       => input_str('poids_naissance') !== '' ? str_replace(',', '.', input_str('poids_naissance')) : null,
            'mere_id'               => input_int('mere_id'),
            'pere_id'               => input_int('pere_id'),
            'origine'               => input_str('origine'),
            'date_entree'           => input_str('date_entree'),
            'prix_achat'            => input_str('prix_achat') !== '' ? str_replace(',', '.', input_str('prix_achat')) : null,
            'couleur'               => input_str('couleur', 60) ?: null,
            'statut'                => input_str('statut') ?: 'actif',
            'date_sortie'           => input_str('date_sortie') ?: null,
            'motif_sortie'          => input_str('motif_sortie') ?: null,
            'prix_vente'            => input_str('prix_vente') !== '' ? str_replace(',', '.', input_str('prix_vente')) : null,
            'observations'          => input_str('observations', 5000) ?: null,
        ];
        if (!preg_match('/^[\p{L}\p{N}\-_.\/ ]{1,40}$/u', $d['numero_identification'])) {
            $e['numero_identification'] = 'Numéro obligatoire (lettres, chiffres, - _ . /).';
        } elseif (Database::value('SELECT id FROM animaux WHERE numero_identification = ? AND id <> ?', [$d['numero_identification'], $old['id'] ?? 0])) {
            $e['numero_identification'] = 'Ce numéro est déjà attribué.';
        }
        if (!isset(Ref::especes()[$d['espece_id']])) $e['espece_id'] = 'Espèce invalide.';
        if ($d['race_id'] && (int) (Ref::races()[$d['race_id']]['espece_id'] ?? 0) !== $d['espece_id']) $e['race_id'] = "Race incompatible avec l'espèce.";
        if (!in_array($d['sexe'], ['M', 'F'], true)) $e['sexe'] = 'Sexe invalide.';
        if ($d['sexe'] === 'F') $d['castre'] = 0;
        if (!valid_date($d['date_naissance']) || $d['date_naissance'] > date('Y-m-d')) $e['date_naissance'] = 'Date de naissance invalide.';
        if (!isset(self::ORIGINES[$d['origine']])) $e['origine'] = 'Origine invalide.';
        if ($d['date_entree'] === '' && valid_date($d['date_naissance'])) $d['date_entree'] = $d['date_naissance'];
        if (!valid_date($d['date_entree'])) $e['date_entree'] = "Date d'entrée invalide.";
        elseif (valid_date($d['date_naissance']) && $d['date_entree'] < $d['date_naissance']) $e['date_entree'] = "La date d'entrée précède la naissance.";
        foreach (['poids_naissance' => [0, 20], 'prix_achat' => [0, 1e8], 'prix_vente' => [0, 1e8]] as $k => [$lo, $hi]) {
            if ($d[$k] !== null && (!is_numeric($d[$k]) || $d[$k] < $lo || $d[$k] > $hi)) $e[$k] = 'Valeur numérique invalide.';
        }
        if (!isset(self::STATUTS[$d['statut']])) $e['statut'] = 'Statut invalide.';
        if ($d['statut'] !== 'actif') {
            if (!valid_date((string) $d['date_sortie'])) $e['date_sortie'] = 'Date de sortie obligatoire pour un animal sorti.';
            elseif ($d['date_sortie'] < $d['date_entree']) $e['date_sortie'] = "La sortie précède l'entrée.";
        } else {
            $d['date_sortie'] = null;
            $d['prix_vente'] = null;
            $d['motif_sortie'] = null;
        }
        // Parents : sexe, espèce, antériorité
        foreach (['mere_id' => 'F', 'pere_id' => 'M'] as $k => $sx) {
            if (!$d[$k]) { $d[$k] = null; continue; }
            $par = Database::one('SELECT id, sexe, espece_id, date_naissance FROM animaux WHERE id = ? AND supprime = 0', [$d[$k]]);
            if (!$par || $par['sexe'] !== $sx) $e[$k] = $sx === 'F' ? 'La mère doit être une femelle existante.' : 'Le père doit être un mâle existant.';
            elseif ((int) $par['espece_id'] !== $d['espece_id']) $e[$k] = "Le parent doit être de la même espèce.";
            elseif ($old && (int) $par['id'] === (int) $old['id']) $e[$k] = "Un animal ne peut être son propre parent.";
            elseif (valid_date($d['date_naissance']) && $par['date_naissance'] >= $d['date_naissance']) $e[$k] = 'Le parent doit être né avant le descendant.';
        }
        // Catégorie : automatique si vide
        if (!$e) {
            if (!$d['categorie_id']) {
                $d['categorie_id'] = Ref::autoCategorie($d['espece_id'], $d['sexe'], $d['date_naissance'], (bool) $d['castre'], $d['date_sortie']);
            }
            $cat = Ref::categories()[$d['categorie_id']] ?? null;
            if (!$cat || (int) $cat['espece_id'] !== $d['espece_id'] || $cat['sexe'] !== $d['sexe']) {
                $e['categorie_id'] = "Catégorie incompatible avec l'espèce ou le sexe.";
            }
        }
        if ($e) {
            flash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }
        return [$d, $e];
    }

    private function doCreate(array $d): int
    {
        return Database::transaction(function () use ($d) {
            $d['cree_par'] = Auth::id();
            $id = Database::insert('animaux', $d);
            Historique::add($id, 'creation', 'Enregistrement de l\'animal ' . $d['numero_identification'], ['origine' => $d['origine']], $d['date_entree']);
            if ($d['statut'] !== 'actif') {
                Historique::add($id, 'statut', 'Statut : ' . self::STATUTS[$d['statut']], null, $d['date_sortie']);
            }
            foreach (['mere_id' => 'Mère', 'pere_id' => 'Père'] as $k => $l) {
                if ($d[$k]) {
                    Historique::add((int) $d[$k], 'naissance', 'Nouveau descendant : ' . $d['numero_identification'], ['descendant_id' => $id], $d['date_naissance']);
                }
            }
            Audit::log('creation', 'animal', $id, 'Création de l\'animal ' . $d['numero_identification'], null, $d);
            $this->invalidate();
            return $id;
        });
    }

    private function doUpdate(array $old, array $d): int
    {
        $id = (int) $old['id'];
        [$avant, $apres] = Audit::diff($old, $d);
        if (!$apres) {
            return $id;
        }
        Database::transaction(function () use ($id, $d, $avant, $apres, $old) {
            $d['modifie_par'] = Auth::id();
            Database::update('animaux', $d, $id);
            $champs = array_map(fn($k) => self::LABELS[$k] ?? $k, array_keys($apres));
            Historique::add($id, 'modification', 'Modification : ' . implode(', ', $champs), ['avant' => $avant, 'apres' => $apres]);
            if (isset($apres['statut'])) {
                Historique::add($id, 'statut', 'Statut : ' . (self::STATUTS[$old['statut']] ?? $old['statut']) . ' → ' . self::STATUTS[$d['statut']],
                    ['motif' => $d['motif_sortie']], $d['date_sortie'] ?? date('Y-m-d'));
            }
            Audit::log('modification', 'animal', $id, 'Modification de l\'animal ' . $d['numero_identification'], $avant, $apres);
        });
        $this->invalidate();
        return $id;
    }

    public function delete(): void
    {
        $id = (int) input_int('id', 0);
        $a = Database::one('SELECT id, numero_identification FROM animaux WHERE id = ? AND supprime = 0', [$id]);
        if ($a) {
            Database::run('UPDATE animaux SET supprime = 1, modifie_par = ? WHERE id = ?', [Auth::id(), $id]);
            Historique::add($id, 'suppression', 'Animal placé dans la corbeille');
            Audit::log('suppression', 'animal', $id, 'Suppression (corbeille) de l\'animal ' . $a['numero_identification']);
            $this->invalidate();
            flash('success', 'Animal ' . $a['numero_identification'] . ' supprimé (restaurable depuis la corbeille).');
        }
        redirect(url('animaux'));
    }

    public function restore(): void
    {
        $id = (int) input_int('id', 0);
        $a = Database::one('SELECT id, numero_identification FROM animaux WHERE id = ? AND supprime = 1', [$id]);
        if ($a) {
            Database::run('UPDATE animaux SET supprime = 0, modifie_par = ? WHERE id = ?', [Auth::id(), $id]);
            Historique::add($id, 'restauration', 'Animal restauré depuis la corbeille');
            Audit::log('restauration', 'animal', $id, 'Restauration de l\'animal ' . $a['numero_identification']);
            $this->invalidate();
            flash('success', 'Animal restauré.');
        }
        redirect(url('animaux/voir', ['id' => $id]));
    }

    private function invalidate(): void
    {
        Cache::flushGroup('animaux');
        Cache::flushGroup('dashboard');
    }
}
