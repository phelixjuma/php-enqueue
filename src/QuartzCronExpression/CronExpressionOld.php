<?php

declare(strict_types=1);

namespace Phelixjuma\Enqueue\QuartzCronExpression;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Webmozart\Assert\Assert;

/**
 * CRON expression parser that can determine whether or not a CRON expression is
 * due to run, the next run date and previous run date of a CRON expression.
 */
class CronExpressionOld
{
    public const SECOND = 0;
    public const MINUTE = 1;
    public const HOUR = 2;
    public const DAY = 3;
    public const MONTH = 4;
    public const WEEKDAY = 5;
    public const YEAR = 6;

    public const MAPPINGS = [
        '@yearly' => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly' => '0 0 1 * *',
        '@weekly' => '0 0 * * 0',
        '@daily' => '0 0 * * *',
        '@midnight' => '0 0 * * *',
        '@hourly' => '0 * * * *',
    ];

    /**
     * @var array CRON expression parts
     */
    protected $cronParts;

    /**
     * @var FieldFactoryInterface CRON field factory
     */
    protected $fieldFactory;

    /**
     * @var int Max iteration count when searching for next run date
     */
    protected $maxIterationCount = 1000;

    /**
     * @var array Order in which to test of cron parts
     */
    protected static $order = [
        self::YEAR,
        self::MONTH,
        self::DAY,
        self::WEEKDAY,
        self::HOUR,
        self::MINUTE,
        self::SECOND,
    ];

    /**
     * @var array<string, string>
     */
    private static $registeredAliases = self::MAPPINGS;

    /**
     * Parse a CRON expression.
     *
     * @param string $expression CRON expression (e.g. '8 * * * *')
     * @param null|FieldFactoryInterface $fieldFactory Factory to create cron fields
     * @throws InvalidArgumentException
     */
    public function __construct(string $expression, ?FieldFactoryInterface $fieldFactory = null)
    {
        $shortcut = strtolower($expression);
        $expression = self::$registeredAliases[$shortcut] ?? $expression;

        $this->fieldFactory = $fieldFactory ?: new FieldFactory();
        $this->setExpression($expression);
    }

