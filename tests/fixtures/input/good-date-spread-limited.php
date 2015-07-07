<?php
$testInput = [
    [
        'id' => 1,
        'identifier' => 'client-xyz',
        'tag' => 'widget-1-campaign',
        'version' => 1,
        'created' => '2015-04-01 00:00:01',
        'data' => '{"firstName": "Ned", "lastName": "Flanders", "age": "47", "comment": "Amen" }'
    ],
    [
        'id' => 2,
        'identifier' => 'client-xyz',
        'tag' => 'widget-1-campaign',
        'version' => 1,
        'created' => '2015-04-01 23:59:59',
        'data' => '{"firstName":"Bob","lastName":"Terwilliger","age":"42","comment":"booyah!"}'
    ],
];

return $testInput;