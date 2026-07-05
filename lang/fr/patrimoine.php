<?php

return [

    'brand_subtitle' => 'Gestion du Patrimoine',

    'nav' => [
        'administration' => 'Administration',
        'referentiel' => 'Référentiel',
        'patrimoine' => 'Patrimoine',
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
        'building' => [
            'label' => 'Bâtiment',
            'plural' => 'Bâtiments',
        ],
        'local' => [
            'label' => 'Local',
            'plural' => 'Locaux',
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
        'faculty_help' => 'Pour N2, définit son périmètre d\'approbation/visibilité (obligatoire). Pour les enseignants et utilisateurs, simple rattachement — ne limite jamais les salles demandables. Laisser vide pour les rôles centraux (A3, N3, admin).',
        'faculty_required_for_n2' => 'Une faculté est requise pour le rôle Responsable faculté (N2) — elle définit son périmètre d\'approbation.',
        'code' => 'Code',
        'type' => 'Type',
        'responsible' => 'Responsable',
        'is_active' => 'Actif',
        'is_active_help' => 'Un compte désactivé est immédiatement bloqué, sans suppression de données.',
        'created_at' => 'Créé le',
        'updated_at' => 'Modifié le',
        'building' => 'Bâtiment',
        'campus' => 'Campus',
        'address' => 'Adresse',
        'floors_count' => 'Étages',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'floor' => 'Étage',
        'capacity' => 'Capacité',
        'surface_m2' => 'Surface (m²)',
        'locals_count' => 'Locaux',
        'on_map' => 'Sur la carte',
        'central_shared' => 'Central / partagé',
        'status' => 'Statut',
        'building_faculty_help' => 'Laisser vide pour les bâtiments centraux ou partagés (bibliothèque, administration…). Les bâtiments de faculté délimitent la visibilité N2 et le routage des approbations de réservation.',
        'local_responsible_help' => 'Contact au quotidien pour ce local (tout utilisateur, aucun rôle particulier).',
        'coordinates_help' => 'Saisir manuellement, ou utiliser « Positionner sur la carte » depuis la page Cartographie campus.',
    ],

    'service_types' => [
        'service' => 'Service',
        'labo' => 'Laboratoire',
        'bureau' => 'Bureau',
    ],

    'building_statuses' => [
        'active' => 'Actif',
        'under_renovation' => 'En rénovation',
        'decommissioned' => 'Désaffecté',
    ],

    'local_types' => [
        'bureau' => 'Bureau',
        'salle_cours' => 'Salle de cours',
        'amphi' => 'Amphithéâtre',
        'labo' => 'Laboratoire',
        'atelier' => 'Atelier',
        'entrepot' => 'Entrepôt',
        'salle_reunion' => 'Salle de réunion',
        'autre' => 'Autre',
    ],

    'local_statuses' => [
        'available' => 'Disponible',
        'occupied' => 'Occupé',
        'under_maintenance' => 'En maintenance',
        'closed' => 'Fermé',
    ],

    'campus_map' => [
        'title' => 'Cartographie campus',
        'rooms' => 'locaux',
        'seats' => 'places',
        'no_rooms' => 'Aucun local enregistré dans ce bâtiment.',
        'select_hint' => 'Cliquez sur un bâtiment de la carte pour voir ses locaux.',
        'set_position' => 'Positionner sur la carte',
        'picking_hint' => 'Cliquez n\'importe où sur la carte pour placer ce bâtiment.',
        'cancel' => 'Annuler',
        'position_saved' => 'Position du bâtiment enregistrée.',
        'select_building' => 'Sélectionner un bâtiment',
        'not_placed' => 'non placé',
        'not_placed_hint' => 'Ce bâtiment n\'a pas encore de position — il n\'apparaît pas sur la carte. Utilisez « Positionner sur la carte » ci-dessous.',
        'place_reminder_title' => 'Bâtiment non placé sur la carte',
        'place_reminder_body' => '« :name » n\'a pas encore de coordonnées. Ouvrez la page Cartographie campus, sélectionnez-le dans la liste, puis « Positionner sur la carte ».',
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
