<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawler_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('alert_min_consecutive_detections')->default(2)->after('alert_on_severity');
            $table->unsignedTinyInteger('alert_min_confidence')->default(70)->after('alert_min_consecutive_detections');
        });
    }

    public function down(): void
    {
        Schema::table('crawler_settings', function (Blueprint $table) {
            $table->dropColumn(['alert_min_consecutive_detections', 'alert_min_confidence']);
        });
    }
};
