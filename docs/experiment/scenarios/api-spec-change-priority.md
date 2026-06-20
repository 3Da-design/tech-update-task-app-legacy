# シナリオ: バックエンド API 仕様変更（priority 追加）

## 目的

REST API のレスポンスに `priority` フィールドを追加し、**新属性追加がアーキテクチャ各層にどう波及するか**を比較する。

## 想定される破壊箇所

| 構成 | 主な修正箇所 |
|------|--------------|
| 改良構成 | マイグレーション, `TaskResource`, FormRequest×2, `TaskService::normalizeTaskPayload`, Blade, テスト, Postman |
| 従来構成 | 上記に加え **`Web\TaskController` と `API\TaskController` の `normalizeTaskPayload`**（許可キーに `priority` 追加）を **両方** 修正 |

> **従来構成の注意:** Service / Repository 層は存在しないため、正規化ロジックは Controller 2 ファイルに分散している。

## 事前条件

- `experiment-baseline-v1` タグまたは同等の CI 緑状態（**main ベースラインは 4 属性のみ**。`priority` は **exp ブランチ** で実施）
- メトリクス用に **`baseline` を先に取得**済み（[BEFORE.md](../BEFORE.md) の 1-1 / 1-2）

## 変更内容（両リポジトリで同一適用）

以下を **セットで** 適用する。

### 1. マイグレーション

`tasks` テーブルに `priority` カラムを追加:

- 型: `string`
- デフォルト: `medium`
- 許可値: `low` / `medium` / `high`

```bash
docker compose exec app php artisan make:migration add_priority_to_tasks_table
```

### 2. `TaskResource`

[`app/Http/Resources/TaskResource.php`](../../app/Http/Resources/TaskResource.php) の JSON に `priority` を追加。

### 3. FormRequest（Store / Update）

`priority` のバリデーションを追加:

```php
'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
```

（Store では `required` でもよいが、両リポジトリで同一ルールにすること）

### 4. 正規化

| 構成 | 修正箇所 |
|------|----------|
| 改良 | `TaskService::normalizeTaskPayload` の許可キーに `priority` を追加 |
| 従来 | [`Web\TaskController`](../../app/Http/Controllers/Web/TaskController.php) と [`API\TaskController`](../../app/Http/Controllers/API/TaskController.php) の `normalizeTaskPayload` 内 `$allowed` 配列に `priority` を追加 |

### 5. `Task` モデル

`$fillable` に `priority` を追加。

### 6. Web フォーム

[`resources/views/tasks/_form.blade.php`](../../resources/views/tasks/_form.blade.php) に priority の select を追加（機能 parity のため推奨）。

### 7. テスト・Postman

- [`tests/Feature/TaskApiTest.php`](../../tests/Feature/TaskApiTest.php): 作成・更新・レスポンスに `priority` を含める
- [`tests/Feature/TaskWebTest.php`](../../tests/Feature/TaskWebTest.php): フォーム送信に `priority` を含める
- [`postman/Task-API.postman_collection.json`](../../postman/Task-API.postman_collection.json): 期待値を更新

## 実施手順

BEFORE は [BEFORE.md](../BEFORE.md) に従う。ブランチ名の例: `exp/api-spec-change-priority`。

```bash
git fetch --tags
git checkout experiment-baseline-v1
git checkout -b exp/api-spec-change-priority

# 0. ベースライン（BEFORE.md の 1-1 / 1-2）
composer experiment:metrics -- --phase baseline --diff-ref experiment-baseline-v1

# 1. マイグレーション作成・適用
docker compose exec app php artisan make:migration add_priority_to_tasks_table
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
composer experiment:record -- --scenario api-spec-change-priority --write
scripts/publish-experiment-results.sh --scenario legacy/api-spec-change-priority
```

## 記録するメトリクス

| 優先 | 指標 | 取得元 |
|------|------|--------|
| 1 | 変更ファイル数 | `git.files_changed`（`--diff-ref experiment-baseline-v1`） |
| 2 | 追加 / 削除行数 | `git.lines_added` / `git.lines_deleted` |
| 3 | 更新直後のテスト失敗数 | `phpunit.fail` / `newman.fail`（`after_update`） |
| 4 | 作業時間（分） | 手動（テンプレート） |

**期待される差:** 従来構成は `files_changed` が改良構成より **+2（Web / API Controller）** 程度多い。

> **履歴:** 旧シナリオ ID `api-spec-change` として実施した結果は [results/legacy/api-spec-change/](../results/legacy/api-spec-change/) を参照。

## 完了条件

- [ ] GitHub Actions 4 ジョブすべて成功（`after_fix`）
- [ ] `experiment/metrics/runs/<run_id>/` に `baseline.json` / `after_update.json` / `after_fix.json` がある
- [ ] （任意）`composer experiment:record -- --scenario api-spec-change-priority --write` で `RECORD.md` を生成
- [ ] [docs/experiment/results/](../results/) に結果をコピー
- [ ] 改良構成リポジトリで同一手順を実施

## 関連

- [api-spec-change.md](./api-spec-change.md) — API 仕様変更シナリオ索引
- [api-spec-change-status-int.md](./api-spec-change-status-int.md) — 別サブシナリオ（status integer 化）
- [EXPERIMENT.md](../../EXPERIMENT.md) — 主評価指標の定義
