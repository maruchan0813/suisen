# SUISEN

北九州高専 生産デザイン工学科の1〜2年生のコース選びを手助けするチャットアプリ。
SUISEN は花の「水仙」と「推薦」をかけている。

- 設計書：[要件定義書](docs/要件定義書.md)、[基本設計書](docs/基本設計書.md)、[詳細設計書](docs/詳細設計書.md)
- 学内 LLM を使うので、学内ネットワークからしか動かない。

## 初めて動かす手順

サーバーには Mac なら MAMP、Windows なら XAMPP を使う。

1. **MAMP / XAMPP を起動し、Apache と MySQL を動かす。**
2. **ドキュメントルートを `SUISEN/public` にする。**
   - MAMP：設定画面の「Server」→「Document Root」で `SUISEN/public` を選ぶ。
   - XAMPP：`xampp/apache/conf/httpd.conf` の `DocumentRoot` と `<Directory>` を `SUISEN/public` に書き換え、Apache を再起動する。
3. **表を作る。** phpMyAdmin の「SQL」タブに `database/schema.sql` の中身を貼り付けて実行する。
   - MAMP：`http://localhost:8888/phpMyAdmin/`
   - XAMPP：`http://localhost/phpmyadmin/`
4. **環境設定を作る。** `config/env.example.php` を `config/env.php` にコピーし、DB・LLM・メールの値を書く。
   - MySQL のポートは推測せず、MAMP / XAMPP の画面で確かめる（MAMP は既定 8889、XAMPP は 3306）。
   - メールの設定は下の「Gmail から確認コードを送る」を見る。
5. **ライブラリを入れる。** SUISEN フォルダで `composer install` を実行する。
6. **コースと先輩のデータを入れる。** MAMP / XAMPP の PHP で `database/seed.php` を動かす。
   - Mac：`/Applications/MAMP/bin/php/php8.3.30/bin/php database/seed.php`（バージョンは MAMP に入っているものに合わせる）
   - Windows：`C:\xampp\php\php.exe database\seed.php`
7. **検索の索引を確かめる。** `data/search_index.json` があれば何もしなくてよい。シラバスを取り直したときは `php database/build_search_index.php` で作り直す（下の「資料の検索（RAG）」）。
8. **ブラウザで開く。** MAMP は `http://localhost:8888/`、XAMPP は `http://localhost/`。

### XAMPP の htdocs に置く方法（Apache の設定を変えない）

手順2（ドキュメントルートの変更）の代わりに、このフォルダごと `C:\xampp\htdocs\suisen` に置いてもよい。

- 開く URL は `http://localhost/suisen/`（自動で `public/app/` の画面に移る）。
- `public/` 以外のフォルダ（設定ファイル、ログ、Git の履歴など）は、一番上の `.htaccess` でブラウザから開けないようにしてある。

## Gmail から確認コードを送る

メール認証はオン（`policy/app.php` の `EMAIL_VERIFICATION_ENABLED` が `true`）。新しい PC で動かすときは、次の準備をしてから使う。

1. アプリ専用の Gmail アカウントを作り、2段階認証をオンにする。
2. Google アカウントの「アプリパスワード」を発行する。
3. `config/env.php` の `MAIL_DRIVER` を `smtp` にし、`SMTP_USER` と `SMTP_APP_PASSWORD` を書く。
4. 学内ネットワークから `smtp.gmail.com` の 587 番ポートにつながるかを確かめる（`nc -vz smtp.gmail.com 587`）。
5. `php database/send_test_mail.php 自分のアドレス` で、試しに1通送って届くか確かめる。
- Gmail から送れるのは24時間で100通程度が目安（新しいアカウントはもっと少ないことがある）。

### 2026-10-13 より前に DB を作った場合

`messages` 表に列を1つ足したので、次の SQL を1回だけ流す（phpMyAdmin の「SQL」タブなど）。

```
database/migrations/2026-10-13_add_message_topic.sql
```

## 画面（React）

画面は React（TypeScript）と Motion で作っている。ソースは `frontend/`、ビルドしたファイルは `public/app/`。MAMP・XAMPP はビルドしたファイルを配るだけなので、Node.js が要るのは画面を直す PC だけ。

```
cd frontend
npm install      # はじめての1回
npm run dev      # 開発中：表示された URL を開く。/api への呼び出しは MAMP（http://localhost）へ転送される
npm run build    # 公開：public/app/ を作り直す
```

- 画面を直したら、最後に `npm run build` をして `public/app/` も共有する（Node.js のない PC でも動かせるように）。
- URL は `http://localhost/`（ほかの PC からは `http://この PC の IP アドレス/`）。`app/#/home` のような形になる。

## 資料の検索（RAG）

学内 LLM は自分で調べられないので、シラバスを検索して、見つかった授業の内容をもとに答えさせる。

```
php database/fetch_syllabus.php       # 高専Webシラバスから詳細ページを取得（取得済みは飛ばす）
php database/build_search_index.php   # 検索の索引 data/search_index.json を作る
```

- 文章のベクトル化は TF-IDF（文字2文字ずつの組）。モデルは使わない。
- チャットでは、学内 LLM に「検索が必要か」と「検索用のキーワード」を考えさせてから検索する。
- 検索の件数や最低の近さは `policy/app.php` の `RAG_*`、指示の文章は `policy/prompts/` で変えられる。

## テスト

```
composer test
```

計算の部品（`src/Core`）と、方針とデータのファイルの食い違いを確かめる。

## フォルダの役割

| フォルダ | 中身 | 変えるとき |
| --- | --- | --- |
| `policy/` | 13項目、プロンプト、往復数などの値、画面の文言 | 意見や要望が変わったとき。プログラムを読まずに直せる |
| `config/env.php` | DB・LLM・メールの接続先 | 動かす PC が変わったとき |
| `templates/`、`public/assets/css/` | 画面の見た目 | デザインを変えるとき |
| `src/Core/` | 推薦と先輩検索の計算 | 計算方法を変えるとき |
| `src/Service/` | ログイン・チャット・診断の手順 | 操作の流れを変えるとき |
| `src/Infra/`、`src/Repository/` | DB・LLM・メールとのやり取り | 外部とのつなぎ方を変えるとき |
| `data/` | コースのデータ、先輩のテストデータ | データを差し替えるとき |

## データの作り直し

```
php database/generate_test_seniors.php
php database/seed.php
```

先輩のテストデータは乱数の種を固定しているので、何度作っても同じになる。実データに差し替えるときは、`data/seniors_test.csv` と同じ形の CSV を用意して `database/seed.php` を動かす。
