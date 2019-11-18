<?php

namespace Webzak\Uzlog\Test;

use Webzak\Uzlog\{Transport, Saver};

class LogTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        $this->socket = new SocketMock();
        $this->tr = new Transport($this->socket);
    }

    public function testSave()
    {
        $saver = new Saver($this->tr);
        $file = 'file.txt';
        $data = 'hello';
        $saver->send($file, $data);
        list($header, $fname, $payload) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals(2, $header['type']);
        $this->assertEquals(0, $header['append']);
        $this->assertEquals($file, $fname);
        $this->assertEquals($data, $payload);
    }

    public function testAppend()
    {
        $saver = new Saver($this->tr);
        $file = 'file.txt';
        $data = 'hello';
        $saver->send($file, $data, ['append' => true]);
        list($header, $fname, $payload) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals(2, $header['type']);
        $this->assertEquals(1, $header['append']);
        $this->assertEquals($file, $fname);
        $this->assertEquals($data, $payload);
    }

    public function testArraySave()
    {
        $saver = new Saver($this->tr);
        $file = 'file.txt';
        $data = ['one' => 'two'];
        $saver->send($file, $data);
        list($header, $fname, $payload) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals(json_encode($data, JSON_PRETTY_PRINT), $payload);
    }

    public function testRawArraySave()
    {
        $saver = new Saver($this->tr);
        $file = 'file.txt';
        $data = ['one' => 'two'];
        $saver->send($file, $data, ['raw' => true]);
        list($header, $fname, $payload) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals(json_encode($data), $payload);
    }

    public function testRotated()
    {
        $saver = new Saver($this->tr);
        $file = 'file.???.txt';
        $data = 'foo';
        $saver->send($file, $data);
        list($header, $fname, $payload) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals('file.000.txt', $fname);
        $this->socket->clear();
        $saver->send($file, $data);
        list($header, $fname, $payload) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals('file.001.txt', $fname);
        $this->socket->clear();
        $saver->send($file, $data);
        list($header, $fname, $payload) = $this->decode(...$this->socket->getPackets());
        $this->assertEquals('file.002.txt', $fname);
    }

    /**
     * @expectedException \Exception
     */
    public function testWrongFilename()
    {
        $saver = new Saver($this->tr);
        $saver->send(true, '123');
    }

    /**
     * @expectedException \Exception
     */
    public function testWrongType()
    {
        $saver = new Saver($this->tr);
        $saver->send('f1.txt', true);
    }

    protected function decode(...$packets)
    {
        $data = '';
        foreach($packets as $p) {
            $data .= substr($p, Transport::HEADER_SIZE);
        }
        $data = substr($data, 0, strlen($data) - 4); // cut transport checksum
        $headerPack = substr($data, 0, Saver::HEADER_SIZE);
        $n = Saver::HEADER_SIZE - 1;
        $end = strlen($data) - 1;
        while($n++ < $end) {
            if ($data[$n] === "\0")
                break;
        }
        $fnamelen = $n - Saver::HEADER_SIZE;
        $fname = substr($data, Saver::HEADER_SIZE, $fnamelen);
        $body = substr($data, Saver::HEADER_SIZE + $fnamelen + 1);
        $up = unpack("C*", $headerPack);
        $header['type'] = $up[1];
        $header['append'] = $up[2];
        return [$header, $fname, $body];
    }
}
