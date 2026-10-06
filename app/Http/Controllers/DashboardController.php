<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        // Один SQL вместо четырёх COUNT и четырёх ненужных выборок списков.
        $counts = DB::query()->selectSub(DB::table('teachers')->selectRaw('count(*)'), 'teachers')
            ->selectSub(DB::table('classrooms')->selectRaw('count(*)'), 'classrooms')
            ->selectSub(DB::table('schedule')->selectRaw('count(*)'), 'schedule')
            ->selectSub(DB::table('software')->selectRaw('count(*)'), 'software')->first();

        return $this->success($counts);
    }
}
