<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending')->index();
            $table->boolean('triggered_manually')->default(false);
            $table->unsignedSmallInteger('pages_crawled')->default(0);
            $table->unsignedSmallInteger('pages_with_issues')->default(0);
            $table->unsignedSmallInteger('critical_issues_found')->default(0);
            $table->unsignedSmallInteger('warning_issues_found')->default(0);
            $table->unsignedSmallInteger('info_issues_found')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_runs');
    }
};
