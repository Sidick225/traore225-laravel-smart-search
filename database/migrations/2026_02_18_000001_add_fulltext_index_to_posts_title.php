<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Important:
        // - MySQL/MariaDB: FULLTEXT supported
        // - SQLite: ignored
        // - PostgreSQL: different strategy (GIN/tsvector) -> ignored here
        $driver = Schema::getConnection()->getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        // Check table/column
        if (!Schema::hasTable('posts') || !Schema::hasColumn('posts', 'title')) {
            return;
        }

        $exists = DB::selectOne("
            SELECT COUNT(1) as c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'posts'
            AND INDEX_NAME = 'fulltext_posts_title'
        ");

        if (($exists->c ?? 0) > 0) {
            return;
        }

        // Add FULLTEXT (raw SQL for compatibility)
        // Note: MySQL may require compatible engine/charset.
        DB::statement('ALTER TABLE posts ADD FULLTEXT fulltext_posts_title (title)');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (!Schema::hasTable('posts')) {
            return;
        }

        $exists = DB::selectOne("
            SELECT COUNT(1) as c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'posts'
            AND INDEX_NAME = 'fulltext_posts_title'
        ");

        if (($exists->c ?? 0) === 0) {
            return;
        }

        DB::statement('ALTER TABLE posts DROP INDEX fulltext_posts_title');
    }
};
