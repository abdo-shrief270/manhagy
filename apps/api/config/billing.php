<?php

/*
 * Subscriptions. Prices in piasters, VAT (14%) included; platform admins override them (billing_catalog).
 * A yearly subscription (when switched on) costs `yearly_months` months. Payment is by InstaPay (the owner sends the transfer reference and
 * a screenshot; a platform admin approves it) or activated by a platform admin directly.
 */
return [
    'trial_days' => 14,
    // After the paid period (or the trial) ends: everything works with a warning, then no new
    // products / users / branches, then read-only. Selling never stops before suspension.
    'grace_days' => 7,
    'suspend_after_days' => 60,

    'vat_basis_points' => 1400,
    'yearly_months' => 10,

    // The platform admin panel (/api/v1/admin/*): answered only on this host (e.g. admin.example.com)
    // and, when set, only from these IPs / CIDR ranges (comma separated). Empty = anywhere (dev, tests).
    'admin' => [
        'domain' => env('ADMIN_DOMAIN'),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_ALLOWED_IPS', ''))))),
        'token_hours' => (int) env('ADMIN_TOKEN_HOURS', 8),
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
    ],

    // Rewards: points the shop earns, turned into credit off its next payment; the welcome a shop
    // invited by another gets (and the inviter's points come only once the new shop really pays).
    'rewards' => [
        'points_per_pound' => 10,
        'min_convert' => 100,
        'points' => [
            'referral' => 500,
            'early_renewal' => 50,
            'yearly' => 200,
            'onboarding' => 100,
        ],
        'referral_discount' => ['percent' => 20, 'months' => 3],
    ],

    // Partners (برنامج الشركاء): people who bring shops with their link earn rate_percent of what each
    // shop pays (without VAT or credit) for `months` from its first payment; a share is held
    // `hold_days` before it can be paid out, in requests of at least `min_payout` piasters.
    'affiliates' => [
        'rate_percent' => (float) env('AFFILIATE_RATE_PERCENT', 20),
        'months' => (int) env('AFFILIATE_MONTHS', 12),
        'hold_days' => (int) env('AFFILIATE_HOLD_DAYS', 14),
        'min_payout' => (int) env('AFFILIATE_MIN_PAYOUT', 20000),
        // A shop that registers with a partner's link gets the same welcome discount as an invited one.
        'welcome_discount' => true,
        'token_days' => 30,
        // Partners' links point at the website (it carries ?aff= to the sign-up page).
        'site_url' => rtrim((string) env('SITE_URL', 'https://manhagy.online'), '/'),
    ],

    // «مدى الحياة»: a one-time price per plan (it covers the first year, payable in installments
    // each covering 12/N months), then a yearly maintenance fee. Only defaults: platform admins edit
    // it all (apps/admin «الأسعار»: billing_settings + billing_catalog). Off until they switch it on.
    // Extra modules without their own lifetime price cost `module_months` of their monthly price
    // for life and `module_maintenance_months` a year (rounded to 50 ج).
    'lifetime' => [
        'name' => 'مدى الحياة',
        'tagline' => 'اشتري السيستم مرة واحدة، وادفع صيانة ودعم بسيطة كل سنة.',
        'installments' => [1, 2, 3, 4],
        'perks' => [
            'السيستم بتاعك مدى الحياة بسعر ثابت',
            'التحديثات والمميزات الجديدة في باقتك من غير زيادة',
            'صيانة ودعم فني بمبلغ بسيط كل سنة',
            'تقدر تقسّط أول دفعة',
        ],
        'affiliate_rate_percent' => 10,
        'allow_coupons' => false,
        'module_months' => 17,
        'module_maintenance_months' => 4,
    ],

    // Yearly subscriptions (a year for `yearly_months` months): off unless an admin turns them on.
    'yearly_enabled' => false,

    'instapay' => [
        'address' => env('BILLING_INSTAPAY_ADDRESS', ''),
        'name' => env('BILLING_INSTAPAY_NAME', ''),
        'phone' => env('BILLING_INSTAPAY_PHONE', ''),
    ],

    // منهجي's plans: priced by the number of active students (assumptions until tested with real
    // teachers; the platform admins change every price from «الأسعار ومدى الحياة»). No optional
    // module exists yet, so plans carry none.
    'plans' => [
        'teacher' => [
            'name' => 'مدرس',
            'description' => 'لحد 300 طالب نشط: الكورسات والفيديو المحمي والأكواد والامتحانات وتقارير ولي الأمر',
            'monthly' => 49900,
            'lifetime' => 900000,
            'maintenance' => 200000,
            'modules' => [],
        ],
        'pro' => [
            'name' => 'مدرس برو',
            'description' => 'لحد 1500 طالب نشط ومساعدين أكتر',
            'monthly' => 99900,
            'lifetime' => 1800000,
            'maintenance' => 350000,
            'modules' => [],
            'featured' => true,
        ],
        'center' => [
            'name' => 'سنتر',
            'description' => 'سنتر بأكتر من مدرس، لحد 3000 طالب نشط',
            'monthly' => 199900,
            'lifetime' => 3500000,
            'maintenance' => 600000,
            'modules' => [],
        ],
    ],

    // What an account says it needs when it registers: each need is covered by optional modules
    // (`Support\Needs`). Empty until the first optional module (السنتر، البث، التطبيق…) is built.
    'needs' => [],

    // Optional modules bought on top of a plan (monthly).
    'modules' => [],
];
