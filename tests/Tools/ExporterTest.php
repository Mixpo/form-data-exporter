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

    /**
     * @group FL-1265
     * @group FL-1161
     * @group FL-1236
     */
    function testConstructSelectQueryWithDateCriteriaSupplied()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            ['startDate' => '2015-04-01', 'endDate' => '2015-04-30'],
            $this->logger
        );
        $exporter->setExporterEngine(
            new FileSystemExporterEngine(TestHelper::getFileSystemTmpPath('out.csv'), $this->logger)
        );
        $expectedQuery = 'SELECT * FROM "tableName" WHERE "created" >= :startDate '
            . 'AND "created" <= :endDate';

        $expectedBindings = [
            'startDate' => '2015-04-01T00:00:00+0000',
            'endDate' => '2015-04-30T23:59:59+0000'
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
    function testValidateSelectCriteriaWithNoDateData()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $validated = TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
            [['col1' => 'x', 'col2' => 'y']]);
        $this->assertTrue($validated, "Validating no date parameters did not return true");
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaInvalidStartDateThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
            [['startDate' => 'nuhuh', 'endDate' => '2015-01-01']]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaInvalidEndDateThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
            [['startDate' => '2015-01-01', 'endDate' => 'nope']]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1161
     * @group FL-1265
     */
    function testValidateSelectCriteriaMissingStartDateThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
            [['endDate' => '2015-01-01']]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1161
     * @group FL-1265
     */
    function testValidateSelectCriteriaMissingEndDateThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
            [['startDate' => '2015-01-01']]);
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
            [['startDate' => '2015-01-01', 'endDate' => '2015-02-01']]);
        $this->assertTrue($isValidated, "Validator didn't validate valid dates.");
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaStartDateAfterEndDateThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
            [['startDate' => '2015-02-01', 'endDate' => '2015-01-01']]);
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
            [['startDate' => '2015-02-01', 'endDate' => '2015-02-01']]);
        $this->assertTrue($validated, "Start date == end date failed to validate");
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1266
     * @group FL-1161
     * @group FL-1236
     */
    function testValidateSelectCriteriaStartDateInFutureThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        // Pick a date 7 days from now.
        $futureStartDate = (new \DateTime())->modify('1 day');

        TestHelper::invokeNonPublicMethod($exporter, 'validateSelectCriteria',
            [['startDate' => $futureStartDate->format(\DateTime::ISO8601), 'endDate' => '2015-02-01']]);
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testCheckDateValidity()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $startDate = '2015-01-01';
        $endDate = '2015-02-01';
        $expectedStartDate = new \DateTime($startDate);
        // This one is fully tested elsewhere.
        $expectedEndDate = TestHelper::invokeNonPublicMethod($exporter, 'getEndDate', [$endDate]);

        list ($actualStartDate, $actualEndDate) = TestHelper::invokeNonPublicMethod($exporter, 'checkDateValidity',
            [$startDate, $endDate]);
        $this->assertEquals($expectedStartDate, $actualStartDate, "Start date did not match expected.");
        $this->assertEquals($expectedEndDate, $actualEndDate, "End date did not match expected.");
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1161
     * @group FL-1265
     */
    function testCheckDateValidityCreatesDateTimeFailsDateTimeUtilCheck()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $startDate = '0000-00-00';
        $endDate = '0000-00-00';

            TestHelper::invokeNonPublicMethod($exporter, 'checkDateValidity',
            [$startDate, $endDate]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1161
     * @group FL-1236
     */
    function testCheckDateValidityThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $startDate = '2015-01-01';
        $endDate = 'weebolahtimmaugh';
        TestHelper::invokeNonPublicMethod($exporter, 'checkDateValidity', [$startDate, $endDate]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1161
     * @group FL-1236
     */
    function testCheckDateBoundsStartDateInFutureThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $futureStartDate = (new \DateTime())->modify('7 days');
        $endDate = '2015-02-01';

        TestHelper::invokeNonPublicMethod($exporter, 'checkDateBounds', [$futureStartDate, $endDate]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1161
     * @group FL-1236
     */
    function testCheckDateBoundsStartDateAfterEndDateThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $startDate = new \DateTime('2015-04-05');
        $endDate = new \DateTime('2015-03-31');

        TestHelper::invokeNonPublicMethod($exporter, 'checkDateBounds', [$startDate, $endDate]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @group FL-1268
     * @group FL-1161
     * @group FL-1236
     */
    function testCheckDateBoundsStartDateIsLessThanOneDayAfterEndDateThrowsException()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $startDate = new \DateTime('2015-04-01');
        $endDate = new \DateTime('2015-03-31 23:59:59');

        TestHelper::invokeNonPublicMethod($exporter, 'checkDateBounds', [$startDate, $endDate]);
    }

    /**
     * @group FL-1161
     * @group FL-1236
     */
    function testCheckDateBoundsWorksWithValidDates()
    {
        $exporter = new FormExporter(
            'dsn',
            'tableName',
            'data',
            [],
            $this->logger
        );

        $startDate = new \DateTime('2015-01-01');
        $endDate = new \DateTime('2015-02-01');

        $dateBounds = TestHelper::invokeNonPublicMethod($exporter, 'checkDateBounds', [$startDate, $endDate]);
        $this->assertTrue($dateBounds, "checkDateBounds didn't return true with valid dates.");
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

        $this->assertEquals($expectedStartDateDateTime,
            TestHelper::invokeNonPublicMethod($exporter, 'getStartDate', [$startDateStr]));
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

        $this->assertEquals($expectedEendDateDateTime,
            TestHelper::invokeNonPublicMethod($exporter, 'getEndDate', [$endDateStr]));
    }

    /**
     * @group FL-1265
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

        $expectedStartDate = '2015-01-01T00:00:00+0000';
        $expectedEndDate = '2015-01-02T23:59:59+0000';

        $selectCriteria = TestHelper::invokeNonPublicMethod($exporter, 'processSelectCriteria',
            [['startDate' => $startDate, 'endDate' => $endDate]]);

        $whereItems = $selectCriteria[0];
        $keyValuePairs = $selectCriteria[1];

        $this->assertEquals('"created" >= :startDate', $whereItems[0]);
        $this->assertEquals('"created" <= :endDate', $whereItems[1]);
        $this->assertEquals($expectedStartDate, $keyValuePairs['startDate']);
        $this->assertEquals($expectedEndDate, $keyValuePairs['endDate']);
    }

    /**
     * @group FL-1532
     */
    function testCreatedDateFormattedInSupportedUSLocalTimes() {

        $supportedTimeZoneOffsets = [
          'EST' => '-05:00',
          'EDT' => '-04:00',
          'CST' => '-06:00',
          'CDT' => '-05:00',
          'MST' => '-07:00',
          'MDT' => '-06:00',
          'PST' => '-08:00',
          'PDT' => '-07:00',
        ];

        $resultsFixture = TestHelper::getFixtureInput('happy-path.php');

        foreach ($supportedTimeZoneOffsets as $abbr => $offset) {

            $mockExporter = $this->getMockBuilder('\Mixpo\Igniter\Tools\FormExporter')
                ->setConstructorArgs(
                    [
                        'dsn',
                        'tableName',
                        'data',
                        ['startDate' => '2015-04-15 00:00:00' . $offset, 'endDate' => '2015-04-22 00:00:00' . $offset],
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

            $expected = TestHelper::getFixtureOutput("good-{$abbr}.csv");
            $actual = TestHelper::getTmpFile(pathinfo($csvFilePath, PATHINFO_BASENAME));

            $this->assertEquals($expected, $actual);
        }

    }
}
