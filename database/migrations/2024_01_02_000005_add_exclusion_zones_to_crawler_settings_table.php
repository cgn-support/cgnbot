<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawler_settings', function (Blueprint $table) {
            $table->json('default_visual_diff_exclusion_zones')->nullable()->after('default_visual_diff_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('crawler_settings', function (Blueprint $table) {
            $table->dropColumn('default_visual_diff_exclusion_zones');
        });
    }
};
