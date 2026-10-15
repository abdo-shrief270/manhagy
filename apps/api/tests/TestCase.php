<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Behave like Redis: values go through serialize(), and objects are not allowed back out.
        config(['cache.stores.array.serialize' => true]);

        // Billing mechanics are tested against fixed test plans built from the test-only modules
        // (tests/Fixtures/Modules), not منهجي's real prices.
        config([
            'billing.plans' => [
                'basic' => ['name' => 'أساسية', 'description' => 'الأساس', 'monthly' => 34900, 'lifetime' => 600000, 'maintenance' => 150000, 'modules' => ['extra']],
                'plus' => ['name' => 'بلس', 'description' => 'الأساس + التجربة', 'monthly' => 49900, 'lifetime' => 850000, 'maintenance' => 180000, 'modules' => ['demo', 'extra']],
                'pro' => ['name' => 'برو', 'description' => 'بلس + التقارير وأدوات السنتر', 'monthly' => 64900, 'lifetime' => 1050000, 'maintenance' => 210000, 'modules' => ['demo', 'reports_plus', 'center_tools', 'extra'], 'featured' => true],
                'max' => ['name' => 'ماكس', 'description' => 'كل اللي في برو + الفروع', 'monthly' => 94900, 'lifetime' => 1600000, 'maintenance' => 300000, 'modules' => ['demo', 'reports_plus', 'center_tools', 'extra', 'branches']],
            ],
            'billing.modules' => ['demo' => 19900, 'center_tools' => 14900, 'reports_plus' => 7900, 'web_page' => 14900, 'branches' => 29900, 'extra' => 0],
            'billing.needs' => [
                'demo' => ['label' => 'تجربة', 'description' => 'للتيستات', 'icon' => 'i-lucide-flask-conical', 'modules' => ['demo'], 'types' => ['teacher']],
                'reports_plus' => ['label' => 'تقارير', 'description' => 'للتيستات', 'icon' => 'i-lucide-chart-column', 'modules' => ['reports_plus'], 'types' => []],
                'web_page' => ['label' => 'صفحة', 'description' => 'للتيستات', 'icon' => 'i-lucide-globe', 'modules' => ['web_page'], 'types' => []],
                'branches' => ['label' => 'فروع', 'description' => 'للتيستات', 'icon' => 'i-lucide-building', 'modules' => ['branches'], 'types' => []],
                'center_tools' => ['label' => 'أدوات السنتر', 'description' => 'للتيستات', 'icon' => 'i-lucide-school', 'modules' => ['center_tools'], 'types' => ['center']],
            ],
        ]);
    }
}
