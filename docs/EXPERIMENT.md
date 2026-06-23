# 実験設計（従来構成）

本リポジトリは、技術更新時の影響を定量評価するための **従来構成（悪い例）** の実験台です。

## 研究ゴール

**設計（モジュール化 + CI/CD）が、技術更新時の影響をどれだけ抑えられるか**を、改良構成（別リポジトリ）と比較して定量的に示す。

| 比較軸 | 本リポジトリ（従来構成） | 改良構成（別リポジトリ） |
|--------|-------------------------|-------------------------|
| 構造 | モノリシック、Controller にロジック集中、DB 直接操作 | Controller / Service / Repository 分離、Interface による抽象化 |
| CI/CD | 改良構成と **同一ワークフロー** | GitHub Actions（4 ジョブ） |
| アプリ | 同一機能のタスク管理（Laravel） | 同一 |

## 本リポジトリの役割

- 従来構成の **ベースライン** を確立する（**`experiment-baseline-v1` タグ** 推奨。タスク属性は `title` / `description` / `due_date` / `status` の 4 項目のみ）
- 更新シナリオ（例: `api-spec-change-status-int` / `api-spec-change-priority`）は **`exp/*` ブランチ** で実施し、ベースラインと混在させない
- 更新シナリオ実施後のメトリクスを記録する
- 改良構成リポジトリと **同一シナリオ・同一手順** で比較する

