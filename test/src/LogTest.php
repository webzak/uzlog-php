<?php

namespace Webzak\Uzlog\Test;

use Webzak\Uzlog\{Transport, Log};

class LogTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        $this->socket = new SocketMock();
        $this->tr = new Transport($this->socket);
    }

    public function testSimpleSend()
    {
        $log = new Log($this->tr);
        $msg = 'hello';
        $log->send($msg);
        list($header, $body) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals(1, $header['type']);
        $this->assertEquals(0, $header['fg']);
        $this->assertEquals(0, $header['bg']);
        $this->assertEquals(0, $header['zero']);
        $this->assertEquals($msg, $body);
    }

    public function testMultiPacketSend()
    {
        $tr = new Transport($this->socket, ['max_packet' => Transport::HEADER_SIZE + 10]);
        $log = new Log($tr);
        $msg = 'hello one two three four five six';
        $log->send($msg);
        list($header, $body) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals($msg, $body);
    }

    public function testColors()
    {
        $log = new Log($this->tr);
        $msg = 'hello';
        $log->send($msg, ['fg' => 20,'bg' => 120]);
        list($header, $body) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals(20, $header['fg']);
        $this->assertEquals(120, $header['bg']);
    }

    public function testLimit()
    {
        $log = new Log($this->tr);
        $msg = 'hello one';
        $log->send($msg, ['limit' => 5]);
        list($header, $body) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals('[9->5..] hello', $body);
    }

    public function testArrayPrint()
    {
        $log = new Log($this->tr);
        $msg = ['foo' => 123];
        $log->send($msg);
        list($header, $body) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals("Array\n(\n    [foo] => 123\n)\n", $body);
    }

    public function testArrayJson()
    {
        $log = new Log($this->tr);
        $msg = ['foo' => 123];
        $log->send($msg, ['transform' => 'json']);
        list($header, $body) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals('{"foo":123}', $body);
    }

    public function testContext()
    {
        $log = new Log($this->tr);
        $msg = 'hello';
        $log->send($msg, ['context' => 1]);
        list($header, $body) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals("PHPUnit\Framework\TestCase::runTest()\n         hello", $body);
    }

    public function testContextFiles()
    {
        $log = new Log($this->tr);
        $log->set('context_files', true);
        $msg = 'hello';
        $log->send($msg, ['context' => 1]);
        list($header, $body) = $this->decode(...$this->socket->getPackets());
        $this->assertTrue(strpos($body, 'vendor/phpunit/phpunit/src/Framework/TestCase.php') > 0);
    }

    public function testPrefix()
    {
        $log = new Log($this->tr);
        $msg = 'hello one';
        $log->send($msg, ['prefix' => 'ABC: ']);
        list($header, $body) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals('ABC: hello one', $body);
    }


    protected function decode(...$packets)
    {
        $data = '';
        foreach($packets as $p) {
            $data .= substr($p, Transport::HEADER_SIZE);
        }
        $data = substr($data, 0, strlen($data) - 4); // cut transport checksum
        $headerPack = substr($data, 0, Log::HEADER_SIZE);
        $body = substr($data, Log::HEADER_SIZE + 9); // cut timer
        $up = unpack("C*", $headerPack);
        $header['type'] = $up[1];
        $header['fg'] = $up[2];
        $header['bg'] = $up[3];
        $header['zero'] = $up[4];
        return [$header, $body];
    }
}
