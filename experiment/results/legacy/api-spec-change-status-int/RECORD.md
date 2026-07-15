# 実験記録（自動生成）

| 項目 | 値 |
|------|----|
| **run_id** | `run-20260715T054248Z` |
| **シナリオ** | `api-spec-change-status-int` |
| **リポジトリ** | `legacy` |

手動項目（CI・作業時間・コミット数など）は [手動記入](#manual) の表に追記してください。 スプレッドシートへそのまま貼る場合は [TSV（全列）](#tsv) を使えます。

**修正工数:** 主指標は `git_app`（`experiment/results/`・`experiment/metrics/` を除外したアプリ差分）。 `git` は実験メタデータ（結果 JSON 等）を含む参考値です。

## 自動収集サマリー

| フェーズ | 記録時刻 | PHPUnit | Newman | PHPStan | ESLint |
|:---------|:---------|:--------|:-------|:--------|:-------|
| ベースライン | `20260715T054248Z` | 47/47 (100.0%) | 13/13 (100.0%) | 0 件 | OK |
| 更新直後 | `20260715T060410Z` | 30/47 (63.8%) | 10/13 (76.9%) | 0 件 | OK |
| 修正後 | `20260715T061239Z` | 47/47 (100.0%) | 13/13 (100.0%) | 0 件 | OK |

<a id="manual"></a>

## 手動記入（実験者が追記）

| フェーズ | CI (失敗/総数) | 作業時間 (分) | アプリ変更ファイル | アプリ追加行 | アプリ削除行 | コミット数 | 手動バグ | メモ |
|:---------|:---------------|:--------------|:-------------------|:-------------|:-------------|:-----------|:---------|:-----|
| ベースライン | 4/4 | 10 | 0 | 0 | 0 | 0 | 0 | |
| 更新直後 | 2/4 | 30 | 10 | 75 | 22 | 1 | 0 | |
| 修正後 | 4/4 | 20 | 14 | 97 | 44 | 1 | 0 | |

## フェーズ別詳細

### ベースライン (`baseline`)

- **JSON:** [`baseline.json`](experiment/metrics/runs/run-20260715T054248Z/baseline.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git_app（アプリ修正工数・主指標）:** 0 files, +0 / -0 (`（なし）`)
- **git（実験メタデータ込み）:** 0 files, +0 / -0 (`（なし）`)

### 更新直後 (`after_update`)

- **JSON:** [`after_update.json`](experiment/metrics/runs/run-20260715T054248Z/after_update.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git_app（アプリ修正工数・主指標）:** 10 files, +75 / -22 (` 10 files changed, 75 insertions(+), 22 deletions(-)`)
- **git（実験メタデータ込み）:** 10 files, +75 / -22 (` 10 files changed, 75 insertions(+), 22 deletions(-)`)

### 修正後 (`after_fix`)

- **JSON:** [`after_fix.json`](experiment/metrics/runs/run-20260715T054248Z/after_fix.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git_app（アプリ修正工数・主指標）:** 14 files, +97 / -44 (` 14 files changed, 97 insertions(+), 44 deletions(-)`)
- **git（実験メタデータ込み）:** 14 files, +97 / -44 (` 14 files changed, 97 insertions(+), 44 deletions(-)`)

<a id="tsv"></a>

<details>
<summary>スプレッドシート用 TSV（全列）</summary>

```tsv
repository	scenario	phase	recorded_at	phpunit_pass	phpunit_total	phpunit_pass_rate	newman_pass	newman_total	newman_pass_rate	phpstan_errors	eslint_ok	ci_jobs_failed	ci_jobs_total	work_minutes	app_files_changed	app_lines_added	app_lines_deleted	meta_files_changed	meta_lines_added	meta_lines_deleted	commits	manual_bugs	metrics_json	notes
legacy	api-spec-change-status-int	baseline	20260715T054248Z	47	47	100.0	13	13	100.0	0	1				0	0	0	0	0	0			experiment/metrics/runs/run-20260715T054248Z/baseline.json	
legacy	api-spec-change-status-int	after_update	20260715T060410Z	30	47	63.83	10	13	76.92	0	1				10	75	22	10	75	22			experiment/metrics/runs/run-20260715T054248Z/after_update.json	 10 files changed, 75 insertions(+), 22 deletions(-)
legacy	api-spec-change-status-int	after_fix	20260715T061239Z	47	47	100.0	13	13	100.0	0	1				14	97	44	14	97	44			experiment/metrics/runs/run-20260715T054248Z/after_fix.json	 14 files changed, 97 insertions(+), 44 deletions(-)
```

</details>
