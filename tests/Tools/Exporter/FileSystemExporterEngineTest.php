<?php

namespace Mixpo\Igniter\Test\Tools\Export;

use Mixpo\Igniter\Test\TestLogger;
use Mixpo\Igniter\Tools\Export\FileSystemExporterEngine;

class FileSystemExporterEngineTest extends \PHPUnit_Framework_TestCase
{

    function testProofOfLife()
    {
        $exporter = new FileSystemExporterEngine('file://var/log', new TestLogger());
        $this->assertNotNull($exporter);
    }

    function testTargetIsS3ReturnsFalse()
    {
        $exporter = new FileSystemExporterEngine('file://var/log', new TestLogger());
        $this->assertFalse($exporter->targetIsS3('file://var/log'));
    }

    function testGetPathProtocolReturnedExpected()
    {
        $exporter = new FileSystemExporterEngine('file://var/log', new TestLogger());
        $this->assertEquals($exporter->getPathProtocol('file://var/log'), 'file');
    }

    function testGetPathProtocolIsCaseInsensitive()
    {
        $exporter = new FileSystemExporterEngine('file://var/log', new TestLogger());
        $this->assertEquals($exporter->getPathProtocol('FILE://var/log'), 'file');
    }

    /**
     * @expectedException \RuntimeException
     */
    function testUnsupportedProtocolThrowsExpectedException()
    {
        $exporter = new FileSystemExporterEngine('', new TestLogger());
        $this->assertEquals($exporter->getPathProtocol('foo://var/log'), 'file');
    }

    /**
     * @expectedException \RuntimeException
     */
    function testMalformedProtocolPathThrowsExpectedException()
    {
        $exporter = new FileSystemExporterEngine('', new TestLogger());
        $this->assertEquals($exporter->getPathProtocol('/malformed/protocol/path'), 'file');
    }


}