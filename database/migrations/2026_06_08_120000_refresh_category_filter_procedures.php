<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    private const PROCEDURE_FILES = [
        'GetCategoryTreeForm.sql',
        'GetCategoryTreeBrand.sql',
        'GetCategoryTreeCountry.sql',
        'GetCategoryTreeReleaseForm.sql',
    ];

    public function up(): void
    {
        $proceduresPath = database_path('sql/procedures/');

        foreach (self::PROCEDURE_FILES as $filename) {
            $path = $proceduresPath . $filename;
            if (!File::exists($path)) {
                throw new RuntimeException("Procedure file not found: {$path}");
            }

            DB::unprepared(File::get($path));
        }
    }

    public function down(): void
    {
        foreach (self::PROCEDURE_FILES as $filename) {
            $name = str_replace('.sql', '', $filename);
            DB::statement("DROP PROCEDURE IF EXISTS {$name}");
        }
    }
};
