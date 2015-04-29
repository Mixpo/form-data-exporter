<?php

namespace Mixpo\Igniter\Test\Tools\DbAdapter;


use Mixpo\Igniter\Test\MockPDO;
use Mixpo\Igniter\Tools\DbAdapter\PdoConnectionAdapter;

class PdoConnectionAdapterTest extends \PHPUnit_Framework_TestCase
{

    function testProofOfLife()
    {
        $adapter = new PdoConnectionAdapter(new MockPDO());
        $this->assertNotNull($adapter);
    }

    function testPrepareIsPassThrough()
    {
        $statement = "statement";
        $prepareOptions = ['prepare' => 'options'];
        $mockPdo = $this->getMock('\Mixpo\Igniter\Test\MockPDO');
        $mockPdo->expects($this->once())->method('prepare')->with($statement, $prepareOptions);
        $adapter = new PdoConnectionAdapter($mockPdo);
        $adapter->prepare($statement, $prepareOptions);
    }

}