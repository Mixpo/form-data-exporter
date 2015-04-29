<?php
namespace Mixpo\Igniter\Test\Tools;

use Mixpo\Igniter\Test\TestHelper;
use Mixpo\Igniter\Test\TestLogger;
use Mixpo\Igniter\Test\Tools\DbAdapter\MockConnectionAdapter;
use Mixpo\Igniter\Tools\Exporter;
use Mixpo\Igniter\Tools\InvalidInputException;

class InvalidInputExceptionTest extends \PHPUnit_Framework_TestCase
{
    function testInstantiate()
    {
        $e = new InvalidInputException();
        $this->assertNotNull($e);
    }
}