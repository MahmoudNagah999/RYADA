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
            'plan' => 'Plan',
            'subscription_starts_at' => 'Subscription starts',
            'subscription_ends_at' => 'Subscription ends',
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
            'assign_plan' => 'Assign plan',
        ],
        'notifications' => [
            'suspended' => 'Club suspended',
            'activated' => 'Club activated',
            'plan_assigned' => 'Plan assigned',
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

    'plans' => [
        'label' => 'Plan',
        'plural' => 'Plans',
        'unlimited' => 'Unlimited',
        'unlimited_hint' => 'Leave empty for unlimited.',
        'fields' => [
            'name' => 'Name',
            'name_en' => 'Name (English)',
            'name_ar' => 'Name (Arabic)',
            'slug' => 'Slug',
            'price' => 'Price (EGP)',
            'duration_months' => 'Duration (months)',
            'max_players' => 'Max players',
            'max_coaches' => 'Max coaches',
            'is_active' => 'Active',
        ],
    ],
];
