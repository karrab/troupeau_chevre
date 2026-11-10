<?php
/**
 * Table de routage : route => [Contrôleur, méthode, permission requise|null, méthodes HTTP]
 * Permission null = utilisateur connecté ; 'public' = accessible sans connexion.
 */
use App\Controllers as C;

return [
    'auth/login'           => [C\AuthController::class, 'login', 'public', ['GET', 'POST']],
    'auth/logout'          => [C\AuthController::class, 'logout', null, ['POST']],
    'auth/profil'          => [C\AuthController::class, 'profil', null, ['GET', 'POST']],

    ''                     => [C\DashboardController::class, 'index', null, ['GET']],
    'dashboard'            => [C\DashboardController::class, 'index', null, ['GET']],

    'animaux'              => [C\AnimalController::class, 'index', 'animaux.voir', ['GET']],
    'animaux/pdf'          => [C\AnimalController::class, 'exportPdf', 'animaux.exporter', ['GET']],
    'animaux/voir'         => [C\AnimalController::class, 'show', 'animaux.voir', ['GET']],
    'animaux/fiche-pdf'    => [C\AnimalController::class, 'fichePdf', 'animaux.exporter', ['GET']],
    'animaux/creer'        => [C\AnimalController::class, 'create', 'animaux.creer', ['GET', 'POST']],
    'animaux/modifier'     => [C\AnimalController::class, 'edit', 'animaux.modifier', ['GET', 'POST']],
    'animaux/supprimer'    => [C\AnimalController::class, 'delete', 'animaux.supprimer', ['POST']],
    'animaux/restaurer'    => [C\AnimalController::class, 'restore', 'animaux.supprimer', ['POST']],
    'animaux/recherche'    => [C\AnimalController::class, 'autocomplete', 'animaux.voir', ['GET']],

    'evenements'           => [C\EvenementController::class, 'index', 'evenements.voir', ['GET']],
    'evenements/pdf'       => [C\EvenementController::class, 'exportPdf', 'animaux.exporter', ['GET']],
    'evenements/creer'     => [C\EvenementController::class, 'create', 'evenements.creer', ['GET', 'POST']],
    'evenements/modifier'  => [C\EvenementController::class, 'edit', 'evenements.modifier', ['GET', 'POST']],
    'evenements/supprimer' => [C\EvenementController::class, 'delete', 'evenements.supprimer', ['POST']],

    'indices'              => [C\IndiceController::class, 'index', 'indices.voir', ['GET']],
    'indices/pdf'          => [C\IndiceController::class, 'exportPdf', 'indices.voir', ['GET']],
    'indices/calculer'     => [C\IndiceController::class, 'calculer', 'indices.calculer', ['GET', 'POST']],
    'indices/formules'     => [C\IndiceController::class, 'formules', 'indices.voir', ['GET']],
    'indices/formule'      => [C\IndiceController::class, 'editFormule', 'indices.formules', ['GET', 'POST']],
    'indices/supprimer'    => [C\IndiceController::class, 'deleteFormule', 'indices.formules', ['POST']],
    'indices/tester'       => [C\IndiceController::class, 'tester', 'indices.formules', ['POST']],

    'utilisateurs'         => [C\UtilisateurController::class, 'index', 'utilisateurs.gerer', ['GET']],
    'utilisateurs/pdf'     => [C\UtilisateurController::class, 'exportPdf', 'utilisateurs.gerer', ['GET']],
    'utilisateurs/form'    => [C\UtilisateurController::class, 'form', 'utilisateurs.gerer', ['GET', 'POST']],
    'utilisateurs/supprimer' => [C\UtilisateurController::class, 'delete', 'utilisateurs.gerer', ['POST']],

    'roles'                => [C\RoleController::class, 'index', 'roles.gerer', ['GET', 'POST']],
    'roles/form'           => [C\RoleController::class, 'form', 'roles.gerer', ['GET', 'POST']],
    'roles/supprimer'      => [C\RoleController::class, 'delete', 'roles.gerer', ['POST']],

    'journal'              => [C\JournalController::class, 'index', 'journal.voir', ['GET']],
    'journal/pdf'          => [C\JournalController::class, 'exportPdf', 'journal.voir', ['GET']],
    'journal/voir'         => [C\JournalController::class, 'show', 'journal.voir', ['GET']],

    'parametres'           => [C\ParametreController::class, 'index', 'parametres.gerer', ['GET', 'POST']],
    'parametres/races'     => [C\ParametreController::class, 'races', 'parametres.gerer', ['GET', 'POST']],
    'parametres/systeme'   => [C\ParametreController::class, 'systeme', 'systeme.cache', ['GET', 'POST']],
];
