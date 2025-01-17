<?php

namespace XTAIN\MonologDatadog\Handler;

use JsonException;
use Monolog\Handler\MissingExtensionException;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use XTAIN\MonologDatadog\Formatter\DatadogFormatter;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class DatadogHandler extends AbstractProcessingHandler
{
    /** @var string Datadog API host */
    private string $host;

    /** @var string Datadog API-Key */
    private string $apiKey;

    /** @var array Datadog optional attributes */
    private array $attributes;

    /**
     * @param string $apiKey Datadog API-Key
     * @param string $host Datadog API host
     * @param array $attributes Datadog optional attributes
     * @param int|string $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     * @throws MissingExtensionException
     */
    public function __construct(
        string $apiKey,
        string $host = 'https://http-intake.logs.datadoghq.com',
        array $attributes = [],
        int|string $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);

        $this->apiKey = $apiKey;
        $this->host = $host;
        $this->attributes = $attributes;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param array $record
     * @return void
     * @throws JsonException
     */
    protected function write(LogRecord $record): void
    {
        $this->send($record);
    }

    /**
     * Send request to Datadog
     *
     * @param LogRecord $record
     * @throws JsonException
     * @noinspection SpellCheckingInspection
     */
    protected function send(LogRecord $record): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'DD-API-KEY' => $this->apiKey
        ];

        $source = $this->getSource();
        $hostname = $this->getHostname();
        $service = $this->getService($record);
        $tags = $this->getTags($record);

        $url = $this->host . '/api/v2/logs';

        $payLoad = json_decode($record['formatted'], true, 512, JSON_THROW_ON_ERROR);

        $message = $record['message'];

        if (isset($payLoad['extra']['message'])) {
            $message = $payLoad['extra']['message'];
            unset($payLoad['extra']['message']);
        }

        if (isset($payLoad['extra']['level_name'])) {
            $payLoad['level_name'] = strtoupper($payLoad['extra']['level_name']);
            unset($payLoad['extra']['level_name']);
        }

        $payLoad['message'] = $message;
        $payLoad['ddsource'] = $source;
        $payLoad['ddtags'] = $tags;
        $payLoad['hostname'] = $hostname;
        $payLoad['service'] = $service;

        $client = new Client();
        $request = new Request('POST', $url, $headers, json_encode($payLoad, JSON_THROW_ON_ERROR));

        try {
            $response = $client->send($request);
        } catch (\Exception $e) {
            // log something
        }
    }

    /**
     * Get Datadog Source from $attributes params.
     *
     * @return string
     */
    protected function getSource(): string
    {
        return $this->attributes['source'] ?? 'php';
    }

    /**
     * Get Datadog Service from $attributes params.
     *
     * @param array $record
     *
     * @return string
     */
    protected function getService(LogRecord $record): string
    {
        return $this->attributes['service'] ?? $record['channel'];
    }

    /**
     * Get Datadog Hostname from $attributes params.
     *
     * @return string
     */
    protected function getHostname(): string
    {
        return $this->attributes['hostname'] ?? gethostname();
    }

    /**
     * Get Datadog Version from $attributes params.
     *
     * @return string
     */
    protected function getVersion(): ?string
    {
        return $this->attributes['version'] ?? null;
    }

    /**
     * Get Datadog Env from $attributes params.
     *
     * @return string
     */
    protected function getEnv(): ?string
    {
        return $this->attributes['env'] ?? null;
    }

    /**
     * Get Datadog Tags from $attributes params.
     *
     * @param array $record
     *
     * @return string
     */
    protected function getTags(LogRecord $record): string
    {
        $defaultTag = 'level:' . $record['level_name'];

        if ($this->getEnv() !== null) {
            $defaultTag .= ',env:' . $this->getEnv();
        }

        if ($this->getVersion() !== null) {
            $defaultTag .= ',version:' . $this->getVersion();
        }

        if (!isset($this->attributes['tags']) || !$this->attributes['tags']) {
            return $defaultTag;
        }

        if (
            (is_array($this->attributes['tags']) || is_object($this->attributes['tags']))
            && !empty($this->attributes['tags'])
        ) {
            $imploded = implode(',', (array)$this->attributes['tags']);

            return $imploded . ',' . $defaultTag;
        }

        return $defaultTag;
    }

    /**
     * Returns the default formatter to use with this handler
     *
     * @return DatadogFormatter
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new DatadogFormatter();
    }
}
