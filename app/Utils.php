<?php

namespace App;

class Utils
{
    private static $env;

    public static function env($group, $index = null)
    {
        if (self::$env === null) {
            self::$env = include ROOT .'/env.php';
        }

        if ($group === null) {
            return self::$env;
        }
        $arr = self::$env;
        if (isset($arr[$group])) {
            $arr = $arr[$group];
        }
        if ($index === null) {
            return $arr;
        }
        if (isset($arr[$index])) {
            return $arr[$index];
        }
        return null;
    }
}