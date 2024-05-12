<?php

declare(strict_types=1);

namespace Phelixjuma\Enqueue\QuartzCronExpression;

use DateTimeInterface;

/**
 * Seconds field.  Allows: * , / -.
 */
class SecondsField extends AbstractField
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
    public function isSatisfiedBy(DateTimeInterface $date, $value, bool $invert): bool
    {
        if ($value === '?') {
            return true;
        }

        return $this->isSatisfied((int)$date->format('s'), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function increment(DateTimeInterface &$date, $invert = false, $parts = null): FieldInterface
    {
        if (is_null($parts)) {
            $date = $this->timezoneSafeModify($date, ($invert ? "-" : "+") . "1 second");
            return $this;
        }

        $current_second = (int)$date->format('s');

        $parts = false !== strpos($parts, ',') ? explode(',', $parts) : [$parts];
        sort($parts);
        $seconds = [];
        foreach ($parts as $part) {
            $seconds = array_merge($seconds, $this->getRangeForExpression($part, 59));
        }

        $position = $invert ? \count($seconds) - 1 : 0;
        if (\count($seconds) > 1) {
            for ($i = 0; $i < \count($seconds) - 1; ++$i) {
                if ((!$invert && $current_second >= $seconds[$i] && $current_second < $seconds[$i + 1]) ||
                    ($invert && $current_second > $seconds[$i] && $current_second <= $seconds[$i + 1])) {
                    $position = $invert ? $i : $i + 1;
                    break;
                }
            }
        }

        $target = (int)$seconds[$position];
        $originalSecond = (int)$date->format("s");

        if (!$invert) {
            if ($originalSecond >= $target) {
                $distance = 60 - $originalSecond;
                $date = $this->timezoneSafeModify($date, "+{$distance} seconds");
                $originalSecond = (int)$date->format("s");
            }

            $distance = $target - $originalSecond;
            $date = $this->timezoneSafeModify($date, "+{$distance} seconds");
        } else {
            if ($originalSecond <= $target) {
                $distance = ($originalSecond + 1);
                $date = $this->timezoneSafeModify($date, "-" . $distance . " seconds");
                $originalSecond = (int)$date->format("s");
            }

            $distance = $originalSecond - $target;
            $date = $this->timezoneSafeModify($date, "-{$distance} seconds");
        }

        return $this;
    }
}
