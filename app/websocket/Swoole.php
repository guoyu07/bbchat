<?php

namespace App\Websocket;

use Exception;
use Swoole\WebSocket\Server;

class Swoole
{
    /**
     * Enable/Disable SSL
     *
     * @var string
     */
    protected $ssl;

    /**
     * Server host
     *
     * @var string
     */
    protected $host;

    /**
     * Server port
     *
     * @var string
     */
    protected $port;

    /**
     * Cert folder
     *
     * @var string
     */
    protected $cert;

    /**
     * Runtime folder
     *
     * @var string
     */
    protected $runtime;

    /**
     * Master PID file
     *
     * @var string
     */
    protected $masterPid;

    /**
     * Manager PID file
     *
     * @var string
     */
    protected $managerPid;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->ssl = env('websocket', 'ssl');
        $this->host = env('websocket', 'host');
        $this->port = env('websocket', 'port');
        $this->cert = ROOT .'/server/cert';
        $this->runtime = ROOT .'/server/runtime';
        $this->masterPid = $this->runtime . '/master.pid';
        $this->managerPid = $this->runtime . '/manager.pid';
    }

    /**
     * Start server
     *
     * @return bool
     */
    public function serviceStart()
    {
        $socket = $this->ssl ? SWOOLE_SOCK_TCP | SWOOLE_SSL : SWOOLE_SOCK_TCP;
        $server = new Server($this->host, $this->port, SWOOLE_PROCESS, $socket);

        $config = [
            'ractor_num' => 1,
            'worker_num' => 2,
            'max_request' => 10000,
            'task_worker_num' => 2,
            'dispatch_mode' => 2,
            'daemonize' => true,
            'debug_mode'=> true,
            'log_file' => $this->runtime .'/websocket.log'
        ];
        if ($this->ssl) {
            $config['ssl_key_file'] = $this->cert .'/ssl.key';
            $config['ssl_cert_file'] = $this->cert .'/ssl.pem';
        }
        $server->set($config);

        $server->on('Start', [$this, 'onStart']);
        $server->on('Shutdown', [$this, 'onShutdown']);
        $server->on('ManagerStart', [$this, 'onManagerStart']);
        $server->on('ManagerStop', [$this, 'onManagerStop']);
        $server->on('WorkerStart', [$this, 'onWorkerStart']);
        $server->on('WorkerStop', [$this, 'onWorkerStop']);
        $server->on('WorkerError', [$this, 'onWorkerError']);
        $server->on('Open', [$this, 'onOpen']);
        $server->on('Message', [$this, 'onMessage']);
        $server->on('Close', [$this, 'onClose']);
        $server->on('Task', [$this, 'onTask']);
        $server->on('Finish', [$this, 'onFinish']);

        return $server->start();
    }

    /**
     * Shutdown server
     *
     * @return bool
     */
    public function serviceStop()
    {
        $pid = file_get_contents($this->masterPid);
        return posix_kill($pid, SIGTERM);
    }

    /**
     * Reload server
     *
     * @return bool
     */
    public function serviceReload()
    {
        if (is_file($this->managerPid)) {
            $pid = file_get_contents($this->managerPid);
            return posix_kill($pid, SIGUSR1);
        }
        return false;
    }

    /**
     * Server status
     *
     * @return bool
     */
    public function serviceStatus()
    {
        return is_file($this->masterPid);
    }

    /**
     * Return the last POSIX error
     *
     * @return string
     */
    public function posixError()
    {
        $num = posix_get_last_error();
        return posix_strerror($num);
    }

    /**
     * On master-process start
     *
     * @param Server $server
     */
    public function onStart($server)
    {
        @cli_set_process_title("swoole_websocket_{$this->port}_master");
        file_put_contents($this->masterPid, $server->master_pid);
        console('Service started');
    }

    /**
     * On master-process shutdown
     *
     * @param Server $server
     */
    public function onShutdown($server)
    {
        if (is_file($this->masterPid)) {
            unlink($this->masterPid);
        }
        console('Service stopped');
    }

    /**
     * On manager-process start
     *
     * @param Server $server
     */
    public function onManagerStart($server)
    {
        @cli_set_process_title("swoole_websocket_{$this->port}_manager");
        file_put_contents($this->managerPid, $server->manager_pid);
    }

    /**
     * On manager-process stop
     */
    public function onManagerStop()
    {
        if (is_file($this->managerPid)) {
            unlink($this->managerPid);
        }
    }

    /**
     * On worker-process start
     *
     * @param Server $server
     * @param integer $workerId
     */
    public function onWorkerStart($server, $workerId)
    {
        if($server->taskworker) {
            $title = "swoole_websocket_{$this->port}_task_{$workerId}";
        } else {
            $title = "swoole_websocket_{$this->port}_worker_{$workerId}";
        }
        @cli_set_process_title($title);
    }

    /**
     * On worker-process error
     *
     * @param Server $server
     * @param integer $workerId
     * @param integer $processId
     * @param integer $code
     */
    public function onWorkerError($server, $workerId, $processId, $code)
    {
        console('Error: '. $code .', Worker('. $workerId .'), Process: ('. $processId .')');
    }

    /**
     * On worker-process stop
     *
     * @param Server $server
     * @param integer $workerId
     */
    public function onWorkerStop($server, $workerId)
    {
    }

    /**
     * On client connected
     *
     * @param Server $server
     * @param \Swoole\Http\Request $request
     * @return bool
     */
    public function onOpen($server, $request) {
        $client = new Client($server, $request->fd);
        $params = isset($request->get) ? $request->get : [];
        return $client->onConnect($params);
    }

    /**
     * On client send message
     *
     * @param Server $server
     * @param \Swoole\WebSocket\Frame $frame
     * @return bool
     */
    public function onMessage($server, $frame) {
        $message = json_decode($frame->data);
        if (json_last_error() === 0) {
            $client = new Client($server, $frame->fd);
            return $client->onMessage($message);
        }
        return $server->push($frame->fd, [
            'code' => 'INVALID_JSON',
            'message' => json_last_error_msg()
        ]);
    }

    /**
     * On client offline
     *
     * @param Server $server
     * @param integer $fd
     * @return bool
     */
    public function onClose($server, $fd)
    {
        $client = new Client($server, $fd);
        return $client->onOffline();
    }

    /**
     * On async-task start
     *
     * @param Server $server
     * @param integer $taskId
     * @param integer $workerId
     * @param string $data
     * @return bool
     */
    public function onTask($server, $taskId, $workerId, $data)
    {
        try {

        } catch (Exception $e) {

        }
        return false;
    }

    /**
     * On async-task finish
     *
     * @param Server $server
     * @param integer $taskId
     * @param string $data
     */
    public function onFinish($server, $taskId, $data)
    {
    }

    /**
     * On timer
     *
     * @param Server $server
     * @param integer $interval
     */
    public function onTimer($server, $interval)
    {
    }
}