<?php

date_default_timezone_set('Asia/Tokyo');
mb_language('ja');
mb_internal_encoding('UTF-8');

use GuzzleHttp\Client;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

$CONFIG = require_once(__DIR__ . '/src/config.php');

if(!$CONFIG['PROD_FLG']) {
    ini_set('xdebug.var_display_max_children', -1);
    ini_set('xdebug.var_display_max_data', -1);
    ini_set('xdebug.var_display_max_depth', -1);
}

$currentDir = __DIR__;
$httpStatusCode = 200;
$hashedPotatoes = [];

if (!file_exists($currentDir . '/target/')) {
    $httpStatusCode = 404;
    header('HTTP/1.1 ' . $httpStatusCode . ' Not Found.');
    die('監視対象用のディレクトリが存在しません。');
}
if (!file_exists($currentDir . '/dist/')) {
    $httpStatusCode = 404;
    header('HTTP/1.1 ' . $httpStatusCode . ' Not Found.');
    die('結果出力用のディレクトリが存在しません。');
}
if (!file_exists($currentDir . '/logs/')) {
    $httpStatusCode = 404;
    header('HTTP/1.1 ' . $httpStatusCode . ' Not Found.');
    die('ログ出力用のディレクトリが存在しません。');
}
if (version_compare(phpversion(), '7.0.0', '<')) {
    $httpStatusCode = 500;
    header('HTTP/1.1 ' . $httpStatusCode . ' Internal Server Error.');
    $msg = 'PHP のバージョンが低すぎます (PHP: ' . phpversion() . ') 。';
    outputLog('ERROR', $msg, $currentDir);
    die($msg);
}

$guzzleClient = new Client();
$options = [
    'version' => 1.1,
    'headers' => [
        'Content-Type' => 'application/vnd.json+api',
    ],
];

if($CONFIG['basicAuth']['auth']) {
    $options['auth'] = [
        $CONFIG['basicAuth']['user'],
        $CONFIG['basicAuth']['password'],
    ];
}

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
 * jsonEncodePretty
 *
 * @param array $data
 * @return string
 */
function jsonEncodePretty($data)
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * jsonDecode
 *
 * @param string $data
 * @return array
 */
function jsonDecode($data)
{
    return json_decode($data, true);
}

/**
 * getInfo
 *
 * @param array $CONFIG
 * @param array $options
 * @param Object $guzzleClient
 * @return Object Guzzle Response Object
 */
