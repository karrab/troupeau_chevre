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
use App\Services\EvenementTypes;
use App\Services\Historique;
use App\Services\PdfExporter;

final class EvenementController extends Controller
{
    private function type(): array
    {
        $code = input_str('type') ?: 'pesee';
        $t = EvenementTypes::get($code);
        if (!$t) {
            flash('danger', "Type d'événement inconnu.");
            redirect(url('evenements'));
        }
        $t['code'] = $code;
        return $t;
    }

    // ------------------------------------------------------------------
    public function index(): void
    {
        $t = $this->type();
        [$q, $f] = $this->query($t);
        $q['limit'] = $this->pageSize();
        $q['cursor'] = input_str('cursor', 500) ?: null;
        $page = KeysetPaginator::paginate($q);
        $total = KeysetPaginator::count($q['from'], $q['where'], $q['params'], 'evenements');
        $this->render('evenements/index', ['t' => $t, 'page' => $page, 'total' => $total, 'f' => $f,
            'types' => EvenementTypes::all(), 'especes' => Ref::especes()]);
    }

    public function exportPdf(): void
    {
        $t = $this->type();
        [$q, $f] = $this->query($t);
        $cols = ['Date', 'N° animal', 'Nom'];
        foreach ($t['fields'] as $k => $fd) {
            if ($k !== $t['date']) $cols[] = $fd['label'];
        }
        $rows = (function () use ($q, $t) {
            foreach (KeysetPaginator::iterate($q, $this->maxExport()) as $r) {
                $line = [fdate($r[$t['date']]), $r['numero_identification'], $r['animal_nom']];
                foreach ($t['fields'] as $k => $fd) {
                    if ($k !== $t['date']) $line[] = self::display($fd, $r, $k);
                }
                yield $line;
            }
        })();
        PdfExporter::liste($t['titre'], $cols, $rows, [
            'Du' => fdate($f['du']), 'Au' => fdate($f['au']), 'Animal' => $f['animal'],
            'Espèce' => $f['espece_id'] ? (Ref::especes()[$f['espece_id']]['libelle'] ?? '') : '',
        ], 'landscape', input_str('mode') !== 'imprimer');
    }

    public static function display(array $fd, array $r, string $k): string
    {
        $v = $r[$k] ?? null;
        return match ($fd['type']) {
            'date'    => fdate($v),
            'decimal' => $v === null ? '' : fnum($v, 2),
            'select'  => $fd['options'][$v] ?? (string) $v,
            'animal'  => (string) ($r[$k . '_numero'] ?? ''),
            default   => (string) $v,
        };
    }

    private function query(array $t): array
    {
        $f = [
            'du' => valid_date(input_str('du')) ? input_str('du') : '',
            'au' => valid_date(input_str('au')) ? input_str('au') : '',
            'animal' => input_str('animal', 40),
            'animal_id' => input_int('animal_id'),
            'espece_id' => input_int('espece_id'),
            'sous_type' => input_str('sous_type', 30),
        ];
        $d = 'ev.' . $t['date'];
        $w = ['a.supprime = 0'];
        $p = [];
        if ($f['du']) { $w[] = "$d >= :du"; $p['du'] = $f['du']; }
        if ($f['au']) { $w[] = "$d <= :au"; $p['au'] = $f['au']; }
        if ($f['animal_id']) { $w[] = 'ev.animal_id = :aid'; $p['aid'] = $f['animal_id']; }
        elseif ($f['animal'] !== '') { $w[] = 'a.numero_identification LIKE :num'; $p['num'] = addcslashes($f['animal'], '%_\\') . '%'; }
        if ($f['espece_id']) { $w[] = 'a.espece_id = :esp'; $p['esp'] = $f['espece_id']; }
        // Filtre sur le premier champ "select" (type de pesée, méthode, issue, type de soin)
        foreach ($t['fields'] as $k => $fd) {
            if ($fd['type'] === 'select') {
                if ($f['sous_type'] !== '' && isset($fd['options'][$f['sous_type']])) {
                    $w[] = "ev.$k = :st";
                    $p['st'] = $f['sous_type'];
                }
                $f['sous_type_champ'] = $k;
                break;
            }
        }
        $select = "ev.*, a.numero_identification, a.nom animal_nom, a.espece_id";
        $from = "{$t['table']} ev JOIN animaux a ON a.id = ev.animal_id";
        foreach ($t['fields'] as $k => $fd) {
            if ($fd['type'] === 'animal') {
                $select .= ", x_$k.numero_identification {$k}_numero";
                $from .= " LEFT JOIN animaux x_$k ON x_$k.id = ev.$k";
            }
        }
        return [[
            'select' => $select, 'from' => $from, 'where' => $w, 'params' => $p,
            'sort' => ['col' => $d, 'key' => $t['date'], 'dir' => 'DESC'], 'id' => ['col' => 'ev.id', 'key' => 'id'],
        ], $f];
    }

