<?php
namespace Mixpo\Igniter\Test\Tools;

use Mixpo\Igniter\Test\MockPDO;
use Mixpo\Igniter\Test\TestHelper;
use Mixpo\Igniter\Test\TestLogger;
use Mixpo\Igniter\Tools\Exporter;

class ExporterTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        array_map('unlink', glob(TestHelper::getTmpPath('/*')));
        parent::setUp();
    }

    function testInstantiate()
    {
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            'outputPath',
            [],
            new TestLogger()
        );
        $this->assertNotNull($exporter, "Should be able to instantiate Exporter");
    }

    function testHappyPath()
    {
        $input = TestHelper::getFixtureInput('happy-path.php');
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            'outputPath',
            [],
            new TestLogger()
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResultToFile',
            [$input, TestHelper::getTmpPath('happy-path.csv')]
        );

        $expected = TestHelper::getFixtureOutput('good.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
    }

    function testWithRandomizeFilenameOff()
    {
        $expectedOutputFilePath = TestHelper::getTmpPath('happy-path.csv');
        $input = TestHelper::getFixtureInput('happy-path.php');
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            [],
            new TestLogger()
        );
        $exporter->setRandomizeOutputFilename(false);
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResultToFile',
            [$input, $expectedOutputFilePath]
        );

        $this->assertEquals($expectedOutputFilePath, $csvFilePath);
        $this->assertFileExists($expectedOutputFilePath);
    }

    function testFieldsOutOfOrder()
    {
        $input = TestHelper::getFixtureInput('fields-out-of-order.php');
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            [],
            new TestLogger()
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResultToFile',
            [$input, TestHelper::getTmpPath('fields-out-of-order.csv')]
        );

        $expected = TestHelper::getFixtureOutput('good.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
    }

    /**
     * @expectedException \Mixpo\Igniter\Tools\InvalidInputException
     */
    function testEmptyInputArrayThrowsException()
    {
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            [],
            new TestLogger()
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResultToFile',
            [[], TestHelper::getTmpPath('first-row-missing-data-field.csv')]
        );
    }

    /**
     * @expectedException \Mixpo\Igniter\Tools\InvalidInputException
     */
    function testNonListInputArrayThrowsException()
    {
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            [],
            new TestLogger()
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResultToFile',
            [['foo' => 'bar'], TestHelper::getTmpPath('first-row-missing-data-field.csv')]
        );
    }

    /**
     * @expectedException \Mixpo\Igniter\Tools\InvalidInputException
     */
    function testMalformedInputArrayThrowsException()
    {
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            [],
            new TestLogger()
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResultToFile',
            [[0 => ['foo' => 'bar']], TestHelper::getTmpPath('malformed.csv')]
        );
    }

    function testFirstRowWithMissingDataField()
    {
        $input = TestHelper::getFixtureInput('first-row-missing-data-field.php');
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            [],
            new TestLogger()
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResultToFile',
            [$input, TestHelper::getTmpPath('first-row-missing-data-field.csv')]
        );

        $expected = TestHelper::getFixtureOutput('first-row-missing-data-field.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
        $this->assertEquals(1, count($exporter->getIssues()));
    }

    function testSecondWithMissingDataField()
    {
        $input = TestHelper::getFixtureInput('second-row-missing-data-field.php');
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            [],
            new TestLogger()
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResultToFile',
            [$input, TestHelper::getTmpPath('second-row-missing-data-field.csv')]
        );

        $expected = TestHelper::getFixtureOutput('second-row-missing-data-field.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
        $this->assertEquals(1, count($exporter->getIssues()));
    }

    function testOneRowWitExtraFieldField()
    {
        $input = TestHelper::getFixtureInput('one-row-with-extra-field.php');
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            [],
            new TestLogger()
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResultToFile',
            [$input, TestHelper::getTmpPath('one-row-with-extra-field.csv')]
        );

        $expected = TestHelper::getFixtureOutput('one-row-with-extra-field.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
    }

    function testSelectQueryBuilderWithEmptyCriteria()
    {
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            [],
            new TestLogger()
        );
        $expectedQuery = 'SELECT * FROM "tableName"';
        $expectedBindings = [];
        list($actualQuery, $actualBindings) = TestHelper::invokeNonPublicMethod($exporter, 'constructSelectQuery');
        $this->assertEquals($expectedQuery, $actualQuery);
        $this->assertEquals($expectedBindings, $actualBindings);
    }

    function testSelectQueryBuilderWithCriteriaSupplied()
    {
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            ['column1' => 'foo', 'column2' => 'bar'],
            new TestLogger()
        );
        $expectedQuery = 'SELECT * FROM "tableName" WHERE "column1" = :column1 AND "column2" = :column2';
        $expectedBindings = ['column1' => 'foo', 'column2' => 'bar'];
        list($actualQuery, $actualBindings) = TestHelper::invokeNonPublicMethod($exporter, 'constructSelectQuery');
        $this->assertEquals($expectedQuery, $actualQuery);
        $this->assertEquals($expectedBindings, $actualBindings);
    }

    function testSelectQueryBuilderWithCriteriaListValuesSupplied()
    {
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            ['column1' => ['foo', 'bar'], 'column2' => 'baz', 'column3' => [1, 2]],
            new TestLogger()
        );
        $expectedQuery = 'SELECT * FROM "tableName" WHERE "column1" IN [:_0column1, :_1column1] '
            .'AND "column2" = :column2 '
            .'AND "column3" IN [:_0column3, :_1column3]';
        $expectedBindings = ['_0column1' => 'foo', '_1column1' => 'bar', 'column2' => 'baz', '_0column3' => 1, '_1column3' => 2];
        list($actualQuery, $actualBindings) = TestHelper::invokeNonPublicMethod($exporter, 'constructSelectQuery');
        $this->assertEquals($expectedQuery, $actualQuery);
        $this->assertEquals($expectedBindings, $actualBindings);
    }

    function testRunHappyPath()
    {
        $resultsFixture = TestHelper::getFixtureInput('happy-path.php');

        $mockExporter = $this->getMockBuilder('\Mixpo\Igniter\Tools\Exporter')
            ->setConstructorArgs(
                [
                    'dsn',
                    'tableName',
                    'data',
                    TestHelper::getTmpPath('happy-path.csv'),
                    ['column1' => 'foo', 'column2' => 'bar'],
                    new TestLogger()
                ]
            )
            ->setMethods(['executeQuery'])->getMock();
        $mockExporter->expects($this->once())->method('executeQuery')->willReturn($resultsFixture);

        $mockPdo = $this->getMockBuilder('\Mixpo\Igniter\Test\MockPDO')->getMock();

        $mockExporter->setPdo($mockPdo);

        $csvFilePath = $mockExporter->run();

        $expected = TestHelper::getFixtureOutput('good.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);

    }

    /**
     * @expectedException \RuntimeException
     */
    function testPrepareQueryIssueThrowsExpectedException()
    {
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            TestHelper::getTmpPath('out.csv'),
            ['column1' => ['foo', 'bar'], 'column2' => 'baz', 'column3' => [1, 2]],
            new TestLogger()
        );
        $mockPdo = $this->getMock('\Mixpo\Igniter\Test\MockPDO');
        $mockPdo->expects($this->once())->method('prepare')->willThrowException(new \Exception());
        $exporter->setPdo($mockPdo);
        TestHelper::invokeNonPublicMethod($exporter, 'executeQuery', ['', []]);
    }

    /**
     * @expectedException \RuntimeException
     */
    function testUnWritableOutputDirThrowExpectedException()
    {
        $exporter = new Exporter(
            'dsn',
            'tableName',
            'data',
            '/not/a/real/path',
            ['column1' => 'foo', 'column2' => 'bar'],
            new TestLogger()
        );
        $exporter->run();
    }


}