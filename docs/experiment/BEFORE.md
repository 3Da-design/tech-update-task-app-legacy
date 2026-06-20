# BEFORE（変更前ベースライン）

legacy / improved とも **同一手順**。本リポジトリ（legacy）は **Web `8001` / DB 公開 `5433`**（改良構成の `8000` / `5432` と衝突しない）。

## 前提

1. `git pull origin main` で最新を取得してから `git fetch --tags` する（`experiment-baseline-v1` が legacy Docker 分離済みであること）。
2. 改良構成（`tech-update-task-app`）と **同時に動かす場合**、改良は **8000**、legacy は **8001** のままにする。

## 1. ブランチ作成

```bash
git fetch --tags
git checkout experiment-baseline-v1
git checkout -b exp/<scenario-id>
```

`<scenario-id>` は実施する [scenarios/](./scenarios/) MD に記載のブランチ名（例: `api-spec-change-status-int` → `exp/api-spec-change-status-int`、`api-spec-change-priority` → `exp/api-spec-change-priority`、`db-schema-change` → `exp/db-schema-change`）。

## 1-1. 品質ゲート確認

```bash
./scripts/check-quality.sh
```

**期待:** すべて成功（PHPStan / ESLint / Vite build / PHPUnit / Newman 13/13）。

- 接続先は `http://localhost:8001`（`scripts/lib/app-base-url.sh`）。
- 起動コンテナは `tech-update-task-app-legacy-*`（`scripts/lib/ensure-docker-stack.sh` が検証）。

## 1-2. ベースライン計測

```bash
composer experiment:metrics -- --phase baseline --diff-ref experiment-baseline-v1
```

## 1-3. 記録

[metrics-record-template.md](./metrics-record-template.md) の列定義に従い記録する。

## タグの更新について

`experiment-baseline-v1` は **CI 緑かつ legacy Docker（8001）が入った `main` の先端** を指す。古いタグ（8000 / `tech-update-task-app-php` のみ）のままでは 1-1 / 1-2 でコンテナ名衝突が起きる。

タグを更新したあと、手元で確認:

```bash
git fetch --tags
git show experiment-baseline-v1:docker-compose.yml | head -5
# → tech-update-task-app-legacy / 8001 系であること
```
