<?php

namespace App\Websocket;

use Redis;

class Worker
{
    /**
     * @var Redis
     */
    private static $redis;

    public static function redis()
    {
        if (self::$redis === null) {
            $host = env('redis', 'host');
            $port = env('redis', 'port');
            self::$redis = new Redis();
            self::$redis->connect($host, $port);
        }
        return self::$redis;
    }

    /**
     * Response
     *
     * @param \Swoole\WebSocket\Server $server
     * @param integer $fd
     * @param array $response
     * @return bool
     */
    public function respond($server, $fd, $response)
    {
        return $server->push($fd, json_encode($response));
    }
}