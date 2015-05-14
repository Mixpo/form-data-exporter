<?php

namespace Mixpo\Igniter\Test;

class TestHelper
{

    public static function getFixtureInput($fixtureFile)
    {
        return self::getTestArtifact("fixtures/input/{$fixtureFile}");
    }

    public static function getFixtureOutput($fixtureFile)
    {
        return self::getTestArtifact("fixtures/output/{$fixtureFile}");
    }

    public static function getTmpFile($fixtureFile)
    {
        return self::getTestArtifact("tmp/{$fixtureFile}");
    }

    public static function invokeNonPublicMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public static function getFileSystemTmpPath($appendedPath = '')
    {
        if ($appendedPath && $appendedPath[0] !== '/') {
            $appendedPath = "/{$appendedPath}";
        }

        return 'file://' . __DIR__ . "/tmp{$appendedPath}";
    }

    protected static function getTestArtifact($relativeFixturePath)
    {
        $fixturePath = __DIR__ . "/{$relativeFixturePath}";
        if (!file_exists($fixturePath)) {
            throw new \RuntimeException("Test fixture file not found at: '{$fixturePath}''.");
        }
        $fileExt = pathinfo($fixturePath, PATHINFO_EXTENSION);
        if ($fileExt == 'php') {
            return include($fixturePath);
        }

        return file_get_contents($fixturePath);
    }
}