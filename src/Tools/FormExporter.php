<?php
namespace Mixpo\Igniter\Tools;

use Mixpo\Igniter\Tools\DbAdapter\ConnectionAdapter;
use Mixpo\Igniter\Tools\Export\ExporterEngine;
use Mixpo\Igniter\Common\ArrayUtil;
use Mixpo\Igniter\Common\DateTimeUtil;
use Psr\Log\LoggerInterface;

class FormExporter
{

    /**
     * @var array
     */
    var $issues = [];
    /**
     * @var \PDO
     */
    protected $pdoConnection;

    /**
     * @var array
     */
    protected $canonicalColumnNamesList = [];

    /**
     * @var string
     */
    protected $dsnString;

    /**
     * @var
     */
    protected $tableName;

    /**
     * @var string
     */
    protected $dataFieldName;

    /**
     * @var array
     */
    protected $selectCriteria;

    /**
     * @var boolean
     */
    protected $someRowsHaveExtraColumns = false;

    /**
     * @var bool If true, the out put file will have a random seed added to help avoid collisions
     *           Example:
     *           The supplied path '/var/data/csv/leadgen99.csv'
     *           becomes '/var/data/csv/leadgen99-55358afdeefa5.csv'
     */
    protected $randomizeOutputFilename = true;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ExporterEngine
     */
    protected $exportEngine;

    const START_DATE_ATTRIBUTE = 'startDate';
    const END_DATE_ATTRIBUTE = 'endDate';

    /**
     * @var string
     */
    protected $timeZoneOffset = "+0:00";

    /**
     * @param string $dsnString
     * @param string $tableName
     * @param string $dataFieldName
     * @param array $selectCriteria
     * @param LoggerInterface $logger
     */
    function __construct(
        $dsnString,
        $tableName,
        $dataFieldName,
        $selectCriteria = [],
        LoggerInterface $logger
    ) {
        $this->dsnString = $dsnString;
        $this->tableName = $tableName;
        $this->dataFieldName = $dataFieldName;
        $this->selectCriteria = $selectCriteria;
        $this->logger = $logger;
    }

    /**
     * @param ConnectionAdapter $pdo Optional injector for the PDO connection. If not set prior to calling init(),
     * a PDO connection will be built using the constructor arguments.
     */
    public function setDbConnectionAdapter(ConnectionAdapter $pdo)
    {
        $this->pdoConnection = $pdo;
    }

    /**
     * @return string
     * @throws InvalidInputException
     */
    public function run()
    {
        if (!$this->exportEngine) {
            throw new \RuntimeException(
                'An Export Engine (Mixpo\Igniter\Tools\Export\ExportEngine) has not been set via ->setExportEngine()'
            );
        }
        if (!$this->pdoConnection) {
            $this->pdoConnection = new \PDO($this->dsnString);
        }
        $this->exportEngine->verifyDestinationIsWritable();

        $this->validateSelectCriteria($this->selectCriteria);
        list($query, $bindings) = $this->constructSelectQuery();
        $results = $this->executeQuery($query, $bindings);

        return $this->exportResult($results);
    }

    public function setExporterEngine(ExporterEngine $exportEngine)
    {
        $this->exportEngine = $exportEngine;
    }

    /**
     * @return array Returns an array of any rows that had parse issues and will not be in the CSV output.
     */
    public function getIssues()
    {
        return $this->issues;
    }

    /**
     * @param $query
     * @param $bindings
     * @return array
     */
    protected function executeQuery($query, $bindings)
    {
        try {
            $sth = $this->pdoConnection->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
            $sth->execute($bindings);

            return $sth->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $this->logger->error(
                "Error executing query to retrieve leadgen data. Error: " . $e->getMessage()
                . ". Query: '{$query}'.  "
                . "With Bindings: " . json_encode($bindings)
            );
            throw new \RuntimeException("There was an error selecting the Leadgen data from the data store.");
        }
    }

    /**
     * @param array $queryResult
     * @return string
     * @throws InvalidInputException
     */
    protected function exportResult(array $queryResult)
    {
        if (!$queryResult) {
            throw new InvalidInputException("No results were returned for the given criteria.");
        }
        if (!isset($queryResult[0])) {
            throw new InvalidInputException("Input Array a list of Results since 0'th index does not exist");
        }
        $transformedResult = $this->extractDataRows($queryResult);
        if (!$transformedResult) {
            throw new InvalidInputException("No valid rows found during transformation");
        }
        if ($this->someRowsHaveExtraColumns) {
            $transformedResult = $this->reconcileWithColumnNamesList(
                $this->canonicalColumnNamesList,
                $transformedResult
            );
        }
        $outputFilePath = $this->exportEngine->writeCsv(
            array_keys($this->canonicalColumnNamesList),
            $transformedResult
        );

        return $outputFilePath;
    }

