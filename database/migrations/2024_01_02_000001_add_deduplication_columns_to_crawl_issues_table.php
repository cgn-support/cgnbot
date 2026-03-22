<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_issues', function (Blueprint $table) {
            $table->unsignedTinyInteger('consecutive_detections')->default(1)->after('context');
            $table->foreignId('first_detected_run_id')->nullable()->after('consecutive_detections')
                ->constrained('crawl_runs')->nullOnDelete();
            $table->unsignedTinyInteger('confidence')->default(100)->after('first_detected_run_id');
            $table->timestamp('verified_at')->nullable()->after('confidence');
            $table->string('verified_by', 20)->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('first_detected_run_id');
            $table->dropColumn(['consecutive_detections', 'confidence', 'verified_at', 'verified_by']);
        });
    }
};
