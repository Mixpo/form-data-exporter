<?php
namespace Mixpo\Igniter\Tests\Common;

use Mixpo\Igniter\Common\DateTimeUtil;

class DataTimeUtilTest extends \PHPUnit_Framework_TestCase
{
    function testValidDateTimeReturnsTrue()
    {
        $this->assertTrue(DateTimeUtil::dateTimeIsValid(new \DateTime()));
    }

    function testInvalidDateTimeReturnsFalse()
    {
        $this->assertFalse(DateTimeUtil::dateTimeIsValid(new \DateTime('0000-00-00')));
    }
}
