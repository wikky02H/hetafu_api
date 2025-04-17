<?php

namespace App\Http\Middleware;

use App\Http\Helpers\Logger;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LogRequestResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $datetime_now = (str_contains("localhost", env("APP_BASE_URL"))) ? Carbon::now()->setTimezone("Asia/Kolkata") : Carbon::now();

        $full_url = $request->fullUrl();
        $method = $request->method();
        $ipAddress = $request->ip();
        $user_agent = $request->userAgent();
        $request_body = $request->all();
        $token = $request->header("authorization");
        $referer = $request->headers->get('referer');
        $message = array(
            "label" => "Request Log",
            "date_time" => $datetime_now->format("Y-m-d H:i:s"),
            "full_url" => $full_url,
            "method" => $method,
            "ip_address" => $ipAddress,
            "client_details" => $user_agent,
            "req_body" => Logger::getMinimalRequestBody($request_body),
            "token" => $token,
            "BrowserUrl" => $referer
        );

        if (env("APP_ENV") == "local") {
            Log::info("Request log", (array)$message);
            // Logger::writeIntoLog("info", json_encode($message));
            // $request->merge([
            //     "requestLog" => $message
            // ]);
        }

        return $next($request);
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  Illuminate\Http\Request $request
     * @param  Illuminate\Http\Response $response
     * @return void
     */
    public function terminate($request, $response)
    {
        $datetime_now = (str_contains("localhost", env("APP_BASE_URL"))) ? Carbon::now()->setTimezone("Asia/Kolkata") : Carbon::now();
        // At this point the response has already been sent to the browser so any
        // modification to the response (such adding HTTP headers) will have no effect
        if (defined("LARAVEL_START") and $request instanceof Request) {
            $message = [
                "label" => "Response Log",
                "date_time" => $datetime_now->format("Y-m-d H:i:s"),
                "method" => $request->method(),
                "uri" => $request->fullUrl(),
                "seconds" => microtime(true) - LARAVEL_START,
                "response_status" => $response->statusText(),
                "response_statusCode" => $response->status(),
                "response_content" => $response->getContent(),
                "response_original" => $response->getOriginalContent(),
            ];
            Log::info("Response log => ", (array)$message);
            // Logger::writeIntoLog("info", json_encode($request->requestLog) . "\n\t\t$request->logMessages" . json_encode($message));
        }
    }
}
