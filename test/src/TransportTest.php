<?php

namespace Webzak\Uzlog\Test;

use Webzak\Uzlog\{Transport, Log};

class TransportTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
    }

    public function testSend()
    {
        $s = new SocketMock();
        $tr = new Transport($s);
        $log = new Log($tr);
        $log->send('hello test');
        $n = $s->packetsCount();
        $this->assertEquals(1, $n);
    }
}
