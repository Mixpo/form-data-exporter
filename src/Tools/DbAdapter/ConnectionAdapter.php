<?php

namespace Mixpo\Igniter\Tools\DbAdapter;


interface ConnectionAdapter {
    function prepare($statement, array $driverOptions = array());

}