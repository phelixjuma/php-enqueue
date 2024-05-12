<?php

declare(strict_types=1);

namespace Phelixjuma\Enqueue\QuartzCronExpression;

use DateTimeInterface;

/**
 * Years field.  Allows: * , / -.
 */
class YearsField extends AbstractField
{
    /**
     * {@inheritdoc}
     */
    protected $rangeStart = 1970;

    /**
     * {@inheritdoc}
     */
    protected $rangeEnd = 2099;

    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(DateTimeInterface $date, $value, bool $invert): bool
    {
        if ($value === '?') {
            return true;
        }

        return $this->isSatisfied((int)$date->format('Y'), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function increment(DateTimeInterface &$date, $invert = false, $parts = null): FieldInterface
    {
        if (! $invert) {
            $date = $date->modify('first day of next year');
            $date = $date->setTime(0, 0);
        } else {
            $date = $date->modify('last day of previous year');
            $date = $date->setTime(23, 59);
        }

        return $this;
    }
}
