<?php

namespace Webzak\Uzlog\Test;

use Webzak\Uzlog\SocketInterface;

/**
 * Class SocketMock
 */
class SocketMock implements SocketInterface
{
    protected $packets = [];

    public function send($packet)
    {
        $this->packets[] = $packet;
    }

    public function getPacket($n)
    {
        return ($n < count($this->packets)) ? $this->packets[$n] : null;
    }

    public function getPackets()
    {
        return $this->packets;
    }

    public function packetsCount()
    {
        return count($this->packets);
    }

    public function clear()
    {
        $this->packets = [];
    }
}
