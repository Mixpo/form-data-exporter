<?php
namespace Mixpo\Igniter\Tools;

use Doctrine\Instantiator\Exception\InvalidArgumentException;
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
                    $bindings["$k"] = $v;
                    $whereClauseSegments[] = "\"$k\" = :{$k}";
                }
            }
        );

        return [$whereClauseSegments, $bindings];
    }

    /**
     * Provide validation for select criteria coming from the client; currently only supports start_date and end_date.
     * start_date & end_date are expected to be epoch (? or perhaps ISO-8601)
     *
     * @param $selectCriteria
     */
    protected function validateSelectCriteria($selectCriteria)
    {
        // The idea here is to validate the in-bound dates, on a three-point system
        // one: check start date for date validity
        // two: check end date for date validity
        // three: check that start date <= end date (if they are equal, we'll go 00:00:00 - 23:59:59)
        // If any of these fail, throw an InvalidArgumentException("usable data");

        if (isset($selectCriteria['start_date']) && isset($selectCriteria['end_date'])) {
            try {
                new \DateTime($selectCriteria['start_date']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("start_date failed to parse with data '{$selectCriteria['start_date']}', original message: {$e->getMessage()}");
            }
            try {
                new \DateTime($selectCriteria['end_date']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("end_date failed to parse with data '{$selectCriteria['end_date']}', original message: {$e->getMessage()}");
            }
        }

        // We _could_ treat a solo start_date as a single-day option here.
        if (isset($selectCriteria['start_date']) && !isset($selectCriteria['end_date'])) {
            throw new \InvalidArgumentException("start_date exists without an end_date.");
        }

        if (!isset($selectCriteria['start_date']) && isset($selectCriteria['end_date'])) {
            throw new \InvalidArgumentException("end_date exists without a start_date.");
        }

    }
}
