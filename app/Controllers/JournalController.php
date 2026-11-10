<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\KeysetPaginator;
use App\Services\PdfExporter;

/** Journal des traces (audit) : consultation, filtres, détail avant/après, export PDF. */
final class JournalController extends Controller
{
    private function query(): array
    {
        $f = [
            'du' => valid_date(input_str('du')) ? input_str('du') : '',
            'au' => valid_date(input_str('au')) ? input_str('au') : '',
            'utilisateur_id' => input_int('utilisateur_id'),
            'action' => input_str('action', 40),
            'entite' => input_str('entite', 50),
            'entite_id' => input_int('entite_id'),
            'q' => input_str('q', 100),
        ];
        $w = [];
        $p = [];
        if ($f['du']) { $w[] = 'j.created_at >= :du'; $p['du'] = $f['du'] . ' 00:00:00'; }
        if ($f['au']) { $w[] = 'j.created_at <= :au'; $p['au'] = $f['au'] . ' 23:59:59'; }
        if ($f['utilisateur_id']) { $w[] = 'j.utilisateur_id = :u'; $p['u'] = $f['utilisateur_id']; }
        if ($f['action'] !== '') { $w[] = 'j.action = :ac'; $p['ac'] = $f['action']; }
        if ($f['entite'] !== '') { $w[] = 'j.entite = :en'; $p['en'] = $f['entite']; }
        if ($f['entite_id']) { $w[] = 'j.entite_id = :eid'; $p['eid'] = $f['entite_id']; }
        if ($f['q'] !== '') { $w[] = 'j.description LIKE :q'; $p['q'] = '%' . addcslashes($f['q'], '%_\\') . '%'; }
        return [[
            'select' => 'j.id, j.created_at, j.login, j.action, j.entite, j.entite_id, j.description, j.ip',
            'from' => 'journal_audit j', 'where' => $w, 'params' => $p,
            'sort' => ['col' => 'j.id', 'key' => 'id', 'dir' => 'DESC'], 'id' => ['col' => 'j.id', 'key' => 'id'],
        ], $f];
    }

    public function index(): void
    {
        [$q, $f] = $this->query();
        $q['limit'] = $this->pageSize();
        $q['cursor'] = input_str('cursor', 500) ?: null;
        $this->render('journal/index', [
            'page' => KeysetPaginator::paginate($q), 'f' => $f,
            'utilisateurs' => Database::all('SELECT id, login, nom_complet FROM utilisateurs ORDER BY nom_complet'),
            'actions' => array_column(Database::all('SELECT DISTINCT action FROM journal_audit ORDER BY action'), 'action'),
            'entites' => array_column(Database::all('SELECT DISTINCT entite FROM journal_audit ORDER BY entite'), 'entite'),
        ]);
    }

    public function show(): void
    {
        $j = Database::one('SELECT * FROM journal_audit WHERE id = ?', [(int) input_int('id', 0)]);
        if (!$j) {
            flash('danger', 'Trace introuvable.');
            redirect(url('journal'));
        }
        $this->render('journal/show', ['j' => $j]);
    }

    public function exportPdf(): void
    {
        [$q, $f] = $this->query();
        $rows = (function () use ($q) {
            foreach (KeysetPaginator::iterate($q, $this->maxExport()) as $r) {
                yield [fdate($r['created_at'], true), $r['login'], $r['action'], $r['entite'] . ($r['entite_id'] ? ' #' . $r['entite_id'] : ''), $r['description'], $r['ip']];
            }
        })();
        PdfExporter::liste('Journal des traces', ['Date', 'Utilisateur', 'Action', 'Objet', 'Description', 'IP'], $rows,
            ['Du' => fdate($f['du']), 'Au' => fdate($f['au']), 'Action' => $f['action'], 'Objet' => $f['entite'], 'Texte' => $f['q']],
            'landscape', input_str('mode') !== 'imprimer');
    }
}
