# シナリオ: バックエンド API 仕様変更（status を integer 化）

## 目的

タスク `status` を文字列（`todo` / `in_progress` / `done`）から整数（`0` / `1` / `2`）に変更し、**型変更がアーキテクチャ各層にどう波及するか**を比較する。

## 想定される破壊箇所

| 構成 | 主な修正箇所 |
|------|--------------|
| 改良構成 | マイグレーション, `config/task.php`, `Task` モデル, FormRequest×3, `TaskService::normalizeTaskPayload` / `normalizeListFilters`, Blade, テスト, Postman |
| 従来構成 | 上記に加え **`Web\TaskController` と `API\TaskController` の private メソッド**（`normalizeListFilters`, `normalizeTaskPayload`, `listForUser`）を **両方** 修正 |

> **従来構成の注意:** Service / Repository 層は存在しないため、正規化・フィルタロジックは Controller 2 ファイルに分散している。

## 事前条件

- `experiment-baseline-v1` タグまたは同等の CI 緑状態（**main ベースラインは 4 属性・status は string**）
- メトリクス用に **`baseline` を先に取得**済み（[BEFORE.md](../BEFORE.md) の 1-1 / 1-2）
- CI / ローカルテストが **PostgreSQL** で実行されていること（マイグレーションの `ALTER COLUMN ... USING CASE` に PostgreSQL が必要）

## 変更内容（両リポジトリで同一適用）

以下を **セットで** 適用する。

### 1. マイグレーション

`tasks.status` を `string` から `smallint` に変換する。

| 旧値（string） | 新値（int） |
|----------------|-------------|
| `todo` | `0` |
| `in_progress` | `1` |
| `done` | `2` |

例（PostgreSQL）:

```php
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
```

### 2. `config/task.php`

```php
'status_values' => [0, 1, 2],
'status_labels' => [
    0 => 'todo',
    1 => 'in_progress',
    2 => 'done',
],
```

### 3. `Task` モデル

- `@property int $status`
- `casts`: `'status' => 'integer'`

### 4. FormRequest（Store / Update / Index）

`status` ルールを `'string'` から `'integer'` + `Rule::in(config('task.status_values'))` に変更。

### 5. `TaskResource`

変更不要（`status` フィールドは int として JSON に含まれる）。

### 6. Controller 正規化・フィルタ（従来構成）

[`Web\TaskController`](../../app/Http/Controllers/Web/TaskController.php) と [`API\TaskController`](../../app/Http/Controllers/API/TaskController.php) の **両方** で以下を更新:

- `normalizeListFilters`: クエリパラメータ `status` を `int` として検証・変換
- `normalizeTaskPayload`: `status` を `(int)` にキャスト
- `listForUser`: `is_int($status)` でフィルタ

改良構成では `TaskService` の同名メソッドを更新する。

### 7. Blade

- [`resources/views/tasks/_form.blade.php`](../../resources/views/tasks/_form.blade.php): option の value を int、`label` は `config('task.status_labels')` から表示
- [`resources/views/tasks/index.blade.php`](../../resources/views/tasks/index.blade.php): フィルタ select と一覧表示を `status_labels` 対応に

### 8. テスト・Postman

- [`tests/Feature/TaskApiTest.php`](../../tests/Feature/TaskApiTest.php): `status` を `0` / `1` / `2`、不正値は `99` 等
- [`tests/Feature/TaskListFilterTest.php`](../../tests/Feature/TaskListFilterTest.php): フィルタクエリを `?status=2` 等に
- [`tests/Feature/TaskWebTest.php`](../../tests/Feature/TaskWebTest.php): 期待値を int status に
- [`postman/Task-API.postman_collection.json`](../../postman/Task-API.postman_collection.json): リクエスト body の `status` を更新

## 実施手順

BEFORE は [BEFORE.md](../BEFORE.md) に従う。ブランチ名の例: `exp/api-spec-change-status-int`。

```bash
git fetch --tags
git checkout experiment-baseline-v1
git checkout -b exp/api-spec-change-status-int

# 0. ベースライン（BEFORE.md の 1-1 / 1-2）
composer experiment:metrics -- --phase baseline --diff-ref experiment-baseline-v1

# 1. マイグレーション作成・適用
docker compose exec app php artisan make:migration change_tasks_status_to_integer
docker compose exec app php artisan migrate

# 2. 上記ファイルを順に編集（テスト・Postman はまだ触らない）

# 3. フロントビルド（Blade テスト用）
docker compose --profile node run --rm node sh -c "npm ci && npm run build"

# 4. 更新直後メトリクス（失敗が想定される）
composer experiment:metrics -- --phase after_update --diff-ref experiment-baseline-v1

# 5. テスト・Postman・PHPStan を修正して CI 緑に
./scripts/check-quality.sh

# 6. 修正完了メトリクス
composer experiment:metrics -- --phase after_fix --diff-ref experiment-baseline-v1

# 7. 記録用 Markdown（任意）
composer experiment:record -- --scenario api-spec-change-status-int --write
scripts/publish-experiment-results.sh --scenario legacy/api-spec-change-status-int
```

## 記録するメトリクス

| 優先 | 指標 | 取得元 |
|------|------|--------|
| 1 | 変更ファイル数 | `git.files_changed`（`--diff-ref experiment-baseline-v1`） |
| 2 | 追加 / 削除行数 | `git.lines_added` / `git.lines_deleted` |
| 3 | 更新直後のテスト失敗数 | `phpunit.fail` / `newman.fail`（`after_update`） |
| 4 | 作業時間（分） | 手動（テンプレート） |

**期待される差:** 従来構成は `files_changed` が改良構成より **+2（Web / API Controller）** 程度多い。

## 完了条件

- [ ] GitHub Actions 4 ジョブすべて成功（`after_fix`）
- [ ] `experiment/metrics/runs/<run_id>/` に `baseline.json` / `after_update.json` / `after_fix.json` がある
- [ ] （任意）`composer experiment:record -- --scenario api-spec-change-status-int --write` で `RECORD.md` を生成
- [ ] [docs/experiment/results/](../results/) に結果をコピー
- [ ] 改良構成リポジトリで同一手順を実施

## 関連

- [api-spec-change-priority.md](./api-spec-change-priority.md) — 別サブシナリオ（`priority` 追加）
- [EXPERIMENT.md](../../EXPERIMENT.md) — 主評価指標の定義
