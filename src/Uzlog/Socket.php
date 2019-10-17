<?php

namespace Webzak\Uzlog;

/**
 * Class Socket
 */
class Socket implements SocketInterface
{
    protected $ip;
    protected $port;
    protected $socket;

    /**
     * Constructor
     *
     * @param mixed $ip
     * @param mixed $port
     */
    public function __construct($ip, $port)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    }

    public function send($packet)
    {
        return socket_sendto($this->socket, $packet, strlen($packet), 0, $this->ip, $this->port);
    }
}
