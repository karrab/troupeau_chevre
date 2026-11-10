<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Historique chronologique d'un animal (création, modifications, changements de
 * statut, événements zootechniques, suppression / restauration...).
 */
final class Historique
{
    public const TYPES = [
        'creation'     => ['Création', 'bi-plus-circle', 'success'],
        'modification' => ['Modification', 'bi-pencil', 'secondary'],
        'statut'       => ['Changement de statut', 'bi-flag', 'warning'],
        'pesee'        => ['Pesée', 'bi-speedometer2', 'info'],
        'reproduction' => ['Reproduction', 'bi-heart', 'danger'],
        'mise_bas'     => ['Mise bas', 'bi-stars', 'success'],
        'lait'         => ['Contrôle laitier', 'bi-droplet', 'primary'],
        'soin'         => ['Soin', 'bi-bandaid', 'danger'],
        'naissance'    => ['Descendant', 'bi-diagram-3', 'success'],
        'suppression'  => ['Suppression', 'bi-trash', 'dark'],
        'restauration' => ['Restauration', 'bi-arrow-counterclockwise', 'success'],
    ];

    public static function add(int $animalId, string $type, string $titre, ?array $details = null, ?string $date = null): void
    {
        Database::insert('animal_historique', [
            'animal_id'      => $animalId,
            'date_evenement' => $date ?: date('Y-m-d'),
            'type'           => $type,
            'titre'          => mb_substr($titre, 0, 200),
            'details'        => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'utilisateur_id' => Auth::id(),
        ]);
    }
}
