<?php

namespace XTAIN\MonologDatadog\Formatter;

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use Monolog\LogRecord;

class DatadogFormatter extends JsonFormatter
{
    protected bool $includeStacktraces = true;

    /**
     * Map Monolog\Logger levels to Datadog's default status type
     */
    private const DATADOG_LEVEL_MAP = [
        Logger::DEBUG => 'info',
        Logger::INFO => 'info',
        Logger::NOTICE => 'warning',
        Logger::WARNING => 'warning',
        Logger::ERROR => 'error',
        Logger::ALERT => 'error',
        Logger::CRITICAL => 'error',
        Logger::EMERGENCY => 'error',
    ];

    private function dumpData(mixed $data): string
    {
        if (is_object($data) || is_array($data)) {
            return json_encode($data);
        }

        return (string)$data;
    }

    private function replacePlaceHolder(LogRecord $record): string
    {
        $message = $record['message'];

        if (!str_contains($message, '{')) {
            return $message;
        }

        $context = $record['context'];

        $replacements = [];
        foreach ($context as $k => $v) {
            // Remove quotes added by the dumper around string.
            $replacements['{'.$k.'}'] = $this->dumpData($v);
        }

        return strtr($message, $replacements);
    }

    public function format(LogRecord $record): string
    {
        $normalized = $this->normalize($record);

        $normalized['extra']['message'] = $this->replacePlaceHolder($record);
        $normalized['extra']['level_name'] = static::DATADOG_LEVEL_MAP[$record['level']];

        return $this->toJson($normalized, true);
    }
}
