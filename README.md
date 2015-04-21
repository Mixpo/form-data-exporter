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

$exporter = new Exporter(
    'dsn', // <- PDO DSN string including credentials
    'tableName', // <- the table the you wish to export data from
    'data', // <- the name of the 'data' column (column that hold the JSON form data)
    '/var/data/csv/leadgen99.csv',  // <- full path to the output CSV
    ["client_id" => "12345", "campaign" => "widget-2015"] // <- criteria used to build an AND'ed WHERE clause
);
$outputCsvFilePath = $exporter->run();

```

**NOTE** In the example above the $outputCsvFilePath will have a random seed in it.  For instance with 
`/var/data/csv/leadgen99.csv` sent into the constructor, `$outputCsvFilePath` will be something like: `/var/data/csv/leadgen99-55358afdeefa5.csv`

To turn off this behavior call `$exporter->setRandomizeOutputFilename(false)` prior to calling `$exporter->run()`


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
 *         "row" => ["id" => 2, "identifier" => "client-xyz", "tag" => "widget-1-campaign", 
 *                   "version" => 1, "created" => "2015-04-16 21:50:39"]
 *       ],
 *       ...
 *      ]
 **/

```
