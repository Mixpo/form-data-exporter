<?php

namespace Mixpo\Igniter\Tools\DbAdapter;

class PdoConnectionAdapter implements ConnectionAdapter{

    /**
     * @var \PDO
     */
    protected $pdo;

    function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    function prepare($statement, array $driverOptions = array())
    {
        return $this->pdo->prepare($statement, $driverOptions);
    }
}