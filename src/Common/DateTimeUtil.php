<?php
namespace Mixpo\Igniter\Common;

class DateTimeUtil
{
    /**
     * Overcoming a PHP \DateTime poor design choice.
     *
     * @param \DateTime $dateTime
     * @return bool
     */
    public static function dateTimeIsValid(\DateTime $dateTime)
    {
        try {
            \DateTime::createFromFormat(\DateTime::ISO8601, $dateTime->format(\DateTime::ISO8601));
        } catch(\Exception $e) {
            return false;
        }
        return \DateTime::getLastErrors()['warning_count'] == 0 && \DateTime::getLastErrors()['error_count'] == 0;
    }
}
