<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawler_settings', function (Blueprint $table) {
            $table->string('webhook_url')->nullable()->after('slack_default_channel');
        });
    }
};
