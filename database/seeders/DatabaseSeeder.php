<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Criminal' => ['Notice of Appeal', 'Petition of Appeal', 'Judgment', 'Ruling', 'Proceedings', 'Charge Sheet', 'Bail Application', 'Other'],
            'Civil' => ['Plaint', 'Written Statement of Defence', 'Judgment', 'Ruling', 'Application', 'Affidavit', 'Other'],
            'Administrative' => ['Correspondence', 'Report', 'Notice', 'Memorandum', 'Other'],
            'Correspondence' => ['Other'],
            'Other' => ['Other'],
        ];
        foreach ($types as $cat => $names) {
            $c = DocumentCategory::firstOrCreate(['name' => $cat]);
            foreach ($names as $n) {
                DocumentType::firstOrCreate(['document_category_id' => $c->id, 'name' => $n]);
            }
        }

        User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Administrator',
            'email' => env('ADMIN_EMAIL', 'admin@example.com'),
            'password' => env('ADMIN_PASSWORD', 'Admin123'),
            'role' => 'admin',
            'can_export' => true,
            'is_active' => true,
        ]);
    }
}
