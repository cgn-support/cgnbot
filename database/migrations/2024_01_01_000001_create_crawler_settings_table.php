<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawler_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('default_crawl_frequency_hours')->default(24);
            $table->unsignedSmallInteger('default_screenshot_frequency_hours')->default(168);
            $table->unsignedTinyInteger('default_max_depth')->default(5);
            $table->unsignedSmallInteger('default_crawl_limit')->default(500);
            $table->unsignedTinyInteger('default_concurrency')->default(3);
            $table->unsignedSmallInteger('default_slow_response_threshold_ms')->default(3000);
            $table->unsignedSmallInteger('default_thin_content_threshold')->default(300);
            $table->unsignedTinyInteger('default_visual_diff_threshold')->default(15);
            $table->unsignedTinyInteger('crawl_runs_to_keep')->default(10);
            $table->unsignedSmallInteger('resolved_issues_retention_days')->default(90);
            $table->string('slack_webhook_url')->nullable();
            $table->string('slack_default_channel')->nullable();
            $table->json('alert_on_severity')->nullable();
            $table->timestamps();
        });

        DB::table('crawler_settings')->insert([
            'alert_on_severity' => json_encode(['critical']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crawler_settings');
    }
};
