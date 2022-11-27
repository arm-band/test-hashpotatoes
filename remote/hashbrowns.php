<?php

date_default_timezone_set('Asia/Tokyo');
mb_language('ja');
mb_internal_encoding('UTF-8');

$currentDir = dirname(__FILE__);
$httpStatusCode = 200;
$hashedBrown = array();

if (!file_exists($currentDir . '/logs/')) {
    $httpStatusCode = 404;
    header('HTTP/1.1 ' . $httpStatusCode . ' Not Found.');
    die('ログ出力用のディレクトリが存在しません。');
}
if (version_compare(phpversion(), '5.2.4', '<')) {
    $httpStatusCode = 500;
    header('HTTP/1.1 ' . $httpStatusCode . ' Internal Server Error.');
    $msg = 'PHP のバージョンが低すぎます (PHP: ' . phpversion() . ') 。';
    outputLog('ERROR', $msg, $currentDir);
    die($msg);
}

$CONFIG = require_once($currentDir . '/src/config.php');

/**
 * _h
 *
 * @param string $str
 * @return string
 */
function _h($str) {
    return htmlspecialchars($str, ENT_QUOTES);
}

/**
 * startsWith
 * http://blog.anoncom.net/2009/02/20/124.html
 *
 * @param string $haystack
 * @param string $needle
 * @return boolean
 */
function startsWith($haystack, $needle){
    return mb_strpos($haystack, $needle, 0) === 0;
}

/**
 * hashedPotato
 *
 * @param string $filepath
 * @return string
 */
function hashedPotato($filepath) {
    return hash('sha256', $filepath);
}

/**
 * hashedBrowns
 *
 * @param string $filepath
 * @return string
 */
function hashedBrowns($filepath) {
    return hash_file('sha256', $filepath);
}

/**
 * jsonEncode
 *
 * @param array $data
 * @return string
 */
function jsonEncode($data)
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

/**
 * outputLog
 *
 * @param string $status
 * @param string $str
 * @param string $currentDir
 * @return string
 */
function outputLog($status, $msg, $currentDir) {
    $date = date('Ymd');
    $filename = $currentDir . '/logs/log-' . $date . '.log';
    $line = '';
    if(file_exists($filename)) {
        $line .= "\n";
    }
    $datetime = date('Y/m/d h:i:s P');
    $line .= '[' . $datetime . '] ' . $status . ': "' . $msg . '"';
    return file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
}

/**
 * walkAndEat
 * 指定したディレクトリとそのサブディレクトリのファイルを表示する
 *
 * @param array $hashedBrown ハッシュ値の配列
 * @param string $currentDir ディレクトリのパス
 * @param array $CONFIG 設定
 */
function walkAndEat(&$hashedBrown, $currentDir, $CONFIG) {
    $dir = new DirectoryIterator($currentDir);
    $dirs = array();

    foreach ($dir as $file) {
        if (
            $file->isDot()
            || startsWith($file->getPathname(), dirname(__FILE__) . '/hashbrowns.php') !== false
            || startsWith($file->getPathname(), dirname(__FILE__) . '/src/') !== false
            || startsWith($file->getPathname(), dirname(__FILE__) . '/logs/') !== false
        ) {
            // '.'と'..', 自分自身, システム関連のディレクトリは対象外
            continue;
        }
        if ($file->isDir()) {
            array_push($dirs, $file->getPathname());
        }
        if ($file->isFile()) {
            if(
                filesize($file->getPathname()) < $CONFIG['excludeFilesizeMax']
                && !in_array($file->getExtension(), $CONFIG['excludeFileExtension'], true)
            ) {
                $hashedBrown[
                    str_replace(
                        dirname(__FILE__),
                        '',
                        $file->getPathname()
                    )
                ] = hashedBrowns(
                    $file->getPathname()
                );
            }
        }
    }
    // サブディレクトリも探索する
    foreach ($dirs as $dir) {
        walkAndEat($hashedBrown, $dir, $CONFIG);
    }
}

/**
 * main process
 * 現在のディレクトリ配下について調べる
 */
outputLog('INFO', '処理開始 (アクセス元: '. $_SERVER['REMOTE_ADDR'] . ', ユーザーエージェント: ' . $_SERVER['HTTP_USER_AGENT'] . ')', $currentDir);

walkAndEat($hashedBrown, $currentDir, $CONFIG);
header('Content-Type: application/json; charset=utf-8');
echo jsonEncode($hashedBrown);

outputLog('INFO', '処理終了 (アクセス元: '. $_SERVER['REMOTE_ADDR'] . ', ユーザーエージェント: ' . $_SERVER['HTTP_USER_AGENT'] . ')', $currentDir);
