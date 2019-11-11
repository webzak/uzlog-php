<?php

namespace Webzak\Uzlog;

/**
 * Class Transport
 */
class Transport
{

    const MAX_PACKET = 508;
    const HEADER_SIZE = 20;

    protected $socket;
	protected $maxPacketPayload;
    protected $session;
    protected $msgId;

    /**
     * Constructor
     *
     * @param object $socket
     * @param array $options
	 * @throw Uzlog\Exception
     */
    public function __construct(SocketInterface $socket, array $options = [])
    {
        $this->socket = $socket;
        $this->msgId = 0;
		$this->maxPacketPayload = $this->configureMaxPacketPayload($options);
        $this->session = $this->generateSession();
    }

    /**
     * Send data
     *
     * @param string $data
     * @return int bytes sent
     */
    public function send($data)
    {
        $ret = 0;
        $this->msgId++;
        $crc32 = crc32($data);
        $data = $data . pack("N", $crc32);
        $msgLen = strlen($data);
        $offset = 0;
        while ($offset < $msgLen) {
            $length = $msgLen - $offset;
            if ($length > $this->maxPacketPayload) {
                $length = $this->maxPacketPayload;
            }
            $ret += $this->sendPacket($this->msgId, $msgLen, $offset, substr($data, $offset, $length));
            $offset += $length;
        }
        return $ret;
    }

    /**
     * Get status
     *
     * @return array
     */
    public function getStatus()
    {
        return [
            'session' => $this->session,
            'msg' => $this->msgId
        ];
    }

    protected function configureMaxPacketPayload(array $opt)
	{
		$mp = (!empty($opt['max_packet'])) ? intval($opt['max_packet']) : self::MAX_PACKET;
		if ($mp <= self::HEADER_SIZE || $mp > self::MAX_PACKET) {
			throw new Exception ("Incorrect max_packet option value: $mp");
		}
		return $mp - self::HEADER_SIZE;
	}

    protected function generateSession()
    {
        return round(microtime(true) * 1000000);
    }

    protected function sendPacket($msgId, $msgLen, $offset, $payload)
    {
        $low = $this->session & 0xffffffff;
        $high = $this->session >> 32;
        $packet = pack("N*", $high, $low, $msgId, $msgLen, $offset) . $payload; // network (big endian) byte order!
        return $this->socket->send($packet);
    }
}