    /**
     * @param array $resultsArray
     * @return array
     */
    protected function extractDataRows(array $resultsArray)
    {
        $extractedDataRows = [];
        if ($resultsArray) {
            foreach ($resultsArray as $resultRow) {
                if (!array_key_exists($this->dataFieldName, $resultRow)) {
                    $this->issues[] = [
                        'message' => "Expected data field of name: '{$this->dataFieldName}', not found for this row",
                        'row' => $resultRow
                    ];
                    continue;
                }
                $this->extractColumnNames($resultRow);
                $data = json_decode($resultRow[$this->dataFieldName], true);

                // Created Date
                $createdDate = null;
                try {

                    $createdDate = new \DateTime($resultRow['created']);

                } catch (\Exception $e) {
                    throw new \InvalidArgumentException("createdDate '{$resultRow['created']}' was unable to be processed.");
                }

                // Set the Timezone of the Created Date
                $createdDate->setTimezone(new \DateTimeZone($this->timeZoneOffset));

                // Format the Created Date
                $data['created'] = $createdDate->format('Y-m-d h:i:s a');

                ksort($data);
                $extractedDataRows[] = $data;
            }
        }

        return $extractedDataRows;
    }

    /**
     * For each data row, the keys (column names) are extracted.  If there are any new column names
     * seen, they are added the $this->canonicalColumnNamesList.  Thus after all rows are analyzed,
     * $this->canonicalHeaderRow hold the whole set of possible column names for a Row.
     *
     * @param $dataRow
     */
    protected function extractColumnNames($dataRow)
    {
        // Parse the Form Data
        $data = json_decode($dataRow[$this->dataFieldName], true);

        // Include the 'created' column manually
        $data['created'] = $dataRow['created'];

        $fieldNames = array_fill_keys(array_keys($data), null);
        $merged = array_merge($this->canonicalColumnNamesList, $fieldNames);
        ksort($merged);
        if ($this->canonicalColumnNamesList && ($this->canonicalColumnNamesList != $merged)) {
            $this->someRowsHaveExtraColumns = true;
        }
        $this->canonicalColumnNamesList = $merged;
    }

    /**
     * Ensures that all rows have all columns represented.  If a row is missing columns, those columns are added
     * with null values.
     *
     * @param $headerFields
     * @param $transformedResult
     * @return array
     */
    protected function reconcileWithColumnNamesList($headerFields, $transformedResult)
    {
        $headerFieldList = array_keys($headerFields);
        foreach ($transformedResult as &$result) {
            if (array_keys($result) != $headerFieldList) {
                $result += array_diff_key($headerFields, $result);
                ksort($result);
            }
        }

        return $transformedResult;
    }

    /**
     * Simple SELECT query constructor.
     * @return array
     */
    protected function constructSelectQuery()
    {
        $whereClauseStatement = '';
        $bindings = [];
        $selectStatement = "SELECT * FROM \"{$this->tableName}\"";
        if ($this->selectCriteria) {
            list($whereClauseSegments, $bindings) = $this->processSelectCriteria($this->selectCriteria);
            $whereClauseStatement = ' WHERE ' . implode(' AND ', $whereClauseSegments);
        }

        return ["{$selectStatement}{$whereClauseStatement}", $bindings];
    }

    /**
     * Extracs select critera from the $selectCriteria array, and turns them into SQL bound options.
     *
     * @param $selectCriteria
     * @return array
     */
    protected function processSelectCriteria($selectCriteria)
    {
        $bindings = [];
        $whereClauseSegments = [];
        array_walk(
            $selectCriteria,
            function ($v, $k) use (&$bindings, &$whereClauseSegments) {
                if (is_array($v)) {
                    $inBindings = [];
                    array_walk(
                        $v,
                        function ($vv) use (&$inBindings, $k) {
                            static $placeHolderIndex = 0;
                            $inBindings["_{$placeHolderIndex}{$k}"] = $vv;
                            $placeHolderIndex++;
                        }
                    );
                    $whereClauseSegments[] = "\"$k\" IN (:" . implode(', :', array_keys($inBindings)) . ")";
                    $bindings += $inBindings;
                } else {
                    if ($k == self::START_DATE_ATTRIBUTE) {
                        $bindings[$k] = $this->getStartDate($v)->format(\DateTime::ISO8601);
                        $whereClauseSegments[] = "\"created\" >= :{$k}";
                    } elseif ($k == self::END_DATE_ATTRIBUTE) {
                        $bindings[$k] = $this->getEndDate($v)->format(\DateTime::ISO8601);
                        $whereClauseSegments[] = "\"created\" <= :{$k}";
                    } else {
                        $bindings[$k] = $v;
                        $whereClauseSegments[] = "\"$k\" = :{$k}";
                    }
                }
            }
        );

        return [$whereClauseSegments, $bindings];
    }

