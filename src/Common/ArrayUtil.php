<?php
namespace Mixpo\Igniter\Common;

class ArrayUtil
{
    /**
     * Ensure both array elements exist.
     *
     * @param array $array
     * @param string $first
     * @param string $second
     * @return bool
     */
    public static function checkBothExist($array, $first, $second)
    {
        if (!isset($array[$first]) || !isset($array[$second])) {
            return false;
        }
        return true;
    }
}
