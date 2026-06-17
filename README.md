# tech-update-task-app-legacy

技術更新時の影響を定量評価するための **従来構成（悪い例）** 実験台です。  
同一機能のタスク管理アプリを、Controller に業務ロジックと DB アクセスを集中させた密結合構成で実装し、改良構成（別リポジトリ）と更新シナリオごとに比較します。

[![CI](https://github.com/3Da-design/tech-update-task-app/actions/workflows/ci.yml/badge.svg)](https://github.com/3Da-design/tech-update-task-app/actions/workflows/ci.yml)

---

## 目次

1. [研究ゴールと比較設計](#研究ゴールと比較設計)
2. [アーキテクチャ](#アーキテクチャ)
3. [技術スタック](#技術スタック)
4. [クイックスタート](#クイックスタート)
5. [テストと CI](#テストと-ci)
6. [実験の進め方](#実験の進め方)
7. [更新シナリオ](#更新シナリオ)
8. [評価指標](#評価指標)
9. [ドキュメント索引](#ドキュメント索引)

---

## 研究ゴールと比較設計

| 項目 | 内容 |
|------|------|
| **ゴール** | 設計（モジュール化 + CI/CD）が技術更新時の影響をどれだけ抑えられるかを定量的に示す |
| **本リポジトリ** | 従来構成（Fat Controller、Service/Repository なし、Web/API でロジック重複） |
| **ベースライン** | **`legacy-architecture` ブランチ**（`experiment-baseline-v1` タグ）。`priority` 等のシナリオ変更は `exp/legacy-*` ブランチで実施 |
| **対照** | 改良構成リポジトリ（`tech-update-task-app` 等、Controller / Service / Repository 分離） |
| **比較条件** | 同一アプリ（タスク管理）、同一スタック（Laravel）、同一 CI ワークフロー・同一 Feature テスト |
| **評価スコープ** | **アプリ全体**（認証・プロフィール・タスク・CI 全ジョブ） |

詳細は [docs/EXPERIMENT.md](docs/EXPERIMENT.md) を参照してください。

---

## アーキテクチャ

### タスク領域（従来構成の核）

```text
HTTP (Web / API)
    │
    ▼
TaskController (Web / API)   … 認可・正規化・クエリ・永続化をすべて内包
    │
    ▼
Task (Model)               … Eloquent を Controller から直接操作
```

| レイヤ | クラス |
|--------|--------|
| Web | `App\Http\Controllers\Web\TaskController`（Fat Controller） |
| API | `App\Http\Controllers\API\TaskController`（Fat Controller、ロジック重複） |
| 入出力 | `StoreTaskRequest`, `UpdateTaskRequest`, `TaskResource` |

Web と API は **同じロジックをそれぞれの Controller に重複実装** しているため、仕様変更時に修正箇所が分散しやすい構成です。

### 認証・プロフィール

Laravel Breeze 標準（Controller から User Model を直接操作）。タスク領域と同様の「Controller 直操作」寄りです。

### ディレクトリ（タスク関連）

```text
app/
└── Http/
    ├── Controllers/
    │   ├── API/TaskController.php   # 業務ロジック + DB 直アクセス
    │   └── Web/TaskController.php   # 同上（重複）
    ├── Requests/          # バリデーション（維持）
    └── Resources/         # API JSON（維持）
```

---

## 技術スタック

| 区分 | 技術 |
|------|------|
| バックエンド | Laravel 13、PHP 8.4 |
| 認証 | Laravel Breeze（セッション） |
| DB | PostgreSQL（Docker Compose） |
| フロント | Blade、Tailwind CSS、Vite、Alpine.js |
| 品質 | PHPStan (Larastan)、Laravel Pint、ESLint |
| テスト | PHPUnit、Postman / Newman |
| CI | GitHub Actions（4 ジョブ並列） |
| 開発環境 | Docker Compose（`http://localhost:8001` — 改良構成と同時起動可） |

機能一覧は [docs/FeatureList.md](docs/FeatureList.md) を参照してください。

---

## クイックスタート

### 前提

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) など Compose v2 対応環境
- 開発フローは **Docker Compose のみ**（ホストで `php artisan serve` は使わない。Web は **ポート `8001`**、DB 公開は **5433**）
- 改良構成（`tech-update-task-app`）と **同時に起動可能**（コンテナ名・ポート・ボリュームを分離済み）
- **フロント（npm）は Docker の `node` サービスのみ**（ホストで `npm install` / `npm ci` しない。`node_modules` の混在で `ENOTEMPTY` などが起きる）

### 初回セットアップ

```bash
cp .env.example .env

docker compose build app
docker compose up -d

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# フロント資産（ログイン画面など @vite 用）
composer npm:docker-build
```

ブラウザで `http://localhost:8001` を開きます。シードユーザー: `test@example.com` / `password`

> `.env` は `.env.example` をコピーし、`APP_HTTP_PORT=8001` 等が入っていることを確認してください。

### よく使うコマンド

```bash
docker compose logs -f
docker compose down              # DB ボリュームは残る
docker compose down -v           # DB ごと削除
```

### API の疎通確認

```bash
chmod +x scripts/curl-api-smoke.sh
./scripts/curl-api-smoke.sh
```

`http_code` が `000` のときは Docker 未起動・URL 誤り・ポート競合を確認してください（README 旧版の curl 例も有効です）。

---

## テストと CI

### ローカル一括（推奨）

```bash
./scripts/check-quality.sh
```

実行内容: PHPStan → ESLint → Vite build → PHPUnit → Newman

### フロントエンド（Docker のみ）

ホストに Node が入っていても、**依存のインストール・ビルドはコンテナ内だけ**で行います。

```bash
composer npm:docker-ci      # コンテナ内 npm ci（node_modules はボリューム分離）
composer npm:docker-build   # 上記 + npm run build
docker compose --profile node run --rm node npm run lint
docker compose --profile node run --rm --service-ports node npm run dev   # Vite 開発サーバー
```

### 個別（PHP / API）

```bash
docker compose exec app composer phpstan
docker compose exec app composer test
docker compose --profile node run --rm node npm run test:api
```

### GitHub Actions（CI）

| ジョブ | 内容 |
|--------|------|
| `php-tests` | PHPUnit（事前に Vite build） |
| `php-quality` | Pint + PHPStan |
| `frontend` | ESLint + Vite build |
| `api-tests` | Newman（Postman コレクション） |

詳細: [docs/CI.md](docs/CI.md)、[docs/TESTING.md](docs/TESTING.md)

---

## 実験の進め方

### 1. ベースラインの確立

従来構成が CI 緑の状態で:

```bash
./scripts/check-quality.sh
composer experiment:metrics -- --phase baseline --diff-ref experiment-baseline-v1
git tag -a experiment-baseline-v1 -m "Experiment baseline: legacy architecture"
```

メトリクス JSON は `experiment/metrics/` に出力されます（Git 管理外）。

### 2. 更新シナリオの実施

[docs/experiment/scenarios/](docs/experiment/scenarios/) の手順に従い、ブランチで変更を適用します。

BEFORE（ベースライン）の手順は [docs/experiment/BEFORE.md](docs/experiment/BEFORE.md) を参照。`experiment-baseline-v1` は legacy Docker（**8001**）込みの CI 緑状態を指します（`git pull` のあと `git fetch --tags`）。

```bash
git fetch --tags
git checkout experiment-baseline-v1
git checkout -b exp/your-scenario-name
# … シナリオに沿って変更 …
composer experiment:metrics -- --phase after_update --diff-ref experiment-baseline-v1
# … テスト・コードを修正 …
./scripts/check-quality.sh
composer experiment:metrics -- --phase after_fix --diff-ref experiment-baseline-v1
```

### 3. 記録

[docs/experiment/metrics-record-template.md](docs/experiment/metrics-record-template.md) の列定義に従い、スプレッドシート等に記録します。

### 4. 改良構成との比較

改良構成リポジトリで **同じシナリオ・同じ手順** を実施し、メトリクスを [docs/experiment/results/COMPARISON.md](docs/experiment/results/COMPARISON.md) の形式で比較します。本リポジトリの作成手順は [docs/experiment/LEGACY_MIGRATION.md](docs/experiment/LEGACY_MIGRATION.md) を参照してください。

---

## 更新シナリオ

| シナリオ | ドキュメント |
|----------|--------------|
| バックエンド API 仕様変更 | [api-spec-change.md](docs/experiment/scenarios/api-spec-change.md) |
| DB / クエリ変更 | [db-schema-change.md](docs/experiment/scenarios/db-schema-change.md) |
| Laravel バージョン更新 | [laravel-upgrade.md](docs/experiment/scenarios/laravel-upgrade.md) |
| テストツール更新 | [test-tool-upgrade.md](docs/experiment/scenarios/test-tool-upgrade.md) |
| JavaScript ライブラリ変更 | [js-library-change.md](docs/experiment/scenarios/js-library-change.md) |

---

## 評価指標

| 指標 | 概要 | 取得 |
|------|------|------|
| **修正工数（主）** | 変更ファイル数・追加/削除行 | `composer experiment:metrics -- --diff-ref experiment-baseline-v1` の `git.*` |
| **テスト通過率** | PHPUnit / Newman 等の成功 ÷ 総数 | 同上（特に `after_update` の失敗数） |
| **作業時間** | 分 | 手動（テンプレート） |
| **エラー発生率** | PHPStan 件数、CI 失敗ジョブ | スクリプト + 手動 |

定義の詳細: [docs/EXPERIMENT.md](docs/EXPERIMENT.md)

---

## ドキュメント索引

| ドキュメント | 内容 |
|--------------|------|
| [docs/COMPARABILITY.md](docs/COMPARABILITY.md) | 比較可能化の修正内容（本ドキュメント） |
| [docs/EXPERIMENT.md](docs/EXPERIMENT.md) | 実験設計・指標・フェーズ |
| [docs/FeatureList.md](docs/FeatureList.md) | 機能一覧 |
| [docs/TESTING.md](docs/TESTING.md) | テストツールの使い方 |
| [docs/CI.md](docs/CI.md) | GitHub Actions |
| [docs/experiment/LEGACY_MIGRATION.md](docs/experiment/LEGACY_MIGRATION.md) | 従来構成リポジトリ作成手順 |
| [docs/experiment/metrics-record-template.md](docs/experiment/metrics-record-template.md) | メトリクス記録テンプレート |
| [docs/experiment/scenarios/](docs/experiment/scenarios/) | 更新シナリオ手順 |

---

## ライセンス

MIT（Laravel プロジェクトスケルトンに準拠）
