<?php

namespace App\Http\Controllers;

use App\Queries\EducationQuery;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function options(string $resource, string $field, EducationQuery $queries)
    {
        $allowed = ['teachers' => ['departments' => 'department', 'degrees' => 'degree', 'titles' => 'title', 'employment-types' => 'employment_type'], 'classrooms' => ['room-types' => 'room_type'], 'software' => ['buildings' => 'building'], 'schedule' => ['groups' => 'group_code']];
        $column = $allowed[$resource][$field] ?? null;
        abort_unless($column, 404);

        return $this->success($queries->for($resource)->withoutEagerLoads()->reorder()->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column)->pluck($column));
    }

    public function search(Request $request, string $resource, EducationQuery $queries)
    {
        abort_unless(in_array($resource, ['teachers', 'classrooms']), 404);

        return $this->success($queries->for($resource, $request->query())->limit(500)->get());
    }
}
