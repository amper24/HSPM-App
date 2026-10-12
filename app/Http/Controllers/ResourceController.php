<?php

namespace App\Http\Controllers;

use App\Http\Resources\ScheduleData;
use App\Queries\EducationQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Общие операции над списками справочников.
 *
 * Реализует пагинированный список, чтение одной записи, удаление и очистку.
 * Специфические операции создания/обновления выполняют дочерние контроллеры,
 * которые задают свойство $resource и переопределяют store()/update().
 */
abstract class ResourceController extends Controller
{
    /** Ключ ресурса: teachers|classrooms|software|schedule|users. */
    protected string $resource;

    /**
     * Внедряет фабрику запросов с разрешёнными фильтрами.
     *
     * @param  EducationQuery  $queries Фабрика Eloquent-запросов по ресурсам.
     */
    public function __construct(protected EducationQuery $queries) {}

    /**
     * Пагинированный список записей с фильтрацией.
     *
     * @param  Request  $request Запрос с фильтрами и параметром per_page (1..100, по умолчанию 50).
     * @return \Illuminate\Http\JsonResponse {items, pagination}.
     */
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 50)));
        $page = $this->queries->for($this->resource, $request->query())->paginate($perPage);

        return $this->success([
            'items' => $page->getCollection()->map(fn ($item) => $this->present($item)),
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'pages' => $page->lastPage()],
        ]);
    }

    /**
     * Одна запись по идентификатору.
     *
     * @param  int  $id Идентификатор записи.
     * @return \Illuminate\Http\JsonResponse Запись либо 404, если не найдена.
     */
    public function show(int $id)
    {
        return $this->success($this->present($this->queries->for($this->resource)->findOrFail($id)));
    }

    /**
     * Удаляет запись по идентификатору.
     *
     * Предварительный SELECT не выполняется: результат DELETE (число удалённых строк)
     * сам показывает, существовала ли запись.
     *
     * @param  int  $id Идентификатор записи.
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        // DELETE возвращает число строк; предварительный SELECT не требуется.
        abort_unless($this->queries->for($this->resource)->whereKey($id)->delete(), 404, 'Запись не найдена');

        return $this->success(null, 'Запись удалена');
    }

    /**
     * Полностью очищает таблицу ресурса (доступно администратору).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clear()
    {
        $this->queries->for($this->resource)->delete();

        return $this->success(null, 'Таблица очищена');
    }

    /**
     * Приводит модель к сериализуемому виду.
     *
     * Для расписания используется плоское представление ScheduleData, для остальных
     * справочников — обычный массив атрибутов.
     *
     * @param  Model  $model Запись для представления.
     * @return array<string, mixed>
     */
    protected function present(Model $model): array
    {
        return $this->resource === 'schedule' ? ScheduleData::from($model) : $model->toArray();
    }
}
