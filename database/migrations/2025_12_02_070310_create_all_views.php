<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{

    public function up(): void
    {
        $viewsPath = database_path('sql/viewes/');
        
        if (!File::exists($viewsPath)) {
            throw new \Exception("Directory '{$viewsPath}' not found!");
        }

        $files = $this->sortedViewFiles($viewsPath);

        if (empty($files)) {
            throw new \Exception("No SQL files found in '{$viewsPath}'!");
        }

        foreach ($files as $file) {
            $sql = File::get($file);
            
            $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
            
            try {
                DB::unprepared($sql);
                echo "Successfully created view from: " . basename($file) . PHP_EOL;
            } catch (\Exception $e) {
                throw new \Exception("Error executing {$file}: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $viewsPath = database_path('sql/viewes/');
        
        if (!File::exists($viewsPath)) {
            return;
        }

        $files = array_reverse($this->sortedViewFiles($viewsPath));

        foreach ($files as $file) {
            $sql = File::get($file);

            if (preg_match('/CREATE\s+(?:OR\s+REPLACE\s+)?VIEW\s+([^\s\(]+)/i', $sql, $matches)) {
                $viewName = trim($matches[1], '`"[]');
                
                try {
                    DB::statement("DROP VIEW IF EXISTS `{$viewName}`");
                    echo "Dropped view: {$viewName}" . PHP_EOL;
                } catch (\Exception $e) {

                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function sortedViewFiles(string $viewsPath): array
    {
        $files = File::glob($viewsPath . '*.sql');
        natsort($files);

        return array_values($files);
    }
};
