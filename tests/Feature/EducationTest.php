<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Import\ImportWriter;
use App\Services\Schedule\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EducationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'admin', 'password' => 'test-password', 'role' => 'admin']);
    }

    public function test_guest_cannot_read_personal_data(): void
    {
        $this->getJson('/api/teachers')->assertUnauthorized()->assertJsonPath('success', false);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data', null);
    }

    public function test_login_and_logout_use_laravel_session(): void
    {
        $this->admin();
        $this->postJson('/api/auth/login', ['username' => 'admin', 'password' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['username' => 'admin', 'password' => 'test-password'])->assertOk()->assertJsonMissingPath('data.password');
        $this->getJson('/api/auth/me')->assertJsonPath('data.username', 'admin');
        $this->postJson('/api/auth/logout')->assertOk();
        $this->getJson('/api/teachers')->assertUnauthorized();
    }

    public function test_user_can_read_but_cannot_write_or_manage_users(): void
    {
        $user = User::create(['username' => 'reader', 'password' => 'test-password', 'role' => 'user']);
        $this->actingAs($user)->getJson('/api/teachers')->assertOk();
        $this->postJson('/api/teachers', ['last_name' => 'Иванов', 'first_name' => 'Иван'])->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/import')->assertForbidden();
    }

    public function test_teacher_crud_filters_validation_and_missing_ids(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/teachers', ['last_name' => 'Иванов'])->assertUnprocessable();
        $created = $this->postJson('/api/teachers', ['last_name' => 'Иванов', 'first_name' => 'Иван', 'department' => 'КиКТ'])->assertCreated();
        $id = $created->json('data.id');
        $this->getJson('/api/teachers?search='.urlencode('Иванов Иван'))->assertJsonCount(1, 'data.items');
        $this->getJson('/api/teachers/departments')->assertJsonPath('data.0', 'КиКТ');
        $this->putJson('/api/teachers/'.$id, ['phone' => '123'])->assertOk()->assertJsonPath('data.phone', '123');
        $this->deleteJson('/api/teachers/'.$id)->assertOk();
        $this->getJson('/api/teachers/'.$id)->assertNotFound();
        $this->deleteJson('/api/teachers/'.$id)->assertNotFound();
    }

    public function test_false_filters_and_software_search_are_not_ignored(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/classrooms', ['room_number' => '101', 'building' => 'Д', 'room_type' => 'лекционная', 'has_projector' => false])->assertCreated();
        $this->postJson('/api/classrooms', ['room_number' => '102', 'building' => 'Д', 'room_type' => 'лаборатория', 'has_projector' => true])->assertCreated();
        $this->getJson('/api/classrooms?has_projector=0')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.room_number', '101');
        $this->postJson('/api/software', ['name' => 'LibreOffice'])->assertCreated();
        $this->postJson('/api/software', ['name' => 'Python'])->assertCreated();
        $this->getJson('/api/software?search=Python')->assertJsonCount(1, 'data.items');
        $this->getJson('/api/classrooms?per_page=100000')->assertJsonPath('data.pagination.per_page', 100);
    }

    public function test_schedule_rooms_are_synchronized_and_cancellations_release_rooms(): void
    {
        $this->actingAs($this->admin());
        $record = ['date' => '2026-09-30', 'discipline' => 'Математика', 'group_code' => '1-ТИД-7', 'classrooms' => 'Д101, В102', 'pair_number' => 1, 'is_occupied' => true];
        $id = $this->postJson('/api/schedule', $record)->assertCreated()->json('data.id');
        $this->assertDatabaseCount('schedule_classrooms', 2);
        $this->getJson('/api/classrooms/free?date=2026-09-30&pair_number=1')->assertJsonCount(0, 'data');
        $this->putJson('/api/schedule/'.$id, ['transfer_cancel' => 'отмена'])->assertOk();
        $this->getJson('/api/classrooms/free?date=2026-09-30&pair_number=1')->assertJsonCount(2, 'data');
        $this->putJson('/api/schedule/'.$id, ['classrooms' => 'ДО'])->assertOk()->assertJsonPath('data.classroom_id', null);
        $this->assertDatabaseCount('schedule_classrooms', 0);
    }

    public function test_admin_cannot_remove_last_admin_or_erase_users(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->deleteJson('/api/users/'.$admin->id)->assertUnprocessable();
        $this->putJson('/api/users/'.$admin->id, ['role' => 'user'])->assertUnprocessable();
        $this->postJson('/api/users/truncate')->assertNotFound();
        $this->getJson('/api/users')->assertJsonMissingPath('data.items.0.password');
    }

    public function test_empty_password_does_not_change_existing_hash(): void
    {
        $admin = $this->admin();
        $hash = $admin->password;
        $this->actingAs($admin)->putJson('/api/users/'.$admin->id, ['full_name' => 'Администратор', 'password' => ''])->assertOk();
        $this->assertSame($hash, $admin->fresh()->password);
    }

    public function test_schedule_query_count_is_constant_and_stats_use_one_query(): void
    {
        $this->actingAs($this->admin());
        $service = app(ScheduleService::class);
        for ($index = 0; $index < 20; $index++) {
            $service->save(['date' => '2026-09-30', 'discipline' => 'Предмет '.$index, 'classrooms' => 'Д101, Д102']);
        }
        DB::enableQueryLog();
        $this->getJson('/api/schedule?per_page=1')->assertOk();
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->getJson('/api/schedule?per_page=20')->assertJsonCount(20, 'data.items');
        $large = count(DB::getQueryLog());
        $this->assertSame($small, $large, 'Количество SQL не должно расти на каждой строке расписания');
        DB::flushQueryLog();
        $this->getJson('/api/dashboard/stats')->assertJsonPath('data.schedule', 20);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_import_is_idempotent_and_replaces_only_groups_in_file(): void
    {
        $writer = app(ImportWriter::class);
        $row = ['date' => '2026-09-30', 'time_start' => '08:30:00', 'discipline' => 'Математика', 'group_code' => 'A', 'classrooms_raw' => 'Д101, Д102', 'examiner' => null, 'is_occupied' => 1];
        $writer->save('schedule', [$row], false, false);
        $writer->save('schedule', [$row], false, false);
        $this->assertDatabaseCount('schedule', 1);
        $this->assertDatabaseCount('schedule_classrooms', 2);
        $writer->save('schedule', [array_replace($row, ['group_code' => 'B']), array_replace($row, ['discipline' => 'Старый предмет'])], false, false);
        $writer->save('schedule', [$row], false, true);
        $this->assertDatabaseCount('schedule', 2);
        $this->assertDatabaseHas('schedule', ['group_code' => 'B']);
        $this->assertDatabaseMissing('schedule', ['discipline' => 'Старый предмет']);
    }

    public function test_schedule_bulk_delete_requires_explicit_scope(): void
    {
        $this->actingAs($this->admin())->postJson('/api/schedule/bulk-delete', [])->assertUnprocessable();
    }

    public function test_import_failure_rolls_back_new_rooms(): void
    {
        try {
            app(ImportWriter::class)->save('schedule', [['discipline' => 'Недельное занятие', 'classrooms_raw' => 'Д777', 'group_code' => 'A']], false, true);
            $this->fail('Expected validation failure');
        } catch (ValidationException $exception) {
            $this->assertDatabaseCount('classrooms', 0);
            $this->assertDatabaseCount('schedule', 0);
        }
    }
}
