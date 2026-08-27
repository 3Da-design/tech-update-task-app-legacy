# 実験記録（自動生成）

| 項目 | 値 |
|------|----|
| **run_id** | `run-20260827T045339Z` |
| **シナリオ** | `api-spec-change-priority` |
| **リポジトリ** | `legacy` |

手動項目（CI・作業時間・コミット数など）は [手動記入](#manual) の表に追記してください。 スプレッドシートへそのまま貼る場合は [TSV（全列）](#tsv) を使えます。

**修正工数:** 主指標は `git_app`（`experiment/results/`・`experiment/metrics/` を除外したアプリ差分）。 `git` は実験メタデータ（結果 JSON 等）を含む参考値です。

## 自動収集サマリー

| フェーズ | 記録時刻 | PHPUnit | Newman | PHPStan | ESLint |
|:---------|:---------|:--------|:-------|:--------|:-------|
| ベースライン | `20260827T045346Z` | 47/47 (100.0%) | 13/13 (100.0%) | 0 件 | OK |
| 更新直後 | `20260827T045421Z` | 47/47 (100.0%) | 13/13 (100.0%) | 0 件 | OK |
| 修正後 | `20260827T045445Z` | 54/54 (100.0%) | 15/15 (100.0%) | 0 件 | OK |

<a id="manual"></a>

## 手動記入（実験者が追記）

| フェーズ | CI (失敗/総数) | 作業時間 (分) | アプリ変更ファイル | アプリ追加行 | アプリ削除行 | コミット数 | 手動バグ | メモ |
|:---------|:---------------|:--------------|:-------------------|:-------------|:-------------|:-----------|:---------|:-----|
| ベースライン | 0/4 | 5 | 0 | 0 | 0 | 1 | 0 | タグと差分ゼロの anchor コミット（`2e9b442`）。CI 4ジョブ緑（run 32934243972・PHPUnit 47/47・Newman 13/13・PHPStan 0・ESLint OK）。**本 run（`run-20260827T045339Z`）で再計測し、旧 run `run-20260826T052846Z` と全項目一致。** |
| 更新直後 | 0/4 | 47 | 11 | 145 | 10 | 1 | 1 | 実装のみ（`5af3112`、tests/Postman 未修正）。**非破壊的変更**のため既存テストは全通過（PHPUnit 47/47・Newman 13/13）。CI 4ジョブ緑（run 32937080543）。**手動バグ1件:** 初回 push の `4cd9b08` に `config('tasks.priority_values')`（正 `task.`）のタイプミスが6箇所あり、Blade の `foreach(null)` で 500 → CI 2/4 赤（run 32936534911・`PHP Tests` / `API Tests (Newman)`）。amend 修正後の `5af3112` で after_update を再計測。**本 run で再計測し、旧 run と `git_app`・テスト数とも一致。** |
| 修正後 | 0/4 | 38 | 15 | 260 | 12 | 2 | 0 | tests 3ファイル + Postman を priority 仕様へ修正（`881f8a6`）。**2026-08-27 に `e6f8989` で desc ソートのテスト2件（`test_web_index_sorts_priority_desc` / `test_api_index_sorts_priority_desc`）を追補し、improved とテストカバレッジを揃えて再計測した**（PHPUnit 52/52 → **54/54**・`git_app` +230 → **+260**、ファイル数 15 は不変）。当初 legacy は `$priorityDirection` の desc 分岐を実装したまま未検証だった。**作業時間 38 分は desc テスト2件の追補（6 分）を含む実計時**であり、上記 `git_app` 15 (+260/−12)・PHPUnit 54/54 に対応する（→ `results/第1章/api-spec-change-priority.md`）。**参考: メタ込み `git` 20 (+510/−12) は publish 済みコミットを含む HEAD で計測したためで、publish 前に測った improved（17 (+262/−10)）とは定義が揃わない。比較は `git_app` で行う。** ローカル `check-quality.sh` 緑（PHPUnit 54/54・Newman 15/15・PHPStan 0・ESLint OK）。**legacy 固有:** Service/Repository が無いため `normalizeTaskPayload` / `normalizeListFilters` / `listForUser` を Web/API 両 Controller に二重修正（15 files の内訳 = Controller 2・Request 3・Resource 1・Model 1・config 1・migration 1・Blade 2・tests 3・Postman 1） |

## フェーズ別詳細

### ベースライン (`baseline`)

- **JSON:** [`baseline.json`](experiment/metrics/runs/run-20260827T045339Z/baseline.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git_app（アプリ修正工数・主指標）:** 0 files, +0 / -0 (`（なし）`)
- **git（実験メタデータ込み）:** 0 files, +0 / -0 (`（なし）`)

### 更新直後 (`after_update`)

- **JSON:** [`after_update.json`](experiment/metrics/runs/run-20260827T045339Z/after_update.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git_app（アプリ修正工数・主指標）:** 11 files, +145 / -10 (` 11 files changed, 145 insertions(+), 10 deletions(-)`)
- **git（実験メタデータ込み）:** 11 files, +145 / -10 (` 11 files changed, 145 insertions(+), 10 deletions(-)`)

### 修正後 (`after_fix`)

- **JSON:** [`after_fix.json`](experiment/metrics/runs/run-20260827T045339Z/after_fix.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git_app（アプリ修正工数・主指標）:** 15 files, +260 / -12 (` 15 files changed, 260 insertions(+), 12 deletions(-)`)
- **git（実験メタデータ込み）:** 20 files, +510 / -12 (` 20 files changed, 510 insertions(+), 12 deletions(-)`)

<a id="tsv"></a>

<details>
<summary>スプレッドシート用 TSV（全列）</summary>

```tsv
repository	scenario	phase	recorded_at	phpunit_pass	phpunit_total	phpunit_pass_rate	newman_pass	newman_total	newman_pass_rate	phpstan_errors	eslint_ok	ci_jobs_failed	ci_jobs_total	work_minutes	app_files_changed	app_lines_added	app_lines_deleted	meta_files_changed	meta_lines_added	meta_lines_deleted	commits	manual_bugs	metrics_json	notes
legacy	api-spec-change-priority	baseline	20260827T045346Z	47	47	100.0	13	13	100.0	0	1				0	0	0	0	0	0			experiment/metrics/runs/run-20260827T045339Z/baseline.json	
legacy	api-spec-change-priority	after_update	20260827T045421Z	47	47	100.0	13	13	100.0	0	1				11	145	10	11	145	10			experiment/metrics/runs/run-20260827T045339Z/after_update.json	 11 files changed, 145 insertions(+), 10 deletions(-)
legacy	api-spec-change-priority	after_fix	20260827T045445Z	54	54	100.0	15	15	100.0	0	1				15	260	12	20	510	12			experiment/metrics/runs/run-20260827T045339Z/after_fix.json	 15 files changed, 260 insertions(+), 12 deletions(-)
```

</details>
