<?php

namespace App\Websocket;

class Client
{
    /**
     * @var integer
     */
    private $fd;

    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var \Swoole\WebSocket\Server
     */
    private $server;

    /**
     * Client constructor.
     * @param \Swoole\WebSocket\Server $server
     * @param integer $fd
     */
    public function __construct($server, $fd)
    {
        $this->fd = $fd;
        $this->server = $server;
        $this->redis = Worker::redis();
    }

    /**
     * On Connect
     *
     * @param array $params
     * @return bool
     */
    public function onConnect($params)
    {
        return $this->respond();
    }

    /**
     * On Message
     *
     * @param object $content
     * @return bool
     */
    public function onMessage($content)
    {
        return $this->respond();
    }

    /**
     * On offline
     *
     * @return bool
     */
    public function onOffline()
    {
        return $this->respond();
    }

    /**
     * Response
     *
     * @param array $response
     * @return bool
     */
    public function respond($response = [])
    {
        return $this->server->push($this->fd, json_encode($response));
    }

    /**
     * Disconnect
     *
     * @param int $after
     * @return bool
     */
    private function disconnect($after = 0)
    {
        if ($after) {
            $this->server->after($after, function() {
                return $this->server->close($this->fd);
            });
        }
        return $this->server->close($this->fd);
    }
}