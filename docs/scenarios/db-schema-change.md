# シナリオ: DB / クエリ変更（タイトル検索の大文字小文字）

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

タスク一覧の `?title=` 部分一致検索を、PostgreSQL 上で大文字小文字を区別しない挙動に変更する実験です。現状は `where('title', 'like', ...)` による区別あり検索（Web/API 各 `TaskController::listForUser` 内）のため、`Important task` に `?title=important` ではヒットしません。

この変更は永続化層のクエリ修正であり、API の入出力形式は変わりません。改良構成と従来構成で「修正が Repository 1 箇所に集約されるか、Controller 2 箇所に分散するか」を比較するのに適したシナリオです。

**legacy 構成の補足:** Service / Repository 層は存在せず、一覧クエリと `escapeLike` が `Web\TaskController` と `API\TaskController` の `listForUser` に重複している。improved が `TaskRepository::getFiltered` 1 箇所で済む修正に加え、**同一内容を Controller 2 ファイルで個別に直す**必要がある（Web を先、API を後）。

## 1. 概要

| 項目 | 値 |
| --- | --- |
| リポジトリ | legacy |
| 実験の内容 | タイトル検索を大文字小文字無視にする |
| ブランチ名 | exp/db-schema-change |
| 参照MD | docs/scenarios/db-schema-change.md |

## 2. 事前条件チェック

- [ ]  experiment-baseline-v1 または CI 緑 — 比較基準タグからブランチを切り、メトリクス diff の参照点を固定するため
- [ ]  Docker 起動 — `check-quality.sh` と PHPUnit / Newman がコンテナ経由で動くため
- [ ]  PostgreSQL（status数値化・タイトル検索のみ） — `LIKE` の大文字小文字挙動を CI（GitHub Actions の `postgres:16-alpine`）とローカル Docker で一致させるため

## 3. 修正対象ファイル一覧

| # | ファイルパス | 修正箇所 | フェーズ | 作業内容 | 解説（なぜ触るか） |
| --- | --- | --- | --- | --- | --- |
| 1 | `app/Http/Controllers/Web/TaskController.php` | `listForUser()` 92–95 行目 | Phase 2 | `LOWER(title) LIKE ?` に変更 | legacy では Web 入口の一覧クエリが Controller 内にあり、Repository がないためここで直す |
| 2 | `app/Http/Controllers/API/TaskController.php` | `listForUser()` 86–89 行目 | Phase 2 | 同上 | Web と API は別 Controller だがクエリが重複している。Web だけ直すと REST API のタイトル検索だけ旧挙動のまま残る |
| 3 | `tests/Feature/TaskListFilterTest.php` | クラス末尾（`seedTasks()` の前） | Phase 4 | ケース無視テスト 2 件を追加 | 新仕様を自動検証し、回帰を防ぐ。シナリオ MD で定義されたテスト名・期待値を実装する |
| 4 | `postman/Task-API.postman_collection.json` | （任意） | Phase 4 | 変更なしで可 | 現コレクションにタイトルフィルタのリクエストはなく、CI の Newman は既存テストのみ実行されるため必須ではない |

## 4. 実施手順

### Phase 0: ブランチ作成

**この Phase の目的:** `experiment-baseline-v1` から実験ブランチを切り、以降の変更をベースラインと分離する。

**Step 0-1.** 実験ブランチを作成する

```bash
git checkout -b exp/db-schema-change experiment-baseline-v1
```

---

### Phase 1: baseline メトリクス

**この Phase の目的:** 変更前の状態を記録し、あとで diff 比較できるようにする。

**Step 1-1.** baseline フェーズのメトリクスを取得する

```bash
composer experiment:metrics -- --phase baseline --diff-ref experiment-baseline-v1
```

---

### Phase 2: 変更適用（テスト・Postman 未着手）

**この Phase の目的:** タイトル検索クエリを大文字小文字無視に変更し、本番コード側の仕様変更を完了する（テストはまだ触らない）。

**Step 2-1.** Web TaskController の `listForUser` タイトルフィルタを修正する

- **ファイル:** `app/Http/Controllers/Web/TaskController.php`
- **場所:** `listForUser()` 93–96 行目
- **解説:** legacy では Web 一覧の検索クエリが Controller 内に直書きされている。`LOWER` 比較に置き換えて大文字小文字を無視する。API 側にも同ロジックが重複しているため、後続 Step で API も同様に直す。
- **変更前:**

