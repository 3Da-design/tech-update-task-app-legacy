# CLAUDE.md

このファイルは、Claude Code (claude.ai/code) が本リポジトリで作業する際のガイダンスです。

## 必読（この順）

1. `../docs/EXPERIMENT-STACK.md` — 研究全体の正典（第1章完了・第2章方針・制約）
2. `../CLAUDE.md` — リポジトリ横断のルールと案内
3. 本ファイル
4. `docs/EXPERIMENT.md` — 第1章 legacy の設計・指標ドキュメント

## 言語設定

ユーザーとのやり取り・説明・コミットメッセージ・コメントは **日本語**。コード識別子は英語可。

## リポジトリの位置づけ

| 項目 | 内容 |
|------|------|
| 研究テーマ | 技術更新に強い Web アプリ基盤の検討 |
| 章 | **第1章のみ**（**第2章では新規構築せず、第1章データを参照するのみ**） |
| スタック ID | **S0 legacy**（Laravel + Blade + Alpine.js。スタックは S0 improved と同一） |
| 設計 | **legacy**（Fat Controller。Service / Repository なし・**意図的な「悪い例」**） |
| 役割 | 第1章 improved との対比基準（従来構成の修正コストを計測） |

**目的:** Fat Controller の「悪い例」を保持し、improved 設計との修正コスト差を第1章で示すための計測対象。`Web\TaskController` と `API\TaskController` に同一ロジックが重複し、仕様変更時に両方を直す必要がある構成を **意図的に維持** する。

## 絶対に守る制約

1. **リファクタ禁止（最重要）** — `Services/` / `Repositories/` を導入する層分離リファクタを **行わない**。Eloquent を Controller から直接操作する legacy 構成を維持する（これが実験対象そのもの）。
2. **第2章では触らない** — 本リポジトリは第2章で新規構築しない。第1章データの参照専用。
3. **ベースライン汚染禁止** — シナリオ変更は `exp/*` ブランチのみ。`main` / `experiment-baseline-v1` タグに混在させない。
4. **Docker Compose のみ** — ホストで `php artisan serve` / `npm install` を実行しない。
5. **ベースライン仕様** — タスク属性は `title` / `description` / `due_date` / `status` の4項目のみ（シナリオ前）。

## 開発環境

| 項目 | 値 |
|------|-----|
| Web（Laravel/nginx） | `http://localhost:8001` |
| Vite dev | `http://localhost:5174` |
| DB 公開ポート | `5433` |
| Compose 名 | `tech-update-task-app-legacy` |
| コンテナ名 | `tech-update-task-app-legacy-{php,node,nginx,postgres}` |
| シードユーザー | `test@example.com` / `password` |

### 初回セットアップ

```bash
docker compose up -d
docker compose exec app composer setup   # install〜key:generate〜migrate〜フロントビルドまで一括
```

### よく使うコマンド

```bash
./scripts/check-quality.sh
docker compose exec app composer check     # phpstan + test
docker compose exec app composer phpstan
docker compose exec app composer test
./scripts/curl-api-smoke.sh
```

## アーキテクチャ（legacy・維持）

```text
Browser (Blade + Alpine.js)
    │
    ├─ Web\TaskController ─→ Eloquent (Task Model)   ┐ 同一ロジックが
    └─ API\TaskController ─→ Eloquent (Task Model)   ┘ 2か所に重複
```

- `app/Services/` / `app/Repositories/` は **持たない**（層分離なし）。
- 仕様変更時に Web/API の両 Controller を修正する必要がある＝「修正箇所の分散」を計測。

## 実験ワークフロー

```bash
docker compose exec app composer experiment:metrics -- --phase baseline    --diff-ref experiment-baseline-v1
docker compose exec app composer experiment:metrics -- --phase after_update --diff-ref experiment-baseline-v1
docker compose exec app composer experiment:metrics -- --phase after_fix    --diff-ref experiment-baseline-v1
docker compose exec app composer experiment:record  -- --scenario <id> --write
```

- 主指標: `git_app` の変更ファイル数・行数（`after_fix`）。通過率だけで判定しない。
- シナリオ手順: `docs/scenarios/*.md`（3件とも計測済み）。
- improved との統合結果: `experiment/results/COMPARISON.md`。

## 関連ドキュメント

| ファイル | 内容 |
|----------|------|
| `../docs/EXPERIMENT-STACK.md` | 研究全体の正典 |
| `../CLAUDE.md` | リポジトリ横断ガイド |
| `docs/EXPERIMENT.md` | 第1章 legacy 設計・指標定義 |
| `docs/scenarios/*.md` | 3シナリオ手順書 |
| `experiment/results/COMPARISON.md` | 第1章 legacy vs improved 統合結果 |
