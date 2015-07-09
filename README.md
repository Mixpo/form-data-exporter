# Form Data Exporter

A somewhat specific tool meant to export to CSV, a JSON *data* column for all rows in a SQL result set.

## Status

[![Build Status](https://travis-ci.org/Mixpo/form-data-exporter.svg?branch=master)](https://travis-ci.org/Mixpo/form-data-exporter)

[![Coverage Status](https://coveralls.io/repos/Mixpo/form-data-exporter/badge.svg)](https://coveralls.io/r/Mixpo/form-data-exporter)

## Install

```
composer install

phpunit
```

## Usage

```php
<?php

$exporter = new FormExporter(
    'dsn', // <- PDO DSN string including credentials
    'tableName', // <- the table the you wish to export data from
    'data', // <- the name of the 'data' column (column that hold the JSON form data)
    ["client_id" => "12345", "campaign" => "widget-2015"], // <- criteria used to build an AND'ed WHERE clause
    $logger // <- Psr\Log\LoggerInterface
);

```

You then set the writer engine (file system and S3 are currently supported and run the export

```php

$exporter->setExporterEngine(
    new FileSystemExporterEngine(
       'file:///var/data/csv/leadgen99.csv',  // <- full path to the output CSV in <protocol>://<path> format
                                                    s3://<bucket>/<object-path> is also supported
        $logger, // <- Psr\Log\LoggerInterface
        $randomizeFileName = true
    )
);
                                              
$outputCsvFilePath = $exporter->run();

```

**NOTE** In the example above the $outputCsvFilePath will have a random seed in it.  For instance with 
`file:///var/data/csv/leadgen99.csv` sent into the `ExporterEngine` constructor, `$outputCsvFilePath` will be something like: `/var/data/csv/leadgen99-55358afdeefa5.csv`

To turn off this behavior call set `$randomizeFileName = false`


### Rows with Issues

After calling `$exporter->run()`, you can call `getIssues()` which will return an array of any rows that had parse 
issues and will not be in the CSV output.

```php
<?php

$exporter->run();
$issues = $exporter->getIssues();

/**
 * $issues will be an empty array if there were no issues, but if there were it will look something like this.
 *      [
 *       [
 *         "message" => "Expected data field of name: 'form_data', not found for this row",
 *         "row" => ["id" => 2, 
 *                   "identifier" => "client-xyz",
 *                   "tag" => "widget-1-campaign",
 *                   "version" => 1,
 *                   "created" => "2015-04-16 21:50:39"]
 *       ],
 *       ...
 *      ]
 **/

```