    /**
     * Set or change the CRON expression.
     *
     * @param string $value CRON expression
     * @throws InvalidArgumentException if not a valid CRON expression
     * @return CronExpression
     */
    public function setExpression(string $value): CronExpression
    {
        $split = preg_split('/\s/', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (count($split) != 7) {
            throw new InvalidArgumentException("{$value} is not a valid CRON expression. Must have exactly 7 parts.");
        }

        $this->cronParts = $split;
        foreach ($this->cronParts as $position => $part) {
            if (!$this->fieldFactory->getField($position)->validate($part)) {
                throw new InvalidArgumentException("Invalid value for cron part at position {$position}: {$part}");
            }
        }

        return $this;
    }

    /**
     * @param $currentTime
     * @param int $nth
     * @param bool $invert
     * @param bool $allowCurrentDate
     * @param $timeZone
     * @return DateTime
     * @throws Exception
     */
    protected function getRunDate($currentTime = null, int $nth = 0, bool $invert = false, bool $allowCurrentDate = false, $timeZone = null): DateTime
    {
        $timeZone = $this->determineTimeZone($currentTime, $timeZone);

        if ($currentTime instanceof DateTimeInterface) {
            $currentDate = clone $currentTime;
        } else {
            $currentDate = new DateTime($currentTime ?? 'now', new DateTimeZone($timeZone));
        }

        $nextRun = clone $currentDate;
        $nextRun->setTime((int)$nextRun->format('H'), (int)$nextRun->format('i'), (int)$nextRun->format('s'));

        $parts = [];
        $fields = [];
        foreach (static::$order as $position) {
            $part = $this->getExpression($position);
            if ($part === null || $part === '*') {
                continue;
            }
            $parts[$position] = $part;
            $fields[$position] = $this->fieldFactory->getField($position);
        }

        // Set a hard limit to bail on an impossible date
        for ($i = 0; $i < $this->maxIterationCount; ++$i) {
            foreach ($parts as $position => $part) {
                if (!$fields[$position]->isSatisfiedBy($nextRun, $part, $invert)) {
                    $fields[$position]->increment($nextRun, $invert, $part);
                    continue 2;  // Continue the outer loop
                }
            }

            if ((!$allowCurrentDate && $nextRun == $currentDate) || --$nth > -1) {
                $this->fieldFactory->getField(CronExpression::SECOND)->increment($nextRun, $invert);
                continue;
            }

            return $nextRun;
        }

        throw new RuntimeException('Impossible CRON expression');
    }

    /**
     * @param int $total
     * @param $currentTime
     * @param bool $invert
     * @param bool $allowCurrentDate
     * @param $timeZone
     * @return array
     * @throws Exception
     */
    public function getMultipleRunDates(int $total, $currentTime = 'now', bool $invert = false, bool $allowCurrentDate = false, $timeZone = null): array
    {
        $timeZone = $this->determineTimeZone($currentTime, $timeZone);

        if ('now' === $currentTime) {
            $currentTime = new DateTime();
        } elseif ($currentTime instanceof DateTime) {
            $currentTime = clone $currentTime;
        } elseif ($currentTime instanceof DateTimeImmutable) {
            $currentTime = DateTime::createFromFormat('U', $currentTime->format('U'));
        } elseif (\is_string($currentTime)) {
            $currentTime = new DateTime($currentTime);
        }

        Assert::isInstanceOf($currentTime, DateTime::class);
        $currentTime->setTimezone(new DateTimeZone($timeZone));

        $matches = [];
        for ($i = 0; $i < $total; ++$i) {
            try {
                $result = $this->getRunDate($currentTime, 0, $invert, $allowCurrentDate, $timeZone);
            } catch (RuntimeException $e) {
                break;
            }

            $allowCurrentDate = false;
            $currentTime = clone $result;
            $matches[] = $result;
        }

        return $matches;
    }


    /**
     * Get the next run date relative to the current date or a specific date
     *
     * @param string|\DateTimeInterface $currentTime      Relative calculation date
     * @param int                       $nth              Number of matches to skip before returning a
     *                                                    matching next run date. 0, the default, will return the
     *                                                    current date and time if the next run date falls on the
     *                                                    current date and time. Setting this value to 1 will
     *                                                    skip the first match and go to the second match.
     *                                                    Setting this value to 2 will skip the first 2
     *                                                    matches and so on.
     * @param bool                      $allowCurrentDate Set to TRUE to return the current date if
     *                                                    it matches the cron expression.
     * @param null|string               $timeZone         TimeZone to use instead of the system default
     * @throws RuntimeException on too many iterations
     * @throws Exception
     *
     * @return \DateTime
     */
    public function getNextRunDate($currentTime = 'now', int $nth = 0, bool $allowCurrentDate = false, $timeZone = null): DateTime
    {
        return $this->getRunDate($currentTime, $nth, false, $allowCurrentDate, $timeZone);
    }

    /**
     * Get a previous run date relative to the current date or a specific date.
     *
     * @param string|\DateTimeInterface $currentTime      Relative calculation date
     * @param int                       $nth              Number of matches to skip before returning
     * @param bool                      $allowCurrentDate Set to TRUE to return the
     *                                                    current date if it matches the cron expression
     * @param null|string               $timeZone         TimeZone to use instead of the system default
     *
     * @throws RuntimeException on too many iterations
     * @throws Exception
     *
     * @return \DateTime
     */
    public function getPreviousRunDate($currentTime = 'now', int $nth = 0, bool $allowCurrentDate = false, $timeZone = null): DateTime
    {
        return $this->getRunDate($currentTime, $nth, true, $allowCurrentDate, $timeZone);
    }

    /**
     * Determine if the cron is due to run based on the current date or a specific date.
     * This method assumes that the current number of seconds are irrelevant, and should be called once per minute.
     *
     * @param string|\DateTimeInterface $currentTime Relative calculation date
     * @param null|string               $timeZone    TimeZone to use instead of the system default
     *
     * @return bool Returns TRUE if the cron is due to run or FALSE if not
     */
    public function isDue($currentTime = 'now', $timeZone = null): bool
    {
        $timeZone = $this->determineTimeZone($currentTime, $timeZone);
        $currentTime = $this->determineCurrentTime($currentTime, $timeZone);

        // Ensure seconds are set to zero to align with the start of the minute
        $currentTime->setTime((int) $currentTime->format('H'), (int) $currentTime->format('i'), 0);

        try {
            // Compare next run date with current time including seconds
            return $this->getNextRunDate($currentTime, 0, true)->getTimestamp() === $currentTime->getTimestamp();
        } catch (Exception $e) {
            return false;
        }
    }

    protected function determineCurrentTime($currentTime, $timeZone)
    {
        if ('now' === $currentTime) {
            $currentTime = new DateTime('now', new DateTimeZone($timeZone));
        } elseif ($currentTime instanceof DateTimeInterface) {
            $currentTime = clone $currentTime;
        } elseif (is_string($currentTime)) {
            $currentTime = new DateTime($currentTime, new DateTimeZone($timeZone));
        } else {
            throw new InvalidArgumentException("Invalid currentTime format.");
        }
        $currentTime->setTimezone(new DateTimeZone($timeZone));

        return $currentTime;
    }

    protected function determineTimeZone($currentTime, ?string $timeZone): string
    {
        return 'UTC';
    }


    /**
     * Registered a user defined CRON Expression Alias.
     *
     * @throws \LogicException If the expression or the alias name are invalid
     *                         or if the alias is already registered.
     */
    public static function registerAlias(string $alias, string $expression): void
    {
        try {
            new self($expression);
        } catch (InvalidArgumentException $exception) {
            throw new \LogicException("The expression `$expression` is invalid", 0, $exception);
        }

        $shortcut = strtolower($alias);
        if (1 !== preg_match('/^@\w+$/', $shortcut)) {
            throw new \LogicException("The alias `$alias` is invalid. It must start with an `@` character and contain alphanumeric (letters, numbers, regardless of case) plus underscore (_).");
        }

        if (isset(self::$registeredAliases[$shortcut])) {
            throw new \LogicException("The alias `$alias` is already registered.");
        }

        self::$registeredAliases[$shortcut] = $expression;
    }

    /**
     * Unregistered a user defined CRON Expression Alias.
     *
     * @throws \LogicException If the user tries to unregister a built-in alias
     */
    public static function unregisterAlias(string $alias): bool
    {
        $shortcut = strtolower($alias);
        if (isset(self::MAPPINGS[$shortcut])) {
            throw new \LogicException("The alias `$alias` is a built-in alias; it can not be unregistered.");
        }

        if (!isset(self::$registeredAliases[$shortcut])) {
            return false;
        }

        unset(self::$registeredAliases[$shortcut]);

        return true;
    }

    /**
     * Tells whether a CRON Expression alias is registered.
     */
    public static function supportsAlias(string $alias): bool
    {
        return isset(self::$registeredAliases[strtolower($alias)]);
    }

    /**
     * Returns all registered aliases as an associated array where the aliases are the key
     * and their associated expressions are the values.
     *
     * @return array<string, string>
     */
    public static function getAliases(): array
    {
        return self::$registeredAliases;
    }

    /**
     * @deprecated since version 3.0.2, use __construct instead.
     */
    public static function factory(string $expression, ?FieldFactoryInterface $fieldFactory = null): CronExpression
    {
        /** @phpstan-ignore-next-line */
        return new static($expression, $fieldFactory);
    }

    /**
     * Validate a CronExpression.
     *
     * @param string $expression the CRON expression to validate
     *
     * @return bool True if a valid CRON expression was passed. False if not.
     */
    public static function isValidExpression(string $expression): bool
    {
        try {
            new CronExpression($expression);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    /**
     * Set part of the CRON expression.
     *
     * @param int $position The position of the CRON expression to set
     * @param string $value The value to set
     *
     * @return CronExpression
     * @throws \InvalidArgumentException if the value is not valid for the part
     *
     */
    public function setPart(int $position, string $value): CronExpression
    {
        if (!$this->fieldFactory->getField($position)->validate($value)) {
            throw new InvalidArgumentException(
                'Invalid CRON field value ' . $value . ' at position ' . $position
            );
        }

        $this->cronParts[$position] = $value;

        return $this;
    }

    /**
     * Set max iteration count for searching next run dates.
     *
     * @param int $maxIterationCount Max iteration count when searching for next run date
     *
     * @return CronExpression
     */
    public function setMaxIterationCount(int $maxIterationCount): CronExpression
    {
        $this->maxIterationCount = $maxIterationCount;

        return $this;
    }

    /**
     * Get all or part of the CRON expression.
     *
     * @param int|string|null $part specify the part to retrieve or NULL to get the full
     *                     cron schedule string
     *
     * @return null|string Returns the CRON expression, a part of the
     *                     CRON expression, or NULL if the part was specified but not found
     */
    public function getExpression($part = null): ?string
    {
        if (null === $part) {
            return implode(' ', $this->cronParts);
        }

        if (array_key_exists($part, $this->cronParts)) {
            return $this->cronParts[$part];
        }

        return null;
    }

    /**
     * Gets the parts of the cron expression as an array.
     *
     * @return string[]
     *   The array of parts that make up this expression.
     */
    public function getParts()
    {
        return $this->cronParts;
    }

    /**
     * Helper method to output the full expression.
     *
     * @return string Full CRON expression
     */
    public function __toString(): string
    {
        return (string)$this->getExpression();
    }

    /**
     * Workout what timeZone should be used.
     *
     * @param string|\DateTimeInterface|null $currentTime Relative calculation date
     * @param string|null $timeZone TimeZone to use instead of the system default
     *
     * @return string
     */
    protected function determineTimeZone_($currentTime, ?string $timeZone): string
    {
        if (null !== $timeZone) {
            return $timeZone;
        }

        if ($currentTime instanceof DateTimeInterface) {
            return $currentTime->getTimezone()->getName();
        }

        return date_default_timezone_get();
    }

}
