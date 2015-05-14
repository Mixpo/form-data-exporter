<?php

namespace Mixpo\Igniter\Tools\Export;


use Aws\S3\S3Client;
use Psr\Log\LoggerInterface;

class S3ExporterEngine extends ExporterEngine
{

    /**
     * @var S3Client
     */
    protected $s3Client;

    /**
     * S3Exporter constructor.
     * @param string $s3ObjectPath Ex: 's3://mybucket/my/object.txt'
     * @param S3Client $s3Client
     * @param LoggerInterface $logger
     * @param bool $randomizeOutputFileName
     */
    public function __construct(
        $s3ObjectPath,
        S3Client $s3Client,
        LoggerInterface $logger,
        $randomizeOutputFileName = true
    ) {
        $this->s3Client = $s3Client;
        parent::__construct($s3ObjectPath, $logger, $randomizeOutputFileName);
    }

    function writeCsv(array $headerRow, array $resultsArray)
    {
        $this->s3Client->registerStreamWrapper();
        $exportBucketPath = $this->getOutputFilePath($this->exportPathWithProtocol);
        $this->writeToDestination($exportBucketPath, $headerRow, $resultsArray);

        return $exportBucketPath;
    }

    /**
     * Allow preemptive check for writable before we do the heavy lift of the Export
     *
     * @return void
     */
    public function verifyDestinationIsWritable()
    {
        /*
         * Preemptive S3 writable check is too expensive
         */
    }
}