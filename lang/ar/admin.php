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
            'plan' => 'الخطة',
            'subscription_starts_at' => 'بداية الاشتراك',
            'subscription_ends_at' => 'نهاية الاشتراك',
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
            'assign_plan' => 'تعيين خطة',
        ],
        'notifications' => [
            'suspended' => 'تم إيقاف النادي',
            'activated' => 'تم تفعيل النادي',
            'plan_assigned' => 'تم تعيين الخطة',
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

    'plans' => [
        'label' => 'خطة',
        'plural' => 'الخطط',
        'unlimited' => 'بلا حد',
        'unlimited_hint' => 'اتركه فارغاً لعدد غير محدود.',
        'fields' => [
            'name' => 'الاسم',
            'name_en' => 'الاسم (إنجليزي)',
            'name_ar' => 'الاسم (عربي)',
            'slug' => 'المعرّف',
            'price' => 'السعر (جنيه)',
            'duration_months' => 'المدة (بالأشهر)',
            'max_players' => 'أقصى عدد لاعبين',
            'max_coaches' => 'أقصى عدد مدربين',
            'is_active' => 'مفعّلة',
        ],
    ],
];
