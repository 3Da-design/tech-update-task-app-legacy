# シナリオ: バックエンド API 仕様変更（status を integer 化）

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

## 0. この実験について

本実験は、タスクの `status` を string（`todo` / `in_progress` / `done`）から integer（`0` / `1` / `2`）へ変更する破壊的 API 仕様変更を適用し、その波及範囲を計測する。既存フィールドの型変更は、属性追加（priority）や DB スキーマ変更（タイトル検索）と対比し、技術更新時の修正コスト・失敗パターンの違いを評価するのに適している。

**legacy 構成の補足:** Service / Repository 層は存在せず、正規化・一覧クエリ・永続化ロジックが `Web\TaskController` と `API\TaskController` に重複している。そのため improved が `TaskService` / `TaskRepository` に集約する修正に加え、**同一内容を Controller 2 ファイルで個別に直す**必要がある（Web を先、API を後）。

## 1. 概要

| 項目 | 値 |
| --- | --- |
| リポジトリ | legacy |
| 実験の内容 | `status` を string から integer（0/1/2）へ変更 |
| ブランチ名 | exp/api-spec-change-status-int |
| 参照MD | docs/scenarios/api-spec-change-status-int.md |

## 2. 事前条件チェック

- [ ]  experiment-baseline-v1 または CI 緑 — 変更前の正しいベースラインと diff 比較の基準点が必要なため
- [ ]  Docker 起動 — `docker compose exec app` でマイグレーション・テスト・Newman を実行するため
- [ ]  PostgreSQL — マイグレーションが PostgreSQL 専用 `ALTER COLUMN ... USING CASE` を使うため

## 3. 修正対象ファイル一覧

| # | ファイルパス | 修正箇所 | フェーズ | 作業内容 | 解説（なぜ触るか） |
| --- | --- | --- | --- | --- | --- |
| 1 | `database/migrations/*_change_status_to_integer_on_tasks_table.php` | `up()` / `down()` | 2 | 新規作成 | DB カラム型変更と既存データ移行のため |
| 2 | `config/task.php` | `status_values`, `status_labels` | 2 | int マッピング定義 | 全層が参照する status の正規値・表示ラベルの単一ソース |
| 3 | `app/Models/Task.php` | PHPDoc 15行目、`casts()` | 2 | integer cast 追加 | Eloquent が status を int として扱うため |
| 4 | `app/Http/Requests/StoreTaskRequest.php` | `rules()` 29行目 | 2 | `string` → `integer` | POST 入力の HTTP 境界バリデーション |
| 5 | `app/Http/Requests/UpdateTaskRequest.php` | `rules()` 29行目 | 2 | `string` → `integer` | PUT 入力の HTTP 境界バリデーション |
| 6 | `app/Http/Requests/IndexTaskRequest.php` | `rules()` 43行目 | 2 | `string` → `integer` | 一覧フィルタ `?status=` のバリデーション |
| 7 | `app/Http/Controllers/Web/TaskController.php` | `normalizeListFilters()` 160–176行目 | 2 | int 正規化ロジック | Web 入口のフィルタ正規化（Service がないため Controller 内） |
| 8 | `app/Http/Controllers/Web/TaskController.php` | `normalizeTaskPayload()` 210行前 | 2 | status の `(int)` キャスト | Web 作成・更新ペイロードの型揃え |
| 9 | `app/Http/Controllers/Web/TaskController.php` | `listForUser()` 85–100行目 | 2 | `is_string` → `is_int` | Web 一覧の status フィルタ比較を int に合わせる |
| 10 | `app/Http/Controllers/API/TaskController.php` | `normalizeListFilters()` 154–170行目 | 2 | int 正規化ロジック | API 入口のフィルタ正規化（Web と同内容を重複修正） |
| 11 | `app/Http/Controllers/API/TaskController.php` | `normalizeTaskPayload()` 204行前 | 2 | status の `(int)` キャスト | API 作成・更新ペイロードの型揃え |
| 12 | `app/Http/Controllers/API/TaskController.php` | `listForUser()` 79–94行目 | 2 | `is_string` → `is_int` | API 一覧の status フィルタ比較を int に合わせる |
| 13 | `resources/views/tasks/_form.blade.php` | 34–37行目 | 2 | option value=int、表示=labels | 作成・編集フォームの送信値を int にするため |
| 14 | `resources/views/tasks/index.blade.php` | 31–34行目、85行目 | 2 | フィルタ option + 一覧表示を labels | 一覧フィルタ送信と人間可読表示を int 仕様に合わせるため |
| 15 | `tests/Feature/TaskApiTest.php` | 全 `status` 参照 | 4 | 期待値を int に | API テストを新仕様に合わせるため |
| 16 | `tests/Feature/TaskWebTest.php` | 全 `status` 参照 | 4 | 期待値を int に | Web テストを新仕様に合わせるため |
| 17 | `tests/Feature/TaskListFilterTest.php` | 38, 98, 127–151行目 | 4 | `?status=2` 等に変更 | status フィルタテストを int クエリに合わせるため |
| 18 | `postman/Task-API.postman_collection.json` | 195, 255行目の request body | 4 | `"status": 0` / `1` に変更 | Newman が新 API 仕様で通るようにするため |

