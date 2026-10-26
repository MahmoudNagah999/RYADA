<?php

return [
    'clubs' => [
        'label' => 'Club',
        'plural' => 'Clubs',
        'fields' => [
            'name' => 'Club name',
            'slug' => 'Slug',
            'status' => 'Status',
            'suspended_at' => 'Suspended at',
            'created_at' => 'Created at',
        ],
        'status' => [
            'active' => 'Active',
            'suspended' => 'Suspended',
        ],
        'actions' => [
            'suspend' => 'Suspend',
            'activate' => 'Activate',
            'suspend_confirm' => 'This club will be suspended. Are you sure?',
            'activate_confirm' => 'This club will be activated again.',
        ],
        'notifications' => [
            'suspended' => 'Club suspended',
            'activated' => 'Club activated',
        ],
        'users' => [
            'title' => 'Users',
            'fields' => [
                'name' => 'Name',
                'email' => 'Email',
                'locale' => 'Language',
                'created_at' => 'Joined at',
            ],
        ],
    ],
];
