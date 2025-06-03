<?php

namespace Src\Sanitise;

class Validate
{

    public static function cleanArray(array $array): string
    {
        $error = "";

        for (
            $x = 0;
            $x < count($array[$x]);
            $x++
        ) {

            for (
                $i = 0;
                $i < count($array[$x][$i]);
                $i++
            ) {
                if ($_POST[$array[$x][$i]] == "") {
                    $nameKey = $array[$x][$i];
                    $cleanNameKey = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', $nameKey));

                    $error  .= "$cleanNameKey is required<br>";
                }
            }
        }

        if ($_POST[$array['createAccount']['passport']] !== $_POST[$array['createAccount']['confirm_password']]) {
            $error .= " Your passwords do not match";
        }

        return $error;
    }
}