## 4. 実施手順

### Phase 0: ブランチ作成

**この Phase の目的:** 実験用ブランチを baseline タグから切り、以降の変更を独立して計測できるようにする。

**Step 0-1.** 実験ブランチを作成する。

```bash
git checkout -b exp/api-spec-change-status-int experiment-baseline-v1
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
git commit --allow-empty -m "chore(exp): baseline anchor for api-spec-change-status-int"
git push -u origin exp/api-spec-change-status-int
gh pr create --draft --base main --head exp/api-spec-change-status-int \
  --title "exp: api-spec-change-status-int（legacy）" \
  --body "$(cat <<'EOF'
## Summary
- タスク `status` を string から integer（0/1/2）へ変更する実験
- legacy 構成（Web/API Controller 重複修正）

## Test plan（フェーズ別 CI）
- [ ] baseline コミットで CI 4 ジョブ緑
- [ ] after_update コミットで CI 赤（テスト未修正・意図的）
- [ ] after_fix コミットで CI 4 ジョブすべて成功
- [ ] `experiment/results/legacy/api-spec-change-status-int/` に 3 フェーズ JSON + RECORD.md がある

実験用 PR。マージはしない。
EOF
)"
gh pr checks exp/api-spec-change-status-int --watch
```

GitHub Actions（4 ジョブ）が緑になることを確認し、失敗0を RECORD.md の baseline 行に記録する。

---

### Phase 2: 変更適用（テスト・Postman 未着手）

**この Phase の目的:** 仕様変更を本番コードに適用し、テスト未修正の失敗状態（after_update）を計測できるようにする。

**Step 2-1.** マイグレーションファイルを生成する。

```bash
docker compose exec app php artisan make:migration change_status_to_integer_on_tasks_table
```

**Step 2-2.** 生成されたマイグレーションを編集する。

- **ファイル:** `database/migrations/*_change_status_to_integer_on_tasks_table.php`（Step 2-1 で生成されたパス）
- **場所:** `up()` / `down()` メソッド全体
- **解説:** PostgreSQL 専用 SQL で既存 string 値を int に移行し、カラム型を smallint に変更する。
- **変更前:** （空の `up()` / `down()` スタブ）
- **変更後:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::statement("
      ALTER TABLE tasks
      ALTER COLUMN status TYPE smallint
      USING (
        CASE status
          WHEN 'todo' THEN 0
          WHEN 'in_progress' THEN 1
          WHEN 'done' THEN 2
          ELSE 0
        END
      )
    ");
  }

  public function down(): void
  {
    DB::statement("
      ALTER TABLE tasks
      ALTER COLUMN status TYPE varchar(255)
      USING (
        CASE status
          WHEN 0 THEN 'todo'
          WHEN 1 THEN 'in_progress'
          WHEN 2 THEN 'done'
          ELSE 'todo'
        END
      )
    ");
  }
};
```

**Step 2-3.** マイグレーションを適用する。

```bash
docker compose exec app php artisan migrate
```

**Step 2-4.** config の status 定義を int ベースに変更する。

- **ファイル:** `config/task.php`
- **場所:** 5行目 `status_values`
- **解説:** 全層が参照する status の許可値と表示ラベルを int ベースで定義する。
- **変更前:**

```php
  'status_values' => ['todo', 'in_progress', 'done'],
```

- **変更後:**

```php
  'status_values' => [0, 1, 2],
  'status_labels' => [
    0 => 'todo',
    1 => 'in_progress',
    2 => 'done',
  ],
