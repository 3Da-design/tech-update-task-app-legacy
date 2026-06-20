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

本リポジトリの作成手順（改良構成からの移行記録）は [experiment/LEGACY_MIGRATION.md](./experiment/LEGACY_MIGRATION.md) を参照してください。

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

各シナリオの手順は [experiment/scenarios/](./experiment/scenarios/) を参照してください。

| シナリオ | ドキュメント |
|----------|--------------|
| バックエンド API 仕様変更（索引） | [api-spec-change.md](./experiment/scenarios/api-spec-change.md) |
| API 仕様変更: status integer 化 | [api-spec-change-status-int.md](./experiment/scenarios/api-spec-change-status-int.md) |
| API 仕様変更: priority 追加 | [api-spec-change-priority.md](./experiment/scenarios/api-spec-change-priority.md) |
| DB / クエリ変更（タイトル検索） | [db-schema-change.md](./experiment/scenarios/db-schema-change.md) |
| Laravel バージョン更新 | [laravel-upgrade.md](./experiment/scenarios/laravel-upgrade.md) |
| テストツール更新 | [test-tool-upgrade.md](./experiment/scenarios/test-tool-upgrade.md) |
| JavaScript ライブラリ変更 | [js-library-change.md](./experiment/scenarios/js-library-change.md) |

**原則:** 1 シナリオ = 1 実験ラン。両リポジトリに **同一の変更内容** を適用し、メトリクスを比較する。

## 評価指標

### 主評価指標（構成差の比較に必須）

| 優先 | 指標 | 取得方法 |
|------|------|----------|
| **1** | 修正工数（変更ファイル数・行数） | `composer experiment:metrics -- --diff-ref experiment-baseline-v1` の `git.files_changed` / `lines_added` / `lines_deleted`（**after_fix** フェーズ） |
| **2** | 更新直後のテスト失敗数 | 同上の `phpunit.fail` / `newman.fail`（**after_update** フェーズ） |
| **3** | 作業時間（分） | [metrics-record-template.md](./experiment/metrics-record-template.md) に手動記録 |

> **注意:** シナリオ 1（API 仕様変更）では **通過率だけでは改良構成と従来構成の差が出ない** 場合がある。修正ファイル数の差を主に見ること（従来構成では `Web\TaskController` と `API\TaskController` の両方を直すことが多い）。

### 1. テスト通過率

```
通過率 (%) = 成功数 ÷ 総数 × 100
```

| 対象 | 成功の定義 |
|------|------------|
| PHPUnit | `php artisan test` の pass |
| Newman | Postman コレクションの `pm.test` 成功数 |
| ESLint | `npm run lint` が exit 0 |
| PHPStan | `composer phpstan` が exit 0（エラー 0 件） |

自動収集: `composer experiment:metrics`（[scripts/collect-experiment-metrics.sh](../scripts/collect-experiment-metrics.sh)）

### 2. 修正工数

自動化しない項目。 [metrics-record-template.md](./experiment/metrics-record-template.md) に手動記録する。

| 項目 | 取得方法 |
|------|----------|
| 作業時間（分） | タイマーまたは手入力 |
| 変更ファイル数 | `git diff --stat` |
| 追加 / 削除行数 | `git diff --shortstat` |
| コミット数 | シナリオ開始〜CI 緑まで |

**完了基準:** 両リポジトリで CI 全ジョブが成功（`after_fix` フェーズ）。

### 3. エラー発生率

事前に定義した「エラー」を数える。

| 種別 | 数え方 |
|------|--------|
| PHPUnit 失敗数 | 更新直後（`after_update`）の fail 件数 |
| PHPStan エラー件数 | 更新直後の error 行数 |
| CI ジョブ失敗 | push ごとの失敗ジョブ数 ÷ 実行ジョブ数 |
| 手動不具合 | ブラウザ / API で発見したバグ件数（メモ欄） |

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

以降のシナリオはこのタグからブランチを切る（手順: [experiment/BEFORE.md](./experiment/BEFORE.md)）。

## 実験の進め方（概要）

1. [BEFORE.md](./experiment/BEFORE.md)（ベースライン tag・品質ゲート・計測）
2. シナリオ用ブランチを切る（例: `exp/api-spec-change-status-int`。各 [scenarios/](./experiment/scenarios/) MD を参照）
3. [scenarios/](./experiment/scenarios/) に従い更新を適用
4. `after_update` でメトリクス収集
5. テスト・コードを修正し CI を緑にする
6. `after_fix` でメトリクス収集
7. [metrics-record-template.md](./experiment/metrics-record-template.md) に記録
8. 改良構成リポジトリで 3〜7 を繰り返し、[results/COMPARISON.md](./experiment/results/COMPARISON.md) で比較

## 実験結果（収集済み）

| ドキュメント | 内容 |
|--------------|------|
| [experiment/results/COMPARISON.md](./experiment/results/COMPARISON.md) | 全シナリオの 3 フェーズ比較表（改良 vs 従来） |
| [experiment/results/legacy/api-spec-change/](./experiment/results/legacy/api-spec-change/) | priority 追加（旧 ID `api-spec-change`・従来構成） |
| [experiment/results/legacy/api-spec-change-status-int/](./experiment/results/legacy/api-spec-change-status-int/) | status integer 化（従来構成） |

## 関連ドキュメント

| ドキュメント | 内容 |
|--------------|------|
| [README.md](../README.md) | プロジェクト概要・クイックスタート |
| [TESTING.md](./TESTING.md) | テストツールの詳細 |
| [CI.md](./CI.md) | GitHub Actions |
| [FEATURE_LIST.md](./FEATURE_LIST.md) | 機能一覧 |
| [experiment/metrics-record-template.md](./experiment/metrics-record-template.md) | 記録テンプレート |
| [experiment/LEGACY_MIGRATION.md](./experiment/LEGACY_MIGRATION.md) | 改良構成から本リポジトリを作った際の移行記録 |
