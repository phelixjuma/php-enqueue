<?php

declare(strict_types=1);

namespace Phelixjuma\Enqueue\QuartzCronExpression;

use DateTimeInterface;

/**
 * Minutes field.  Allows: * , / -.
 */
class MinutesField extends AbstractField
{
    /**
     * {@inheritdoc}
     */
    protected $rangeStart = 0;

    /**
     * {@inheritdoc}
     */
    protected $rangeEnd = 59;

    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(DateTimeInterface $date, $value, bool $invert):bool
    {
        if ($value === '?') {
            return true;
        }

        return $this->isSatisfied((int)$date->format('i'), $value);
    }

    public function increment(DateTimeInterface &$date, $invert = false, $parts = null): FieldInterface
    {
        if (is_null($parts)) {
            $date = $this->timezoneSafeModify($date, ($invert ? "-" : "+") . "1 minute");
            return $this;
        }

        $current_minute = (int) $date->format('i');
        $current_second = (int) $date->format('s');

        $parts = false !== strpos($parts, ',') ? explode(',', $parts) : [$parts];
        sort($parts);
        $minutes = [];
        foreach ($parts as $part) {
            $minutes = array_merge($minutes, $this->getRangeForExpression($part, 59));
        }

        $position = $invert ? count($minutes) - 1 : 0;
        if (count($minutes) > 1) {
            for ($i = 0; $i < count($minutes) - 1; ++$i) {
                if ((!$invert && $current_minute >= $minutes[$i] && $current_minute < $minutes[$i + 1]) ||
                    ($invert && $current_minute > $minutes[$i] && $current_minute <= $minutes[$i + 1])) {
                    $position = $invert ? $i : $i + 1;
                    break;
                }
            }
        }

        $target = (int) $minutes[$position];
        $originalMinute = (int) $date->format("i");

        if (!$invert) {
            if ($originalMinute > $target || ($originalMinute == $target && $current_second != 0)) {
                // Move to the next hour if the target minute has passed within the current hour
                $date = $date->modify('+1 hour')->setTime((int) $date->format('H'), $target, 0);
            } else {
                // Set the minute directly if within the current hour and seconds are 0
                $date = $date->setTime((int) $date->format('H'), $target, 0);
            }
        } else {
            if ($originalMinute < $target) {
                // Move to the previous hour if the target minute is before the current time
                $date = $date->modify('-1 hour')->setTime((int) $date->format('H'), $target, 59);
            } else {
                // Set the minute directly if within the current hour
                $date = $date->setTime((int) $date->format('H'), $target, 59);
            }
        }

        return $this;
    }

}