```

**Step 2-5.** Model に integer cast と PHPDoc を追加する。

- **ファイル:** `app/Models/Task.php`
- **場所:** PHPDoc 15行目、`casts()` 21–26行目
- **解説:** Eloquent が DB の smallint を PHP int として扱い、JSON 出力も int になるようにする。
- **変更前:**

```php
 * @property string $status
```

```php
  protected function casts(): array
  {
    return [
      'due_date' => 'date',
    ];
  }
```

- **変更後:**

```php
 * @property int $status
```

```php
  protected function casts(): array
  {
    return [
      'status' => 'integer',
      'due_date' => 'date',
    ];
  }
```

**Step 2-6.** StoreTaskRequest の status バリデーションを integer に変更する。

- **ファイル:** `app/Http/Requests/StoreTaskRequest.php`
- **場所:** `rules()` 29行目
- **解説:** POST リクエストの status を int として受け付ける HTTP 境界にする。
- **変更前:**

```php
      'status' => ['required', 'string', Rule::in(config('task.status_values'))],
```

- **変更後:**

```php
      'status' => ['required', 'integer', Rule::in(config('task.status_values'))],
```

**Step 2-7.** UpdateTaskRequest の status バリデーションを integer に変更する。

- **ファイル:** `app/Http/Requests/UpdateTaskRequest.php`
- **場所:** `rules()` 29行目
- **解説:** PUT リクエストの status を int として受け付ける HTTP 境界にする。
- **変更前:**

```php
      'status' => ['sometimes', 'required', 'string', Rule::in(config('task.status_values'))],
```

- **変更後:**

```php
      'status' => ['sometimes', 'required', 'integer', Rule::in(config('task.status_values'))],
```

**Step 2-8.** IndexTaskRequest の status フィルタバリデーションを integer に変更する。

- **ファイル:** `app/Http/Requests/IndexTaskRequest.php`
- **場所:** `rules()` 43行目
- **解説:** 一覧クエリ `?status=` を int として検証する HTTP 境界にする。
- **変更前:**

```php
      'status' => ['nullable', 'string', Rule::in(config('task.status_values'))],
```

- **変更後:**

```php
      'status' => ['nullable', 'integer', Rule::in(config('task.status_values'))],
```

**Step 2-9.** Web TaskController の normalizeListFilters を int 正規化に変更する。

- **ファイル:** `app/Http/Controllers/Web/TaskController.php`
- **場所:** PHPDoc 158行目、`normalizeListFilters()` 171–176行目
- **解説:** legacy では Service がないため、Web 入口でフィルタ status を許可された int に正規化する。API 側にも同ロジックが重複しているため、後続 Step で API も同様に直す。
- **変更前:**

```php
   * @return array{title?: string, status?: string, due_date_sort?: string}
```

```php
    if (isset($query['status']) && is_string($query['status'])) {
      $status = trim($query['status']);
      if ($status !== '') {
        $filters['status'] = $status;
      }
    }
```

- **変更後:**

```php
   * @return array{title?: string, status?: int, due_date_sort?: string}
```

```php
    if (isset($query['status']) && is_numeric($query['status'])) {
      $status = (int) $query['status'];
      if (in_array($status, config('task.status_values'), true)) {
        $filters['status'] = $status;
      }
    }
```

**Step 2-10.** Web TaskController の normalizeTaskPayload に status の int キャストを追加する。

- **ファイル:** `app/Http/Controllers/Web/TaskController.php`
- **場所:** `normalizeTaskPayload()` `return $data;` の直前（210行前）
- **解説:** Web 経由の作成・更新で、永続化前に status を int に揃える。
- **変更前:**

```php
    }

    return $data;
  }
```

- **変更後:**

```php
    }

    if (array_key_exists('status', $data)) {
      $data['status'] = (int) $data['status'];
    }

    return $data;
  }
```

**Step 2-11.** Web TaskController の listForUser の status フィルタ比較を is_int に変更する。

- **ファイル:** `app/Http/Controllers/Web/TaskController.php`
- **場所:** PHPDoc 85行目、`listForUser()` 97–100行目
- **解説:** 正規化済み int フィルタで DB クエリを組み立てる。Repository 層がないため、この比較ロジックも Controller 内にある。
- **変更前:**

```php
   * @param  array{title?: string, status?: string, due_date_sort?: string}  $filters
```

```php
    $status = $filters['status'] ?? null;
    if (is_string($status) && $status !== '') {
      $query->where('status', $status);
    }
