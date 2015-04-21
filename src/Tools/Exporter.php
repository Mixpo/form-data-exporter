<?php
namespace Mixpo\Igniter\Tools;

class Exporter
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
     * @var string
     */
    protected $outputPath;

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
     * @param string $dsnString
     * @param string $tableName
     * @param string $dataFieldName
     * @param string $outputPath
     * @param array $selectCriteria
     */
    function __construct(
        $dsnString,
        $tableName,
        $dataFieldName,
        $outputPath,
        $selectCriteria = []
    ) {
        $this->dsnString = $dsnString;
        $this->tableName = $tableName;
        $this->dataFieldName = $dataFieldName;
        $this->outputPath = $outputPath;
        $this->selectCriteria = $selectCriteria;
    }

    /**
     * @param \PDO $pdo Optional injector for the PDO connection.  If not set prior to calling init(), a PDO connection
     *                  will be built using the constructor arguments.
     */
    public function setPdo(\PDO $pdo)
    {
        $this->pdoConnection = $pdo;
    }

    /**
     * @return string
     * @throws InvalidInputException
     */
    public function run()
    {
        if (!$this->pdoConnection) {
            $this->pdoConnection = new \PDO($this->dsnString);
        }
        $this->verifyDestinationIsWritable();

        list($query, $bindings) = $this->constructSelectQuery();
        $results = $this->executeQuery($query, $bindings);

        return $this->exportResultToFile($results, $this->outputPath);
    }

    /**
     * @return array Returns an array of any rows that had parse issues and will not be in the CSV output.
     */
    public function getIssues()
    {
        return $this->issues;
    }

    /**
     * @param boolean $randomizeOutputFilename
     */
    public function setRandomizeOutputFilename($randomizeOutputFilename)
    {
        $this->randomizeOutputFilename = $randomizeOutputFilename;
    }

    /**
     * @param $query
     * @param $bindings
     * @return array
     */
    protected function executeQuery($query, $bindings)
    {
        $sth = $this->pdoConnection->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $sth->execute($bindings);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array $queryResult
     * @param $targetFilePath
     * @return string
     * @throws InvalidInputException
     */
    protected function exportResultToFile(array $queryResult, $targetFilePath)
    {
        if (!$queryResult) {
            throw new InvalidInputException("Array of results to export to CSV was Empty");
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
        $outputFilePath = $this->getOutputFilePath($targetFilePath);
        $this->writeToCsv(array_keys($this->canonicalColumnNamesList), $transformedResult, $outputFilePath);

        return $outputFilePath;
    }

    /**
     * @param array $headerRow
     * @param array $resultsArray
     * @param string $targetFilePath
     */
    protected function writeToCsv(array $headerRow, array $resultsArray, $targetFilePath)
    {
        $fp = fopen($targetFilePath, 'w');
        fputcsv($fp, $headerRow);
        foreach ($resultsArray as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);
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
        $bindings = [];
        $whereClauseSegments = [];
        $whereClauseStatement = '';
        $selectStatement = "SELECT * FROM \"{$this->tableName}\"";
        if ($this->selectCriteria) {
            array_walk(
                $this->selectCriteria,
                function ($v, $k) use (&$bindings, &$whereClauseSegments) {
                    $bindings[":{$k}"] = $v;
                    $whereClauseSegments[] = "\"$k\" = :{$k}";
                }
            );
            $whereClauseStatement = ' WHERE ' . implode(' AND ', $whereClauseSegments);
        }

        return ["{$selectStatement}{$whereClauseStatement}", $bindings];
    }

    protected function verifyDestinationIsWritable()
    {
        if (!is_writable(pathinfo($this->outputPath, PATHINFO_DIRNAME))) {
            throw new \RuntimeException("Target directory for output '{$this->outputPath}', not found or not writable");
        }
    }

    /**
     * @param string $targetFilePath
     * @return string
     */
    protected function getOutputFilePath($targetFilePath)
    {
        if ($this->randomizeOutputFilename) {
            $pathParts = pathinfo($targetFilePath);
            $random = uniqid();
            $targetFilePath = "{$pathParts['dirname']}/{$pathParts['filename']}-{$random}.{$pathParts['extension']}";
        }

        return $targetFilePath;
    }
}