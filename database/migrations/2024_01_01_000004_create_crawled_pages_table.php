<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawled_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crawl_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('redirect_url')->nullable();
            $table->unsignedTinyInteger('redirect_count')->default(0);
            $table->text('canonical_url')->nullable();
            $table->boolean('canonical_is_self')->nullable();
            $table->string('meta_title', 512)->nullable();
            $table->unsignedSmallInteger('meta_title_length')->default(0);
            $table->string('meta_description', 1024)->nullable();
            $table->unsignedSmallInteger('meta_description_length')->default(0);
            $table->string('h1', 512)->nullable();
            $table->unsignedTinyInteger('h1_count')->default(0);
            $table->unsignedSmallInteger('word_count')->default(0);
            $table->boolean('is_indexable')->default(true);
            $table->boolean('in_sitemap')->default(false);
            $table->boolean('has_schema_markup')->default(false);
            $table->json('schema_types')->nullable();
            $table->unsignedSmallInteger('internal_links_count')->default(0);
            $table->unsignedSmallInteger('external_links_count')->default(0);
            $table->unsignedSmallInteger('broken_links_count')->default(0);
            $table->unsignedSmallInteger('response_time_ms')->nullable();
            $table->char('page_hash', 64)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'crawl_run_id']);
            $table->index(['crawl_run_id', 'status_code']);
        });

        DB::statement('ALTER TABLE crawled_pages ADD INDEX crawled_pages_client_url_index (client_id, url(255))');
    }

    public function down(): void
    {
        Schema::dropIfExists('crawled_pages');
    }
};