```

- **変更後:**

```php
   * @param  array{title?: string, status?: int, due_date_sort?: string}  $filters
```

```php
    $status = $filters['status'] ?? null;
    if (is_int($status)) {
      $query->where('status', $status);
    }
```

**Step 2-12.** API TaskController の normalizeListFilters を int 正規化に変更する。

- **ファイル:** `app/Http/Controllers/API/TaskController.php`
- **場所:** PHPDoc 152行目、`normalizeListFilters()` 165–170行目
- **解説:** Web と API は別入口だが正規化ロジックが重複している。Web だけ直すと API の `?status=` フィルタが旧 string 前提のまま残り、Web/API 間で挙動がずれる。
- **変更前:**

```php
   * @return array{title?: string, status?: string, due_date_sort?: string}
```

```php
    if (isset($query['status']) && is_string($query['status'])) {
      $status = trim($query['status']);
      if ($status !== '') {
        $filters['status'] = $status;
      }
    }
```

- **変更後:**

```php
   * @return array{title?: string, status?: int, due_date_sort?: string}
```

```php
    if (isset($query['status']) && is_numeric($query['status'])) {
      $status = (int) $query['status'];
      if (in_array($status, config('task.status_values'), true)) {
        $filters['status'] = $status;
      }
    }
```

**Step 2-13.** API TaskController の normalizeTaskPayload に status の int キャストを追加する。

- **ファイル:** `app/Http/Controllers/API/TaskController.php`
- **場所:** `normalizeTaskPayload()` `return $data;` の直前（204行前）
- **解説:** REST API の POST/PUT でも Web と同じ int 正規化が必要。片方だけ直すと JSON API と Blade フォームで保存値の型が不一致になる。
- **変更前:**

```php
    }

    return $data;
  }
```

- **変更後:**

```php
    }

    if (array_key_exists('status', $data)) {
      $data['status'] = (int) $data['status'];
    }

    return $data;
  }
```

**Step 2-14.** API TaskController の listForUser の status フィルタ比較を is_int に変更する。

- **ファイル:** `app/Http/Controllers/API/TaskController.php`
- **場所:** PHPDoc 79行目、`listForUser()` 91–94行目
- **解説:** API 一覧エンドポイントの status フィルタを int 比較に合わせる。Web 側 Step 2-11 と対になる修正。
- **変更前:**

```php
   * @param  array{title?: string, status?: string, due_date_sort?: string}  $filters
```

```php
    $status = $filters['status'] ?? null;
    if (is_string($status) && $status !== '') {
      $query->where('status', $status);
    }
```

- **変更後:**

```php
   * @param  array{title?: string, status?: int, due_date_sort?: string}  $filters
```

```php
    $status = $filters['status'] ?? null;
    if (is_int($status)) {
      $query->where('status', $status);
    }
```

**Step 2-15.** フォームの status セレクトを int value + ラベル表示に変更する。

- **ファイル:** `resources/views/tasks/_form.blade.php`
- **場所:** 34–37行目
- **解説:** フォーム送信値を int にしつつ、ユーザーには文字ラベルを表示する。
- **変更前:**

```php
            @foreach (config('task.status_values') as $status)
                <option value="{{ $status }}" @selected(old('status', $task?->status ?? '') === $status)>
                    {{ $status }}
                </option>
            @endforeach
```

- **変更後:**

```php
            @foreach (config('task.status_values') as $status)
                <option value="{{ $status }}" @selected((int) old('status', $task?->status ?? '') === $status)>
                    {{ config('task.status_labels')[$status] }}
                </option>
            @endforeach
