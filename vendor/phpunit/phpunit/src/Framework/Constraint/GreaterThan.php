<?php

namespace MolliePrefix;

/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Constraint that asserts that the value it is evaluated for is greater
 * than a given value.
 */
class PHPUnit_Framework_Constraint_GreaterThan extends \MolliePrefix\PHPUnit_Framework_Constraint
{
    /**
     * @var numeric
     */
    protected $value;
    /**
     * @param numeric $value
     */
    public function __construct($value)
    {
        parent::__construct();
        $this->value = $value;
    }
    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     *
     * @param mixed $other Value or object to evaluate.
     *
     * @return bool
     */
    protected function matches($other)
    {
        return $this->value < $other;
    }
    /**
     * Returns a string representation of the constraint.
     *
     * @return string
     */
    public function toString()
    {
        return 'is greater than ' . $this->exporter->export($this->value);
    }
}
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Constraint that asserts that the value it is evaluated for is greater
 * than a given value.
 */
\class_alias('MolliePrefix\\PHPUnit_Framework_Constraint_GreaterThan', 'PHPUnit_Framework_Constraint_GreaterThan', \false);
