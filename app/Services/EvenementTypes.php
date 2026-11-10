<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Définition déclarative des événements zootechniques : un seul contrôleur
 * générique gère liste (keyset), saisie, modification, suppression et export PDF.
 */
final class EvenementTypes
{
    public static function all(): array
    {
        return [
            'pesee' => [
                'table' => 'pesees', 'titre' => 'Pesées', 'singulier' => 'Pesée', 'icone' => 'bi-speedometer2',
                'date' => 'date_pesee', 'sexe' => null,
                'fields' => [
                    'date_pesee' => ['label' => 'Date', 'type' => 'date', 'required' => true],
                    'poids_kg'   => ['label' => 'Poids (kg)', 'type' => 'decimal', 'required' => true, 'min' => 0.1, 'max' => 250],
                    'type_pesee' => ['label' => 'Type', 'type' => 'select', 'required' => true, 'options' => [
                        'controle' => 'Contrôle', 'naissance' => 'Naissance', 'sevrage' => 'Sevrage', 'vente' => 'Vente', 'autre' => 'Autre']],
                    'note'       => ['label' => 'Note', 'type' => 'text', 'max' => 255],
                ],
                'resume' => fn($r) => 'Pesée : ' . fnum($r['poids_kg'], 1) . ' kg (' . ($r['type_pesee'] ?? '') . ')',
            ],
            'reproduction' => [
                'table' => 'reproductions', 'titre' => 'Reproductions', 'singulier' => 'Saillie / insémination', 'icone' => 'bi-heart',
                'date' => 'date_saillie', 'sexe' => 'F',
                'fields' => [
                    'date_saillie'  => ['label' => 'Date de saillie', 'type' => 'date', 'required' => true],
                    'male_id'       => ['label' => 'Mâle (bouc / bélier)', 'type' => 'animal', 'sexe' => 'M'],
                    'methode'       => ['label' => 'Méthode', 'type' => 'select', 'required' => true, 'options' => [
                        'naturelle' => 'Monte naturelle', 'insemination' => 'Insémination artificielle', 'lutte_libre' => 'Lutte libre']],
                    'date_prevue_mise_bas' => ['label' => 'Mise bas prévue', 'type' => 'date', 'help' => 'Calculée automatiquement si vide (durée de gestation)'],
                    'resultat'      => ['label' => 'Résultat', 'type' => 'select', 'required' => true, 'options' => [
                        'en_attente' => 'En attente', 'gestante' => 'Gestante (confirmée)', 'vide' => 'Vide', 'avortement' => 'Avortement', 'mise_bas' => 'Mise bas']],
                    'note'          => ['label' => 'Note', 'type' => 'text', 'max' => 255],
                ],
                'resume' => fn($r) => 'Saillie (' . ($r['methode'] ?? '') . ') — résultat : ' . ($r['resultat'] ?? ''),
            ],
            'mise_bas' => [
                'table' => 'mises_bas', 'titre' => 'Mises bas', 'singulier' => 'Mise bas', 'icone' => 'bi-stars',
                'date' => 'date_mise_bas', 'sexe' => 'F',
                'fields' => [
                    'date_mise_bas' => ['label' => 'Date', 'type' => 'date', 'required' => true],
                    'issue'         => ['label' => 'Déroulement', 'type' => 'select', 'required' => true, 'options' => [
                        'normale' => 'Normale', 'difficile' => 'Difficile (assistée)', 'cesarienne' => 'Césarienne', 'avortement' => 'Avortement']],
                    'nb_nes'        => ['label' => 'Nombre de nés', 'type' => 'int', 'required' => true, 'min' => 0, 'max' => 8],
                    'nb_vivants'    => ['label' => 'Nés vivants', 'type' => 'int', 'required' => true, 'min' => 0, 'max' => 8],
                    'nb_males'      => ['label' => 'Dont mâles', 'type' => 'int', 'min' => 0, 'max' => 8],
                    'nb_femelles'   => ['label' => 'Dont femelles', 'type' => 'int', 'min' => 0, 'max' => 8],
                    'note'          => ['label' => 'Note', 'type' => 'text', 'max' => 255],
                ],
                'resume' => fn($r) => 'Mise bas ' . ($r['issue'] ?? '') . ' : ' . ($r['nb_nes'] ?? 0) . ' né(s), ' . ($r['nb_vivants'] ?? 0) . ' vivant(s)',
            ],
            'lait' => [
                'table' => 'productions_lait', 'titre' => 'Contrôles laitiers', 'singulier' => 'Contrôle laitier', 'icone' => 'bi-droplet',
                'date' => 'date_controle', 'sexe' => 'F',
                'fields' => [
                    'date_controle'   => ['label' => 'Date', 'type' => 'date', 'required' => true],
                    'quantite_litres' => ['label' => 'Quantité (L/jour)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 20],
                    'taux_mg'         => ['label' => 'Matière grasse (g/L)', 'type' => 'decimal', 'min' => 0, 'max' => 150],
                    'taux_mp'         => ['label' => 'Matière protéique (g/L)', 'type' => 'decimal', 'min' => 0, 'max' => 150],
                    'note'            => ['label' => 'Note', 'type' => 'text', 'max' => 255],
                ],
                'resume' => fn($r) => 'Contrôle laitier : ' . fnum($r['quantite_litres'], 2) . ' L/j',
            ],
            'soin' => [
                'table' => 'soins', 'titre' => 'Soins & traitements', 'singulier' => 'Soin', 'icone' => 'bi-bandaid',
                'date' => 'date_soin', 'sexe' => null,
                'fields' => [
                    'date_soin'   => ['label' => 'Date', 'type' => 'date', 'required' => true],
                    'type_soin'   => ['label' => 'Type', 'type' => 'select', 'required' => true, 'options' => [
                        'vaccination' => 'Vaccination', 'vermifugation' => 'Vermifugation', 'traitement' => 'Traitement',
                        'parage' => 'Parage', 'chirurgie' => 'Chirurgie', 'autre' => 'Autre']],
                    'produit'     => ['label' => 'Produit', 'type' => 'text', 'max' => 120],
                    'dose'        => ['label' => 'Dose', 'type' => 'text', 'max' => 60],
                    'veterinaire' => ['label' => 'Vétérinaire / intervenant', 'type' => 'text', 'max' => 120],
                    'cout'        => ['label' => 'Coût', 'type' => 'decimal', 'min' => 0, 'max' => 1000000],
                    'delai_attente_jours' => ['label' => "Délai d'attente (jours)", 'type' => 'int', 'min' => 0, 'max' => 365],
                    'note'        => ['label' => 'Note', 'type' => 'text', 'max' => 255],
                ],
                'resume' => fn($r) => 'Soin ' . ($r['type_soin'] ?? '') . ($r['produit'] ? ' : ' . $r['produit'] : ''),
            ],
        ];
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }
}
