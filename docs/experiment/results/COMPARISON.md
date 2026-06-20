# 改良構成 vs 従来構成 — 実験比較表

本ドキュメントは、**主シナリオ 3 件** について改良構成（improved）と従来構成（legacy）で実施した 3 フェーズ計測の要約です。詳細 JSON は各サブディレクトリを参照してください。

> **比較の読み方:** `after_update` の PHPUnit / Newman **通過率は構成によって同一になることがある**（同一テストスイートのため）。構成差の **主指標は `after_fix` の `git.files_changed` / `lines_added` / `lines_deleted`**（`composer experiment:metrics -- --diff-ref experiment-baseline-v1`）。詳細は [EXPERIMENT.md](../EXPERIMENT.md) を参照。

> **main ブランチ:** タスクのベースライン仕様は **4 属性**（`title` / `description` / `due_date` / `status`）。`priority` 追加・status integer 化は **`exp/*` ブランチ** で実施し、新規実験の起点は `experiment-baseline-v1` タグを使用すること。

---

## 主シナリオ 1: API 仕様変更（status integer 化）

手順: [scenarios/api-spec-change-status-int.md](../scenarios/api-spec-change-status-int.md)

| 構成 | フェーズ | PHPUnit | Newman | 備考 |
|------|----------|---------|--------|------|
| improved | baseline | — | — | 未収集または別 run |
| improved | after_update | — | — | |
| improved | after_fix | — | — | **主指標: files_changed** |
| legacy | baseline | — | — | |
| legacy | after_update | — | — | |
| legacy | after_fix | — | — | |

結果: [legacy/api-spec-change-status-int/](./legacy/api-spec-change-status-int/)

**期待される差:** 従来構成は `Web\TaskController` と `API\TaskController` の **両方** を修正するため、`files_changed` が改良構成より多い。

---

## 主シナリオ 2: API 仕様変更（`priority` 追加）

手順: [scenarios/api-spec-change-priority.md](../scenarios/api-spec-change-priority.md)

| 構成 | フェーズ | PHPUnit | Newman | PHPStan | Vite build |
|------|----------|---------|--------|---------|------------|
| improved | baseline | 38/38 (100%) | 13/13 (100%) | 0 | OK |
| improved | after_update | 36/38 (94.74%) | 10/13 (76.92%) | 0 | OK |
| improved | after_fix | 38/38 (100%) | 13/13 (100%) | 0 | OK |
| legacy | baseline | 38/38 (100%) | 13/13 (100%) | 0 | OK |
| legacy | after_update | 36/38 (94.74%) | 10/13 (76.92%) | 0 | OK |
| legacy | after_fix | 38/38 (100%) | 13/13 (100%) | 0 | OK |

**主な修正ファイル（改良）:** `TaskResource`, FormRequest×2, `TaskService`, migration, テスト, Postman（Controller / Repository は未変更）

**主な修正ファイル（従来）:** 上記に加え **`Web\TaskController` と `API\TaskController` の `normalizeTaskPayload` を両方更新**

| run_id | 構成 | 結果ディレクトリ |
|--------|------|------------------|
| `run-20260521T060318Z` | improved | [api-spec-change/](./api-spec-change/) |
| `run-20260521T061416Z` | legacy | [legacy/api-spec-change/](./legacy/api-spec-change/) |

> **履歴:** 旧シナリオ ID `api-spec-change` として実施。現行 ID は `api-spec-change-priority`。

**所見:** 更新直後の失敗数・通過率は同一。構成差は **`after_fix` の修正工数**（従来構成で Controller 2 ファイル分が増える）で評価する。

---

## 主シナリオ 3: DB / クエリ変更（タイトル検索）

手順: [scenarios/db-schema-change.md](../scenarios/db-schema-change.md)

| 構成 | フェーズ | PHPUnit | Newman | 備考 |
|------|----------|---------|--------|------|
| improved | after_fix | — | — | Repository 1 ファイル修正想定 |
| legacy | after_fix | — | — | Controller 2 ファイル修正想定 |

結果: （収集後に `legacy/db-schema-change/` 等を追記）

**期待される差:** 従来構成は `files_changed` が改良構成より **+1（Web Controller）** 程度多い。

---

## 拡張実験（参考）

主シナリオ 3 件とは **別枠** の参考計測。手順 MD は本リポジトリに含めない。

### Laravel バージョン更新（13.8.0 → 13.11.2）

| フェーズ | PHPUnit | Newman | PHPStan | 備考 |
|----------|---------|--------|---------|------|
| baseline | 38/38 (100%) | 13/13 (100%) | 0 | |
| after_update | 38/38 (100%) | 13/13 (100%) | 0 | 同一メジャー内マイナー更新 |
| after_fix | 38/38 (100%) | 13/13 (100%) | 0 | コード修正不要 |

結果: [laravel-upgrade/](./laravel-upgrade/)（`run-20260521T060830Z`）

### テストツール更新（PHPUnit / PHPStan / Larastan / Newman）

| フェーズ | PHPUnit | Newman | PHPStan | 備考 |
|----------|---------|--------|---------|------|
| baseline | 38/38 (100%) | 13/13 (100%) | 0 | |
| after_update | 38/38 (100%) | 13/13 (100%) | 0 | lock 更新のみ |
| after_fix | 38/38 (100%) | 13/13 (100%) | 0 | テストコード修正不要 |

結果: [test-tool-upgrade/](./test-tool-upgrade/)（`run-20260521T060939Z`）

### JavaScript ライブラリ変更（Alpine / Vite / Tailwind 4）

| フェーズ | PHPUnit | Newman | ESLint | Vite build |
|----------|---------|--------|--------|------------|
| baseline | 38/38 (100%) | 13/13 (100%) | OK | OK |
| after_update | 38/38 (100%) | 13/13 (100%) | OK | **失敗**（Tailwind 4 PostCSS 非互換） |
| after_fix | 38/38 (100%) | 13/13 (100%) | OK | OK（`@tailwindcss/vite` 移行後） |

結果: [js-library-change/](./js-library-change/)（`run-20260521T061059Z`）

**after_fix で修正したファイル:** `vite.config.js`, `resources/css/app.css`, `postcss.config.js`

---

## ブランチ一覧

| ブランチ / タグ | 内容 |
|-----------------|------|
| `main` | 従来構成ベースライン（4 属性） |
| `experiment-baseline-v1` | ベースラインタグ（メトリクス比較の起点） |
| `exp/*` | 更新シナリオ実施用ブランチ |

---

## 評価指標の達成

| 指標 | 状態 |
|------|------|
| 修正工数（主） | `after_fix` の `git.files_changed` / 行数を比較表に記載 |
| 更新直後のテスト失敗数 | 主シナリオ 2 で `after_update` の失敗数を記録 |
| エラー発生率 | 主シナリオ 2・拡張 JS 変更で `after_update` の失敗を記録 |
| 従来構成比較 | 主シナリオ 2（priority）で improved vs legacy を実施 |
