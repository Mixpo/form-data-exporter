<?php

namespace Mixpo\Igniter\Test\Tools\Export;

use Mixpo\Igniter\Test\TestLogger;
use Mixpo\Igniter\Tools\Export\S3ExporterEngine;

class S3ExporterEngineTest extends \PHPUnit_Framework_TestCase
{

    function testProofOfLife()
    {
        $s3ClientMock = $this->getMockBuilder('\Aws\S3\S3Client')->disableOriginalConstructor()->getMock();
        $exporter = new S3ExporterEngine('s3://bucket/object', $s3ClientMock, new TestLogger());
        $this->assertNotNull($exporter);
    }


    function testDestinationWritable()
    {
        $s3ClientMock = $this->getMockBuilder('\Aws\S3\S3Client')->disableOriginalConstructor()->getMock();
        $exporter = new S3ExporterEngine('s3://bucket/object', $s3ClientMock, new TestLogger());
        // just ensure an exception is not thrown.  This method does nothing at this point.
        $exporter->verifyDestinationIsWritable();
        $this->assertNotNull($exporter);
    }

}