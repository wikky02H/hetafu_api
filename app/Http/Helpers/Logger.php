<?php

namespace App\Http\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class Logger
{
    const FILE_EXTENSION = ".log";
    static function getTodayLog()
    {
        $file_path = storage_path("logs/" . date("Y") . "_" . date("m") . "_" . date("d") . self::FILE_EXTENSION);
        $log = "";
        if (!file_exists($file_path)) {
            file_put_contents($file_path, "");
        } else {
            $log = file_get_contents(storage_path("logs/" . date("Y") . "_" . date("m") . "_" . date("d") . self::FILE_EXTENSION));
            if (!$log) {
                $log = "";
            }
        }
        return $log;
    }

    static function getLogMessage(string $date_now, string $level, string $message)
    {
        return "{$date_now}:\n\tLevel: $level\n\tMessage: {$message}\n\n";
    }

    static function writeIntoLog(string $level, string $message,)
    {
        $file_path = storage_path("logs/" . date("Y") . "_" . date("m") . "_" . date("d") . self::FILE_EXTENSION);
        $date_now = Carbon::now()->format("Y-m-d H:i:s");
        // $current_log = self::getTodayLog();
        File::append($file_path, self::getLogMessage($date_now, $level, $message));
        // file_put_contents($file_path, "$date_now:\n\tMessage: $message\n\n");
    }

    static function getMinimalRequestBody(&$body)
    {
        foreach ($body as $key => $value) {
            if (gettype($value) == "string" && strlen((string)$value) > 50) {
                $value = substr($value, 0, 50) . ".....";
            }
        }
        return $body;
    }
}
