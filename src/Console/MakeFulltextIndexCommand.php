<?php

namespace Traore225\LaravelSmartSearch\Console;

use Illuminate\Console\Command;

class MakeFulltextIndexCommand extends Command
{
    protected $signature = 'smart-search:make-index {--table=} {--columns=} {--name=}';
    protected $description = 'Generate a migration to add a FULLTEXT index.';

    public function handle(): int
    {
        $table = (string) $this->option('table');
        $columns = (string) $this->option('columns');
        $name = (string) $this->option('name');

        if ($table === '' || $columns === '') {
            $this->error('Usage: php artisan smart-search:make-index --table=posts --columns=title,description');
            return self::FAILURE;
        }

        $cols = array_values(array_filter(array_map('trim', explode(',', $columns))));
        if (empty($cols)) {
            $this->error('No valid columns provided.');
            return self::FAILURE;
        }

        $indexName = $name !== '' ? $name : ('fulltext_' . $table . '_' . implode('_', $cols));

        $migrationName = 'add_fulltext_index_to_' . $table . '_' . implode('_', $cols);
        $fileName = date('Y_m_d_His') . '_' . $migrationName . '.php';

        $colsSql = implode(', ', array_map(fn($c) => "`{$c}`", $cols));

        $content = <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        \$driver = Schema::getConnection()->getDriverName();
        if (!in_array(\$driver, ['mysql','mariadb'], true)) return;

        if (!Schema::hasTable('{$table}')) return;

        DB::statement("ALTER TABLE `{$table}` ADD FULLTEXT `{$indexName}` ({$colsSql})");
    }

    public function down(): void
    {
        \$driver = Schema::getConnection()->getDriverName();
        if (!in_array(\$driver, ['mysql','mariadb'], true)) return;

        if (!Schema::hasTable('{$table}')) return;

        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
    }
};
PHP;

        $path = database_path('migrations/' . $fileName);
        file_put_contents($path, $content);

        $this->info("✅ Migration created: database/migrations/{$fileName}");
        $this->line("Next: php artisan migrate");

        return self::SUCCESS;
    }
}