```php
    $title = $filters['title'] ?? null;
    if (is_string($title) && $title !== '') {
      $query->where('title', 'like', '%'.$this->escapeLike($title).'%');
    }
```

- **変更後:**

```php
    $title = $filters['title'] ?? null;
    if (is_string($title) && $title !== '') {
      $query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($this->escapeLike($title)).'%']);
    }
```

**Step 2-2.** API TaskController の `listForUser` タイトルフィルタを修正する

- **ファイル:** `app/Http/Controllers/API/TaskController.php`
- **場所:** `listForUser()` 87–90 行目
- **解説:** Web と API は別入口だが `listForUser` と `escapeLike` が完全に重複している。Web だけ直すと Blade 経由の検索だけ新仕様になり、REST API は旧 `LIKE` のまま残る。
- **変更前:**

```php
    $title = $filters['title'] ?? null;
    if (is_string($title) && $title !== '') {
      $query->where('title', 'like', '%'.$this->escapeLike($title).'%');
    }
```

- **変更後:**

```php
    $title = $filters['title'] ?? null;
    if (is_string($title) && $title !== '') {
      $query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($this->escapeLike($title)).'%']);
    }
```

---

### Phase 3: after_update メトリクス

**この Phase の目的:** テスト未修正のまま、どれだけ壊れたかを数値化する。

**Step 3-1.** 変更をコミットする

```bash
git add app/Http/Controllers/Web/TaskController.php \
        app/Http/Controllers/API/TaskController.php
git commit -m "Make title search case-insensitive in Web/API controllers."
```

**Step 3-2.** after_update フェーズのメトリクスを取得する

```bash
composer experiment:metrics -- --phase after_update --diff-ref experiment-baseline-v1
```

> **補足:** 既存の `TaskListFilterTest` は `title=Foo` で `Foo task` を検索しており、ケース無視化後も通過する。新規のケース無視テストは Phase 4 で追加するため、この時点の `phpunit.fail` は **0 の可能性が高い**（シナリオ MD の「テスト先行追加」手順とは異なるが、Phase 2/4 分離フォーマットに沿った想定内の挙動）。
> 

---

### Phase 4: テスト・Postman 修正 → CI 緑

**この Phase の目的:** 新仕様（大文字小文字無視）をテストで固定し、CI を緑にする。

**Step 4-1.** ケース無視テスト 2 件を `TaskListFilterTest` に追加する

- **ファイル:** `tests/Feature/TaskListFilterTest.php`
- **場所:** `test_api_index_sorts_due_date_desc()` の直後（125 行目の後）、`seedTasks()` の前
- **解説:** シナリオ MD で定義されたテスト名で、Web/API それぞれ `Important task` に `?title=important` がヒットすることを検証する。Phase 2 で Controller 2 箇所を直済みのため、このテスト追加後は即座に緑になる想定。
- **変更前:** （該当メソッドなし）
- **変更後:** 以下 2 メソッドを追加

```php
  public function test_web_index_title_search_is_case_insensitive(): void
  {
    Task::query()->create([
      'user_id' => $this->user->id,
      'title' => 'Important task',
      'description' => null,
      'status' => 'todo',
      'due_date' => null,
    ]);

    $response = $this->actingAs($this->user)->get('/tasks?title=important');

    $response->assertOk();
    $response->assertSee('Important task', false);
  }

  public function test_api_index_title_search_is_case_insensitive(): void
  {
    Task::query()->create([
      'user_id' => $this->user->id,
      'title' => 'Important task',
      'description' => null,
      'status' => 'todo',
      'due_date' => null,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/tasks?title=important');

    $response->assertOk();
    $titles = collect($response->json('data'))->pluck('title')->all();
    $this->assertSame(['Important task'], $titles);
  }
```

**Step 4-2.** CI 品質チェックを実行する

```bash
./scripts/check-quality.sh
```

> **補足:** フロントエンド変更はないため `composer npm:docker-build` は不要。`check-quality.sh` 内で ESLint・Vite build が実行される。
> 

---

### Phase 5: after_fix メトリクス・記録

**Step 5-1.** 変更をコミットする。

```bash
git add tests/Feature/TaskListFilterTest.php
git commit -m "$(cat <<'EOF'
test: add case-insensitive title search tests

Add web/API filter tests for db-schema-change scenario.
EOF
)"
```

