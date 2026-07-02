<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TaskController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index(IndexTaskRequest $request): JsonResponse
  {
    $userId = $this->currentUserId();
    $filters = $this->normalizeListFilters($request->validated());
    $collection = $this->listForUser($userId, $filters);

    return TaskResource::collection($collection)->response();
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(StoreTaskRequest $request): JsonResponse
  {
    $data = $request->validated();
    unset($data['user_id']);
    $userId = $this->currentUserId();
    $task = $this->createTask($userId, $this->normalizeTaskPayload($data));

    return (new TaskResource($task))->response()->setStatusCode(201);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(UpdateTaskRequest $request, string $id): JsonResponse
  {
    $taskId = $this->parseTaskId($id);
    $data = $request->validated();
    unset($data['user_id']);
    $task = $this->updateTask($this->findOwnedTask($taskId), $this->normalizeTaskPayload($data));

    return (new TaskResource($task))->response();
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(string $id): Response
  {
    $this->deleteTask($this->findOwnedTask($this->parseTaskId($id)));

    return response()->noContent();
  }

  private function currentUserId(): int
  {
    $id = Auth::id();

    if ($id === null) {
      throw new AuthenticationException;
    }

    return (int) $id;
  }

  /**
   * @param  array{title?: string, status?: string, due_date_sort?: string}  $filters
   * @return Collection<int, Task>
   */
  private function listForUser(int $userId, array $filters = []): Collection
  {
    $query = Task::query()->where('user_id', $userId);

    $title = $filters['title'] ?? null;
    if (is_string($title) && $title !== '') {
      $query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($this->escapeLike($title)).'%']);
    }

    $status = $filters['status'] ?? null;
    if (is_string($status) && $status !== '') {
      $query->where('status', $status);
    }

    $dueSort = $filters['due_date_sort'] ?? 'asc';
    $direction = $dueSort === 'desc' ? 'desc' : 'asc';
    $query->orderByRaw('due_date IS NULL DESC')->orderBy('due_date', $direction)->orderBy('id');

    /** @var Collection<int, Task> */
    return $query->get();
  }

  /**
   * @param  array<string, mixed>  $attributes
   */
  private function createTask(int $userId, array $attributes): Task
  {
    $task = new Task;
    $task->fill($attributes);
    $task->user_id = $userId;
    $task->save();

    return $task->fresh() ?? $task;
  }

  private function findOwnedTask(int $taskId): Task
  {
    $userId = $this->currentUserId();

    /** @var Task|null $task */
    $task = Task::query()
      ->where('user_id', $userId)
      ->whereKey($taskId)
      ->first();

    if ($task === null) {
      throw (new ModelNotFoundException)->setModel(Task::class, [$taskId]);
    }

    return $task;
  }

  /**
   * @param  array<string, mixed>  $attributes
   */
  private function updateTask(Task $task, array $attributes): Task
  {
    $task->fill($attributes);
    $task->save();

    return $task->fresh() ?? $task;
  }

  private function deleteTask(Task $task): void
  {
    $task->delete();
  }

  /**
   * @param  array<string, mixed>  $query
   * @return array{title?: string, status?: string, due_date_sort?: string}
   */
  private function normalizeListFilters(array $query): array
  {
    $filters = [];

    if (isset($query['title']) && is_string($query['title'])) {
      $title = trim($query['title']);
      if ($title !== '') {
        $filters['title'] = $title;
      }
    }

    if (isset($query['status']) && is_string($query['status'])) {
      $status = trim($query['status']);
      if ($status !== '') {
        $filters['status'] = $status;
      }
    }

    if (isset($query['due_date_sort']) && $query['due_date_sort'] === 'desc') {
      $filters['due_date_sort'] = 'desc';
    } elseif (isset($query['due_date_sort']) && $query['due_date_sort'] === 'asc') {
      $filters['due_date_sort'] = 'asc';
    }

    return $filters;
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  private function normalizeTaskPayload(array $data): array
  {
    $allowed = ['title', 'description', 'status', 'due_date'];
    $data = array_intersect_key($data, array_flip($allowed));

    if (array_key_exists('title', $data) && is_string($data['title'])) {
      $data['title'] = trim($data['title']);
    }

    if (array_key_exists('description', $data)) {
      $desc = $data['description'];
      if ($desc === null || $desc === '') {
        $data['description'] = null;
      } elseif (is_string($desc)) {
        $trimmed = trim($desc);
        $data['description'] = $trimmed === '' ? null : $trimmed;
      }
    }

    return $data;
  }

  private function escapeLike(string $value): string
  {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
  }

  private function parseTaskId(string $id): int
  {
    if (! ctype_digit($id)) {
      abort(404);
    }

    return (int) $id;
  }
}
