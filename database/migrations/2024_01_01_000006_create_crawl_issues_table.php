<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crawl_run_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('issue_type', 100)->index();
            $table->enum('severity', ['critical', 'warning', 'info'])->index();
            $table->json('context')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'severity', 'resolved_at']);
            $table->index(['client_id', 'issue_type', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_issues');
    }
};
