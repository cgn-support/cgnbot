<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_runs', function (Blueprint $table) {
            $table->json('context')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_runs', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