本リポジトリの作成手順（改良構成からの移行記録）は [従来構成リポジトリ作成記録](#従来構成リポジトリ作成記録) を参照してください。

## 比較条件

| 項目 | 内容 |
|------|------|
| アプリケーション | タスク管理（Web + REST API） |
| 技術スタック | Laravel 13、PHP 8.4、PostgreSQL、Breeze、Vite |
| 評価スコープ | **アプリ全体**（認証・プロフィール・タスク・CI 全ジョブ） |

### アーキテクチャの範囲

- **タスク領域（従来の核）:** `Web\TaskController` / `API\TaskController` から Eloquent を直接操作（Service / Repository なし、Web と API でロジック重複）
- **認証・プロフィール:** Laravel Breeze 標準（Controller から Model 直接）。Laravel / テストツール / JS 更新の影響は **全体メトリクスに含める**

## 更新シナリオ

各シナリオの手順は [scenarios/](./scenarios/) を参照してください。

### 主シナリオ（3 件）

| # | シナリオ | ドキュメント |
|---|----------|--------------|
| 1 | API 仕様変更: status integer 化 | [api-spec-change-status-int.md](./scenarios/api-spec-change-status-int.md) |
| 2 | API 仕様変更: priority 追加 | [api-spec-change-priority.md](./scenarios/api-spec-change-priority.md) |
| 3 | DB / クエリ変更（タイトル検索） | [db-schema-change.md](./scenarios/db-schema-change.md) |

**原則:** 1 シナリオ = 1 実験ラン。両リポジトリに **同一の変更内容** を適用し、メトリクスを比較する。

### 拡張実験（参考）

以下は主シナリオとは別枠の参考計測です。手順 MD は本リポジトリには含めません。収集済み結果は `experiment/results/` を参照してください。

| シナリオ | 結果ディレクトリ |
|----------|------------------|
| Laravel バージョン更新 | [experiment/results/laravel-upgrade/](../experiment/results/laravel-upgrade/) |
| テストツール更新 | [experiment/results/test-tool-upgrade/](../experiment/results/test-tool-upgrade/) |
| JavaScript ライブラリ変更 | [experiment/results/js-library-change/](../experiment/results/js-library-change/) |

## 評価指標

### 主評価指標（構成差の比較に必須）

| 優先 | 指標 | 取得方法 |
|------|------|----------|
| **1** | 修正工数（変更ファイル数・行数） | `composer experiment:metrics -- --diff-ref experiment-baseline-v1` の `git.files_changed` / `lines_added` / `lines_deleted`（**after_fix** フェーズ） |
| **2** | 更新直後のテスト失敗数 | 同上の `phpunit.fail` / `newman.fail`（**after_update** フェーズ） |
| **3** | 作業時間（分） | [メトリクス記録テンプレート](#メトリクス記録テンプレート) に手動記録 |

> **注意:** API 仕様変更シナリオ（1・2）では **通過率だけでは改良構成と従来構成の差が出ない** 場合がある。修正ファイル数の差を主に見ること（従来構成では `Web\TaskController` と `API\TaskController` の両方を直すことが多い）。

### 1. 修正工数（主指標）

`after_fix` フェーズで CI が緑になった時点の diff を計測する。

| 項目 | 取得方法 |
|------|----------|
| 変更ファイル数 | `composer experiment:metrics` の `git.files_changed` |
| 追加 / 削除行数 | `git.lines_added` / `git.lines_deleted` |
| コミット数 | シナリオ開始〜CI 緑まで（手動） |
| 作業時間（分） | [メトリクス記録テンプレート](#メトリクス記録テンプレート) に手動記録 |

**完了基準:** 両リポジトリで CI 全ジョブが成功（`after_fix` フェーズ）。

### 2. 更新直後のテスト失敗数

通過率（成功 ÷ 総数）は参考値。**構成差の判定には使わない**（API 系シナリオで同一になりうる）。

| 対象 | 成功の定義 |
|------|------------|
| PHPUnit | `php artisan test` の pass |
| Newman | Postman コレクションの `pm.test` 成功数 |
| ESLint | `npm run lint` が exit 0 |
| PHPStan | `composer phpstan` が exit 0（エラー 0 件） |

自動収集: `composer experiment:metrics`（[scripts/collect-experiment-metrics.sh](../scripts/collect-experiment-metrics.sh)）。主に **after_update** の `phpunit.fail` / `newman.fail` を記録する。

### 3. エラー発生率

事前に定義した「エラー」を数える。

| 種別 | 数え方 |
|------|--------|
| PHPUnit 失敗数 | 更新直後（`after_update`）の fail 件数 |
| PHPStan エラー件数 | 更新直後の error 行数 |
| CI ジョブ失敗 | push ごとの失敗ジョブ数 ÷ 実行ジョブ数 |
| 手動不具合 | ブラウザ / API で発見したバグ件数（メモ欄） |

## フロントエンドスタック（拡張比較）

本リポジトリおよび改良構成リポジトリの **主実験** は Blade + Tailwind CSS + Vite + Alpine.js で統一している。**React 等の SPA フレームワークへの移行は本リポジトリには含まれない**。

Blade と React の比較は、主シナリオ 3 件とは別枠の **拡張比較** として位置づける。フロントエンド刷新が技術更新の影響範囲に与える差を調べる場合は、別リポジトリまたは別ブランチで同一シナリオを再実施すること。

## 実験フェーズ

| フェーズ | 説明 | メトリクス収集 |
|----------|------|----------------|
| `baseline` | 更新前・CI 緑の状態 | `composer experiment:metrics -- --phase baseline` |
| `after_update` | 更新適用直後・テスト未修正 | 同上 `--phase after_update` |
| `after_fix` | 修正完了・CI 緑 | 同上 `--phase after_fix` |

## ベースラインの確立

従来構成で CI がすべて成功したら、`main` の先端にタグを付ける（**legacy Docker: Web 8001 / DB 5433** が含まれること）。

```bash
git tag -a experiment-baseline-v1 -m "Experiment baseline: legacy architecture (8001 Docker)"
git push origin experiment-baseline-v1
```

以降のシナリオはこのタグからブランチを切る（手順: [ベースライン手順（BEFORE）](#ベースライン手順before)）。

## ベースライン手順（BEFORE）

legacy / improved とも **同一手順**。本リポジトリ（legacy）は **Web `8001` / DB 公開 `5433`**（改良構成の `8000` / `5432` と衝突しない）。

### 前提

1. `git pull origin main` で最新を取得してから `git fetch --tags` する（`experiment-baseline-v1` が legacy Docker 分離済みであること）。
2. 改良構成（`tech-update-task-app`）と **同時に動かす場合**、改良は **8000**、legacy は **8001** のままにする。

### 1. ブランチ作成

```bash
git fetch --tags
git checkout experiment-baseline-v1
git checkout -b exp/<scenario-id>
```

`<scenario-id>` は実施する [scenarios/](./scenarios/) MD に記載のブランチ名（例: `api-spec-change-status-int` → `exp/api-spec-change-status-int`、`api-spec-change-priority` → `exp/api-spec-change-priority`、`db-schema-change` → `exp/db-schema-change`）。

### 1-1. 品質ゲート確認

```bash
./scripts/check-quality.sh
```

**期待:** すべて成功（PHPStan / ESLint / Vite build / PHPUnit / Newman 13/13）。

- 接続先は `http://localhost:8001`（`scripts/lib/app-base-url.sh`）。
- 起動コンテナは `tech-update-task-app-legacy-*`（`scripts/lib/ensure-docker-stack.sh` が検証）。

### 1-2. ベースライン計測

```bash
composer experiment:metrics -- --phase baseline --diff-ref experiment-baseline-v1
```

### 1-3. 記録

[メトリクス記録テンプレート](#メトリクス記録テンプレート) の列定義に従い記録する。

### タグの更新について

`experiment-baseline-v1` は **CI 緑かつ legacy Docker（8001）が入った `main` の先端** を指す。古いタグ（8000 / `tech-update-task-app-php` のみ）のままでは 1-1 / 1-2 でコンテナ名衝突が起きる。

タグを更新したあと、手元で確認:

```bash
git fetch --tags
git show experiment-baseline-v1:docker-compose.yml | head -5
# → tech-update-task-app-legacy / 8001 系であること
```

## 実験の進め方（概要）

1. [ベースライン手順（BEFORE）](#ベースライン手順before)（ベースライン tag・品質ゲート・計測）
2. シナリオ用ブランチを切る（例: `exp/api-spec-change-status-int`。各 [scenarios/](./scenarios/) MD を参照）
3. [scenarios/](./scenarios/) に従い更新を適用
4. `after_update` でメトリクス収集
5. テスト・コードを修正し CI を緑にする
6. `after_fix` でメトリクス収集
7. [メトリクス記録テンプレート](#メトリクス記録テンプレート) に記録
8. 改良構成リポジトリで 3〜7 を繰り返し、各リポジトリの `experiment/results/<scenario>/` で比較

## メトリクス記録テンプレート

スプレッドシート等に転記する列定義。**主指標は `after_fix` の修正工数**（変更ファイル数・行数）。API 仕様変更シナリオでは通過率が構成間で同一になりうるため、通過率だけで構成差を判定しないこと。

### 列定義

| 列 | 説明 | 取得元 |
|----|------|--------|
| `repository` | `legacy` または `improved` | 手動 |
| `scenario` | シナリオ ID（例: `api-spec-change-status-int`） | 手動 |
| `phase` | `baseline` / `after_update` / `after_fix` | 手動 |
| `recorded_at` | ISO 8601 タイムスタンプ | metrics JSON |
| `phpunit_pass` | PHPUnit 成功数 | metrics JSON |
| `phpunit_total` | PHPUnit 総数 | metrics JSON |
| `phpunit_pass_rate` | 通過率（参考） | metrics JSON |
| `newman_pass` | Newman 成功数 | metrics JSON |
| `newman_total` | Newman 総数 | metrics JSON |
| `newman_pass_rate` | 通過率（参考） | metrics JSON |
| `phpstan_errors` | PHPStan エラー件数 | metrics JSON |
| `eslint_ok` | ESLint 成功（1/0） | metrics JSON |
| `ci_jobs_failed` | CI 失敗ジョブ数 | 手動 |
| `ci_jobs_total` | CI 実行ジョブ数 | 手動 |
| `work_minutes` | 作業時間（分） | **手動** |
| `files_changed` | 変更ファイル数 | metrics JSON `git.files_changed`（**after_fix が主指標**） |
| `lines_added` | 追加行数 | metrics JSON `git.lines_added` |
| `lines_deleted` | 削除行数 | metrics JSON `git.lines_deleted` |
| `commits` | コミット数 | **手動** |
| `manual_bugs` | 手動で発見した不具合件数 | **手動** |
| `metrics_json` | JSON ファイルへの相対パス | 自動 |
| `notes` | メモ | **手動** |

### 記録例（CSV ヘッダ）

```text
repository,scenario,phase,recorded_at,phpunit_pass,phpunit_total,phpunit_pass_rate,newman_pass,newman_total,newman_pass_rate,phpstan_errors,eslint_ok,ci_jobs_failed,ci_jobs_total,work_minutes,files_changed,lines_added,lines_deleted,commits,manual_bugs,metrics_json,notes
```

`composer experiment:record -- --scenario <id> --write` で上記列に沿った `RECORD.md` を生成できます。

## 従来構成リポジトリ作成記録

本節は、**改良構成が完成したあと**に本リポジトリ（従来構成）を作る際に実施した移行手順の記録です。新規作業時の参照用です。

### 前提

- 改良構成リポジトリに `experiment-baseline-v1` タグが付いている
- 機能・API 仕様・DB スキーマ・テスト期待値は **同一** に保つ
- `.github/workflows/ci.yml` は **コピーして同一** にする

### 1. リポジトリの複製

```bash
git clone <improved-repo-url> tech-update-task-app-legacy
cd tech-update-task-app-legacy
git remote rename origin improved-origin   # 任意
git remote add origin <new-legacy-repo-url>
```

### 2. タスク領域の「悪化」リファクタ

以下を **タスク機能のみ** 対象に実施する（認証・プロフィールは Breeze のまま）。

| 改良構成（削除・統合） | 従来構成（移行先） |
|------------------------|-------------------|
| `App\Services\TaskService` | `Web\TaskController` / `API\TaskController` にビジネスロジックを移動 |
| `TaskRepository` + `TaskRepositoryInterface` | Controller 内で `Task::query()` を直接使用 |
| `RepositoryServiceProvider` の bind | 削除 |
| Web / API で共有していた Service | 必要なら Web / API でロジックを重複実装 |

#### チェックリスト

- [ ] `app/Services/TaskService.php` を削除
- [ ] `app/Repositories/` を削除
- [ ] `RepositoryServiceProvider` を `bootstrap/providers.php` から削除
- [ ] `Web\TaskController` に一覧フィルタ・正規化・認可ロジックを集約
- [ ] `API\TaskController` に同様のロジックを集約（または Web にのみ集約し API から呼ぶ — いずれも Service 層なし）
- [ ] FormRequest / TaskResource は維持してよい（仕様・テストを揃えるため）
- [ ] `tests/Feature/TaskApiTest.php` の期待値は **変更しない**
- [ ] `tests/Feature/TaskWebTest.php` の期待値は **変更しない**

### 3. 動作・CI の確認

legacy リポジトリの Docker は改良構成と **ポート・コンテナ名を分離** 済み（Web `8001` / DB `5433`）。`.env.example` をコピーし `APP_HTTP_PORT=8001` 等を設定してから:

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
./scripts/check-quality.sh
```

- [ ] PHPUnit 全件成功
- [ ] PHPStan 0 件
- [ ] Newman 全件成功
- [ ] GitHub Actions 4 ジョブ成功

### 4. 従来構成のベースライン tag

```bash
git tag -a experiment-baseline-v1 -m "Experiment baseline: legacy architecture"
```

### 5. 比較実験時の注意

- 各シナリオは [scenarios/](./scenarios/) の **同じ変更内容** を両リポジトリに適用する
- 修正は「CI が緑になる最小限」に留め、リファクタで差を広げない
- メトリクスは [メトリクス記録テンプレート](#メトリクス記録テンプレート) の列定義で両方記録する

## 関連ドキュメント

| ドキュメント | 内容 |
|--------------|------|
| [README.md](../README.md) | プロジェクト概要・クイックスタート |
