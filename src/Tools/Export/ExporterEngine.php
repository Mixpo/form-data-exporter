<?php

namespace Mixpo\Igniter\Tools\Export;


use Psr\Log\LoggerInterface;

abstract class ExporterEngine
{

    const EXPORT_TYPE_FILE = 'file';
    const EXPORT_TYPE_S3 = 's3';

    public static $supportedExportTargets = [ExporterEngine::EXPORT_TYPE_FILE, ExporterEngine::EXPORT_TYPE_S3];

    /**
     * @var string
     */
    protected $exportPathWithProtocol;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var boolean
     */
    protected $randomizeOutputFileName;


    /**
     * ExporterEngine constructor.
     * @param string $exportPath Examples: 's3://mybucket/my/object.txt' or 'file:///var/tmp'
     * @param LoggerInterface $logger
     * @param bool $randomizeOutputFileName
     */
    public function __construct($exportPath, LoggerInterface $logger, $randomizeOutputFileName = true)
    {
        $this->exportPathWithProtocol = $exportPath;
        $this->logger = $logger;
        $this->randomizeOutputFileName = $randomizeOutputFileName;
    }

    /**
     * @param array $headerRow
     * @param array $resultsArray
     * @return string Path to file/bucket that was written
     */
    public abstract function writeCsv(array $headerRow, array $resultsArray);

    /**
     * Allow preemptive check for writable before we do the heavy lift of the Export
     *
     * @return void
     */
    public abstract function verifyDestinationIsWritable();

    public static function targetIsS3($targetPath)
    {
        return self::getPathProtocol($targetPath) == ExporterEngine::EXPORT_TYPE_S3;
    }

    public static function getPathProtocol($path)
    {
        list($protocol, $path) = self::parseTargetPath($path);

        return $protocol;
    }

    public static function getNakedPath($path)
    {
        list($protocol, $path) = self::parseTargetPath($path);

        return $path;
    }

    protected static function parseTargetPath($exportPath)
    {
        if (!preg_match('%(?P<protocol>\w+)://(?P<path>.+)$%', $exportPath, $match)) {
            throw new \RuntimeException(
                "Unsupported export path format: '{$exportPath}'"
                . " Should be in form '<protocol>://<path>', ex: 'file:///var/tmp'"
            );
        }
        $exportTargetType = strtolower($match['protocol']);
        if (!in_array($exportTargetType, self::$supportedExportTargets)) {
            throw new \RuntimeException(
                "Unsupported export target type: '{$exportTargetType}'"
                . " Supported target types are " . implode(', ', self::$supportedExportTargets)
            );
        }

        return [$exportTargetType, $match['path']];
    }

    /**
     * @param string $targetFilePath
     * @return string
     */
    protected function getOutputFilePath($targetFilePath)
    {
        if ($this->randomizeOutputFileName) {
            $pathParts = pathinfo($targetFilePath);
            $random = uniqid();
            $targetFilePath = "{$pathParts['dirname']}/{$pathParts['filename']}-{$random}.{$pathParts['extension']}";
        }

        return $targetFilePath;
    }

    /**
     * @param string $exportPath
     * @param array $headerRow
     * @param array $resultsArray
     * @throws \Exception
     */
    protected function writeToDestination($exportPath, $headerRow, $resultsArray)
    {
        $logger = $this->logger;
        set_error_handler(
            function ($errorNumber, $errorMessage, $errorFile, $errorLine) use ($logger, $exportPath) {
                $logger->error(
                    "Error Saving file to path:{$exportPath}.  Error Message: {$errorMessage}.  "
                    . "Occurred at {$errorFile}::{$errorLine}"
                );
                throw new \RuntimeException("Error occurred while saving CSV file");
            }
        );
        try {
            $fp = fopen($exportPath, 'w');
            fputcsv($fp, $headerRow);
            foreach ($resultsArray as $fields) {
                fputcsv($fp, $fields);
            }
            fclose($fp);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        } finally {
            // ensure we restore the error handler
            restore_error_handler();
        }
    }
}