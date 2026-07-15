# シナリオ: バックエンド API 仕様変更（priority 追加）

## 目次

- [0. この実験について](#0-この実験について)
- [1. 概要](#1-概要)
- [2. 事前条件チェック](#2-事前条件チェック)
- [3. 修正対象ファイル一覧](#3-修正対象ファイル一覧)
- [4. 実施手順](#4-実施手順)
  - [Phase 0: ブランチ作成](#phase-0-ブランチ作成)
  - [Phase 1: baseline メトリクス](#phase-1-baseline-メトリクス)
  - [Phase 2: 変更適用（テスト・Postman 未着手）](#phase-2-変更適用テストpostman-未着手)
  - [Phase 3: after_update メトリクス](#phase-3-after_update-メトリクス)
  - [Phase 4: テスト・Postman 修正 → CI 緑](#phase-4-テストpostman-修正-ci-緑)
  - [Phase 5: after_fix メトリクス・記録](#phase-5-after_fix-メトリクス・記録)
- [5. 完了条件](#5-完了条件)
- [6. 触らないファイルとその理由](#6-触らないファイルとその理由)
- [関連](#関連)

以下が、**一覧表示・フィルタ・並び替えを含む完全版**の legacy 向け実施手順書です。前回と同じレイアウトで、そのままコピペできます。行番号は **main ブランチ**（`experiment-baseline-v1` 相当）基準です。

---

## 0. この実験について

タスク REST API と Web フォームに新属性 `priority`（`low` / `medium` / `high`、デフォルト `medium`）を追加する実験です。作成・更新だけでなく、**一覧での表示・フィルタ（`?priority=`）・並び替え（`?priority_sort=`）** まで含め、priority 追加として自然な Web / API 体験を揃えます。legacy 構成（Controller 内に正規化・一覧クエリロジックあり）と improved 構成とで修正が何ファイルに分散するかを測ります。

**legacy 構成の補足:** Service / Repository 層は存在せず、正規化・一覧クエリが `Web\TaskController` と `API\TaskController` に重複している。improved が `TaskService` / `TaskRepository` に集約する修正に加え、**同一内容を Controller 2 ファイルで個別に直す**必要がある（Web を先、API を後）。

## 1. 概要

| 項目 | 値 |
| --- | --- |
| リポジトリ | legacy |
| 実験の内容 | API 仕様変更 — priority 属性追加（一覧表示・フィルタ・並び替え含む） |
| ブランチ名 | exp/api-spec-change-priority |
| 参照MD | docs/scenarios/api-spec-change-priority.md |

## 2. 事前条件チェック

- [ ]  experiment-baseline-v1 または CI 緑 — ベースラインは `title` / `description` / `due_date` / `status` の 4 属性のみで、比較の起点になる
- [ ]  Docker 起動 — `artisan migrate`・PHPUnit・Newman・`check-quality.sh` がコンテナ前提のため
- [ ]  PostgreSQL（status数値化・タイトル検索のみ） — 本シナリオのマイグレーションは標準の `Schema::table` であり PostgreSQL 専用 SQL は不要

## 3. 修正対象ファイル一覧

| # | ファイルパス | 修正箇所 | フェーズ | 作業内容 | 解説（なぜ触るか） |
| --- | --- | --- | --- | --- | --- |
| 1 | `database/migrations/*_add_priority_to_tasks_table.php`（新規） | `up()` / `down()` | 2 | `priority` カラム追加 | DB に属性を永続化するため |
| 2 | `config/task.php` | 配列末尾 | 2 | `priority_values` 追加 | バリデーション・フォーム・フィルタで値を共有するため |
| 3 | `app/Models/Task.php` | PHPDoc・`#[Fillable]` | 2 | `priority` 追加 | マスアサインメントと型注釈を揃えるため |
| 4 | `app/Http/Resources/TaskResource.php` | `toArray()` 行 21–27 | 2 | JSON に `priority` 追加 | API レスポンスに新属性を含めるため |
| 5 | `app/Http/Requests/StoreTaskRequest.php` | `rules()` 行 26–31 | 2 | `priority` バリデーション追加 | 作成時の入力検証のため |
| 6 | `app/Http/Requests/UpdateTaskRequest.php` | `rules()` 行 26–31 | 2 | `priority` バリデーション追加 | 更新時の入力検証のため |
| 7 | `app/Http/Requests/IndexTaskRequest.php` | `prepareForValidation()`・`rules()` | 2 | `priority` / `priority_sort` 追加 | 一覧クエリ `?priority=` / `?priority_sort=` の HTTP 境界 |
| 8 | `app/Http/Controllers/Web/TaskController.php` | `normalizeTaskPayload()` 行 194 | 2 | `$allowed` に `priority` 追加 | Web 作成・更新の正規化（Service がないため Controller 内） |
| 9 | `app/Http/Controllers/Web/TaskController.php` | `normalizeListFilters()` 行 157–186 | 2 | `priority` / `priority_sort` 正規化 | Web 一覧フィルタの正規化 |
| 10 | `app/Http/Controllers/Web/TaskController.php` | `listForUser()` 行 85–108 | 2 | priority フィルタ・CASE 並び替え | Web 一覧クエリ（Repository がないため Controller 内） |
| 11 | `app/Http/Controllers/Web/TaskController.php` | `update()` 行 61 | 2 | リダイレクトクエリに priority 追加 | 更新後も一覧フィルタ状態を維持するため |
| 12 | `app/Http/Controllers/API/TaskController.php` | `normalizeTaskPayload()` 行 188 | 2 | `$allowed` に `priority` 追加 | API 作成・更新の正規化（Web と同内容を重複修正） |
| 13 | `app/Http/Controllers/API/TaskController.php` | `normalizeListFilters()` 行 151–180 | 2 | `priority` / `priority_sort` 正規化 | API 一覧フィルタの正規化 |
| 14 | `app/Http/Controllers/API/TaskController.php` | `listForUser()` 行 79–102 | 2 | priority フィルタ・CASE 並び替え | API 一覧クエリ |
| 15 | `resources/views/tasks/_form.blade.php` | status と due_date の間 | 2 | priority の select 追加 | 作成・編集で priority を送れるようにするため |
| 16 | `resources/views/tasks/index.blade.php` | フィルタ・テーブル全体 | 2 | 列・フィルタ・並び替え UI 追加 | 設定した priority を一覧で見える・絞れる・並べ替えられるようにするため |
| 17 | `tests/Feature/TaskApiTest.php` | 各テストメソッド | 4 | 期待値・新テスト追加 | API の priority 挙動を CI で固定するため |
| 18 | `tests/Feature/TaskWebTest.php` | store / update テスト | 4 | DB 期待値・入力追加 | Web 経由の priority を CI で固定するため |
| 19 | `tests/Feature/TaskListFilterTest.php` | `seedTasks()`・新メソッド | 4 | priority フィルタ・ソートテスト | 一覧の priority フィルタ・並び替えを Web/API で固定するため |
| 20 | `postman/Task-API.postman_collection.json` | POST / PUT の test スクリプト | 4 | `priority` アサーション追加 | Newman CI で API 契約を検証するため |

## 4. 実施手順

### Phase 0: ブランチ作成

**この Phase の目的:** ベースラインタグから実験ブランチを切り、以降の diff を `experiment-baseline-v1` 基準で計測できるようにする。

**Step 0-1.** 実験ブランチを作成する。

```bash
git checkout -b exp/api-spec-change-priority experiment-baseline-v1
```

---

### Phase 1: baseline メトリクス

**この Phase の目的:** 変更前の状態を記録し、あとで diff 比較できるようにする。

**Step 1-1.** baseline フェーズのメトリクスを取得する。

```bash
composer experiment:metrics -- --phase baseline --diff-ref experiment-baseline-v1
```

**Step 1-2.** baseline を GitHub に push し、draft PR を作成して CI 緑を確認する

**この Step の目的:** exp ブランチへの push だけでは CI が走らない（push トリガーは `main`/`master` のみ）。フェーズ別の CI 結果を GitHub 上に残すため、この時点で draft PR を1本作り、以降の各 push を同じ PR に積む。ブランチはタグと同一で差分ゼロのため、`gh pr create` を成立させる anchor として空コミットを1つ積む（app ファイル差分を増やさないので `git_app` メトリクスに影響しない）。

```bash
git commit --allow-empty -m "chore(exp): baseline anchor for api-spec-change-priority"
git push -u origin exp/api-spec-change-priority
gh pr create --draft --base main --head exp/api-spec-change-priority \
  --title "exp: api-spec-change-priority（legacy）" \
  --body "$(cat <<'EOF'
実験用 PR。マージはしない。

## Test plan（フェーズ別 CI）
- [ ] baseline コミットで CI 4 ジョブ緑
- [ ] after_update コミットで CI 結果を記録（非破壊的なため fail 0 のこともある）
- [ ] after_fix コミットで CI 4 ジョブすべて成功
EOF
)"
gh pr checks exp/api-spec-change-priority --watch
```

GitHub Actions（4 ジョブ）が緑になることを確認し、失敗0を RECORD.md の baseline 行に記録する。

---

### Phase 2: 変更適用（テスト・Postman 未着手）

**この Phase の目的:** 仕様変更を本番コードに適用し、テスト未修正の中間状態（after_update）を計測できるようにする。

**Step 2-1.** マイグレーションファイルを生成する。

```bash
docker compose exec app php artisan make:migration add_priority_to_tasks_table
```

**Step 2-2.** 生成されたマイグレーションを編集する。

- **ファイル:** `database/migrations/YYYY_MM_DD_HHMMSS_add_priority_to_tasks_table.php`（Step 2-1 で生成されたパス）
- **場所:** `up()` / `down()` メソッド
- **解説:** `tasks` テーブルに `priority` 列を追加し、未指定時は DB デフォルト `medium` が入るようにする。
- **変更前:**

```php
public function up(): void
{
    //
}

public function down(): void
{
    //
}
```

- **変更後:**

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

public function up(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->string('priority')->default('medium');
    });
}

public function down(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->dropColumn('priority');
    });
}
```

**Step 2-3.** マイグレーションを実行する。

```bash
docker compose exec app php artisan migrate
```

**Step 2-4.** 設定ファイルに priority 定義を追加する。

- **ファイル:** `config/task.php`
- **場所:** 配列全体（行 3–6）
- **解説:** `status_values` と同様のパターンで、FormRequest・Blade・一覧フィルタが同じ許可値を参照できるようにする。
- **変更前:**

```php
return [
  'default_user_email' => env('DEFAULT_TASK_USER_EMAIL', 'test@example.com'),
  'status_values' => ['todo', 'in_progress', 'done'],
];
```

- **変更後:**

```php
return [
  'default_user_email' => env('DEFAULT_TASK_USER_EMAIL', 'test@example.com'),
  'status_values' => ['todo', 'in_progress', 'done'],
  'priority_values' => ['low', 'medium', 'high'],
];
```

**Step 2-5.** Model に `priority` を追加する。

- **ファイル:** `app/Models/Task.php`
- **場所:** PHPDoc（行 10–17）・`#[Fillable]`（行 18）
- **解説:** Controller 内の `fill()` で `priority` が保存されるよう、マスアサイン可能属性と型注釈を更新する。
- **変更前:**

```php
/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $due_date
 */
#[Fillable(['user_id', 'title', 'description', 'status', 'due_date'])]
```

- **変更後:**

```php
/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property Carbon|null $due_date
 */
#[Fillable(['user_id', 'title', 'description', 'status', 'priority', 'due_date'])]
```

**Step 2-6.** API レスポンスに `priority` を含める。

- **ファイル:** `app/Http/Resources/TaskResource.php`
- **場所:** `TaskResource::toArray()` 行 21–27
- **解説:** JSON シリアライズ層で新属性を公開し、API 契約を拡張する。
- **変更前:**

```php
return [
  'id' => $this->id,
  'title' => $this->title,
  'status' => $this->status,
  'due_date' => $this->due_date?->format('Y-m-d'),
  'description' => $this->description,
];
```

- **変更後:**

```php
return [
  'id' => $this->id,
  'title' => $this->title,
  'status' => $this->status,
  'priority' => $this->priority,
  'due_date' => $this->due_date?->format('Y-m-d'),
  'description' => $this->description,
];
```

**Step 2-7.** 作成リクエストのバリデーションを追加する。

- **ファイル:** `app/Http/Requests/StoreTaskRequest.php`
- **場所:** `StoreTaskRequest::rules()` 行 26–31
- **解説:** 省略時は DB デフォルトに任せ、送信時のみ `low` / `medium` / `high` を許可する。
- **変更前:**

```php
return [
  'title' => ['required', 'string', 'max:255'],
  'description' => ['nullable', 'string'],
  'status' => ['required', 'string', Rule::in(config('task.status_values'))],
  'due_date' => ['nullable', 'date'],
];
```

- **変更後:**

```php
return [
  'title' => ['required', 'string', 'max:255'],
  'description' => ['nullable', 'string'],
  'status' => ['required', 'string', Rule::in(config('task.status_values'))],
  'priority' => ['nullable', 'string', Rule::in(config('task.priority_values'))],
  'due_date' => ['nullable', 'date'],
];
```

**Step 2-8.** 更新リクエストのバリデーションを追加する。

- **ファイル:** `app/Http/Requests/UpdateTaskRequest.php`
- **場所:** `UpdateTaskRequest::rules()` 行 26–31
- **解説:** 部分更新（`sometimes`）に合わせ、送信された `priority` のみ検証する。
- **変更前:**

```php
return [
  'title' => ['sometimes', 'required', 'string', 'max:255'],
  'description' => ['sometimes', 'nullable', 'string'],
  'status' => ['sometimes', 'required', 'string', Rule::in(config('task.status_values'))],
  'due_date' => ['sometimes', 'nullable', 'date'],
];
```

- **変更後:**

```php
return [
  'title' => ['sometimes', 'required', 'string', 'max:255'],
  'description' => ['sometimes', 'nullable', 'string'],
  'status' => ['sometimes', 'required', 'string', Rule::in(config('task.status_values'))],
  'priority' => ['sometimes', 'nullable', 'string', Rule::in(config('task.priority_values'))],
  'due_date' => ['sometimes', 'nullable', 'date'],
];
```

**Step 2-9.** 一覧リクエストに priority フィルタ・並び替えを追加する。

- **ファイル:** `app/Http/Requests/IndexTaskRequest.php`
- **場所:** `prepareForValidation()` 行 23、`rules()` 行 41–45
- **解説:** Web / API 共通の一覧入口で `?priority=` / `?priority_sort=` を検証する。Controller 2 箇所より先に HTTP 境界を定義する。
- **変更前:**

```php
foreach (['title', 'status', 'due_date_sort'] as $key) {
```

```php
return [
  'title' => ['nullable', 'string', 'max:255'],
  'status' => ['nullable', 'string', Rule::in(config('task.status_values'))],
  'due_date_sort' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
];
```

- **変更後:**

```php
foreach (['title', 'status', 'priority', 'due_date_sort', 'priority_sort'] as $key) {
```

```php
return [
  'title' => ['nullable', 'string', 'max:255'],
  'status' => ['nullable', 'string', Rule::in(config('task.status_values'))],
  'priority' => ['nullable', 'string', Rule::in(config('task.priority_values'))],
  'due_date_sort' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
  'priority_sort' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
];
```

**Step 2-10.** Web TaskController の正規化許可リストに `priority` を追加する。

- **ファイル:** `app/Http/Controllers/Web/TaskController.php`
- **場所:** `normalizeTaskPayload()` 行 194
- **解説:** legacy では improved の `TaskService::normalizeTaskPayload` に相当する処理が Web Controller 内にある。許可リストに無いキーは破棄されるため、ここに `priority` を追加しないと Web 経由の作成・更新で保存されない。
- **変更前:**

```php
$allowed = ['title', 'description', 'status', 'due_date'];
```

- **変更後:**

```php
$allowed = ['title', 'description', 'status', 'priority', 'due_date'];
```

**Step 2-11.** Web TaskController の `normalizeListFilters` に priority を追加する。

- **ファイル:** `app/Http/Controllers/Web/TaskController.php`
- **場所:** PHPDoc 159行目、`normalizeListFilters()` 172–183行目の後（`return` の前）
- **解説:** Web 一覧の `?priority=` / `?priority_sort=` を正規化する。legacy では Service がないため Controller 内で実施する。
- **変更前:**

```php
   * @return array{title?: string, status?: string, due_date_sort?: string}
```

```php
    if (isset($query['due_date_sort']) && $query['due_date_sort'] === 'desc') {
      $filters['due_date_sort'] = 'desc';
    } elseif (isset($query['due_date_sort']) && $query['due_date_sort'] === 'asc') {
      $filters['due_date_sort'] = 'asc';
    }

    return $filters;
```

- **変更後:**

```php
   * @return array{title?: string, status?: string, priority?: string, due_date_sort?: string, priority_sort?: string}
```

```php
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
```

**Step 2-12.** Web TaskController の `listForUser` に priority フィルタ・並び替えを追加する。

- **ファイル:** `app/Http/Controllers/Web/TaskController.php`
- **場所:** PHPDoc 86行目、`listForUser()` 98–105行目
- **解説:** 正規化済みフィルタで DB クエリを組み立てる。`priority` は string のため辞書順ではなく CASE 式で low → medium → high の意味順に並べる。
- **変更前:**

```php
   * @param  array{title?: string, status?: string, due_date_sort?: string}  $filters
```

```php
    $status = $filters['status'] ?? null;
    if (is_string($status) && $status !== '') {
      $query->where('status', $status);
    }

    $dueSort = $filters['due_date_sort'] ?? 'asc';
    $direction = $dueSort === 'desc' ? 'desc' : 'asc';
    $query->orderByRaw('due_date IS NULL DESC')->orderBy('due_date', $direction)->orderBy('id');
```

- **変更後:**

```php
   * @param  array{title?: string, status?: string, priority?: string, due_date_sort?: string, priority_sort?: string}  $filters
```

```php
    $status = $filters['status'] ?? null;
    if (is_string($status) && $status !== '') {
      $query->where('status', $status);
    }

    $priority = $filters['priority'] ?? null;
    if (is_string($priority) && $priority !== '') {
      $query->where('priority', $priority);
    }

    $dueSort = $filters['due_date_sort'] ?? 'asc';
    $direction = $dueSort === 'desc' ? 'desc' : 'asc';
    $query->orderByRaw('due_date IS NULL DESC')->orderBy('due_date', $direction);

    $prioritySort = $filters['priority_sort'] ?? null;
    if ($prioritySort === 'asc' || $prioritySort === 'desc') {
      $priorityDirection = $prioritySort === 'desc' ? 'desc' : 'asc';
      $query->orderByRaw(
        "CASE priority WHEN 'low' THEN 0 WHEN 'medium' THEN 1 WHEN 'high' THEN 2 ELSE 1 END {$priorityDirection}"
      );
    }

    $query->orderBy('id');
```

**Step 2-13.** Web TaskController の更新後リダイレクトに priority クエリを追加する。

- **ファイル:** `app/Http/Controllers/Web/TaskController.php`
- **場所:** `update()` 行 61
- **解説:** 一覧で priority フィルタ・並び替えを適用した状態から編集した場合、更新後も同じ一覧状態に戻れるようにする（既存の `title` / `status` / `due_date_sort` と同パターン）。
- **変更前:**

```php
      ->route('tasks.index', $request->only(['title', 'status', 'due_date_sort']))
```

- **変更後:**

```php
      ->route('tasks.index', $request->only(['title', 'status', 'priority', 'due_date_sort', 'priority_sort']))
```

**Step 2-14.** API TaskController の正規化許可リストに `priority` を追加する。

- **ファイル:** `app/Http/Controllers/API/TaskController.php`
- **場所:** `normalizeTaskPayload()` 行 188
- **解説:** Web と API は別入口だが正規化ロジックが重複している。Web だけ直すと REST API の POST/PUT で `priority` が保存されない。
- **変更前:**

```php
$allowed = ['title', 'description', 'status', 'due_date'];
```

- **変更後:**

```php
$allowed = ['title', 'description', 'status', 'priority', 'due_date'];
```

**Step 2-15.** API TaskController の `normalizeListFilters` に priority を追加する。

- **ファイル:** `app/Http/Controllers/API/TaskController.php`
- **場所:** PHPDoc 153行目、`normalizeListFilters()` 166–177行目の後（`return` の前）
- **解説:** API 一覧の `?priority=` / `?priority_sort=` を正規化する。Web 側 Step 2-11 と対になる修正。
- **変更前:**

```php
   * @return array{title?: string, status?: string, due_date_sort?: string}
```

```php
    if (isset($query['due_date_sort']) && $query['due_date_sort'] === 'desc') {
      $filters['due_date_sort'] = 'desc';
    } elseif (isset($query['due_date_sort']) && $query['due_date_sort'] === 'asc') {
      $filters['due_date_sort'] = 'asc';
    }

    return $filters;
```

- **変更後:**

```php
   * @return array{title?: string, status?: string, priority?: string, due_date_sort?: string, priority_sort?: string}
```

```php
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
```

**Step 2-16.** API TaskController の `listForUser` に priority フィルタ・並び替えを追加する。

- **ファイル:** `app/Http/Controllers/API/TaskController.php`
- **場所:** PHPDoc 80行目、`listForUser()` 92–99行目
- **解説:** API 一覧エンドポイントでも Web と同じ priority フィルタ・意味順ソートを提供する。片方だけ直すと Web/API 間で一覧挙動がずれる。
- **変更前:**

```php
   * @param  array{title?: string, status?: string, due_date_sort?: string}  $filters
```

```php
    $status = $filters['status'] ?? null;
    if (is_string($status) && $status !== '') {
      $query->where('status', $status);
    }

    $dueSort = $filters['due_date_sort'] ?? 'asc';
    $direction = $dueSort === 'desc' ? 'desc' : 'asc';
    $query->orderByRaw('due_date IS NULL DESC')->orderBy('due_date', $direction)->orderBy('id');
```

- **変更後:**

```php
   * @param  array{title?: string, status?: string, priority?: string, due_date_sort?: string, priority_sort?: string}  $filters
```

```php
    $status = $filters['status'] ?? null;
    if (is_string($status) && $status !== '') {
      $query->where('status', $status);
    }

    $priority = $filters['priority'] ?? null;
    if (is_string($priority) && $priority !== '') {
      $query->where('priority', $priority);
    }

    $dueSort = $filters['due_date_sort'] ?? 'asc';
    $direction = $dueSort === 'desc' ? 'desc' : 'asc';
    $query->orderByRaw('due_date IS NULL DESC')->orderBy('due_date', $direction);

    $prioritySort = $filters['priority_sort'] ?? null;
    if ($prioritySort === 'asc' || $prioritySort === 'desc') {
      $priorityDirection = $prioritySort === 'desc' ? 'desc' : 'asc';
      $query->orderByRaw(
        "CASE priority WHEN 'low' THEN 0 WHEN 'medium' THEN 1 WHEN 'high' THEN 2 ELSE 1 END {$priorityDirection}"
      );
    }

    $query->orderBy('id');
```

**Step 2-17.** 作成・編集フォームに priority の select を追加する。

- **ファイル:** `resources/views/tasks/_form.blade.php`
- **場所:** status フィールド（行 31–41）の直後、due_date の前
- **解説:** 作成・編集画面から priority を送れるようにする。
- **変更前:**

```php
    <div class="app-form-field">
        <x-input-label for="status" value="ステータス" />
        <x-select-input id="status" name="status" required class="block w-full">
            @foreach (config('task.status_values') as $status)
                <option value="{{ $status }}" @selected(old('status', $task?->status ?? '') === $status)>
                    {{ $status }}
                </option>
            @endforeach
        </x-select-input>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>

    <div class="app-form-field">
        <x-input-label for="due_date" value="期限" />
```

- **変更後:**

```php
    <div class="app-form-field">
        <x-input-label for="status" value="ステータス" />
        <x-select-input id="status" name="status" required class="block w-full">
            @foreach (config('task.status_values') as $status)
                <option value="{{ $status }}" @selected(old('status', $task?->status ?? '') === $status)>
                    {{ $status }}
                </option>
            @endforeach
        </x-select-input>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>

    <div class="app-form-field">
        <x-input-label for="priority" value="優先度" />
        <x-select-input id="priority" name="priority" class="block w-full">
            @foreach (config('task.priority_values') as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $task?->priority ?? 'medium') === $priority)>
                    {{ $priority }}
                </option>
            @endforeach
        </x-select-input>
        <x-input-error :messages="$errors->get('priority')" class="mt-2" />
    </div>

    <div class="app-form-field">
        <x-input-label for="due_date" value="期限" />
```

**Step 2-18.** 一覧画面に優先度列・フィルタ・並び替えを追加する。

- **ファイル:** `resources/views/tasks/index.blade.php`
- **場所:** フィルタフォーム（行 27–65）、テーブルヘッダ（行 74–78）、テーブル行（行 84–86）、空行 colspan（行 103）
- **解説:** 作成・編集で設定した priority を一覧で確認・絞り込み・並び替えできるようにする。9行目の `session('status')` はフラッシュメッセージ用のため変更しない。
- **変更前（フィルタ: status の直後）:**

```php
                <div class="app-form-field mb-0 min-w-[8rem] flex-1">
                    <x-input-label for="filter-status" value="ステータス" />
                    ...
                </div>

                <div class="app-form-field mb-0 min-w-[10rem] flex-1">
                    <x-input-label value="期限並び替え" />
```

- **変更後（フィルタ: status の直後に priority フィルタ・並び替えを挿入）:**

```php
                <div class="app-form-field mb-0 min-w-[8rem] flex-1">
                    <x-input-label for="filter-status" value="ステータス" />
                    ...
                </div>

                <div class="app-form-field mb-0 min-w-[8rem] flex-1">
                    <x-input-label for="filter-priority" value="優先度" />
                    <x-select-input id="filter-priority" name="priority" class="block w-full">
                        <option value="">すべて</option>
                        @foreach (config('task.priority_values') as $priority)
                            <option value="{{ $priority }}" @selected(old('priority', request('priority')) === $priority)>
                                {{ $priority }}
                            </option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('priority')" class="mt-1" />
                </div>

                <div class="app-form-field mb-0 min-w-[10rem] flex-1">
                    <x-input-label value="期限並び替え" />
                    ...
                </div>

                <div class="app-form-field mb-0 min-w-[10rem] flex-1">
                    <x-input-label value="優先度並び替え" />
                    <div class="app-radio-group">
                        <label class="app-radio-label">
                            <input
                                type="radio"
                                name="priority_sort"
                                value="asc"
                                class="app-radio"
                                @checked(old('priority_sort', request('priority_sort', 'asc')) === 'asc')
                            >
                            昇順
                        </label>
                        <label class="app-radio-label">
                            <input
                                type="radio"
                                name="priority_sort"
                                value="desc"
                                class="app-radio"
                                @checked(old('priority_sort', request('priority_sort')) === 'desc')
                            >
                            降順
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('priority_sort')" class="mt-1" />
                </div>
```

- **変更前（テーブル）:**

```php
                        <th>タイトル</th>
                        <th>ステータス</th>
                        <th>期限</th>
                        <th class="text-right">操作</th>
```

```php
                            <td class="font-medium text-gray-900">{{ $task->title }}</td>
                            <td>{{ $task->status }}</td>
                            <td>{{ $task->due_date?->format('Y-m-d') ?? '-' }}</td>
```

```php
                            <td colspan="4" class="py-8 text-center text-gray-500">タスクがありません</td>
```

- **変更後（テーブル）:**

```php
                        <th>タイトル</th>
                        <th>ステータス</th>
                        <th>優先度</th>
                        <th>期限</th>
                        <th class="text-right">操作</th>
```

```php
                            <td class="font-medium text-gray-900">{{ $task->title }}</td>
                            <td>{{ $task->status }}</td>
                            <td>{{ $task->priority }}</td>
                            <td>{{ $task->due_date?->format('Y-m-d') ?? '-' }}</td>
```

```php
                            <td colspan="5" class="py-8 text-center text-gray-500">タスクがありません</td>
```

**Step 2-19.** フロントエンド資産をビルドする（Blade 変更の反映）。

```bash
composer npm:docker-build
```

---

### Phase 3: after_update メトリクス

**この Phase の目的:** テスト未修正のまま、どれだけ壊れたかを数値化する。

**Step 3-1.** 変更をコミットする。

```bash
git add -A
git commit -m "feat(tasks): add priority field (scenario update, tests not yet updated)"
```

**Step 3-2.** after_update フェーズのメトリクスを取得する。

```bash
composer experiment:metrics -- --phase after_update --diff-ref experiment-baseline-v1
```

> **補足:** 非破壊的変更のため、既存テストが `priority` を検証していなければ `phpunit.fail` / `newman.fail` は **0** になる可能性がある。それでもメトリクスは記録し、Phase 4 で契約テストを追加する。
> 

**Step 3-3.** after_update コミットを push し、CI 結果を確認・記録する

**この Step の目的:** 更新直後の状態を GitHub Actions 上にも残す。本シナリオは非破壊的なため fail 0（CI 緑）のこともあれば、seed 不整合で `php-tests`・`api-tests` が赤になることもある。いずれの結果でも失敗ジョブ数を RECORD.md の after_update 行に記録する。

```bash
git push origin exp/api-spec-change-priority
gh pr checks exp/api-spec-change-priority --watch
```

> **注意:** `ci.yml` は `concurrency: cancel-in-progress` のため、run が完了する前に次の push（Phase 5）を行うとキャンセルされる。`--watch` で CI 完了を待ってから Phase 4 に進む。

---

### Phase 4: テスト・Postman 修正 → CI 緑

**この Phase の目的:** 仕様変更に合わせてテストを直し、修正工数（after_fix）を計測可能にする。

**Step 4-1.** API テストに priority の期待値と検証を追加する。

- **ファイル:** `tests/Feature/TaskApiTest.php`
- **場所:** `test_store_returns_201_with_created_task()` 行 57–59、`test_update_returns_200()` 行 97–103、ファイル末尾（新メソッド）
- **解説:** デフォルト値・明示指定・不正値の 3 パターンを API テストで固定する。
- **変更前（store テスト抜粋）:**

```php
$response->assertCreated();
$response->assertJsonPath('data.title', 'New Task');
$this->assertDatabaseHas('tasks', ['title' => 'New Task']);
```

- **変更後（store テスト抜粋）:**

```php
$response->assertCreated();
$response->assertJsonPath('data.title', 'New Task');
$response->assertJsonPath('data.priority', 'medium');
$this->assertDatabaseHas('tasks', ['title' => 'New Task', 'priority' => 'medium']);
```

- **変更前（update テスト抜粋）:**

```php
$response = $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", [
  'title' => 'Updated',
  'status' => 'in_progress',
]);

$response->assertOk();
$response->assertJsonPath('data.title', 'Updated');
```

- **変更後（update テスト抜粋）:**

```php
$response = $this->actingAs($this->user)->putJson("/api/tasks/{$task->id}", [
  'title' => 'Updated',
  'status' => 'in_progress',
  'priority' => 'high',
]);

$response->assertOk();
$response->assertJsonPath('data.title', 'Updated');
$response->assertJsonPath('data.priority', 'high');
```

- **追加（ファイル末尾、`test_destroy_return_204_and_removes_row()` の後）:**

```php
/** POST priority 不正 → 422 */
public function test_store_with_invalid_priority_returns_422(): void
{
  $response = $this->actingAs($this->user)->postJson('/api/tasks', [
    'title' => 't',
    'status' => 'todo',
    'priority' => 'urgent',
  ]);

  $response->assertStatus(422);
  $response->assertJsonValidationErrors('priority');
}
```

**Step 4-2.** Web テストに priority の期待値を追加する。

- **ファイル:** `tests/Feature/TaskWebTest.php`
- **場所:** `test_store_creates_task_and_redirects()` 行 48–57、`test_update_changes_task_and_redirects()` 行 80–93
- **解説:** Web フォーム経由でも priority が保存・更新されることを DB アサーションで確認する。
- **変更前（store テスト）:**

```php
$response = $this->actingAs($this->user)->post('/tasks', [
  'title' => 'New web task',
  'status' => 'todo',
]);

$response->assertRedirect(route('tasks.index'));
$this->assertDatabaseHas('tasks', [
  'user_id' => $this->user->id,
  'title' => 'New web task',
]);
```

- **変更後（store テスト）:**

```php
$response = $this->actingAs($this->user)->post('/tasks', [
  'title' => 'New web task',
  'status' => 'todo',
  'priority' => 'low',
]);

$response->assertRedirect(route('tasks.index'));
$this->assertDatabaseHas('tasks', [
  'user_id' => $this->user->id,
  'title' => 'New web task',
  'priority' => 'low',
]);
```

- **変更前（update テスト）:**

```php
$response = $this->actingAs($this->user)->put("/tasks/{$task->id}", [
  'title' => 'After',
  'status' => 'in_progress',
]);

$response->assertRedirect(route('tasks.index', [
  'title' => 'After',
  'status' => 'in_progress',
]));
$this->assertDatabaseHas('tasks', [
  'id' => $task->id,
  'title' => 'After',
  'status' => 'in_progress',
]);
```

- **変更後（update テスト）:**

```php
$response = $this->actingAs($this->user)->put("/tasks/{$task->id}", [
  'title' => 'After',
  'status' => 'in_progress',
  'priority' => 'high',
]);

$response->assertRedirect(route('tasks.index', [
  'title' => 'After',
  'status' => 'in_progress',
]));
$this->assertDatabaseHas('tasks', [
  'id' => $task->id,
  'title' => 'After',
  'status' => 'in_progress',
  'priority' => 'high',
]);
```

**Step 4-3.** 一覧フィルタ・ソートテストに priority を追加する。

- **ファイル:** `tests/Feature/TaskListFilterTest.php`
- **場所:** `seedTasks()` 行 127–151、ファイル末尾（新メソッド 4 件）
- **解説:** Web / API 両方で `?priority=` フィルタと `?priority_sort=` 意味順ソートを固定する。`seedTasks()` の各タスクに異なる `priority` を付与する。
- **変更前（`seedTasks()` 抜粋）:**

```php
Task::query()->create([
  'user_id' => $this->user->id,
  'title' => 'Foo task',
  'description' => null,
  'status' => 'todo',
  'due_date' => null,
]);

Task::query()->create([
  'user_id' => $this->user->id,
  'title' => 'Bar task',
  ...
  'status' => 'done',
  'due_date' => '2026-06-01',
]);

Task::query()->create([
  'user_id' => $this->user->id,
  'title' => 'Baz task',
  ...
  'status' => 'in_progress',
  'due_date' => '2026-06-15',
]);
```

- **変更後（`seedTasks()` 抜粋）:**

```php
Task::query()->create([
  'user_id' => $this->user->id,
  'title' => 'Foo task',
  'description' => null,
  'status' => 'todo',
  'priority' => 'low',
  'due_date' => null,
]);

Task::query()->create([
  'user_id' => $this->user->id,
  'title' => 'Bar task',
  ...
  'status' => 'done',
  'priority' => 'high',
  'due_date' => '2026-06-01',
]);

Task::query()->create([
  'user_id' => $this->user->id,
  'title' => 'Baz task',
  ...
  'status' => 'in_progress',
  'priority' => 'medium',
  'due_date' => '2026-06-15',
]);
```

- **追加（ファイル末尾）:**

```php
public function test_web_index_filters_by_priority(): void
{
  $this->seedTasks();

  $response = $this->actingAs($this->user)->get('/tasks?priority=high');

  $response->assertOk();
  $response->assertSee('Bar task', false);
  $response->assertDontSee('Foo task', false);
  $response->assertDontSee('Baz task', false);
}

public function test_web_index_sorts_priority_asc(): void
{
  $this->seedTasks();

  $response = $this->actingAs($this->user)->get('/tasks?priority_sort=asc');

  $response->assertOk();
  $content = $response->getContent();
  $this->assertNotFalse($content);
  $fooPos = strpos($content, 'Foo task');
  $bazPos = strpos($content, 'Baz task');
  $barPos = strpos($content, 'Bar task');
  $this->assertNotFalse($fooPos);
  $this->assertNotFalse($bazPos);
  $this->assertNotFalse($barPos);
  $this->assertLessThan($bazPos, $fooPos);
  $this->assertLessThan($barPos, $bazPos);
}

public function test_api_index_filters_by_priority(): void
{
  $this->seedTasks();

  $response = $this->actingAs($this->user)->getJson('/api/tasks?priority=high');

  $response->assertOk();
  $titles = collect($response->json('data'))->pluck('title')->all();
  $this->assertSame(['Bar task'], $titles);
}

public function test_api_index_sorts_priority_asc(): void
{
  $this->seedTasks();

  $response = $this->actingAs($this->user)->getJson('/api/tasks?priority_sort=asc');

  $response->assertOk();
  $titles = collect($response->json('data'))->pluck('title')->all();
  $this->assertSame(['Foo task', 'Baz task', 'Bar task'], $titles);
}
```

**Step 4-4.** Postman コレクションに priority アサーションを追加する。

- **ファイル:** `postman/Task-API.postman_collection.json`
- **場所:** `POST /api/tasks (valid)` の test スクリプト（行 205–210）、`PUT /api/tasks/{{taskId}}` の test スクリプト（行 265）・request body（行 255）
- **解説:** Newman が API レスポンスに `priority` が含まれることを検証する。
- **変更前（POST test スクリプト）:**

```jsx
"pm.test('status 201', () => pm.response.to.have.status(201));",
"const json = pm.response.json();",
"const id = json.data && json.data.id;",
"if (id) {",
"  pm.collectionVariables.set('taskId', String(id));",
"}"
```

- **変更後（POST test スクリプト）:**

```jsx
"pm.test('status 201', () => pm.response.to.have.status(201));",
"const json = pm.response.json();",
"pm.test('priority defaults to medium', () => {",
"  pm.expect(json.data.priority).to.eql('medium');",
"});",
"const id = json.data && json.data.id;",
"if (id) {",
"  pm.collectionVariables.set('taskId', String(id));",
"}"
```

- **変更前（PUT test スクリプト）:**

```jsx
"pm.test('status 200', () => pm.response.to.have.status(200));"
```

- **変更後（PUT test スクリプト）:**

```jsx
"pm.test('status 200', () => pm.response.to.have.status(200));",
"pm.test('response has priority', () => {",
"  pm.expect(pm.response.json().data.priority).to.exist;",
"});"
```

- **変更前（PUT リクエスト body、行 255）:**

```json
{
  "title": "Updated from Postman",
  "status": "in_progress"
}
```

- **変更後（PUT リクエスト body）:**

```json
{
  "title": "Updated from Postman",
  "status": "in_progress",
  "priority": "high"
}
```

**Step 4-5.** CI 相当の品質チェックを実行し、すべて緑にする。

```bash
./scripts/check-quality.sh
```

---

### Phase 5: after_fix メトリクス・記録

**Step 5-1.** 変更をコミットする。

```bash
git add -A
git commit -m "test: update tests and Postman for priority field"
```

**Step 5-2.** after_fix フェーズのメトリクスを取得する。

```bash
composer experiment:metrics -- --phase after_fix --diff-ref experiment-baseline-v1
```

**Step 5-3.** 実験記録を書き込む。

```bash
composer experiment:record -- --scenario api-spec-change-priority --write
```

**Step 5-4.** 結果を公開ディレクトリにコピーする。

```bash
./scripts/publish-experiment-results.sh --scenario legacy/api-spec-change-priority
```

**Step 5-5.** 結果をコミット・プッシュする。

```bash
git add experiment/results/legacy/api-spec-change-priority/
git commit -m "docs(experiment): publish legacy api-spec-change-priority results"
git push origin exp/api-spec-change-priority
```

**Step 5-6.** after_fix の CI 緑を確認する（PR は Phase 1 Step 1-2 で作成済みのため新規作成しない）。直前の push が after_fix の CI（緑）を発火する。

```bash
gh pr checks exp/api-spec-change-priority --watch
gh pr ready exp/api-spec-change-priority   # 任意: draft を Ready に切り替え
```

GitHub Actions 4 ジョブすべて成功を確認し、失敗0を RECORD.md の after_fix 行に記録する。

**Step 5-7.** 結果を公開ディレクトリにコピーする。

```bash
./scripts/publish-experiment-results.sh --scenario legacy/api-spec-change-priority
```

**Step 5-8.** 結果を手動で変更し、コミット・プッシュする。

```bash
git add experiment/results/legacy/api-spec-change-priority/RECORD.md
git commit -m "$(cat <<'EOF'
docs: fill manual experiment record for api-spec-change-priority
Add CI, work time, commits, and notes to the manual recording table.
EOF
)"
git push origin exp/api-spec-change-priority
```

---

## 5. 完了条件

- [ ]  GitHub Actions 4 ジョブすべて成功（ローカルでは `./scripts/check-quality.sh` が通ること）
- [ ]  各フェーズの GitHub CI を同一 PR に記録済み（baseline / after_update / after_fix）
- [ ]  `experiment/metrics/runs/<run_id>/` に `baseline.json` / `after_update.json` / `after_fix.json` の 3 フェーズがある
- [ ]  `composer experiment:record -- --scenario api-spec-change-priority --write` 済み
- [ ]  `experiment/results/` に結果がコピーされている（`publish-experiment-results.sh` 実行済み）
- [ ]  API レスポンス・DB・Web フォームで `priority`（`low` / `medium` / `high`、デフォルト `medium`）が動作する
- [ ]  一覧で優先度が表示され、`?priority=` フィルタと `?priority_sort=asc|desc` 並び替えが Web / API 両方で動作する
- [ ]  改良構成リポジトリ（`tech-update-task-app`）で同一手順を実施済み（比較実験）

## 6. 触らないファイルとその理由

| ファイル | 理由 |
| --- | --- |
| `app/Services/TaskService.php` | legacy 構成に存在しない（正規化・一覧クエリは Controller 2 箇所で実施済み） |
| `app/Repositories/TaskRepository.php` | legacy 構成に存在しない |
| `app/Repositories/Contracts/TaskRepositoryInterface.php` | legacy 構成に存在しない |

**legacy 固有の期待差分:** improved 比で `git_app.files_changed` が **+2（Web / API Controller）** 多い想定。

## 関連

- [api-spec-change-status-int.md](./api-spec-change-status-int.md) — 別サブシナリオ（status integer 化）
- [EXPERIMENT.md](../../EXPERIMENT.md) — 主評価指標の定義
