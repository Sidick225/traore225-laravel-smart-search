<?php

namespace Traore225\LaravelSmartSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SmartSearchInstallCommand extends Command
{
    protected $signature = 'smart-search:install {--table=posts} {--column=title}';
    protected $description = 'Check Smart Search requirements (DB driver + FULLTEXT index).';

    public function handle(): int
    {
        $table = (string) $this->option('table');
        $column = (string) $this->option('column');

        $driver = Schema::getConnection()->getDriverName();
        $this->info("DB driver: {$driver}");

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->warn("FULLTEXT boolean search is supported only on MySQL/MariaDB by default. (Driver: {$driver})");
            return self::SUCCESS;
        }

        if (!Schema::hasTable($table)) {
            $this->error("Table '{$table}' not found.");
            return self::FAILURE;
        }

        if (!Schema::hasColumn($table, $column)) {
            $this->error("Column '{$column}' not found on table '{$table}'.");
            return self::FAILURE;
        }

        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Index_type='FULLTEXT'");
        $hasFulltextOnColumn = false;

        foreach ($indexes as $idx) {
            if (($idx->Column_name ?? null) === $column) {
                $hasFulltextOnColumn = true;
                break;
            }
        }

        if ($hasFulltextOnColumn) {
            $this->info("[OK] FULLTEXT index found for {$table}.{$column}");
            $this->info('You can use MATCH() AGAINST() safely.');
            return self::SUCCESS;
        }

        $this->warn("[WARN] No FULLTEXT index found for {$table}.{$column}");
        $this->line('If you included the package migration, run: php artisan migrate');
        $this->line('Or add it manually:');
        $this->line("  ALTER TABLE {$table} ADD FULLTEXT fulltext_{$table}_{$column} ({$column});");

        return self::SUCCESS;
    }
}
