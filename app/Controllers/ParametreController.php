<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Ref;

final class ParametreController extends Controller
{
    public function index(): void
    {
        $params = Database::all('SELECT * FROM parametres ORDER BY groupe, cle');
        if ($this->isPost()) {
            $avant = $apres = [];
            $errors = [];
            foreach ($params as $p) {
                $val = input_str('p_' . $p['cle'], 500);
                if ($p['type'] === 'entier' && !preg_match('/^\d+$/', $val)) { $errors[] = $p['libelle'] . ' : entier attendu.'; continue; }
                if ($p['type'] === 'decimal' && !is_numeric(str_replace(',', '.', $val))) { $errors[] = $p['libelle'] . ' : nombre attendu.'; continue; }
                if ($p['type'] === 'booleen') $val = input_int('p_' . $p['cle']) ? '1' : '0';
                if ((string) $p['valeur'] !== $val) {
                    Database::run('UPDATE parametres SET valeur = ? WHERE cle = ?', [$val, $p['cle']]);
                    $avant[$p['cle']] = $p['valeur'];
                    $apres[$p['cle']] = $val;
                }
            }
            if ($apres) {
                Audit::log('modification', 'parametres', null, 'Modification des paramètres', $avant, $apres);
                Cache::flushGroup('ref');
            }
            foreach ($errors as $err) flash('danger', $err);
            if (!$errors) flash('success', 'Paramètres enregistrés.');
            redirect(url('parametres'));
        }
        $groupes = [];
        foreach ($params as $p) $groupes[$p['groupe']][] = $p;
        $this->render('parametres/index', ['groupes' => $groupes]);
    }

    /** Référentiel des races (et lecture des espèces / catégories). */
    public function races(): void
    {
        if ($this->isPost()) {
            $act = input_str('action');
            if ($act === 'ajouter') {
                $esp = (int) input_int('espece_id', 0);
                $lib = input_str('libelle', 80);
                if ($lib !== '' && isset(Ref::especes()[$esp])) {
                    try {
                        $id = Database::insert('races', ['espece_id' => $esp, 'libelle' => $lib]);
                        Audit::log('creation', 'race', $id, "Ajout de la race $lib");
                        flash('success', 'Race ajoutée.');
                    } catch (\PDOException) {
                        flash('danger', 'Cette race existe déjà.');
                    }
                }
            } elseif ($act === 'basculer') {
                $id = (int) input_int('id', 0);
                Database::run('UPDATE races SET actif = 1 - actif WHERE id = ?', [$id]);
                Audit::log('modification', 'race', $id, 'Activation / désactivation de race');
            } elseif ($act === 'supprimer') {
                $id = (int) input_int('id', 0);
                if (Database::value('SELECT 1 FROM animaux WHERE race_id = ? LIMIT 1', [$id])) {
                    flash('danger', 'Race utilisée par des animaux : désactivez-la plutôt.');
                } else {
                    Database::run('DELETE FROM races WHERE id = ?', [$id]);
                    Audit::log('suppression', 'race', $id, 'Suppression de race');
                    flash('success', 'Race supprimée.');
                }
            }
            Cache::flushGroup('ref');
            redirect(url('parametres/races'));
        }
        $races = Database::all('SELECT r.*, e.libelle espece, (SELECT COUNT(*) FROM animaux a WHERE a.race_id = r.id AND a.supprime = 0) nb
                                  FROM races r JOIN especes e ON e.id = r.espece_id ORDER BY r.espece_id, r.libelle');
        $this->render('parametres/races', ['races' => $races, 'especes' => Ref::especes(), 'categories' => Ref::categories()]);
    }

    /** État et vidage des caches OPcache et APCu, statistiques des tables InnoDB. */
    public function systeme(): void
    {
        if ($this->isPost()) {
            $what = input_str('vider');
            if ($what === 'apcu') {
                Cache::clearAll();
                flash('success', 'Cache APCu vidé.');
            } elseif ($what === 'opcache' && function_exists('opcache_reset')) {
                opcache_reset();
                flash('success', 'OPcache réinitialisé.');
            } elseif ($what === 'analyze') {
                foreach (['animaux', 'pesees', 'reproductions', 'mises_bas', 'productions_lait', 'soins', 'indicateur_resultats', 'journal_audit', 'animal_historique'] as $t) {
                    Database::all("ANALYZE TABLE `$t`");
                }
                flash('success', 'Statistiques des index mises à jour (ANALYZE TABLE).');
            }
            Audit::log('maintenance', 'systeme', null, 'Maintenance : ' . $what);
            redirect(url('parametres/systeme'));
        }
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $apcu = Cache::enabled() ? ['info' => apcu_cache_info(true), 'sma' => apcu_sma_info(true)] : null;
        $tables = Database::all(
            "SELECT TABLE_NAME, ENGINE, TABLE_ROWS, ROUND(DATA_LENGTH/1024/1024, 2) data_mb, ROUND(INDEX_LENGTH/1024/1024, 2) index_mb
               FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
        $this->render('parametres/systeme', [
            'opcache' => $opcache, 'apcu' => $apcu, 'tables' => $tables,
            'php' => PHP_VERSION, 'mysql' => Database::value('SELECT VERSION()'),
            'dompdf' => \App\Services\PdfExporter::available(),
        ]);
    }
}
