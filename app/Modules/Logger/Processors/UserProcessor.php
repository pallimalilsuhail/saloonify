<?php

declare(strict_types=1);

namespace App\Modules\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class UserProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (app()->runningInConsole()) {
            return $record;
        }

        $user = request()->user();

        if (! $user) {
            return $record;
        }

        $record->extra['user_id'] = $user->getAuthIdentifier();

        if (isset($user->workos_id)) {
            $record->extra['workos_id'] = $user->workos_id;
        }

        return $record;
    }
}
