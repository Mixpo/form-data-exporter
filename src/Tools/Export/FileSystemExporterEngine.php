<?php

namespace Mixpo\Igniter\Tools\Export;

use Psr\Log\LoggerInterface;

class FileSystemExporterEngine extends ExporterEngine
{


    /**
     * FileSystemExporter constructor.
     * @param LoggerInterface $logger
     */
    public function __construct($exportPath, LoggerInterface $logger, $randomizeOutputFileName = true)
    {
        parent::__construct($exportPath, $logger, $randomizeOutputFileName);
    }

    /**
     * @param array $headerRow
     * @param array $resultsArray
     * @return string
     */
    public function writeCsv(array $headerRow, array $resultsArray)
    {
        $exportFilePath = self::getNakedPath($this->getOutputFilePath($this->exportPathWithProtocol));
        $this->writeToDestination($exportFilePath, $headerRow, $resultsArray);

        return $exportFilePath;
    }

    /**
     * Allow preemptive check for writable before we do the heavy lift of the Export
     */
    public function verifyDestinationIsWritable()
    {
        if (!is_writable(pathinfo($this->exportPathWithProtocol, PATHINFO_DIRNAME))) {
            $this->logger->error(
                "Target directory to write the leadgen export: '{$this->exportPathWithProtocol}', not found or not writable"
            );
            throw new \RuntimeException("There was a problem in preparing the Export file");
        }
    }
}