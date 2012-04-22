<?php

namespace Igorw\SocketServer;

use Evenement\EventEmitter;

class EventLoop extends EventEmitter
{
    private $timeout;
    private $streams = array();

    // timeout = microseconds
    public function __construct($timeout = 1000000)
    {
        $this->timeout = $timeout;
    }

    public function addStream($stream, $listener)
    {
        $this->streams[] = $stream;
        $this->on('stream.'.(int) $stream, $listener);
    }

    public function hasStream($stream)
    {
        return isset($this->streams[(int) $stream]);
    }

    public function removeStream($stream)
    {
        // TODO: exception if stream id not set

        unset($this->streams[(int) $stream]);
    }

    public function run()
    {
        // @codeCoverageIgnoreStart
        while (true) {
            $this->tick();
        }
        // @codeCoverageIgnoreEnd
    }

    public function tick()
    {
        $readystreams = $this->select();
        foreach ($readystreams as $stream) {
            $this->emit('stream.'.(int)$stream, array($stream));
        }
    }

    public function select()
    {
        $readystreams = $this->streams;
        @stream_select($readystreams, $write = null, $except = null, 0, $this->timeout);
        return $readystreams;
    }
}
