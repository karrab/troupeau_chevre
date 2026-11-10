<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Installation de la base : création, schéma, données initiales, fichier de configuration.
 * Partagé par l'installateur web (public/install.php) et la ligne de commande (database/install.php).
 */
final class Installer
{
    public const LOCK = 'storage/installed.lock';

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function isLocked(): bool
    {
        return is_file(self::root() . '/' . self::LOCK);
    }

    public static function lock(): void
    {
        file_put_contents(self::root() . '/' . self::LOCK, 'Installé le ' . date('c') . PHP_EOL);
    }

    /** Connexion au serveur MySQL (sans base sélectionnée). */
    public static function connect(array $db): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], (int) $db['port']),
            $db['user'],
            $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
    }

    public static function databaseHasTables(PDO $pdo, string $name): bool
    {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $st->execute([$name, 'utilisateurs']);
        return (int) $st->fetchColumn() > 0;
    }

    /** Crée la base si besoin puis exécute schema.sql et seed.sql (les tables existantes sont recréées). */
    public static function install(PDO $pdo, string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            throw new \InvalidArgumentException('Nom de base invalide (lettres, chiffres et _ uniquement).');
        }
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");
        foreach (['schema.sql', 'seed.sql'] as $file) {
            $sql = (string) file_get_contents(self::root() . '/database/' . $file);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
                if (trim($stmt) !== '') {
                    $pdo->exec($stmt);
                }
            }
        }
    }

    /** Écrit config/config.local.php. */
    public static function writeConfig(array $db, string $baseUrl = '', string $env = 'production'): bool
    {
        $cfg = [
            'app' => ['env' => $env, 'base_url' => $baseUrl],
            'db'  => ['host' => $db['host'], 'port' => (int) $db['port'], 'name' => $db['name'], 'user' => $db['user'], 'pass' => $db['pass']],
        ];
        $php = "<?php\n// Généré par l'installateur le " . date('d/m/Y H:i') . "\nreturn " . var_export($cfg, true) . ";\n";
        return @file_put_contents(self::root() . '/config/config.local.php', $php) !== false;
    }
}
