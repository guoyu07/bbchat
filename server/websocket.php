<?php
require dirname(__DIR__).'/bootstrap.php';

$server = new \App\Websocket\Swoole();
$action = isset($argv[1]) ? $argv[1] : null;
switch ($action) {
    case 'start':
        if ($server->serviceStatus()) {
            console('Server is running');
        } else {
            console('Server is starting');
            if ($server->serviceStart() === false) {
                console('Failed: '. $server->posixError());
            } else {
                console('Server started');
            }
        }
        break;
    case 'stop';
        if ($server->serviceStatus()) {
            console('Server is stopping');
            if ($server->serviceStop() === false) {
                console('Failed:'. $server->posixError());
            } else {
                console('Server stopped');
            }
        } else {
            console('Server not running');
        }
        break;
    case 'status';
        if ($server->serviceStatus()) {
            console('Server is running');
        } else {
            console('Server not running');
        }
        break;
    default:
        console('Usage: php websocket.php {start|stop}');
}