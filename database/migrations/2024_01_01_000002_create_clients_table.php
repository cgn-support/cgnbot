<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_crawled_at')->nullable()->index();
            $table->timestamp('last_screenshot_at')->nullable();
            $table->json('settings')->nullable();
            $table->string('slack_channel')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
