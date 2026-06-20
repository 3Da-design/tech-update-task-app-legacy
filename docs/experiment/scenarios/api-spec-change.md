# シナリオ: バックエンド API 仕様変更（索引）

API 仕様変更シナリオは、比較対象を明確にするため **2 つのサブシナリオ** に分割されています。いずれも `experiment-baseline-v1` タグから `exp/*` ブランチを切って実施します。

| サブシナリオ | シナリオ ID | 内容 |
|-------------|-------------|------|
| [status integer 化](./api-spec-change-status-int.md) | `api-spec-change-status-int` | `status` を string → int（0/1/2）に変更 |
| [priority 追加](./api-spec-change-priority.md) | `api-spec-change-priority` | レスポンスに `priority` フィールドを追加 |

## 共通の進め方

1. [BEFORE.md](../BEFORE.md) — ベースライン tag・品質ゲート・`baseline` 計測
2. 上記サブシナリオ MD の手順に従い更新を適用
3. `after_update` → テスト修正 → `after_fix` でメトリクス収集
4. 改良構成リポジトリで同一手順を実施

評価指標の定義: [EXPERIMENT.md](../../EXPERIMENT.md)

## 履歴

旧シナリオ ID `api-spec-change`（`priority` 追加）として実施した結果:

- 改良構成: [results/api-spec-change/](../results/api-spec-change/)
- 従来構成: [results/legacy/api-spec-change/](../results/legacy/api-spec-change/)

`status` integer 化の結果:

- 従来構成: [results/legacy/api-spec-change-status-int/](../results/legacy/api-spec-change-status-int/)