    // ------------------------------------------------------------------
    public function create(): void
    {
        $this->form($this->type(), null);
    }

    public function edit(): void
    {
        $t = $this->type();
        $row = Database::one("SELECT * FROM {$t['table']} WHERE id = ?", [(int) input_int('id', 0)]);
        if (!$row) {
            flash('danger', 'Événement introuvable.');
            redirect(url('evenements', ['type' => $t['code']]));
        }
        $this->form($t, $row);
    }

    private function form(array $t, ?array $row): void
    {
        $errors = [];
        $v = $row ?? ['animal_id' => input_int('animal_id'), $t['date'] => date('Y-m-d')];
        if ($this->isPost()) {
            [$data, $errors] = $this->validate($t, $row);
            if (!$errors) {
                $id = $row ? $this->doUpdate($t, $row, $data) : $this->doCreate($t, $data);
                flash('success', $t['singulier'] . ($row ? ' modifié(e).' : ' enregistré(e).'));
                $to = input_str('retour') === 'fiche'
                    ? url('animaux/voir', ['id' => $data['animal_id'], 'onglet' => $t['code']])
                    : url('evenements', ['type' => $t['code']]);
                if (!$row && input_int('encore') === 1) {
                    $to = url('evenements/creer', ['type' => $t['code'], 'retour' => input_str('retour')]);
                }
                redirect($to);
            }
            $v = array_merge($v, $_POST);
        }
        $animal = !empty($v['animal_id']) ? Database::one('SELECT id, numero_identification, nom, sexe, espece_id FROM animaux WHERE id = ?', [(int) $v['animal_id']]) : null;
        $labels = [];
        foreach ($t['fields'] as $k => $fd) {
            if ($fd['type'] === 'animal' && !empty($v[$k])) {
                $labels[$k] = Database::value('SELECT numero_identification FROM animaux WHERE id = ?', [(int) $v[$k]]);
            }
        }
        $this->render('evenements/form', ['t' => $t, 'row' => $row, 'v' => $v, 'errors' => $errors, 'animal' => $animal, 'labels' => $labels]);
    }

    private function validate(array $t, ?array $row): array
    {
        $e = [];
        $d = ['animal_id' => input_int('animal_id')];
        $animal = $d['animal_id'] ? Database::one('SELECT id, numero_identification, sexe, espece_id, date_naissance FROM animaux WHERE id = ? AND supprime = 0', [$d['animal_id']]) : null;
        if (!$animal) {
            $e['animal_id'] = 'Animal obligatoire.';
        } elseif ($t['sexe'] && $animal['sexe'] !== $t['sexe']) {
            $e['animal_id'] = 'Cet événement concerne uniquement les ' . ($t['sexe'] === 'F' ? 'femelles.' : 'mâles.');
        }
        foreach ($t['fields'] as $k => $fd) {
            $raw = input_str($k, $fd['max'] ?? 255);
            $req = !empty($fd['required']);
            if ($raw === '') {
                if ($req) $e[$k] = $fd['label'] . ' est obligatoire.';
                $d[$k] = $fd['type'] === 'int' && $req ? 0 : null;
                continue;
            }
            switch ($fd['type']) {
                case 'date':
                    if (!valid_date($raw)) $e[$k] = 'Date invalide.';
                    $d[$k] = $raw;
                    break;
                case 'decimal':
                case 'int':
                    $raw = str_replace(',', '.', $raw);
                    $ok = $fd['type'] === 'int' ? preg_match('/^\d+$/', $raw) : is_numeric($raw);
                    if (!$ok || (isset($fd['min']) && $raw < $fd['min']) || (isset($fd['max']) && $raw > $fd['max'])) {
                        $e[$k] = $fd['label'] . ' : valeur invalide' . (isset($fd['min'], $fd['max']) ? " ({$fd['min']} à {$fd['max']})" : '') . '.';
                    }
                    $d[$k] = $fd['type'] === 'int' ? (int) $raw : $raw;
                    break;
                case 'select':
                    if (!isset($fd['options'][$raw])) $e[$k] = 'Choix invalide.';
                    $d[$k] = $raw;
                    break;
                case 'animal':
                    $id = (int) $raw;
                    $m = Database::one('SELECT id, sexe, espece_id FROM animaux WHERE id = ? AND supprime = 0', [$id]);
                    if (!$m || ($fd['sexe'] ?? null) && $m['sexe'] !== $fd['sexe']) $e[$k] = 'Animal invalide (sexe attendu : ' . ($fd['sexe'] ?? '-') . ').';
                    elseif ($animal && (int) $m['espece_id'] !== (int) $animal['espece_id']) $e[$k] = "Le mâle doit être de la même espèce.";
                    $d[$k] = $id;
                    break;
                default:
                    $d[$k] = $raw;
            }
        }
        $dateEv = $d[$t['date']] ?? null;
        if ($animal && $dateEv && valid_date($dateEv)) {
            if ($dateEv > date('Y-m-d')) $e[$t['date']] = 'La date ne peut pas être dans le futur.';
            if ($dateEv < $animal['date_naissance']) $e[$t['date']] = "La date précède la naissance de l'animal.";
        }
        // Règles métier spécifiques
        if ($t['code'] === 'reproduction' && empty($e) && empty($d['date_prevue_mise_bas'])) {
            $d['date_prevue_mise_bas'] = date('Y-m-d', strtotime($d['date_saillie'] . ' +' . (int) Settings::get('duree_gestation_jours', 150) . ' days'));
        }
        if ($t['code'] === 'mise_bas') {
            if ($d['issue'] === 'avortement') $d['nb_vivants'] = 0;
            if ((int) $d['nb_vivants'] > (int) $d['nb_nes']) $e['nb_vivants'] = 'Les nés vivants ne peuvent dépasser le nombre de nés.';
            if ((int) ($d['nb_males'] ?? 0) + (int) ($d['nb_femelles'] ?? 0) > (int) $d['nb_nes']) $e['nb_males'] = 'Mâles + femelles dépasse le nombre de nés.';
            $d['nb_males'] = (int) ($d['nb_males'] ?? 0);
            $d['nb_femelles'] = (int) ($d['nb_femelles'] ?? 0);
        }
        if ($e) flash('danger', 'Veuillez corriger les erreurs du formulaire.');
        return [$d, $e];
    }

