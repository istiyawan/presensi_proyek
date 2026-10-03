<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['individual', 'combined', 'recap']);
            $table->enum('format', ['pdf', 'xlsx'])->default('pdf');
            $table->json('params');
            $table->string('title');
            $table->enum('status', ['queued', 'processing', 'done', 'failed'])->default('queued');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_jobs');
    }
};
