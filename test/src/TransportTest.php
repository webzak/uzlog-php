<?php

namespace Webzak\Uzlog\Test;

use Webzak\Uzlog\Transport;

class TransportTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        $this->socket = new SocketMock();
    }

    public function testInit()
    {
        $tr = new Transport($this->socket);
        $status = $tr->getStatus();
        $delta = microtime(true) * 1000000 - $status['session'];
        $this->assertTrue($delta >= 0 && $delta < 5000);
        $this->assertEquals(0, $status['msg']);
    }

    public function testOnePacketMessage()
    {
        $tr = new Transport($this->socket);
        $payload = 'hello';
        $tr->send($payload);
        $status = $tr->getStatus();
        $this->assertEquals(1, $status['msg']);
        $this->assertEquals(1, $this->socket->packetsCount());
        $packet = $this->socket->getPacket(0);
        list($header, $body) = $this->decodePacket($packet);
        list($pl, $ok) = $this->getPayload($body);
        $this->assertTrue($ok);
        $this->assertEquals($payload, $pl);
        $this->assertEquals($status['session'] >> 32, $header['sessionHigh']);
        $this->assertEquals($status['session'] & 0xffffffff, $header['sessionLow']);
        $this->assertEquals(1, $header['msgId']);
        $this->assertEquals(strlen($payload) + 4, $header['msgLen']);
        $this->assertEquals(0, $header['msgOffset']);
    }

    public function testSendMultiplePackets()
    {
        $maxbody = 10;
        $tr = new Transport($this->socket, ['max_packet' => Transport::HEADER_SIZE + $maxbody]);
        $payload = 'hello again';
        $tr->send($payload);
        $status = $tr->getStatus();
        $this->assertEquals(1, $status['msg']);
        $this->assertEquals(2, $this->socket->packetsCount());

        $packet = $this->socket->getPacket(0);
        list($header, $body1) = $this->decodePacket($packet);
        $this->assertEquals($status['session'] >> 32, $header['sessionHigh']);
        $this->assertEquals($status['session'] & 0xffffffff, $header['sessionLow']);
        $this->assertEquals(1, $header['msgId']);
        $this->assertEquals(strlen($payload) + 4, $header['msgLen']);
        $this->assertEquals(0, $header['msgOffset']);

        $packet = $this->socket->getPacket(1);
        list($header, $body2) = $this->decodePacket($packet);
        $this->assertEquals($status['session'] >> 32, $header['sessionHigh']);
        $this->assertEquals($status['session'] & 0xffffffff, $header['sessionLow']);
        $this->assertEquals(1, $header['msgId']);
        $this->assertEquals(strlen($payload) + 4, $header['msgLen']);
        $this->assertEquals($maxbody, $header['msgOffset']);

        list($pl, $ok) = $this->getPayload($body1 . $body2);
        $this->assertTrue($ok);
        $this->assertEquals($payload, $pl);
    }


    public function testSendMultipleMessages()
    {
        $maxbody = 10;
        $tr = new Transport($this->socket, ['max_packet' => Transport::HEADER_SIZE + $maxbody]);
        $payload1 = 'hello one';
        $tr->send($payload1);
        $payload2 = 'hello two';
        $tr->send($payload2);

        $status = $tr->getStatus();
        $this->assertEquals(2, $status['msg']);
        $this->assertEquals(4, $this->socket->packetsCount());

        $packet = $this->socket->getPacket(0);
        list($header, $body1) = $this->decodePacket($packet);
        $packet = $this->socket->getPacket(1);
        list($header, $body2) = $this->decodePacket($packet);
        list($pl, $ok) = $this->getPayload($body1 . $body2);
        $this->assertTrue($ok);
        $this->assertEquals($payload1, $pl);

        $packet = $this->socket->getPacket(2);
        list($header, $body1) = $this->decodePacket($packet);
        $packet = $this->socket->getPacket(3);
        list($header, $body2) = $this->decodePacket($packet);
        list($pl, $ok) = $this->getPayload($body1 . $body2);
        $this->assertTrue($ok);
        $this->assertEquals($payload2, $pl);

    }

    /**
     * @expectedException \Exception
     */
    public function testWrongMaxPacketOption()
    {
        $tr = new Transport($this->socket, ['max_packet' => 10]);
    }

    protected function decodePacket($data)
    {
        $headerPack = substr($data, 0, Transport::HEADER_SIZE);
        $body = substr($data, Transport::HEADER_SIZE);
        $up = unpack("N*", $headerPack);
        $header['sessionHigh'] = $up[1];
        $header['sessionLow'] = $up[2];
        $header['msgId'] = $up[3];
        $header['msgLen'] = $up[4];
        $header['msgOffset'] = $up[5];
        return [$header, $body];
    }

    protected function getPayload($body)
    {
        $end = strlen($body) - 4;
        if ($end < 0) {
            throw new \Exception("Body size is less than crc32 suffix!");
        }
        $payload = substr($body, 0, $end);
        $crcPack = substr($body, $end);
        $crc = unpack("N", $crcPack)[1];
        return [$payload, $crc === crc32($payload)];
    }
}
