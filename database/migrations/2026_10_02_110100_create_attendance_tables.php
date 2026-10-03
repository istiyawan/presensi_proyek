<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');

            foreach (['check_in', 'check_out'] as $p) {
                // uuid dari aplikasi → kirim ulang (offline sync) tidak membuat data ganda
                $table->uuid("{$p}_uuid")->nullable()->unique();
                $table->dateTime("{$p}_at")->nullable();
                $table->decimal("{$p}_lat", 10, 7)->nullable();
                $table->decimal("{$p}_lng", 10, 7)->nullable();
                $table->decimal("{$p}_accuracy", 8, 2)->nullable();
                $table->foreignId("{$p}_location_id")->nullable()->constrained('project_locations')->nullOnDelete();
                $table->unsignedInteger("{$p}_distance_m")->nullable();
                $table->enum("{$p}_mode", ['onsite', 'offsite'])->nullable();
                $table->string("{$p}_note")->nullable();
                $table->string("{$p}_photo")->nullable();
                $table->string("{$p}_thumb")->nullable();
                $table->boolean("{$p}_offline")->default(false);
                $table->dateTime("{$p}_device_time")->nullable();
                $table->dateTime("{$p}_received_at")->nullable();
                $table->boolean("{$p}_is_mock")->default(false);
                $table->json("{$p}_device")->nullable();
            }

            $table->enum('status', ['hadir', 'terlambat', 'izin', 'sakit', 'cuti', 'alpha', 'libur']);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_holiday_work')->default(false);
            $table->enum('offsite_approval', ['none', 'pending', 'approved', 'rejected'])->default('none');
            $table->foreignId('offsite_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('offsite_approved_at')->nullable();
            $table->foreignId('leave_request_id')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            // mis. ["time_suspicious","low_accuracy","missing_checkout"]
            $table->json('flags')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'project_id', 'work_date']);
            $table->index(['project_id', 'work_date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->enum('type', ['izin', 'sakit', 'cuti']);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('attachment')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['employee_id', 'start_date']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('leave_request_id')->references('id')->on('leave_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['leave_request_id']);
        });
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendances');
    }
};
