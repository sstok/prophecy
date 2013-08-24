<?php

namespace Prophecy\Util;

/*
 * This file is part of the Prophecy.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *     Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * String utility.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class StringUtil
{
    /**
     * Stringifies any provided value.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function stringify($value)
    {
        if (is_array($value)) {
            if (range(0, count($value) - 1) == array_keys($value)) {
                return '['.implode(', ', array_map(array($this, __FUNCTION__), $value)).']';
            }

            $stringify = array($this, __FUNCTION__);
            return '['.implode(', ', array_map(function($item, $key) use($stringify) {
                return (is_integer($key) ? $key : '"'.$key.'"').
                    ' => '.call_user_func($stringify, $item);
            }, $value, array_keys($value))).']';
        }
        if (is_resource($value)) {
            return get_resource_type($value).':'.$value;
        }
        if (is_object($value)) {
            return $this->export($value);
            //return sprintf('%s:%s', get_class($value), spl_object_hash($value));
        }
        if (true === $value || false === $value) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            $str = sprintf('"%s"', str_replace("\n", '\\n', $value));

            if (50 <= strlen($str)) {
                return substr($str, 0, 50).'"...';
            }

            return $str;
        }
        if (null === $value) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * Stringifies provided array of calls.
     *
     * @param array $calls Array of Call instances
     *
     * @return string
     */
    public function stringifyCalls(array $calls)
    {
        $self = $this;

        return implode(PHP_EOL, array_map(function($call) use($self) {
            return sprintf('  - %s(%s) @ %s',
                $call->getMethodName(),
                implode(', ', array_map(array($self, 'stringify'), $call->getArguments())),
                str_replace(GETCWD().DIRECTORY_SEPARATOR, '', $call->getCallPlace())
            );
        }, $calls));
    }

    /**
     * Exports a value into a string
     *
     * The output of this method is similar to the output of print_r(), but
     * improved in various aspects:
     *
     *  - NULL is rendered as "null" (instead of "")
     *  - TRUE is rendered as "true" (instead of "1")
     *  - FALSE is rendered as "false" (instead of "")
     *  - Strings are always quoted with single quotes
     *  - Carriage returns and newlines are normalized to \n
     *  - Recursion and repeated rendering is treated properly
     *
     * @param  mixed $value The value to export
     * @param  integer $indentation The indentation level of the 2nd+ line
     * @return string
     * @since  Method available since Release 3.6.0
     */
    public static function export($value, $indentation = 0)
    {
        return self::recursiveExport($value, $indentation);
    }

    /**
     * Recursive implementation of export
     *
     * @param  mixed $value The value to export
     * @param  integer $indentation The indentation level of the 2nd+ line
     * @param  array $processedObjects Contains all objects that were already
     *                                 rendered
     * @return string
     * @since  Method available since Release 3.6.0
     * @see    PHPUnit_Util_Type::export
     */
    protected static function recursiveExport($value, $indentation, &$processedObjects = array())
    {
        if ($value === NULL) {
            return 'null';
        }

        if ($value === TRUE) {
            return 'true';
        }

        if ($value === FALSE) {
            return 'false';
        }

        if (is_string($value)) {
            // Match for most non printable chars somewhat taking multibyte chars into account
            if (preg_match('/[^\x09-\x0d\x20-\xff]/', $value)) {
                return 'Binary String: 0x' . bin2hex($value);
            }

            return "'" .
                   str_replace(array("\r\n", "\n\r", "\r"), array("\n", "\n", "\n"), $value) .
                   "'";
        }

        $origValue = $value;

        if (is_object($value)) {
            if (in_array($value, $processedObjects, TRUE)) {
                return sprintf(
                  '%s Object (*RECURSION*)',

                  get_class($value)
                );
            }

            $processedObjects[] = $value;

            // Convert object to array
            $value = self::toArray($value);
        }

        if (is_array($value)) {
            $whitespace = str_repeat('    ', $indentation);

            // There seems to be no other way to check arrays for recursion
            // http://www.php.net/manual/en/language.types.array.php#73936
            preg_match_all('/\n            \[(\w+)\] => Array\s+\*RECURSION\*/', print_r($value, TRUE), $matches);
            $recursiveKeys = array_unique($matches[1]);

            // Convert to valid array keys
            // Numeric integer strings are automatically converted to integers
            // by PHP
            foreach ($recursiveKeys as $key => $recursiveKey) {
                if ((string)(integer)$recursiveKey === $recursiveKey) {
                    $recursiveKeys[$key] = (integer)$recursiveKey;
                }
            }

            $content = '';

            foreach ($value as $key => $val) {
                if (in_array($key, $recursiveKeys, TRUE)) {
                    $val = 'Array (*RECURSION*)';
                }

                else {
                    $val = self::recursiveExport($val, $indentation+1, $processedObjects);
                }

                $content .=  $whitespace . '    ' . self::export($key) . ' => ' . $val . "\n";
            }

            if (strlen($content) > 0) {
                $content = "\n" . $content . $whitespace;
            }

            return sprintf(
              "%s (%s)",

              is_object($origValue) ? get_class($origValue) . ' Object' : 'Array',
              $content
            );
        }

        if (is_double($value) && (double)(integer)$value === $value) {
            return $value . '.0';
        }

        return (string)$value;
    }

    /**
     * Converts an object to an array containing all of its private, protected
     * and public properties.
     *
     * @param  object $object
     * @return array
     * @since  Method available since Release 3.6.0
     */
    public static function toArray($object)
    {
        $array = array();

        foreach ((array)$object as $key => $value) {
            // properties are transformed to keys in the following way:

            // private   $property => "\0Classname\0property"
            // protected $property => "\0*\0property"
            // public    $property => "property"

            if (preg_match('/^\0.+\0(.+)$/', $key, $matches)) {
                $key = $matches[1];
            }

            $array[$key] = $value;
        }

        // Some internal classes like SplObjectStorage don't work with the
        // above (fast) mechanism nor with reflection
        // Format the output similarly to print_r() in this case
        if ($object instanceof SplObjectStorage) {
            foreach ($object as $key => $value) {
                $array[spl_object_hash($value)] = array(
                    'obj' => $value,
                    'inf' => $object->getInfo(),
                );
            }
        }

        return $array;
    }
}
