<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TaskController extends Controller
{
  public function index(IndexTaskRequest $request): View
  {
    $userId = $this->currentUserId();
    $filters = $this->normalizeListFilters($request->validated());

    return view('tasks.index', [
      'tasks' => $this->listForUser($userId, $filters),
    ]);
  }

  public function create(): View
  {
    return view('tasks.create');
  }

  public function store(StoreTaskRequest $request): RedirectResponse
  {
    $data = $request->validated();
    unset($data['user_id']);
    $userId = $this->currentUserId();
    $this->createTask($userId, $this->normalizeTaskPayload($data));

    return redirect()
      ->route('tasks.index')
      ->with('status', 'タスクを作成しました。');
  }

  public function edit(string $id): View
  {
    $task = $this->findOwnedTask($this->parseTaskId($id));

    return view('tasks.edit', compact('task'));
  }

  public function update(UpdateTaskRequest $request, string $id): RedirectResponse
  {
    $taskId = $this->parseTaskId($id);
    $data = $request->validated();
    unset($data['user_id']);
    $this->updateTask($this->findOwnedTask($taskId), $this->normalizeTaskPayload($data));

    return redirect()
      ->route('tasks.index', $request->only(['title', 'status', 'priority', 'due_date_sort', 'priority_sort']))
      ->with('status', 'タスクを更新しました。');
  }

  public function destroy(string $id): RedirectResponse
  {
    $this->deleteTask($this->findOwnedTask($this->parseTaskId($id)));

    return redirect()
      ->route('tasks.index')
      ->with('status', 'タスクを削除しました。');
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
   * @param  array{title?: string, status?: string, priority?: string, due_date_sort?: string, priority_sort?: string}  $filters
   * @return Collection<int, Task>
   */
  private function listForUser(int $userId, array $filters = []): Collection
  {
    $query = Task::query()->where('user_id', $userId);

    $title = $filters['title'] ?? null;
    if (is_string($title) && $title !== '') {
      $query->where('title', 'like', '%'.$this->escapeLike($title).'%');
    }

    $status = $filters['status'] ?? null;
    if (is_string($status) && $status !== '') {
      $query->where('status', $status);
    }

    $priority = $filters['priority'] ?? null;
    if (is_string($priority) && $priority !== '') {
      $query->where('priority', $priority);
    }

    $prioritySort = $filters['priority_sort'] ?? null;
    if ($prioritySort === 'asc' || $prioritySort === 'desc') {
      $priorityDirection = $prioritySort === 'desc' ? 'desc' : 'asc';
      $query->orderByRaw(
        "CASE priority WHEN 'low' THEN 0 WHEN 'medium' THEN 1 WHEN 'high' THEN 2 ELSE 1 END {$priorityDirection}"
      );
    }

    $dueSort = $filters['due_date_sort'] ?? 'asc';
    $direction = $dueSort === 'desc' ? 'desc' : 'asc';
    $query->orderByRaw('due_date IS NULL DESC')->orderBy('due_date', $direction);

    $query->orderBy('id');

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
   * @return array{title?: string, status?: string, priority?: string, due_date_sort?: string, priority_sort?: string}
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

    if (isset($query['priority']) && is_string($query['priority'])) {
      $priority = trim($query['priority']);
      if ($priority !== '' && in_array($priority, config('task.priority_values'), true)) {
        $filters['priority'] = $priority;
      }
    }

    if (isset($query['due_date_sort']) && $query['due_date_sort'] === 'desc') {
      $filters['due_date_sort'] = 'desc';
    } elseif (isset($query['due_date_sort']) && $query['due_date_sort'] === 'asc') {
      $filters['due_date_sort'] = 'asc';
    }

    if (isset($query['priority_sort']) && $query['priority_sort'] === 'desc') {
      $filters['priority_sort'] = 'desc';
    } elseif (isset($query['priority_sort']) && $query['priority_sort'] === 'asc') {
      $filters['priority_sort'] = 'asc';
    }

    return $filters;
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  private function normalizeTaskPayload(array $data): array
  {
    $allowed = ['title', 'description', 'status', 'priority', 'due_date'];
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
