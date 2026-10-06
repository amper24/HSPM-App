<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing installations must be adopted explicitly; never silently mix schemas.
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 50);
            $table->string('password', 255);
            $table->string('role', 20)->default('user');
            $table->string('full_name', 255)->nullable();
            $table->timestamps();
            $table->unique(['username']);
        });
        Schema::create('classrooms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('room_number', 20);
            $table->string('building', 5);
            $table->string('room_type', 50)->default('');
            $table->text('software_installed')->nullable();
            $table->integer('seats')->nullable();
            $table->boolean('has_projector')->nullable()->default(0);
            $table->boolean('has_speakers')->nullable()->default(0);
            $table->integer('computers_count')->nullable()->default(0);
            $table->timestamps();
            $table->unique(['room_number', 'building']);
            $table->index(['building']);
            $table->index(['room_type']);
        });
        Schema::create('teachers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('position', 255)->nullable();
            $table->string('degree', 100)->nullable();
            $table->string('title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('employment_type', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['last_name', 'first_name', 'middle_name']);
            $table->index(['department']);
            $table->index(['employment_type']);
        });
        Schema::create('schedule', function (Blueprint $table) {
            $table->increments('id');
            $table->string('dedup_key', 64);
            $table->unsignedInteger('classroom_id')->nullable();
            $table->string('classrooms_raw', 255)->nullable();
            $table->unsignedInteger('teacher_id')->nullable();
            $table->string('numerator_denominator', 20)->nullable();
            $table->date('date')->nullable();
            $table->string('day_of_week', 20)->nullable();
            $table->string('discipline', 255)->nullable();
            $table->string('group_department', 100)->nullable();
            $table->string('group_code', 50)->nullable();
            $table->string('teacher_department', 100)->nullable();
            $table->string('teacher_position', 100)->nullable();
            $table->string('examiner', 255)->nullable();
            $table->string('exam_type', 20)->nullable();
            $table->date('session_start')->nullable();
            $table->date('session_end')->nullable();
            $table->integer('pair_number')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->boolean('is_nonstandard_time')->nullable()->default(0);
            $table->string('lesson_type', 20)->nullable();
            $table->boolean('is_occupied')->nullable()->default(0);
            $table->string('transfer_cancel', 20)->nullable()->default('нет');
            $table->timestamps();
            $table->unique(['dedup_key']);
            $table->index(['date', 'pair_number']);
            $table->index(['classroom_id', 'date', 'is_occupied']);
            $table->index(['teacher_id']);
            $table->index(['group_code', 'date']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('set null');
        });
        Schema::create('software', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('classroom_id')->nullable();
            $table->string('room_number', 20)->nullable();
            $table->string('building', 5)->nullable();
            $table->string('name', 255);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['room_number', 'building', 'name']);
            $table->index(['classroom_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('set null');
        });
        Schema::create('schedule_classrooms', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('schedule_id');
            $table->unsignedInteger('classroom_id');
            $table->unique(['schedule_id', 'classroom_id']);
            $table->index(['classroom_id', 'schedule_id']);
            $table->foreign('schedule_id')->references('id')->on('schedule')->onDelete('cascade');
            $table->foreign('classroom_id')->references('id')->on('classrooms')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        foreach (['schedule_classrooms', 'software', 'schedule', 'teachers', 'classrooms', 'users'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
