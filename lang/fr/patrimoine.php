<?php

return [

    'brand_subtitle' => 'Gestion du Patrimoine',

    'nav' => [
        'administration' => 'Administration',
        'referentiel' => 'Référentiel',
    ],

    'resources' => [
        'user' => [
            'label' => 'Utilisateur',
            'plural' => 'Utilisateurs',
        ],
        'faculty' => [
            'label' => 'Faculté',
            'plural' => 'Facultés',
        ],
        'service' => [
            'label' => 'Service',
            'plural' => 'Services',
        ],
    ],

    'fields' => [
        'name' => 'Nom',
        'full_name' => 'Nom & Prénom',
        'email' => 'Adresse e-mail',
        'email_verified_at' => 'E-mail vérifié le',
        'password' => 'Mot de passe',
        'roles' => 'Rôles',
        'faculty' => 'Faculté',
        'code' => 'Code',
        'type' => 'Type',
        'responsible' => 'Responsable',
        'is_active' => 'Actif',
        'is_active_help' => 'Un compte désactivé est immédiatement bloqué, sans suppression de données.',
        'created_at' => 'Créé le',
        'updated_at' => 'Modifié le',
    ],

    'service_types' => [
        'service' => 'Service',
        'labo' => 'Laboratoire',
        'bureau' => 'Bureau',
    ],

    'roles' => [
        'gestionnaire_patrimoine' => 'Gestionnaire patrimoine (A3)',
        'responsable_faculte' => 'Responsable faculté (N2)',
        'rectorat' => 'Rectorat (N3)',
        'service_technique' => 'Service technique',
        'tout_utilisateur' => 'Tout utilisateur',
        'enseignant' => 'Enseignant',
        'super_admin' => 'Super administrateur',
    ],

    'notifications' => [
        'test_title' => 'Notification de test',
        'test_body' => 'La chaîne de notifications temps réel fonctionne.',
    ],

    'registration' => [
        'email_domain' => 'L\'inscription est réservée aux adresses e-mail institutionnelles (:domains).',
    ],

];
