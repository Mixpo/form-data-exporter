<?php
namespace Mixpo\Igniter\Tests\Common;

use Mixpo\Igniter\Common\ArrayUtil;

class ArrayUtilTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testCheckBothExistFailsOnSecondParamMissing()
    {
        $testArray = ['startDate' => '2015-01-01'];

        $dateTest = ArrayUtil::checkBothExist($testArray, 'startDate', 'endDate');
        $this->assertFalse($dateTest, "Second parameter check failed.");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testCheckBothExistOnFirstParamMissing()
    {
        $testArray = ['endDate' => '2015-01-01'];

        $dateTest = ArrayUtil::checkBothExist($testArray, 'startDate', 'endDate');
        $this->assertFalse($dateTest, "First param check failed.");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testCheckBothExistOnBothParamsMissing()
    {
        $testArray = [];

        $dateTest = ArrayUtil::checkBothExist($testArray, 'startDate', 'endDate');
        $this->assertFalse($dateTest, "Neither parameter existed, checkBothExist should return false.");
    }
}
