<?php

namespace App\Http\BusinessLogics;

use Illuminate\Support\Facades\DB;

class DBLogic
{
    public static function currentDateTime()
    {
        return DB::raw("SELECT NOW() AS `dateTime`")[0]["dateTime"];
    }
    public static function getMaxId($tableName)
    {
        $maxId = DB::table($tableName)->max('id');
        return $maxId ? $maxId + 1 : 1;
    }

}
