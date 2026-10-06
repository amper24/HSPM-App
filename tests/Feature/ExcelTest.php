<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Export\ExportService;
use App\Services\Import\ExcelValue;
use App\Services\Import\ImportService;
use App\Services\Import\WeeklyScheduleParser;
use App\Services\Schedule\RoomParser;
use App\Services\Schedule\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_import_round_trip_and_formula_protection(): void
    {
        Storage::fake('local');
        Teacher::create(['last_name' => 'Иванов', 'first_name' => 'Иван', 'middle_name' => 'Иванович', 'email' => 'i@example.test', 'department' => 'КиКТ', 'position' => '=1+1']);
        $filename = app(ExportService::class)->create('teachers', 1);
        $path = Storage::disk('local')->path('exports/1/'.$filename);
        $book = IOFactory::load($path);
        $this->assertSame('s', $book->getActiveSheet()->getCell('C3')->getDataType());
        $book->disconnectWorksheets();
        $result = app(ImportService::class)->import($path, 'teachers');
        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseCount('teachers', 1);
        $this->assertDatabaseHas('teachers', ['email' => 'i@example.test', 'position' => '=1+1']);
    }

    public function test_classroom_and_schedule_round_trip(): void
    {
        Storage::fake('local');
        Classroom::create(['room_number' => '008', 'building' => 'Д', 'room_type' => 'Лаборатория', 'seats' => 30]);
        app(ScheduleService::class)->save(['date' => '2026-09-30', 'discipline' => 'Тест', 'time_start' => '08:30:00', 'time_end' => '09:55:00', 'group_code' => 'A', 'classrooms' => 'Д008', 'pair_number' => 1, 'is_occupied' => true]);
        foreach (['classrooms', 'schedule'] as $type) {
            $filename = app(ExportService::class)->create($type, 1);
            app(ImportService::class)->import(Storage::disk('local')->path('exports/1/'.$filename), $type);
        }
        $this->assertDatabaseCount('classrooms', 1);
        $this->assertDatabaseCount('schedule', 1);
        $this->assertDatabaseHas('schedule', ['time_start' => '08:30:00', 'classrooms_raw' => 'Д008']);
    }

    public function test_export_file_is_private_to_its_owner(): void
    {
        Storage::fake('local');
        $owner = User::create(['username' => 'owner', 'password' => 'test-password', 'role' => 'user']);
        $other = User::create(['username' => 'other', 'password' => 'test-password', 'role' => 'user']);
        $url = $this->actingAs($owner)->getJson('/api/export/report')->assertOk()->json('data.url');
        $this->get($url)->assertOk();
        $this->actingAs($other)->get($url)->assertNotFound();
    }

    public function test_dates_times_and_room_numbers_keep_their_meaning(): void
    {
        $this->assertSame(['08:30:00', '09:55:00'], ExcelValue::time('8:30-9:55'));
        $this->assertSame(['08:30:00', null], ExcelValue::time(8.5 / 24));
        $this->assertNull(ExcelValue::date('31.02.2026'));
        $this->assertSame([['building' => 'Д', 'room_number' => '008'], ['building' => 'В', 'room_number' => '102']], RoomParser::parse('Д 008, В102, ДО'));
    }

    public function test_weekly_grid_keeps_merged_lessons_and_repeated_course_headers(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(['Дни нед.', 'Часы', '1-ТИД-1', '№ ауд.', '1-ТИД-2', '№ ауд.'], null, 'A1');
        $sheet->fromArray(['понедельник', '8:30-9:55', 'Общая лекция'], null, 'A2');
        $sheet->mergeCells('C2:E2');
        $sheet->setCellValue('F2', 'Д008');
        $sheet->fromArray(['Дни нед.', 'Часы', '2-ТИД-1', '№ ауд.'], null, 'A4');
        $sheet->fromArray(['вторник', '10:05-11:30', 'Второй курс', 'В102'], null, 'A5');
        $rows = app(WeeklyScheduleParser::class)->parse($sheet);
        $this->assertCount(3, $rows);
        $this->assertSame('1-ТИД-2', $rows[1]['group_code']);
        $this->assertSame('Д008', $rows[1]['classrooms_raw']);
        $this->assertSame('2-ТИД-1', $rows[2]['group_code']);
        $this->assertSame('вторник', $rows[2]['day_of_week']);
    }
}
