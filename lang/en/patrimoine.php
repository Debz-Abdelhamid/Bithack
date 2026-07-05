<?php

return [

    'brand_subtitle' => 'Asset Management',

    'nav' => [
        'administration' => 'Administration',
        'referentiel' => 'Reference data',
    ],

    'resources' => [
        'user' => [
            'label' => 'User',
            'plural' => 'Users',
        ],
        'faculty' => [
            'label' => 'Faculty',
            'plural' => 'Faculties',
        ],
        'service' => [
            'label' => 'Service',
            'plural' => 'Services',
        ],
    ],

    'fields' => [
        'name' => 'Name',
        'full_name' => 'Full name',
        'email' => 'Email address',
        'email_verified_at' => 'Email verified at',
        'password' => 'Password',
        'roles' => 'Roles',
        'faculty' => 'Faculty',
        'faculty_help' => 'For N2 this defines their approval/visibility scope (required). For teachers and users it is affiliation only — it never limits which rooms they can request. Leave empty for central roles (A3, N3, admin).',
        'faculty_required_for_n2' => 'A faculty is required for the Faculty Head (N2) role — it defines their approval scope.',
        'code' => 'Code',
        'type' => 'Type',
        'responsible' => 'Responsible',
        'is_active' => 'Active',
        'is_active_help' => 'Deactivated accounts are locked out of the panel immediately, without deleting any data.',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],

    'service_types' => [
        'service' => 'Service',
        'labo' => 'Laboratory',
        'bureau' => 'Office',
    ],

    'roles' => [
        'gestionnaire_patrimoine' => 'Asset manager (A3)',
        'responsable_faculte' => 'Faculty head (N2)',
        'rectorat' => 'Rectorate (N3)',
        'service_technique' => 'Technical service',
        'tout_utilisateur' => 'Any user',
        'enseignant' => 'Teacher',
        'super_admin' => 'Super administrator',
    ],

    'notifications' => [
        'test_title' => 'Test notification',
        'test_body' => 'The realtime notification pipeline is working.',
    ],

    'registration' => [
        'email_domain' => 'Registration is limited to institutional email addresses (:domains).',
    ],

];