    private function doCreate(array $t, array $d): int
    {
        return Database::transaction(function () use ($t, $d) {
            $d['cree_par'] = Auth::id();
            $id = Database::insert($t['table'], $d);
            Historique::add((int) $d['animal_id'], $t['code'], ($t['resume'])($d), ['evenement_id' => $id], $d[$t['date']]);
            if ($t['code'] === 'mise_bas') {
                // Clôture la dernière saillie en attente de la femelle
                Database::run(
                    "UPDATE reproductions SET resultat = ? WHERE animal_id = ? AND resultat IN ('en_attente','gestante')
                       AND date_saillie BETWEEN ? - INTERVAL 220 DAY AND ? ORDER BY date_saillie DESC LIMIT 1",
                    [$d['issue'] === 'avortement' ? 'avortement' : 'mise_bas', $d['animal_id'], $d['date_mise_bas'], $d['date_mise_bas']]
                );
            }
            Audit::log('creation', $t['code'], $id, $t['singulier'] . ' ajouté(e) pour l\'animal #' . $d['animal_id'], null, $d);
            $this->invalidate();
            return $id;
        });
    }

    private function doUpdate(array $t, array $old, array $d): int
    {
        $id = (int) $old['id'];
        [$avant, $apres] = Audit::diff($old, $d);
        if ($apres) {
            Database::transaction(function () use ($t, $id, $d, $avant, $apres) {
                Database::update($t['table'], $d, $id);
                Historique::add((int) $d['animal_id'], $t['code'], 'Modification — ' . ($t['resume'])($d), ['evenement_id' => $id, 'avant' => $avant, 'apres' => $apres], $d[$t['date']]);
                Audit::log('modification', $t['code'], $id, $t['singulier'] . ' modifié(e)', $avant, $apres);
            });
            $this->invalidate();
        }
        return $id;
    }

    public function delete(): void
    {
        $t = $this->type();
        $id = (int) input_int('id', 0);
        $row = Database::one("SELECT * FROM {$t['table']} WHERE id = ?", [$id]);
        if ($row) {
            Database::transaction(function () use ($t, $row, $id) {
                Database::run("DELETE FROM {$t['table']} WHERE id = ?", [$id]);
                Historique::add((int) $row['animal_id'], 'suppression', 'Suppression — ' . ($t['resume'])($row), ['evenement' => $row]);
                Audit::log('suppression', $t['code'], $id, $t['singulier'] . ' supprimé(e)', $row, null);
            });
            $this->invalidate();
            flash('success', $t['singulier'] . ' supprimé(e).');
        }
        $this->back(url('evenements', ['type' => $t['code']]));
    }

    private function invalidate(): void
    {
        Cache::flushGroup('evenements');
        Cache::flushGroup('dashboard');
        Cache::flushGroup('animaux');
    }
}
