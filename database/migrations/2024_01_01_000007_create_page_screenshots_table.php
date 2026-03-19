<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('file_path');
            $table->string('disk')->default('local');
            $table->string('siteshot_job_id')->nullable();
            $table->unsignedSmallInteger('viewport_width')->default(1440);
            $table->boolean('full_page')->default(true);
            $table->foreignId('previous_screenshot_id')->nullable()->constrained('page_screenshots')->nullOnDelete();
            $table->decimal('diff_percentage', 5, 2)->nullable();
            $table->string('diff_image_path')->nullable();
            $table->timestamp('captured_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_screenshots');
    }
};
