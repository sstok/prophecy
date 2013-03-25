<?php

namespace Prophecy\Prediction;

use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophecy\MethodProphecy;

use Prophecy\Exception\Prediction\NoCallsException;

/*
 * This file is part of the Prophecy.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *     Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Call prediction.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class CallPrediction implements PredictionInterface
{
    /**
     * Tests that there was at least one call.
     *
     * @param array          $calls
     * @param ObjectProphecy $object
     * @param MethodProphecy $method
     *
     * @throws \Prophecy\Exception\Prediction\NoCallsException
     */
    public function check(array $calls, ObjectProphecy $object, MethodProphecy $method)
    {
        if (count($calls)) {
            return;
        }

        throw new NoCallsException(sprintf(
            'No calls been made that match `%s->%s(%s)`, but expected at least one.',
            get_class($object->reveal()),
            $method->getMethodName(),
            $method->getArgumentsWildcard()
        ), $method);
    }
}