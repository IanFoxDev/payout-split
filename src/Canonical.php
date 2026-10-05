<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

/**
 * @internal
 */
final class Canonical
{
    /**
     * JSON with object keys sorted, so the same data gives the same bytes.
     */
    public static function json(mixed $value): string
    {
        return json_encode(self::sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * An object with string keys, even when PHP made numeric ids into int keys, so
     * {"10": 1} is not written as a list.
     *
     * @param array<array-key, int> $map
     */
    public static function stringKeys(array $map): \stdClass
    {
        $object = new \stdClass();
        foreach ($map as $k => $v) {
            $object->{(string) $k} = $v;
        }

        return $object;
    }

    private static function sort(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $array = get_object_vars($value);
            ksort($array, SORT_STRING);
            $object = new \stdClass();
            foreach ($array as $k => $v) {
                $object->{$k} = self::sort($v);
            }

            return $object;
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(self::sort(...), $value);
            }
            ksort($value, SORT_STRING);

            return self::assoc($value);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function assoc(array $value): \stdClass
    {
        $object = new \stdClass();
        foreach ($value as $k => $v) {
            $object->{(string) $k} = self::sort($v);
        }

        return $object;
    }
}