**Step 5-2.** after_fix フェーズのメトリクスを取得する。

```bash
composer experiment:metrics -- --phase after_fix --diff-ref experiment-baseline-v1
```

**Step 5-3.** 実験記録を書き込む。

```bash
composer experiment:record -- --scenario db-schema-change --write
```

**Step 5-4.** 結果を公開ディレクトリにコピーする。

```bash
./scripts/publish-experiment-results.sh --scenario legacy/db-schema-change
```

**Step 5-5.** 結果をコミット・プッシュする。

```bash
git add experiment/results/legacy/db-schema-change/
git commit -m "$(cat <<'EOF'
docs: publish experiment results for db-schema-change

Record baseline, after_update, and after_fix metrics for the
case-insensitive title search scenario on the legacy architecture.
EOF
)"
git push -u origin exp/db-schema-change
```

**Step 5-6.** PR を作って CI を確認する。

```bash
gh pr create --base main --head exp/db-schema-change \
  --title "exp: db-schema-change" \
  --body "実験用。マージはしない。"
```

**Step 5-7.** 結果を公開ディレクトリにコピーする。

```bash
./scripts/publish-experiment-results.sh --scenario legacy/db-schema-change
```

**Step 5-8.** 結果を手動で変更し、コミット・プッシュする。

```bash
git add experiment/results/legacy/db-schema-change/RECORD.md
git commit -m "$(cat <<'EOF'
docs: fill manual experiment record for db-schema-change

Add CI, work time, commits, and notes to the manual recording table.
EOF
)"
git push origin exp/db-schema-change
```

---

## 5. 完了条件

- [ ]  GitHub Actions 4 ジョブ（`php-tests` / `php-quality` / `frontend` / `api-tests`）すべて成功（`after_fix`）
- [ ]  `experiment/metrics/runs/<run_id>/` に `baseline` / `after_update` / `after_fix` の 3 フェーズ JSON がある
- [ ]  `experiment/results/` に `publish-experiment-results.sh` の出力がある
- [ ]  `?title=important` で `Important task` が Web・API 両方でヒットする
- [ ]  既存の `title=Foo` 部分一致テストが引き続き通過する
- [ ]  改良構成リポジトリ（`tech-update-task-app`）で同一シナリオを実施し、`git_app.files_changed` を比較できる

## 6. 触らないファイルとその理由

| ファイル | 理由 |
| --- | --- |
| `app/Services/TaskService.php` | legacy 構成に存在しない |
| `app/Repositories/TaskRepository.php` | legacy 構成に存在しない |
| `app/Repositories/Contracts/TaskRepositoryInterface.php` | legacy 構成に存在しない |
| `app/Http/Controllers/Web/TaskController.php` の `normalizeListFilters` / `normalizeTaskPayload` | タイトル文字列の trim のみで、大文字小文字変換はクエリ層（`listForUser`）の責務 |
| `app/Http/Controllers/API/TaskController.php` の `normalizeListFilters` / `normalizeTaskPayload` | 同上 |
| `app/Http/Resources/TaskResource.php` | レスポンス形式の変更はなく、検索挙動のみの変更 |
| `app/Http/Requests/IndexTaskRequest.php` | `title` クエリパラメータの受け入れルールは変更不要 |
| `app/Models/Task.php` | DB スキーマ・Model 属性の変更はない（クエリのみ変更） |
| `database/migrations/*` | カラム追加・型変更はなく、`LOWER()` によるクエリ変更で完結する |
| `resources/views/tasks/*` | フロントの表示・フォームに変更なし |
| `postman/Task-API.postman_collection.json` | タイトルフィルタのリクエストがなく、CI Newman は既存コレクションで緑のまま（任意で追加可能） |

---

**legacy 固有の期待差分:** improved 比で `git_app.files_changed` が **+1（Web Controller）** 程度多くなる想定です。improved は `TaskRepository::getFiltered` 1 ファイル、legacy は Web / API の `listForUser` 2 ファイルに同一クエリ変更が分散する。

## 関連

- [EXPERIMENT.md](../../EXPERIMENT.md) — 主評価指標の定義
- [api-spec-change-priority.md](./api-spec-change-priority.md) — API 仕様変更（`priority` は **exp ブランチ** で実施。main ベースラインには含めない）
- [api-spec-change-status-int.md](./api-spec-change-status-int.md) — API 仕様変更（status integer 化）
