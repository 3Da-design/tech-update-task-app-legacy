# 実験記録（自動生成）

| 項目 | 値 |
|------|----|
| **run_id** | `run-20260827T012200Z` |
| **シナリオ** | `db-schema-change` |
| **リポジトリ** | `legacy` |

手動項目（CI・作業時間・コミット数など）は [手動記入](#manual) の表に追記してください。 スプレッドシートへそのまま貼る場合は [TSV（全列）](#tsv) を使えます。

**修正工数:** 主指標は `git_app`（`experiment/results/`・`experiment/metrics/` を除外したアプリ差分）。 `git` は実験メタデータ（結果 JSON 等）を含む参考値です。

## 自動収集サマリー

| フェーズ | 記録時刻 | PHPUnit | Newman | PHPStan | ESLint |
|:---------|:---------|:--------|:-------|:--------|:-------|
| ベースライン | `20260827T012200Z` | 47/47 (100.0%) | 13/13 (100.0%) | 0 件 | OK |
| 更新直後 | `20260827T013604Z` | 47/47 (100.0%) | 13/13 (100.0%) | 0 件 | OK |
| 修正後 | `20260827T014819Z` | 49/49 (100.0%) | 13/13 (100.0%) | 0 件 | OK |

<a id="manual"></a>

## 手動記入（実験者が追記）

| フェーズ | CI (失敗/総数) | 作業時間 (分) | アプリ変更ファイル | アプリ追加行 | アプリ削除行 | コミット数 | 手動バグ | メモ |
|:---------|:---------------|:--------------|:-------------------|:-------------|:-------------|:-----------|:---------|:-----|
| ベースライン | 0/4 | 5 | 0 | 0 | 0 | 1 | 0 | タグと差分ゼロの anchor コミットのみ |
| 更新直後 | 0/4 | 11 | 2 | 2 | 2 | 1 | 0 | Web/API 両 Controller の listForUser に同一クエリ変更が分散 |
| 修正後 | 0/4 | 10 | 3 | 35 | 2 | 1 | 0 | ケース無視テスト2件を追加。improved は Repository 1 ファイルで済む想定 |

## フェーズ別詳細

### ベースライン (`baseline`)

- **JSON:** [`baseline.json`](experiment/metrics/runs/run-20260827T012200Z/baseline.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git_app（アプリ修正工数・主指標）:** 0 files, +0 / -0 (`（なし）`)
- **git（実験メタデータ込み）:** 0 files, +0 / -0 (`（なし）`)

### 更新直後 (`after_update`)

- **JSON:** [`after_update.json`](experiment/metrics/runs/run-20260827T012200Z/after_update.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git_app（アプリ修正工数・主指標）:** 2 files, +2 / -2 (` 2 files changed, 2 insertions(+), 2 deletions(-)`)
- **git（実験メタデータ込み）:** 2 files, +2 / -2 (` 2 files changed, 2 insertions(+), 2 deletions(-)`)

### 修正後 (`after_fix`)

- **JSON:** [`after_fix.json`](experiment/metrics/runs/run-20260827T012200Z/after_fix.json)
- **git diff_ref:** `experiment-baseline-v1`
- **git_app（アプリ修正工数・主指標）:** 3 files, +35 / -2 (` 3 files changed, 35 insertions(+), 2 deletions(-)`)
- **git（実験メタデータ込み）:** 3 files, +35 / -2 (` 3 files changed, 35 insertions(+), 2 deletions(-)`)

<a id="tsv"></a>

<details>
<summary>スプレッドシート用 TSV（全列）</summary>

```tsv
repository	scenario	phase	recorded_at	phpunit_pass	phpunit_total	phpunit_pass_rate	newman_pass	newman_total	newman_pass_rate	phpstan_errors	eslint_ok	ci_jobs_failed	ci_jobs_total	work_minutes	app_files_changed	app_lines_added	app_lines_deleted	meta_files_changed	meta_lines_added	meta_lines_deleted	commits	manual_bugs	metrics_json	notes
legacy	db-schema-change	baseline	20260827T012200Z	47	47	100.0	13	13	100.0	0	1				0	0	0	0	0	0			experiment/metrics/runs/run-20260827T012200Z/baseline.json	
legacy	db-schema-change	after_update	20260827T013604Z	47	47	100.0	13	13	100.0	0	1				2	2	2	2	2	2			experiment/metrics/runs/run-20260827T012200Z/after_update.json	 2 files changed, 2 insertions(+), 2 deletions(-)
legacy	db-schema-change	after_fix	20260827T014819Z	49	49	100.0	13	13	100.0	0	1				3	35	2	3	35	2			experiment/metrics/runs/run-20260827T012200Z/after_fix.json	 3 files changed, 35 insertions(+), 2 deletions(-)
```

</details>
