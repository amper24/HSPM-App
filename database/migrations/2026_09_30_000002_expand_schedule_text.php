<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Недельные сетки содержат описание занятия и примечания в одной ячейке.
        Schema::table('schedule', function (Blueprint $table) {
            $table->text('discipline')->nullable()->change();
            $table->text('examiner')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Не сужаем поля: rollback не должен обрезать сохранённый текст.
    }
};