```

**Step 2-16.** 一覧のフィルタ・表示を int value + ラベル表示に変更する。

- **ファイル:** `resources/views/tasks/index.blade.php`
- **場所:** 31–34行目（フィルタ）、85行目（一覧表示）
- **解説:** 一覧フィルタの送信値を int にし、テーブルにはラベルを表示する。9行目の `session('status')` はフラッシュメッセージ用のため変更しない。
- **変更前:**

```php
                        @foreach (config('task.status_values') as $status)
                            <option value="{{ $status }}" @selected(old('status', request('status')) === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
```

```php
                            <td>{{ $task->status }}</td>
```

- **変更後:**

```php
                        @foreach (config('task.status_values') as $status)
                            <option value="{{ $status }}" @selected((int) old('status', request('status', '')) === $status)>
                                {{ config('task.status_labels')[$status] }}
                            </option>
                        @endforeach
```

```php
                            <td>{{ config('task.status_labels')[$task->status] ?? $task->status }}</td>
```

**Step 2-17.** フロント資産を Docker 経由でビルドする。

```bash
composer npm:docker-build
```

---

### Phase 3: after_update メトリクス

**この Phase の目的:** テスト未修正のまま、どれだけ壊れたかを数値化する。

**Step 3-1.** 変更をコミットする。

```bash
git add -A
git commit -m "$(cat <<'EOF'
feat: change task status from string to integer
Migration, config, model, requests, Web/API controllers, and Blade views.
Tests and Postman are intentionally unchanged for after_update measurement.
EOF
```

**Step 3-2.** after_update フェーズのメトリクスを取得する（PHPUnit / Newman の失敗数を記録）。

```bash
composer experiment:metrics -- --phase after_update --diff-ref experiment-baseline-v1
```

**Step 3-3.** after_update コミットを push し、CI が赤くなることを確認・記録する

**この Step の目的:** テスト未修正の壊れた状態を GitHub Actions 上でも赤として残す。`php-tests`（PHPUnit）と `api-tests`（Newman）が失敗し、依存側でフォーマット/型/lint が崩れていれば `php-quality`・`frontend` も赤になる。失敗ジョブ数を RECORD.md の after_update 行に記録する。

```bash
git push origin exp/api-spec-change-status-int
gh pr checks exp/api-spec-change-status-int --watch
```

> **注意:** `ci.yml` は `concurrency: cancel-in-progress` のため、run が完了する前に次の push（Phase 5）を行うとキャンセルされる。`--watch` で CI 完了を待ってから Phase 4 に進む。

---

### Phase 4: テスト・Postman 修正 → CI 緑

**この Phase の目的:** 仕様変更に合わせてテストと Postman を直し、修正工数（after_fix）を計測可能にする。

**Step 4-1.** TaskApiTest の status 期待値を int に書き換える。

- **ファイル:** `tests/Feature/TaskApiTest.php`
- **場所:** ファイル全体の `status` 参照
- **解説:** API Feature テストが新しい int status 仕様と一致するようにする。
- **変更前（代表箇所）:**

```php
      'status' => 'todo',
```

```php
      'status' => 'in_progress',
```

- **変更後（代表箇所）:**

```php
      'status' => 0,
```

```php
      'status' => 1,
```

全置換対応表:

| 変更前 | 変更後 |
| --- | --- |
| `'status' => 'todo'` | `'status' => 0` |
| `'status' => 'in_progress'` | `'status' => 1` |
| `'status' => 'done'` | `'status' => 2` |

（422 テストの `'status' => 'invalid'` はバリデーションエラー確認用のためそのまま）

**Step 4-2.** TaskWebTest の status 期待値を int に書き換える。

- **ファイル:** `tests/Feature/TaskWebTest.php`
- **場所:** ファイル全体の `status` 参照
- **解説:** Web Feature テストとリダイレクト先クエリの status パラメータを int に合わせる。
- **変更前（代表箇所）:**

```php
      'status' => 'todo',
```

```php
      'status' => 'in_progress',
```

- **変更後（代表箇所）:**

```php
      'status' => 0,
```

```php
      'status' => 1,
```

**Step 4-3.** TaskListFilterTest の status フィルタと seed データを int に書き換える。

- **ファイル:** `tests/Feature/TaskListFilterTest.php`
- **場所:** 38行目、98行目、127–151行目
- **解説:** フィルタクエリとシードデータを int status に合わせ、`done` 相当は `2` で検証する。
- **変更前:**

```php
    $response = $this->actingAs($this->user)->get('/tasks?status=done');
```

```php
    $response = $this->actingAs($this->user)->getJson('/api/tasks?status=done');
```

```php
      'status' => 'todo',
```

```php
      'status' => 'done',
```

```php
      'status' => 'in_progress',
```

- **変更後:**

```php
    $response = $this->actingAs($this->user)->get('/tasks?status=2');
```

```php
    $response = $this->actingAs($this->user)->getJson('/api/tasks?status=2');
```

```php
      'status' => 0,
```

```php
      'status' => 2,
```

```php
      'status' => 1,
```

**Step 4-4.** Postman コレクションの request body を int status に更新する。

- **ファイル:** `postman/Task-API.postman_collection.json`
- **場所:** 195行目、255行目（`raw` body）
- **解説:** Newman が POST/PUT で int status を送り、新 API 仕様で通るようにする。422 テスト（226行目）は `"invalid"` のまま。
- **変更前:**

```json
"raw": "{\n  \"title\": \"Postman task\",\n  \"status\": \"todo\"\n}"
```

```json
"raw": "{\n  \"title\": \"Updated from Postman\",\n  \"status\": \"in_progress\"\n}"
```

- **変更後:**

```json
"raw": "{\n  \"title\": \"Postman task\",\n  \"status\": 0\n}"
```

```json
"raw": "{\n  \"title\": \"Updated from Postman\",\n  \"status\": 1\n}"
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
git commit -m "$(cat <<'EOF'
feat: change task status from string to integer
Update migration, config, controllers, Blade, tests, and Postman for 0/1/2 status values.
EOF
)"
```

**Step 5-2.** after_fix フェーズのメトリクスを取得する。

```bash
composer experiment:metrics -- --phase after_fix --diff-ref experiment-baseline-v1
```

**Step 5-3.** 実験記録を生成する。

```bash
composer experiment:record -- --scenario api-spec-change-status-int --write
```

**Step 5-4.** 結果を experiment/results/ に公開する。

```bash
./scripts/publish-experiment-results.sh --scenario legacy/api-spec-change-status-int
```

**Step 5-5.** 手動項目を追加する。

**Step 5-6.** 結果をコミット・プッシュする。

```bash
git add experiment/results/legacy/api-spec-change-status-int/
git commit -m "docs(experiment): publish api-spec-change-status-int results"
git push origin exp/api-spec-change-status-int
```

**Step 5-7.** after_fix の CI 緑を確認する（PR は Phase 1 Step 1-2 で作成済みのため新規作成しない）。直前の push が after_fix の CI（緑）を発火する。

```bash
gh pr checks exp/api-spec-change-status-int --watch
gh pr ready exp/api-spec-change-status-int   # 任意: draft を Ready に切り替え
```

GitHub Actions 4 ジョブすべて成功を確認し、失敗0を RECORD.md の after_fix 行に記録する。

**Step 5-8.** 結果を公開ディレクトリにコピーする。

```bash
./scripts/publish-experiment-results.sh --scenario legacy/api-spec-change-status-int
```

**Step 5-9.** 結果を手動で変更し、コミット・プッシュする。

```bash
git add experiment/results/legacy/api-spec-change-status-int/RECORD.md
git commit -m "$(cat <<'EOF'
docs: fill manual experiment record for api-spec-change-status-int

