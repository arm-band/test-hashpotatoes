<?php

if (!file_exists(__DIR__ . '/local/src/config.php')) {
    header('HTTP/1.1 404 Not Found.');
    die('監視サーバ用の設定ファイル /local/src/config.php が存在しません。');
}
$CONFIG = require_once(__DIR__ . '/local/src/config.php');

if (!file_exists(__DIR__ . '/path.php')) {
    header('HTTP/1.1 404 Not Found.');
    die('.htpasswd のパスを指定する設定ファイル path.php が存在しません。');
}
$PATH = require_once(__DIR__ . '/path.php');

if (!file_exists(__DIR__ . '/remote/')) {
    header('HTTP/1.1 404 Not Found.');
    die('リモートサーバ用のディレクトリが存在しません。');
}

if($CONFIG['basicAuth']['auth']) {
    $us = $CONFIG['basicAuth']['user'];
    $ps = password_hash($CONFIG['basicAuth']['password'], PASSWORD_BCRYPT);

    $line = $us . ':' . $ps;

    file_put_contents(__DIR__ . '/remote/.htpasswd', $line, LOCK_EX);
}

$htaccess = <<<EOF

SetEnvIf Request_URI "(\.(log|txt|dat|dist|csv|ini|tpl|yml|xml|json|env|htaccess|htpasswd|md)|/(app|bin|logs|migrations|src|tests|tmp|var|vendor)(.)*/)$" ng_dir
Order Allow,Deny
Allow from all
Deny from env=ng_dir

Options All -Indexes

EOF;

if($CONFIG['basicAuth']['auth']) {
    $htaccess .= <<<EOF

AuthType Basic
AuthName "Input your ID and Password."
AuthUserFile {$PATH['path']}
<Files hashbrowns.php>
    require valid-user
</Files>

EOF;

}

file_put_contents(__DIR__ . '/remote/.htaccess', $htaccess, LOCK_EX);
