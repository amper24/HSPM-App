<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // В новой базе индексы уже созданы. Здесь добавляем их при принятии старой базы.
        foreach ([['schedule', ['date', 'pair_number']], ['schedule', ['classroom_id', 'date', 'is_occupied']], ['schedule', ['group_code', 'date']], ['schedule_classrooms', ['classroom_id', 'schedule_id']]] as [$name,$columns]) {
            if (! Schema::hasIndex($name, $columns)) {
                Schema::table($name, fn (Blueprint $table) => $table->index($columns));
            }
        }
    }

    public function down(): void {}
};
