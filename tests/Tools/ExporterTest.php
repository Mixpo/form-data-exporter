<?php
namespace Mixpo\Igniter\Test\Tools;

use Mixpo\Igniter\Test\TestHelper;
use Mixpo\Igniter\Test\TestLogger;
use Mixpo\Igniter\Test\Tools\DbAdapter\MockConnectionAdapter;
use Mixpo\Igniter\Tools\Export\ExporterEngine;
use Mixpo\Igniter\Tools\Export\FileSystemExporterEngine;
use Mixpo\Igniter\Tools\FormExporter;

class ExporterTest extends \PHPUnit_Framework_TestCase
{
    protected $logger;

    protected function setUp()
    {
        array_map('unlink', glob(TestHelper::getFileSystemTmpPath('/*')));
        parent::setUp();
        $this->logger = new TestLogger();
    }

    function testInstantiate()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(new FileSystemExporterEngine('file:///var/tmp', $this->logger));
        $this->assertNotNull($exporter, "Should be able to instantiate FormExporter");
    }

    function testHappyPath()
    {
        $input = TestHelper::getFixtureInput('happy-path.php');
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('happy-path.csv'), $this->logger)
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [$input]
        );

        $expected = TestHelper::getFixtureOutput('good.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
    }

    function testWithRandomizeFilenameOff()
    {
        $expectedOutputFilePath = ExporterEngine::getNakedPath(TestHelper::getFileSystemTmpPath('happy-path.csv'));
        $input = TestHelper::getFixtureInput('happy-path.php');
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(
                TestHelper::getFileSystemTmpPath('happy-path.csv'),
                $this->logger,
                $randomizeFileName = false
            )
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [$input]
        );

        $this->assertEquals($expectedOutputFilePath, $csvFilePath);
        $this->assertFileExists($expectedOutputFilePath);
    }

    function testFieldsOutOfOrder()
    {
        $input = TestHelper::getFixtureInput('fields-out-of-order.php');
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [$input]
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
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [[]]
        );
    }

    /**
     * @expectedException \Mixpo\Igniter\Tools\InvalidInputException
     */
    function testNonListInputArrayThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [['foo' => 'bar']]
        );
    }

    /**
     * @expectedException \Mixpo\Igniter\Tools\InvalidInputException
     */
    function testMalformedInputArrayThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [[0 => ['foo' => 'bar']]]
        );
    }

    function testFirstRowWithMissingDataField()
    {
        $input = TestHelper::getFixtureInput('first-row-missing-data-field.php');
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [$input]
        );

        $expected = TestHelper::getFixtureOutput('first-row-missing-data-field.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
        $this->assertEquals(1, count($exporter->getIssues()));
    }

    function testSecondWithMissingDataField()
    {
        $input = TestHelper::getFixtureInput('second-row-missing-data-field.php');
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [$input]
        );

        $expected = TestHelper::getFixtureOutput('second-row-missing-data-field.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
        $this->assertEquals(1, count($exporter->getIssues()));
    }

    function testOneRowWitExtraFieldField()
    {
        $input = TestHelper::getFixtureInput('one-row-with-extra-field.php');
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [$input]
        );

        $expected = TestHelper::getFixtureOutput('one-row-with-extra-field.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
    }

    function testSelectQueryBuilderWithEmptyCriteria()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        $expectedQuery = 'SELECT * FROM "tableName"';
        $expectedBindings = [];
        list($actualQuery, $actualBindings) = TestHelper::invokeNonPublicMethod($exporter, 'constructSelectQuery');
        $this->assertEquals($expectedQuery, $actualQuery);
        $this->assertEquals($expectedBindings, $actualBindings);
    }

    function testSelectQueryBuilderWithCriteriaSupplied()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            ['column1' => 'foo', 'column2' => 'bar'],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        $expectedQuery = 'SELECT * FROM "tableName" WHERE "column1" = :column1 AND "column2" = :column2';
        $expectedBindings = ['column1' => 'foo', 'column2' => 'bar'];
        list($actualQuery, $actualBindings) = TestHelper::invokeNonPublicMethod($exporter, 'constructSelectQuery');
        $this->assertEquals($expectedQuery, $actualQuery);
        $this->assertEquals($expectedBindings, $actualBindings);
    }

    function testSelectQueryBuilderWithCriteriaListValuesSupplied()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            ['column1' => ['foo', 'bar'], 'column2' => 'baz', 'column3' => [1, 2]],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        $expectedQuery = 'SELECT * FROM "tableName" WHERE "column1" IN (:_0column1, :_1column1) '
            . 'AND "column2" = :column2 '
            . 'AND "column3" IN (:_0column3, :_1column3)';
        $expectedBindings = [
            '_0column1' => 'foo',
            '_1column1' => 'bar',
            'column2' => 'baz',
            '_0column3' => 1,
            '_1column3' => 2
        ];
        list($actualQuery, $actualBindings) = TestHelper::invokeNonPublicMethod($exporter, 'constructSelectQuery');
        $this->assertEquals($expectedQuery, $actualQuery);
        $this->assertEquals($expectedBindings, $actualBindings);
    }

    function testRunHappyPath()
    {
        $resultsFixture = TestHelper::getFixtureInput('happy-path.php');

        $mockExporter = $this->getMockBuilder('\Mixpo\Igniter\Tools\FormExporter')
            ->setConstructorArgs(
                [
                    'dsn',
                    'tableName',
                    'data',
                    ['column1' => 'foo', 'column2' => 'bar'],
                    $this->logger
                ]
            )
            ->setMethods(['executeQuery'])->getMock();
        $mockExporter->expects($this->once())->method('executeQuery')->willReturn($resultsFixture);

        /** @var FormExporter $mockExporter */
        $mockExporter->setDbConnectionAdapter(new MockConnectionAdapter());
        $mockExporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('happy-path.csv'), $this->logger)
        );

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
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            ['column1' => ['foo', 'bar'], 'column2' => 'baz', 'column3' => [1, 2]],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        $mockPdo = $this->getMock('\Mixpo\Igniter\Test\Tools\DbAdapter\MockConnectionAdapter');
        $mockPdo->expects($this->once())->method('prepare')->willThrowException(new \Exception());
        $exporter->setDbConnectionAdapter($mockPdo);
        TestHelper::invokeNonPublicMethod($exporter, 'executeQuery', ['', []]);
    }

    /**
     * @expectedException \RuntimeException
     */
    function testUnWritableOutputDirThrowExpectedException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            ['column1' => 'foo', 'column2' => 'bar'],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine('/not/a/real/path', $this->logger)
        );
        $exporter->run();
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testProcessSelectCriteria()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            ['column1' => 'foo', 'column2' => 'bar'],
            $this->logger
        );

//        $exporter->processSelectCriteria('x');
        $thing = TestHelper::invokeNonPublicMethod($exporter, 'processSelectCriteria', [['x' => 'y']]);
        $this->assertTrue(false);
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testvalidateSelectCriteria()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            ['column1' => 'foo', 'column2' => 'bar'],
            $this->logger
        );

//        $exporter->processSelectCriteria('x');
        $thing = TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria', [['x' => 'y']]);
        $this->assertTrue(false);
    }

}
