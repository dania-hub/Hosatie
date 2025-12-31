<?php

namespace Database\Seeders;

use App\Models\Drug;
use Illuminate\Database\Seeder;

class DrugSeeder extends Seeder
{
    public function run()
    {
        Drug::create([
            'name' => 'Panadol',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'form' => 'Tablet',
            'category' => 'Analgesic',
            'unit' => 'قرص',
            'max_monthly_dose' => 100,
            'status' => 'متوفر',
            'manufacturer' => 'GSK',
            'country' => 'UK',
            'utilization_type' => 'Acute',
            'warnings' => 'لا تستخدم مع الكحول أو في حال وجود حساسية من الباراسيتامول.',
            'indications' => 'الحمى والصداع وآلام الجسم.',
            'contraindications' => 'وظائف كبد أو كلى ضعيفة بدون استشارة الطبيب.',
            'expiry_date' => '2026-12-31',
        ]);

        Drug::create([
            'name' => 'Amoclan',
            'generic_name' => 'Amoxicillin/Clavulanate',
            'strength' => '1g',
            'form' => 'Tablet',
            'category' => 'Antibiotic',
            'unit' => 'قرص',
            'max_monthly_dose' => 30,
            'status' => 'متوفر',
            'manufacturer' => 'Hikma',
            'country' => 'Jordan',
            'utilization_type' => 'Acute',
            'warnings' => 'أكمل الدورة كاملة وتجنب المشروبات الحمضية.',
            'indications' => 'العدوى البكتيرية التنفسية والجهاز البولي.',
            'contraindications' => 'فرط الحساسية للبنسيلينات.',
            'expiry_date' => '2025-06-30',
        ]);
    }
}
