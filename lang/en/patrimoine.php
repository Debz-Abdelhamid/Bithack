<?php

return [

    'brand_subtitle' => 'Asset Management',

    'nav' => [
        'administration' => 'Administration',
        'referentiel' => 'Reference data',
        'patrimoine' => 'Facilities',
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
        'building' => [
            'label' => 'Building',
            'plural' => 'Buildings',
        ],
        'local' => [
            'label' => 'Room',
            'plural' => 'Rooms',
        ],
        'equipment' => [
            'label' => 'Equipment',
            'plural' => 'Equipments',
        ],
        'purchase_reference' => [
            'label' => 'Purchase reference',
            'plural' => 'Purchase references',
        ],
    ],

    'sections' => [
        'identification' => 'Identification',
        'details' => 'Details',
        'location_acquisition' => 'Location & acquisition',
        'qr_code' => 'QR code',
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
        'building' => 'Building',
        'campus' => 'Campus',
        'address' => 'Address',
        'floors_count' => 'Floors',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'floor' => 'Floor',
        'capacity' => 'Capacity',
        'surface_m2' => 'Surface (m²)',
        'locals_count' => 'Rooms',
        'on_map' => 'On map',
        'central_shared' => 'Central / shared',
        'status' => 'Status',
        'building_faculty_help' => 'Leave empty for central or shared buildings (library, administration…). Faculty buildings scope what N2 sees and drive reservation approval routing.',
        'local_responsible_help' => 'Day-to-day contact for this room (any user, no special role).',
        'coordinates_help' => 'Fill manually, or use "Set position on map" from the Campus map page.',
        'inventory_code' => 'Inventory code',
        'inventory_code_help' => 'Leave blank to auto-generate (UBMA-YYYY-NNNNN). Type a code only when re-registering an existing registry number.',
        'designation' => 'Designation',
        'category' => 'Category',
        'sub_category' => 'Sub-category',
        'brand' => 'Brand',
        'model' => 'Model',
        'serial_number' => 'Serial number',
        'condition' => 'Condition',
        'photo' => 'Photo',
        'notes' => 'Notes',
        'local' => 'Room',
        'unplaced' => 'Unplaced (central stock)',
        'equipment_local_help' => 'Current physical location. Leave empty for unplaced/central-stock assets.',
        'acquisition_date' => 'Acquisition date',
        'acquisition_value' => 'Acquisition value',
        'warranty_end_date' => 'Warranty end date',
        'purchase_reference' => 'Purchase reference (R7)',
        'purchase_reference_help' => 'Optional link to the procurement module (R7) order this asset came from.',
        'external_purchase_id' => 'External purchase reference',
        'external_purchase_id_help' => 'Identifier of the order in the procurement module (R7).',
        'supplier' => 'Supplier',
        'order_date' => 'Order date',
        'label_printed' => 'Label printed',
    ],

    'service_types' => [
        'service' => 'Service',
        'labo' => 'Laboratory',
        'bureau' => 'Office',
    ],

    'building_statuses' => [
        'active' => 'Active',
        'under_renovation' => 'Under renovation',
        'decommissioned' => 'Decommissioned',
    ],

    'local_types' => [
        'bureau' => 'Office',
        'salle_cours' => 'Classroom',
        'amphi' => 'Amphitheater',
        'labo' => 'Laboratory',
        'atelier' => 'Workshop',
        'entrepot' => 'Warehouse',
        'salle_reunion' => 'Meeting room',
        'autre' => 'Other',
    ],

    'local_statuses' => [
        'available' => 'Available',
        'occupied' => 'Occupied',
        'under_maintenance' => 'Under maintenance',
        'closed' => 'Closed',
    ],

    'equipment_statuses' => [
        'in_service' => 'In service',
        'under_repair' => 'Under repair',
        'decommissioned' => 'Decommissioned',
        'lost' => 'Lost',
    ],

    'equipment_conditions' => [
        'new' => 'New',
        'good' => 'Good',
        'worn' => 'Worn',
        'damaged' => 'Damaged',
    ],

    'qr' => [
        'print_label' => 'Print label',
        'label_title' => 'QR label',
        'printed' => 'Label printed',
        'not_printed' => 'Label not printed yet',
        'generated_at' => 'generated :date',
        'missing' => 'No QR code — this record predates QR generation. Contact an administrator.',
    ],

    'lookup' => [
        'report_hint' => 'To report an issue with this asset, please contact the facilities department. Online reporting from this page is coming soon.',
    ],

    'campus_map' => [
        'title' => 'Campus map',
        'rooms' => 'rooms',
        'seats' => 'seats',
        'no_rooms' => 'No rooms registered in this building yet.',
        'select_hint' => 'Click a building marker on the map to see its rooms.',
        'set_position' => 'Set position on map',
        'picking_hint' => 'Click anywhere on the map to place this building.',
        'cancel' => 'Cancel',
        'position_saved' => 'Building position saved.',
        'select_building' => 'Select a building',
        'not_placed' => 'not placed',
        'not_placed_hint' => 'This building has no position yet, so it does not appear on the map. Use "Set position on map" below to place it.',
        'place_reminder_title' => 'Building not placed on the map',
        'place_reminder_body' => '":name" has no coordinates yet. Open the Campus map page, select it in the building list, then use "Set position on map".',
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
