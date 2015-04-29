<?php

namespace Mixpo\Igniter\Test\Tools\DbAdapter;


use Mixpo\Igniter\Tools\DbAdapter\ConnectionAdapter;

class MockConnectionAdapter implements ConnectionAdapter
{

    function prepare($statement, array $driverOptions = array())
    {
        // TODO: Implement prepare() method.
    }
}