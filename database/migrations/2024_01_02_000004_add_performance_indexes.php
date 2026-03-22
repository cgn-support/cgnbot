<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_issues', function (Blueprint $table) {
            $table->index(['crawl_run_id', 'alerted_at']);
            $table->index(['client_id', 'crawl_run_id', 'resolved_at']);
        });

        Schema::table('crawled_pages', function (Blueprint $table) {
            $table->index(['crawl_run_id', 'is_indexable']);
        });
    }

    public function down(): void
    {
        Schema::table('crawl_issues', function (Blueprint $table) {
            $table->dropIndex(['crawl_run_id', 'alerted_at']);
            $table->dropIndex(['client_id', 'crawl_run_id', 'resolved_at']);
        });

        Schema::table('crawled_pages', function (Blueprint $table) {
            $table->dropIndex(['crawl_run_id', 'is_indexable']);
        });
    }
};
