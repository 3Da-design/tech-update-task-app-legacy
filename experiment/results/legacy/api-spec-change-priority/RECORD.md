# 実験記録（自動生成）

| 項目 | 値 |
|------|----|
| **run_id** | `run-20260703T063745Z` |
| **シナリオ** | `api-spec-change-priority` |
| **リポジトリ** | `legacy` |

手動項目（CI・作業時間・コミット数など）は [手動記入](#manual) の表に追記してください。 スプレッドシートへそのまま貼る場合は [TSV（全列）](#tsv) を使えます。

## 自動収集サマリー

| フェーズ | 記録時刻 | PHPUnit | Newman | PHPStan | ESLint |
|:---------|:---------|:--------|:-------|:--------|:-------|
| ベースライン | `20260703T063745Z` | 47/47 (100.0%) | 13/13 (100.0%) | 0 件 | OK |
| 更新直後 | `20260703T064012Z` | 47/47 (100.0%) | 13/13 (100.0%) | 0 件 | OK |
| 修正後 | `20260703T064309Z` | 52/52 (100.0%) | 15/15 (100.0%) | 0 件 | OK |

<a id="manual"></a>

## 手動記入（実験者が追記）

| フェーズ | CI (失敗/総数) | 作業時間 (分) | 変更ファイル | 追加行 | 削除行 | コミット数 | 手動バグ | メモ |
|:---------|:---------------|:--------------|:-------------|:-------|:-------|:-----------|:---------|:-----|
| ベースライン | 0/4 | 10| 0 | 0 | 0 | 0 | 0 | 変更前 |
| 更新直後 | 2/4 | 45 | 10 | 81 | 28 | 1 | 0 | テスト・Postman 未修正（PHPUnit 17失敗、Newman 3失敗） |
| 修正後 | 0/4 | 50 | 14 | 103 | 50 | 2 | 0 | テスト・Postman 修正完了 |

## フェーズ別詳細

### ベースライン (`baseline`)

- **JSON:** [`baseline.json`](experiment/metrics/runs/run-20260703T063745Z/baseline.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git:** 0 files, +0 / -0 (`（なし）`)

### 更新直後 (`after_update`)

- **JSON:** [`after_update.json`](experiment/metrics/runs/run-20260703T063745Z/after_update.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git:** 11 files, +157 / -12 (` 11 files changed, 157 insertions(+), 12 deletions(-)`)

### 修正後 (`after_fix`)

- **JSON:** [`after_fix.json`](experiment/metrics/runs/run-20260703T063745Z/after_fix.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git:** 15 files, +263 / -19 (` 15 files changed, 263 insertions(+), 19 deletions(-)`)

<a id="tsv"></a>

<details>
<summary>スプレッドシート用 TSV（全列）</summary>

```tsv
repository	scenario	phase	recorded_at	phpunit_pass	phpunit_total	phpunit_pass_rate	newman_pass	newman_total	newman_pass_rate	phpstan_errors	eslint_ok	ci_jobs_failed	ci_jobs_total	work_minutes	files_changed	lines_added	lines_deleted	commits	manual_bugs	metrics_json	notes
legacy	api-spec-change-priority	baseline	20260703T063745Z	47	47	100.0	13	13	100.0	0	1			0	0	0			experiment/metrics/runs/run-20260703T063745Z/baseline.json	
legacy	api-spec-change-priority	after_update	20260703T064012Z	47	47	100.0	13	13	100.0	0	1			11	157	12			experiment/metrics/runs/run-20260703T063745Z/after_update.json	 11 files changed, 157 insertions(+), 12 deletions(-)
legacy	api-spec-change-priority	after_fix	20260703T064309Z	52	52	100.0	15	15	100.0	0	1			15	263	19			experiment/metrics/runs/run-20260703T063745Z/after_fix.json	 15 files changed, 263 insertions(+), 19 deletions(-)
```

</details>
