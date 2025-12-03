<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{

    public function up(): void
    {
        $proceduresPath = database_path('sql/procedures/');
        $files = File::files($proceduresPath);
        
        foreach ($files as $file) {
            $sql = File::get($file->getPathname());
            DB::unprepared($sql);
            echo 'Created procedure from: ' . $file->getFilename().PHP_EOL;
        }
    }

    public function down(): void
    {
        DB::statement("DROP PROCEDURE IF EXISTS GetCategoryTreeBrand");
        DB::statement("DROP PROCEDURE IF EXISTS GetCategoryTreeCountry");
        DB::statement("DROP PROCEDURE IF EXISTS GetCategoryTreeForm");
        DB::statement("DROP PROCEDURE IF EXISTS GetCategoryTreeReleaseForm");
    }
};