Add CI, work time, commits, and notes to the manual recording table.
EOF
)"
git push origin exp/api-spec-change-status-int
```

---

## 5. 完了条件

- [ ]  GitHub Actions 4 ジョブすべて成功（`./scripts/check-quality.sh` がローカルで緑）
- [ ]  各フェーズの GitHub CI を同一 PR に記録済み（baseline / after_update / after_fix）
- [ ]  `baseline` / `after_update` / `after_fix` の 3 フェーズ JSON が `experiment/metrics/` に存在する
- [ ]  `experiment/results/` に結果がコピーされている（`publish-experiment-results.sh` 実行済み）
- [ ]  改良構成リポジトリ（`tech-update-task-app`）で同一手順を実施済み（比較実験）

## 6. 触らないファイルとその理由

| ファイル | 理由 |
| --- | --- |
| `app/Services/TaskService.php` | legacy 構成に存在しない |
| `app/Repositories/TaskRepository.php` | legacy 構成に存在しない |
| `app/Repositories/Contracts/TaskRepositoryInterface.php` | legacy 構成に存在しない |
| `app/Http/Resources/TaskResource.php` | Model の `integer` cast により JSON 出力の `status` も自動的に int になるため変更不要 |
| `database/migrations/2026_05_12_052659_create_tasks_table.php` | 既存マイグレーションは変更せず、新規マイグレーションで型変更・データ移行を行うため |

---

**legacy 固有の期待差分:** improved 比で `git_app.files_changed` が **+2（Web / API Controller）** 程度多くなる想定です。

## 関連

- [api-spec-change-priority.md](./api-spec-change-priority.md) — 別サブシナリオ（`priority` 追加）
- [EXPERIMENT.md](../../EXPERIMENT.md) — 主評価指標の定義
