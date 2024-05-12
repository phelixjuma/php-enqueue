<?php

declare(strict_types=1);

namespace Phelixjuma\Enqueue\QuartzCronExpression;

use InvalidArgumentException;

/**
 * CRON field factory implementing a flyweight factory.
 */
class FieldFactory implements FieldFactoryInterface
{
    /**
     * @var array Cache of instantiated fields
     */
    private $fields = [];

    /**
     * Get an instance of a field object for a cron expression position.
     *
     * @param int $position CRON expression position value to retrieve
     *
     * @throws InvalidArgumentException if a position is not valid
     */
    public function getField(int $position): FieldInterface
    {
        return $this->fields[$position] ?? $this->fields[$position] = $this->instantiateField($position);
    }

    private function instantiateField(int $position): FieldInterface
    {
        switch ($position) {
            case CronExpression::SECOND:
                return new SecondsField();
            case CronExpression::MINUTE:
                return new MinutesField();
            case CronExpression::HOUR:
                return new HoursField();
            case CronExpression::DAY:
                return new DayOfMonthField();
            case CronExpression::MONTH:
                return new MonthField();
            case CronExpression::WEEKDAY:
                return new DayOfWeekField();
            case CronExpression::YEAR:
                return new YearsField();
            default:
                throw new InvalidArgumentException(
                    "Position {$position} is not a valid position"
                );
        }
    }
}