function getInfo($CONFIG, $options, $guzzleClient)
{
    try {
        $response = $guzzleClient->get(
            $CONFIG['target'],
            $options,
        );
        return $response->getBody()->getContents();
    } catch (\Exception $e) {
        return $e->getResponse()->getBody()->getContents();
    }
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
 * outputResults
 *
 * @param string $results
 * @param string $currentDir
 * @return string
 */
function outputResults($results, $currentDir) {
    $datetime = date('Ymd_h_i_s');
    $filename = $currentDir . '/dist/results-' . $datetime . '.json';
    return file_put_contents($filename, $results, LOCK_EX);
}

/**
 * mailSend
 *
 * @param array $CONFIG
 * @param array $results
 * @return bool
 */
function mailSend($CONFIG, $results) {
    $mismatches = '';
    foreach ($results['mismatch'] as $k => $v) {
        $mismatches .= PHP_EOL . $k;

    }
    $body = <<<EOF
Webサイト {$CONFIG['commons']['website']} で
原本と不一致だったファイルの一覧です。
{$mismatches}

--
このメールは {$CONFIG['commons']['appName']} による自動送信です。

EOF;

    try {
        $mail = new PHPMailer();

        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = $CONFIG['mail']['MAIL_HOST'];
        $mail->Username = $CONFIG['mail']['MAIL_USERNAME'];
        $mail->Password = $CONFIG['mail']['MAIL_PASSWORD'];
        $mail->SMTPSecure = $CONFIG['mail']['MAIL_ENCRPT'];
        $mail->Port = $CONFIG['mail']['SMTP_PORT'];

        //メール内容設定
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom($CONFIG['mail']['FROM_MAIL'], $CONFIG['mail']['FROM_MAIL']);
        $mail->addAddress($CONFIG['mail']['TO_MAIL'], $CONFIG['mail']['TO_MAIL']);
        $mail->Subject = '[' . $CONFIG['commons']['appName'] . '] 更新検知メール';
        $mail->isHTML(false);
        $mail->Body  = $body;

        //メール送信の実行
        return $mail->send();
    } catch (\Exception $e) {
        die($e->getMessage());
    }
}

/**
 * walkAndEat
 * 指定したディレクトリとそのサブディレクトリのファイルを表示する
 *
 * @param array $hashedPotatoes ハッシュ値の配列
 * @param string $currentDir ディレクトリのパス
 * @param array $CONFIG 設定
 */
function walkAndEat(&$hashedPotatoes, $currentDir, $CONFIG) {
    $dir = new \DirectoryIterator($currentDir);
    $dirs = [];

    foreach ($dir as $file) {
        if (
            $file->isDot()
            || startsWith($file->getPathname(), __DIR__ . '/hashpotatoes.php') !== false
            || startsWith($file->getPathname(), __DIR__ . '/target/.gitkeep') !== false
            || startsWith($file->getPathname(), __DIR__ . '/src/') !== false
            || startsWith($file->getPathname(), __DIR__ . '/logs/') !== false
        ) {
            // '.'と'..', 自分自身, システム関連のディレクトリは対象外
            continue;
        }
        if ($file->isDir()) {
            $dirs[] = $file->getPathname();
        }
        if ($file->isFile()) {
            if(
                filesize($file->getPathname()) < $CONFIG['exclude']['excludeFilesizeMax']
                && !in_array($file->getExtension(), $CONFIG['exclude']['excludeFileExtension'], true)
            ) {
                $hashedPotatoes[
                    str_replace(
                        str_replace(
                            DIRECTORY_SEPARATOR,
                            '/',
                            __DIR__
                        ) . '/target/',
                        '/',
                        str_replace(
                            DIRECTORY_SEPARATOR,
                            '/',
                            $file->getPathname()
                        )
                    )
                ] = hashedBrowns(
                    $file->getPathname()
                );
            }
        }
    }
    // サブディレクトリも探索する
    foreach ($dirs as $dir) {
        walkAndEat($hashedPotatoes, $dir, $CONFIG);
    }
}

/**
 * main process
 * 現在のディレクトリ配下について調べる
 */
outputLog('INFO', '処理開始 (アクセス元: '. $_SERVER['REMOTE_ADDR'] . ', ユーザーエージェント: ' . $_SERVER['HTTP_USER_AGENT'] . ')', $currentDir);

walkAndEat($hashedPotatoes, $currentDir . '/target/', $CONFIG);
$eatHashBrowns = jsonDecode(getInfo($CONFIG, $options, $guzzleClient));

$results = [
    'match' => [],
    'mismatch' => [],
];
foreach ($hashedPotatoes as $k => $v) {
    if(array_key_exists($k, $eatHashBrowns) && $v === $eatHashBrowns[$k]) {
        $results['match'][$k] = true;
    }
    else {
        $results['mismatch'][$k] = false;
    }
}

outputResults(jsonEncodePretty($results), $currentDir);
header('Content-Type: application/json; charset=utf-8');
echo jsonEncode($results);
mailSend($CONFIG, $results);

outputLog('INFO', '比較結果: 全件(' . count($hashedPotatoes) . ' ファイル), 一致(' . count($results['match']) . ' 件), 不一致(' . count($results['mismatch']) . ' 件), 一致率(' . floor((count($results['match']) / count($hashedPotatoes)) * 100) . '%)', $currentDir);
outputLog('INFO', '処理終了 (アクセス元: '. $_SERVER['REMOTE_ADDR'] . ', ユーザーエージェント: ' . $_SERVER['HTTP_USER_AGENT'] . ')', $currentDir);
