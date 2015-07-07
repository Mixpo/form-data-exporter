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
     * @todo: something
     * @group FL-1161
     * @group FL-1236
     */
    function testProcessSelectCriteria()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $thing = TestHelper::invokeNonPublicMethod($exporter, 'processSelectCriteria', [['start_date' => '2015-01-01', 'end_date' => '2015-02-01']]);
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaNoData()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $validated = TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria', [[]]);
        $this->assertTrue($validated, "No parameters to validate did not return true");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaInvalidStartDateNoEndDate()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $dateData = '2015-01-01';

        try {
            TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria', [['start_date' => $dateData]]);
        } catch (\InvalidArgumentException $e) {
            $this->assertContains('start_date exists without an end_date.', $e->getMessage());
            return;
        }
        $this->assertFalse(true, "Exception not thrown");

    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaInvalidEndDateNoStartDate()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $dataData = '2015-01-01';

        try {
            TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria', [['end_date' => $dataData]]);
        } catch (\InvalidArgumentException $e) {
            // We're throwing lots of IAE's, so let's sanity check we got the message we expected.
            $this->assertContains('end_date exists without a start_date.', $e->getMessage());
            return;
        }
        $this->assertFalse(true, "Exception not thrown");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaInvalidStartDate()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $invalidStartDateData = 'nuhuh';

        try {
            TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
                [['start_date' => $invalidStartDateData, 'end_date' => '2015-01-01']]);
        } catch (\InvalidArgumentException $e) {
            // We're throwing lots of IAE's, so let's sanity check we got the message we expected.
            $this->assertContains('start_date', $e->getMessage());
            $this->assertContains($invalidStartDateData, $e->getMessage());
            return;
        }
        $this->assertFalse(true, "Exception not thrown");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaInvalidEndDate()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $invalidEndDateData = 'nope';

        try {
            TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
                [['start_date' => '2015-01-01', 'end_date' => $invalidEndDateData]]);
        } catch (\InvalidArgumentException $e) {
            // We're throwing lots of IAE's, so let's sanity check we got the message we expected.
            $this->assertContains('end_date', $e->getMessage());
            $this->assertContains($invalidEndDateData, $e->getMessage());
            return;
        }
        $this->assertFalse(true, "Exception not thrown");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaRealDates()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $isValidated = TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
            [['start_date' => '2015-01-01', 'end_date' => '2015-02-01']]);
        $this->assertTrue($isValidated, "Validator didn't validate valid dates.");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaStartDateAfterEndDate()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        try {
            TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
                [['start_date' => '2015-02-01', 'end_date' => '2015-01-01']]);
        } catch (\InvalidArgumentException $e) {
            $this->assertContains("Date order problem", $e->getMessage());
            return;
        }
        $this->assertFalse(true, "Exception not thrown");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaStartDateEqualsEndDate()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $validated = TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
            [['start_date' => '2015-02-01', 'end_date' => '2015-02-01']]);
        $this->assertTrue($validated, "Start date == end date failed to validate");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaStartDateInTheFuture()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        // Pick a date 7 days from now.
        $futureStartDate = (new \DateTime())->add(new \DateInterval('P7D'));

        try {
            TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
                [['start_date' => $futureStartDate->format(\DateTime::ISO8601), 'end_date' => '2015-02-01']]);
        } catch (\InvalidArgumentException $e) {
            $this->assertContains("Start date in the future", $e->getMessage());
            return;
        }

        $this->assertFalse(true, "Exception not thrown");
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testGetStartDate()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $startDateStr = '2015-01-01';
        $expectedStartDateDateTime = new \DateTime($startDateStr . ' 00:00:00');

        $this->assertEquals($expectedStartDateDateTime, TestHelper::invokeNonPublicMethod($exporter, 'getStartDate', [$startDateStr]));
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testGetEndDate()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $endDateStr = '2015-01-01';
        $expectedEendDateDateTime = new \DateTime($endDateStr . ' 23:59:59');

        $this->assertEquals($expectedEendDateDateTime, TestHelper::invokeNonPublicMethod($exporter, 'getEndDate', [$endDateStr]));
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testProcessSelectCriteriaStartEndDates()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $startDate = '2015-01-01';
        $endDate = '2015-01-02';
        $selectCriteria = TestHelper::invokeNonPublicMethod($exporter, 'processSelectCriteria', [['start_date' => $startDate, 'end_date' => $endDate]]);

        $whereItems = $selectCriteria[0];
        $keyValuePairs = $selectCriteria[1];

        $this->assertEquals('created >= :start_date', $whereItems[0]);
        $this->assertEquals('"start_date" = :start_date', $whereItems[1]);
        $this->assertEquals('created <= :end_date', $whereItems[2]);
        $this->assertEquals('"end_date" = :end_date', $whereItems[3]);
        $this->assertEquals($startDate, $keyValuePairs['start_date']);
        $this->assertEquals($endDate, $keyValuePairs['end_date']);
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testExportsTimeboxRanger()
    {
        $input = TestHelper::getFixtureInput('date-spread.php');
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            ['start_date' => '2015-04-01', 'end_date' => '2015-04-30'],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('date-spread.csv'), $this->logger)
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [$input]
        );

        $expected = TestHelper::getFixtureOutput('good-date-spread.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testExportsTimeboxRangeSingleDay()
    {
        $input = TestHelper::getFixtureInput('date-spread.php');
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            ['start_date' => '2015-04-01', 'end_date' => '2015-04-01'],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('date-spread-one-day.csv'), $this->logger)
        );
        $csvFilePath = TestHelper::invokeNonPublicMethod(
            $exporter,
            'exportResult',
            [$input]
        );

        $expected = TestHelper::getFixtureOutput('good-date-spread-one-day.csv');
        $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

        $this->assertEquals($expected, $actual);
    }
}
