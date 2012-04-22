<?php

namespace Igorw\SocketServer;

use Evenement\EventEmitter;

class Server extends EventEmitter
{
    private $master;
    private $inputs = array();
    private $clients = array();

    public function __construct($host, $port, EventLoop $loop)
    {
        $this->master = stream_socket_server("tcp://$host:$port", $errno, $errstr);
        if (false === $this->master) {
            throw new ConnectionException($errstr, $errno);
        }

        $this->loop = $loop;
        $this->loop->addStream($this->master, array($this, 'handleAccept'));
    }

    public function addInput($name, $stream)
    {
        $this->inputs[$name] = $stream;
        $this->loop->addStream($stream, array($this, 'handleInput'));
    }

    public function run()
    {
        $this->loop->run();
    }

    public function tick()
    {
        $this->loop->tick();
    }

    public function handleAccept($master)
    {
        $socket = stream_socket_accept($master);
        if (false === $socket) {
            $this->emit('error', array('Error accepting new connection'));
            return;
        }
        $this->handleConnection($socket);
    }

    public function handleConnection($socket)
    {
        $client = $this->createConnection($socket);

        $this->clients[(int) $socket] = $client;
        $this->loop->addStream($socket, array($this, 'handleData'));

        $this->emit('connect', array($client));
    }

    public function handleInput($stream)
    {
        $name = array_search($stream, $this->inputs);
        if (false !== $name) {
            $this->emit("input.$name", array($stream));
        }
    }

    public function handleData($socket)
    {
        $data = @stream_socket_recvfrom($socket, 4096);
        if ($data === '') {
            $this->handleDisconnect($socket);
            return;
        }

        $client = $this->getClient($socket);
        $client->emit('data', array($data));
    }

    public function handleDisconnect($socket)
    {
        $this->close($socket);
    }

    public function getClient($socket)
    {
        return $this->clients[(int) $socket];
    }

    public function getClients()
    {
        return $this->clients;
    }

    public function write($data)
    {
        foreach ($this->clients as $conn) {
            $conn->write($data);
        }
    }

    public function close($socket)
    {
        $client = $this->getClient($socket);

        $client->emit('end');

        unset($this->clients[(int) $socket]);
        unset($client);

        unset($this->loop->removeStream[$socket]);

        fclose($socket);
    }

    public function getPort()
    {
        $name = stream_socket_get_name($this->master, false);
        return (int) substr(strrchr($name, ':'), 1);
    }

    public function shutdown()
    {
        stream_socket_shutdown($this->master, STREAM_SHUT_RDWR);
    }

    public function createConnection($socket)
    {
        return new Connection($socket, $this);
    }
}
