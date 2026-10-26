<?php

return [
    'clubs' => [
        'label' => 'نادي',
        'plural' => 'الأندية',
        'fields' => [
            'name' => 'اسم النادي',
            'slug' => 'المعرّف',
            'status' => 'الحالة',
            'suspended_at' => 'تاريخ الإيقاف',
            'created_at' => 'تاريخ الإنشاء',
        ],
        'status' => [
            'active' => 'نشط',
            'suspended' => 'موقوف',
        ],
        'actions' => [
            'suspend' => 'إيقاف',
            'activate' => 'تفعيل',
            'suspend_confirm' => 'سيتم إيقاف هذا النادي. هل أنت متأكد؟',
            'activate_confirm' => 'سيتم إعادة تفعيل هذا النادي.',
        ],
        'notifications' => [
            'suspended' => 'تم إيقاف النادي',
            'activated' => 'تم تفعيل النادي',
        ],
        'users' => [
            'title' => 'المستخدمون',
            'fields' => [
                'name' => 'الاسم',
                'email' => 'البريد الإلكتروني',
                'locale' => 'اللغة',
                'created_at' => 'تاريخ الانضمام',
            ],
        ],
    ],
];
