# Hashed Potatoes

## Abstract

対象Webサイトに対して更新検知をするユーティリティ。

- 対象サーバ(Webサイト): 設置場所配下のファイルに対してハッシュ値を計算し、JSON形式のレスポンスを返却する
- 監視サーバ(開発環境側): 対象サーバに設置した PHP プログラムへ HTTPリクエスト を投げて、返却されたレソポンスのハッシュ値と、手元の原本ファイルから計算したハッシュ値を比較して一致・不一致を判定する

## Usage

### 対象サーバ側

1. (Basic認証を付ける場合) `path.php.sample` から `path.php` を作成し、必要に応じて設定を変更する
2. (Basic認証を付ける場合) `local/src/config.php.sample` から `local/src/config.php` を作成し次の項目を設定する
    - `basicAuth` => `auth`: `true`
    - `basicAuth` => `user`: ユーザ名
    - `basicAuth` => `password`: パスワード
3. `composer start` で `htGenerate.php` を実行する
4. `remote/src/config.php.sample` から `remote/src/config.php` を作成し、必要に応じて設定を変更する
5. `remote/`ディレクトリのファイル一式を監視したい対象サーバのディレクトリにアップロードする
6. サーバ上の `logs/` ディレクトリの権限を `777` にする

### 監視サーバ側

1. (対象サーバ側 2.で実施済みならば不要) `local/src/config.php.sample` から `local/src/config.php` を作成し、必要に応じて設定を変更する
2. `composer require`
3. `local/target/` に監視対象サイトの原本ファイルを配置する
4. `local/` 以下のファイル・ディレクトリを監視をさせるサーバにアップロード
5. サーバ上の `logs/` ディレクトリの権限を `777` にする
6. cron や手動で `local/hashpotatoes.php` にアクセスするようにする
