<?php

namespace App\Http\Controllers;

use App\Http\Resources\ScheduleData;
use App\Queries\EducationQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Общие операции списков. Специфические записи обрабатывают дочерние контроллеры. */
abstract class ResourceController extends Controller
{
    protected string $resource;

    public function __construct(protected EducationQuery $queries) {}

    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 50)));
        $page = $this->queries->for($this->resource, $request->query())->paginate($perPage);

        return $this->success([
            'items' => $page->getCollection()->map(fn ($item) => $this->present($item)),
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'pages' => $page->lastPage()],
        ]);
    }

    public function show(int $id)
    {
        return $this->success($this->present($this->queries->for($this->resource)->findOrFail($id)));
    }

    public function destroy(int $id)
    {
        // DELETE возвращает число строк; предварительный SELECT не требуется.
        abort_unless($this->queries->for($this->resource)->whereKey($id)->delete(), 404, 'Запись не найдена');

        return $this->success(null, 'Запись удалена');
    }

    public function clear()
    {
        $this->queries->for($this->resource)->delete();

        return $this->success(null, 'Таблица очищена');
    }

    protected function present(Model $model): array
    {
        return $this->resource === 'schedule' ? ScheduleData::from($model) : $model->toArray();
    }
}
