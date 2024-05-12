<?php

namespace Phelixjuma\Enqueue\QuartzCronExpression;

interface FieldFactoryInterface
{
    public function getField(int $position): FieldInterface;
}
