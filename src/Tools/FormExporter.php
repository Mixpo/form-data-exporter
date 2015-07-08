<?php
namespace Mixpo\Igniter\Tools;

use Mixpo\Igniter\Tools\DbAdapter\ConnectionAdapter;
use Mixpo\Igniter\Tools\Export\ExporterEngine;
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

    /**
     * @var string
     */
    protected $startDateParam = 'startDate';

    /**
     * @var string
     */
    protected $endDateParam   = 'endDate';

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
        $data = json_decode($dataRow[$this->dataFieldName], true);
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
                    $bindings[$k] = $v;
                    //Potential hack here, adding date checks would make this un-reusable but have stronger typing.
                    if ($k == $this->startDateParam) {
                        $whereClauseSegments[] = "\"created\" >= :{$k}";
                    } elseif ($k == $this->endDateParam) {
                        $whereClauseSegments[] = "\"created\" <= :{$k}";
                    } else {
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
     * @todo: Break it down, funky
     * @param array $selectCriteria
     * @return bool
     * @throws \InvalidArgumentException
     */
    protected function validateSelectCriteria($selectCriteria)
    {
        // The idea here is to validate the in-bound dates, on a three-point system
        // one: check start date for date validity
        // two: check end date for date validity
        // three: check that start date <= end date (if they are equal, we'll go 00:00:00 - 23:59:59)
        // If any of these fail, throw an InvalidArgumentException("usable data");

        // If we didn't get any criteria, we'll consider it validated for now.
        if (is_array($selectCriteria) && count($selectCriteria) == 0) {
            return true;
        }

        // We _could_ treat a solo startDate as a single-day option here.
        if (isset($selectCriteria[$this->startDateParam]) && !isset($selectCriteria[$this->endDateParam])) {
            throw new \InvalidArgumentException("startDate exists without an endDate.");
        }

        if (!isset($selectCriteria[$this->startDateParam]) && isset($selectCriteria[$this->endDateParam])) {
            throw new \InvalidArgumentException("endDate exists without a startDate.");
        }

        if (isset($selectCriteria[$this->startDateParam]) && isset($selectCriteria[$this->endDateParam])) {
            try {
                $startDate = $this->getStartDate($selectCriteria[$this->startDateParam]);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("startDate failed to parse with data '{$selectCriteria[$this->startDateParam]}', original message: {$e->getMessage()}");
            }
            try {
                $endDate = $this->getEndDate($selectCriteria[$this->endDateParam]);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("endDate failed to parse with data '{$selectCriteria[$this->endDateParam]}', original message: {$e->getMessage()}");
            }
        }

        // Now for the actual data validation.
        // Ignore this for now
//        $x = \Igniter\Common\DateTimeUtil::dateTimeIsValid($startDate);
//        $x;

        // Check if the start date is in the future.
        $todayDiff = $startDate->diff(new \DateTime());
        if ($todayDiff->days > 0 && $todayDiff->invert == 1) {
            $today = (new \DateTime())->format(\DateTime::ISO8601);
            throw new \InvalidArgumentException("Start date in the future ({$startDate->format(\DateTime::ISO8601)}), today is ({$today})");
        }

        // Create a diff of start and end dates for comparison
        $diff = $startDate->diff($endDate);

        if ($diff->days > 0 and $diff->invert == 0) {                       // startDate < endDate, our typical scenario
            return true;
        } else {
            if ($diff->days > 0 && $diff->invert == 1) {                    // startDate > endDate, no resolution
                throw new \InvalidArgumentException("Date order problem: startDate({$selectCriteria[$this->startDateParam]}) > endDate({$selectCriteria[$this->startDateParam]})");
            } else {
                if ($diff->days == 0) {                                     // startDate = endDate, one day scenario
                    return true;
                }
            }
        }
        return true;
    }

    /**
     * @param string $startDate
     * @return \DateTime
     */
    protected function getStartDate($startDate)
    {
        return new \DateTime($startDate);
    }

    /**
     * Returns the end date ensuring end-of-day time.
     *
     * @param string $endDate
     * @return \DateTime
     */
    protected function getEndDate($endDate)
    {
        $endDateTime = new \DateTime($endDate);
        // Make $endDate 23:59:59 of specified date for inclusive exports
        $endDateTime->add(new \DateInterval('PT' . 86399 . 'S'));
        return $endDateTime;
    }
}
