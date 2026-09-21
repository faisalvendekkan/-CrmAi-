<?php
declare(strict_types=1);

final class Activity
{
    public static function log(string $action, string $module = "", ?int $recordId = null, string $summary = "", ?int $userId = null): void
    {
        try {
            DB::insert("activity", [
                "user_id"   => $userId ?? (Auth::user()["id"] ?? null),
                "action"    => mb_substr($action, 0, 40),
                "module"    => mb_substr($module, 0, 40),
                "record_id" => $recordId,
                "summary"   => mb_substr($summary, 0, 255),
                "ip"        => client_ip(),
            ]);
        } catch (Throwable $e) {
            ErrorHandler::log($e);
        }
    }
}