    /**
     * Provide validation for select criteria coming from the client; currently only supports startDate and endDate.
     * startDate & endDate are expected to be epoch (? or perhaps ISO-8601)
     *
     * @param array $selectCriteria
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    protected function validateSelectCriteria($selectCriteria)
    {
        // If we didn't get any criteria, or we didn't get any date criteria, we'll consider it validated for now
        if ((is_array($selectCriteria) && count($selectCriteria) == 0)
            || (!isset($selectCriteria[self::START_DATE_ATTRIBUTE]) && !isset($selectCriteria[self::END_DATE_ATTRIBUTE]))
        ) {
            return true;
        }

        // We _could_ treat a solo startDate as a single-day option here.
        if (!ArrayUtil::checkBothExist($selectCriteria, self::START_DATE_ATTRIBUTE, self::END_DATE_ATTRIBUTE)) {
            throw new \InvalidArgumentException("Both startDate and endDate need to be supplied.");
        }

        // Will throw an \InvalidParameterException if anything goes wrong.
        list($startDate, $endDate) = $this->checkDateValidity($selectCriteria[self::START_DATE_ATTRIBUTE],
            $selectCriteria[self::END_DATE_ATTRIBUTE]);

        $this->checkDateBounds($startDate, $endDate);

        return true;
    }

    /**
     * @param string $startDate
     * @return \DateTime
     *
     * @throws \InvalidArgumentException
     */
    protected function getStartDate($startDate)
    {
        try {
            $startDateTime = new \DateTime($startDate);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("startDate '{$startDate}' was unable to be processed.");
        }
        return $startDateTime;
    }

    /**
     * Returns the end date ensuring end-of-day time.
     *
     * @param string $endDate
     * @return \DateTime
     *
     * @throws \InvalidArgumentException
     */
    protected function getEndDate($endDate)
    {
        try {
            $endDateTime = new \DateTime($endDate);
            // Make $endDate 23:59:59 of specified date for inclusive exports
            $endDateTime->modify('tomorrow')->modify('1 second ago');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("endDate '{$endDate}' was unable to be processed.");
        }

        return $endDateTime;
    }

    /**
     * Ensure startDate and endDate parse.
     *
     * @param $startDateParam
     * @param $endDateParam
     * @return \DateTime[]
     */
    protected function checkDateValidity($startDateParam, $endDateParam)
    {
        try {
            $startDate = $this->getStartDate($startDateParam);
            $endDate = $this->getEndDate($endDateParam);
            if (!DateTimeUtil::dateTimeIsValid($startDate) || !DateTimeUtil::dateTimeIsValid($endDate)) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("A date failed to parse: startDate = '{$startDateParam}', endDate = '{$endDateParam}'");
        }

        // Store the Time Zone Offset for formatting the results later
        $this->timeZoneOffset = $startDate->getTimezone()->getName();

        return [$startDate, $endDate];
    }

    /**
     * Ensure our scenario is startDate < endDate and startDate is not in the future.
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return bool
     */
    protected function checkDateBounds($startDate, $endDate)
    {

        // Check if the start date is in the future.
        $todayDiff = $startDate->diff(new \DateTime());
        if ($todayDiff->invert == 1) {
            $today = (new \DateTime())->format(\DateTime::ISO8601);
            throw new \InvalidArgumentException("Start date in the future ({$startDate->format(\DateTime::ISO8601)}), today is ({$today})");
        }

        // Create a diff of start and end dates for ensuring startDate comes before or is equal to endDate
        $diff = $startDate->diff($endDate);

        if ($diff->invert == 1) {                    // startDate > endDate, no resolution
            throw new \InvalidArgumentException("Date order problem: startDate({$startDate->format(\DateTime::ISO8601)}) > endDate({$startDate->format(\DateTime::ISO8601)})");
        }

        return true;
    }
}
