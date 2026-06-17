# Driver Interface Design for Audit Plugin

## Overview

This document outlines a proposed driver-based architecture for the Audit plugin that supports multiple simultaneous logging destinations beyond the database. This addresses the feature request in [PLU-25] for optional file logging (for ELK stack integration) and extends it to support various modern logging services.

## Architecture

### Core Interface

```php
<?php
namespace superbig\audit\drivers;

use superbig\audit\models\AuditModel;

/**
 * Base interface for audit log drivers
 * 
 * Drivers handle logging audit events to external destinations
 * beyond the primary database storage.
 */
interface AuditDriverInterface
{
    /**
     * Initialize the driver with configuration
     * 
     * @param array $config Driver-specific configuration
     * @return void
     */
    public function init(array $config): void;

    /**
     * Check if the driver is properly configured and ready to log
     * 
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Log an audit event
     * 
     * @param AuditModel $model The audit model to log
     * @param array $context Additional context data (filtered snapshot, metadata)
     * @return bool Success status
     */
    public function log(AuditModel $model, array $context = []): bool;

    /**
     * Batch log multiple audit events (optional optimization)
     * 
     * @param array $entries Array of [model, context] pairs
     * @return bool Success status
     */
    public function logBatch(array $entries): bool;

    /**
     * Get driver name for identification
     * 
     * @return string
     */
    public function getName(): string;

    /**
     * Get driver version
     * 
     * @return string
     */
    public function getVersion(): string;

    /**
     * Handle driver-specific cleanup or shutdown
     * 
     * @return void
     */
    public function shutdown(): void;
}
```

### Abstract Base Driver

```php
<?php
namespace superbig\audit\drivers;

use Craft;
use superbig\audit\models\AuditModel;

/**
 * Abstract base driver with common functionality
 */
abstract class BaseAuditDriver implements AuditDriverInterface
{
    protected array $config = [];
    protected bool $enabled = false;

    public function init(array $config): void
    {
        $this->config = $config;
        $this->enabled = $config['enabled'] ?? false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function logBatch(array $entries): bool
    {
        // Default implementation: log individually
        foreach ($entries as [$model, $context]) {
            if (!$this->log($model, $context)) {
                return false;
            }
        }
        return true;
    }

    public function shutdown(): void
    {
        // Default: no-op
    }

    /**
     * Format audit model for logging
     * 
     * @param AuditModel $model
     * @param array $context
     * @return array
     */
    protected function formatLogEntry(AuditModel $model, array $context): array
    {
        return [
            'id' => $model->id,
            'event' => $model->event,
            'title' => $model->title,
            'userId' => $model->userId,
            'elementId' => $model->elementId,
            'elementType' => $model->elementType,
            'siteId' => $model->siteId,
            'ip' => $model->ip,
            'userAgent' => $model->userAgent,
            'sessionId' => $model->sessionId,
            'parentId' => $model->parentId,
            'timestamp' => $model->dateCreated ? $model->dateCreated->format('c') : null,
            'context' => $context,
        ];
    }

    /**
     * Log errors safely without disrupting main audit flow
     * 
     * @param string $message
     * @param \Exception|null $exception
     */
    protected function logError(string $message, \Exception $exception = null): void
    {
        $fullMessage = sprintf('[%s] %s', $this->getName(), $message);
        if ($exception) {
            $fullMessage .= ': ' . $exception->getMessage();
        }
        Craft::error($fullMessage, 'audit-drivers');
    }
}
```

## Driver Implementations

### 1. File Driver (for ELK Stack)

```php
<?php
namespace superbig\audit\drivers;

use Craft;
use superbig\audit\models\AuditModel;

/**
 * File-based logging driver for ELK stack integration
 * 
 * Configuration:
 * ```php
 * 'drivers' => [
 *     'file' => [
 *         'class' => FileAuditDriver::class,
 *         'enabled' => true,
 *         'path' => '@storage/logs/audit',
 *         'filename' => 'audit-{date}.log',
 *         'category' => 'audit',
 *         'level' => 'info',
 *         'format' => 'json', // json or text
 *         'rotate' => 'daily', // daily, weekly, monthly
 *         'maxFiles' => 30,
 *     ],
 * ],
 * ```
 */
class FileAuditDriver extends BaseAuditDriver
{
    protected string $logPath;
    protected string $category = 'audit';
    protected string $format = 'json';

    public function init(array $config): void
    {
        parent::init($config);
        
        $this->logPath = Craft::parseEnv($config['path'] ?? '@storage/logs/audit');
        $this->category = $config['category'] ?? 'audit';
        $this->format = $config['format'] ?? 'json';
    }

    public function log(AuditModel $model, array $context = []): bool
    {
        try {
            $entry = $this->formatLogEntry($model, $context);
            
            if ($this->format === 'json') {
                $message = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $message = $this->formatTextEntry($entry);
            }

            Craft::info($message, $this->category);
            
            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to log to file', $e);
            return false;
        }
    }

    protected function formatTextEntry(array $entry): string
    {
        return sprintf(
            '[%s] Event: %s | User: %s | Element: %s (%s) | IP: %s',
            $entry['timestamp'] ?? 'N/A',
            $entry['event'],
            $entry['userId'] ?? 'guest',
            $entry['elementId'] ?? 'N/A',
            $entry['elementType'] ?? 'N/A',
            $entry['ip'] ?? 'N/A'
        );
    }

    public function getName(): string
    {
        return 'File Driver';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
}
```

### 2. CloudWatch Driver

```php
<?php
namespace superbig\audit\drivers;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Craft;
use superbig\audit\models\AuditModel;

/**
 * AWS CloudWatch Logs driver
 * 
 * Configuration:
 * ```php
 * 'drivers' => [
 *     'cloudwatch' => [
 *         'class' => CloudWatchAuditDriver::class,
 *         'enabled' => true,
 *         'region' => 'us-east-1',
 *         'credentials' => [
 *             'key' => '$CLOUDWATCH_ACCESS_KEY',
 *             'secret' => '$CLOUDWATCH_SECRET_KEY',
 *         ],
 *         'logGroup' => '/craft/audit',
 *         'logStream' => 'production-{hostname}',
 *         'batchSize' => 10,
 *         'batchTimeout' => 5, // seconds
 *     ],
 * ],
 * ```
 */
class CloudWatchAuditDriver extends BaseAuditDriver
{
    protected ?CloudWatchLogsClient $client = null;
    protected string $logGroup;
    protected string $logStream;
    protected array $batchBuffer = [];
    protected int $batchSize = 10;

    public function init(array $config): void
    {
        parent::init($config);

        if (!$this->enabled) {
            return;
        }

        try {
            $this->client = new CloudWatchLogsClient([
                'region' => $config['region'] ?? 'us-east-1',
                'version' => 'latest',
                'credentials' => [
                    'key' => Craft::parseEnv($config['credentials']['key'] ?? ''),
                    'secret' => Craft::parseEnv($config['credentials']['secret'] ?? ''),
                ],
            ]);

            $this->logGroup = $config['logGroup'] ?? '/craft/audit';
            $this->logStream = $this->resolveLogStream($config['logStream'] ?? 'default');
            $this->batchSize = $config['batchSize'] ?? 10;

            $this->ensureLogGroupExists();
            $this->ensureLogStreamExists();
        } catch (\Exception $e) {
            $this->logError('Failed to initialize CloudWatch client', $e);
            $this->enabled = false;
        }
    }

    public function log(AuditModel $model, array $context = []): bool
    {
        try {
            $entry = $this->formatLogEntry($model, $context);
            
            $this->batchBuffer[] = [
                'timestamp' => ($model->dateCreated ? $model->dateCreated->getTimestamp() : time()) * 1000,
                'message' => json_encode($entry),
            ];

            if (count($this->batchBuffer) >= $this->batchSize) {
                return $this->flush();
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to log to CloudWatch', $e);
            return false;
        }
    }

    public function logBatch(array $entries): bool
    {
        foreach ($entries as [$model, $context]) {
            $this->log($model, $context);
        }
        return $this->flush();
    }

    protected function flush(): bool
    {
        if (empty($this->batchBuffer)) {
            return true;
        }

        try {
            $this->client->putLogEvents([
                'logGroupName' => $this->logGroup,
                'logStreamName' => $this->logStream,
                'logEvents' => $this->batchBuffer,
            ]);

            $this->batchBuffer = [];
            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to flush to CloudWatch', $e);
            $this->batchBuffer = [];
            return false;
        }
    }

    protected function ensureLogGroupExists(): void
    {
        try {
            $this->client->createLogGroup(['logGroupName' => $this->logGroup]);
        } catch (\Aws\CloudWatchLogs\Exception\ResourceAlreadyExistsException $e) {
            // Already exists, that's fine
        }
    }

    protected function ensureLogStreamExists(): void
    {
        try {
            $this->client->createLogStream([
                'logGroupName' => $this->logGroup,
                'logStreamName' => $this->logStream,
            ]);
        } catch (\Aws\CloudWatchLogs\Exception\ResourceAlreadyExistsException $e) {
            // Already exists, that's fine
        }
    }

    protected function resolveLogStream(string $template): string
    {
        return str_replace(
            ['{hostname}', '{date}'],
            [gethostname(), date('Y-m-d')],
            $template
        );
    }

    public function shutdown(): void
    {
        $this->flush();
    }

    public function getName(): string
    {
        return 'CloudWatch Driver';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
}
```

### 3. HyperDX Driver

```php
<?php
namespace superbig\audit\drivers;

use Craft;
use superbig\audit\models\AuditModel;
use GuzzleHttp\Client;

/**
 * HyperDX observability platform driver
 * 
 * Configuration:
 * ```php
 * 'drivers' => [
 *     'hyperdx' => [
 *         'class' => HyperDXAuditDriver::class,
 *         'enabled' => true,
 *         'apiKey' => '$HYPERDX_API_KEY',
 *         'endpoint' => 'https://in-otel.hyperdx.io/v1/logs',
 *         'service' => 'craft-audit',
 *         'environment' => 'production',
 *         'batchSize' => 100,
 *     ],
 * ],
 * ```
 */
class HyperDXAuditDriver extends BaseAuditDriver
{
    protected Client $httpClient;
    protected string $apiKey;
    protected string $endpoint;
    protected string $service;
    protected string $environment;
    protected array $batchBuffer = [];
    protected int $batchSize = 100;

    public function init(array $config): void
    {
        parent::init($config);

        if (!$this->enabled) {
            return;
        }

        $this->apiKey = Craft::parseEnv($config['apiKey'] ?? '');
        $this->endpoint = $config['endpoint'] ?? 'https://in-otel.hyperdx.io/v1/logs';
        $this->service = $config['service'] ?? 'craft-audit';
        $this->environment = $config['environment'] ?? 'production';
        $this->batchSize = $config['batchSize'] ?? 100;

        $this->httpClient = new Client([
            'timeout' => 5,
            'headers' => [
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function log(AuditModel $model, array $context = []): bool
    {
        try {
            $entry = $this->formatLogEntry($model, $context);
            
            $logRecord = [
                'timestamp' => $model->dateCreated ? $model->dateCreated->getTimestamp() * 1000000000 : time() * 1000000000,
                'severityText' => 'INFO',
                'severityNumber' => 9,
                'body' => $entry,
                'attributes' => [
                    'service.name' => $this->service,
                    'deployment.environment' => $this->environment,
                    'audit.event' => $model->event,
                    'audit.userId' => $model->userId,
                    'audit.elementType' => $model->elementType,
                ],
            ];

            $this->batchBuffer[] = $logRecord;

            if (count($this->batchBuffer) >= $this->batchSize) {
                return $this->flush();
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to log to HyperDX', $e);
            return false;
        }
    }

    protected function flush(): bool
    {
        if (empty($this->batchBuffer)) {
            return true;
        }

        try {
            $response = $this->httpClient->post($this->endpoint, [
                'json' => [
                    'resourceLogs' => [
                        [
                            'scopeLogs' => [
                                [
                                    'logRecords' => $this->batchBuffer,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $this->batchBuffer = [];
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $this->logError('Failed to flush to HyperDX', $e);
            $this->batchBuffer = [];
            return false;
        }
    }

    public function shutdown(): void
    {
        $this->flush();
    }

    public function getName(): string
    {
        return 'HyperDX Driver';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
}
```

### 4. Axiom Driver

```php
<?php
namespace superbig\audit\drivers;

use Craft;
use superbig\audit\models\AuditModel;
use GuzzleHttp\Client;

/**
 * Axiom logging and analytics driver
 * 
 * Configuration:
 * ```php
 * 'drivers' => [
 *     'axiom' => [
 *         'class' => AxiomAuditDriver::class,
 *         'enabled' => true,
 *         'apiToken' => '$AXIOM_API_TOKEN',
 *         'dataset' => 'craft-audit',
 *         'orgId' => '$AXIOM_ORG_ID', // Optional for personal tokens
 *         'endpoint' => 'https://api.axiom.co',
 *         'batchSize' => 1000,
 *     ],
 * ],
 * ```
 */
class AxiomAuditDriver extends BaseAuditDriver
{
    protected Client $httpClient;
    protected string $apiToken;
    protected string $dataset;
    protected string $endpoint;
    protected array $batchBuffer = [];
    protected int $batchSize = 1000;

    public function init(array $config): void
    {
        parent::init($config);

        if (!$this->enabled) {
            return;
        }

        $this->apiToken = Craft::parseEnv($config['apiToken'] ?? '');
        $this->dataset = $config['dataset'] ?? 'craft-audit';
        $this->endpoint = $config['endpoint'] ?? 'https://api.axiom.co';
        $this->batchSize = $config['batchSize'] ?? 1000;

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Content-Type' => 'application/json',
        ];

        if (!empty($config['orgId'])) {
            $headers['X-Axiom-Org-Id'] = Craft::parseEnv($config['orgId']);
        }

        $this->httpClient = new Client([
            'base_uri' => $this->endpoint,
            'timeout' => 10,
            'headers' => $headers,
        ]);
    }

    public function log(AuditModel $model, array $context = []): bool
    {
        try {
            $entry = $this->formatLogEntry($model, $context);
            
            // Axiom expects _time field for timestamp
            $entry['_time'] = $model->dateCreated ? $model->dateCreated->format('c') : date('c');

            $this->batchBuffer[] = $entry;

            if (count($this->batchBuffer) >= $this->batchSize) {
                return $this->flush();
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to log to Axiom', $e);
            return false;
        }
    }

    protected function flush(): bool
    {
        if (empty($this->batchBuffer)) {
            return true;
        }

        try {
            $response = $this->httpClient->post("/v1/datasets/{$this->dataset}/ingest", [
                'json' => $this->batchBuffer,
            ]);

            $this->batchBuffer = [];
            return in_array($response->getStatusCode(), [200, 204]);
        } catch (\Exception $e) {
            $this->logError('Failed to flush to Axiom', $e);
            $this->batchBuffer = [];
            return false;
        }
    }

    public function shutdown(): void
    {
        $this->flush();
    }

    public function getName(): string
    {
        return 'Axiom Driver';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
}
```

### 5. OpenTelemetry Driver

```php
<?php
namespace superbig\audit\drivers;

use Craft;
use superbig\audit\models\AuditModel;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Exporter\ConsoleExporter;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;

/**
 * OpenTelemetry standard driver for vendor-agnostic observability
 * 
 * Configuration:
 * ```php
 * 'drivers' => [
 *     'opentelemetry' => [
 *         'class' => OpenTelemetryAuditDriver::class,
 *         'enabled' => true,
 *         'exporter' => 'otlp', // otlp, console, or custom class
 *         'endpoint' => 'http://localhost:4318/v1/logs',
 *         'headers' => [
 *             'Authorization' => 'Bearer $OTEL_TOKEN',
 *         ],
 *         'serviceName' => 'craft-audit',
 *         'serviceVersion' => '1.0.0',
 *         'attributes' => [
 *             'deployment.environment' => 'production',
 *             'service.namespace' => 'craft',
 *         ],
 *     ],
 * ],
 * ```
 */
class OpenTelemetryAuditDriver extends BaseAuditDriver
{
    protected LoggerProviderInterface $loggerProvider;
    protected $logger;

    public function init(array $config): void
    {
        parent::init($config);

        if (!$this->enabled) {
            return;
        }

        try {
            $resource = ResourceInfo::create([
                'service.name' => $config['serviceName'] ?? 'craft-audit',
                'service.version' => $config['serviceVersion'] ?? '1.0.0',
            ] + ($config['attributes'] ?? []));

            // Initialize exporter based on configuration
            $exporter = $this->createExporter($config);

            $this->loggerProvider = LoggerProvider::builder()
                ->setResource($resource)
                ->addLogRecordProcessor(
                    new \OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor($exporter)
                )
                ->build();

            $this->logger = $this->loggerProvider->getLogger('craft.audit', '1.0.0');
        } catch (\Exception $e) {
            $this->logError('Failed to initialize OpenTelemetry', $e);
            $this->enabled = false;
        }
    }

    public function log(AuditModel $model, array $context = []): bool
    {
        try {
            $entry = $this->formatLogEntry($model, $context);

            $this->logger
                ->logRecord()
                ->setTimestamp($model->dateCreated ? $model->dateCreated->getTimestamp() * 1000000000 : time() * 1000000000)
                ->setSeverityText('INFO')
                ->setBody(json_encode($entry))
                ->setAttributes([
                    'audit.event' => $model->event,
                    'audit.userId' => $model->userId,
                    'audit.elementId' => $model->elementId,
                    'audit.elementType' => $model->elementType,
                    'audit.sessionId' => $model->sessionId,
                ])
                ->emit();

            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to log to OpenTelemetry', $e);
            return false;
        }
    }

    protected function createExporter(array $config)
    {
        $exporterType = $config['exporter'] ?? 'console';

        switch ($exporterType) {
            case 'console':
                return new ConsoleExporter();
            
            case 'otlp':
                // OTLP HTTP exporter
                return new \OpenTelemetry\SDK\Logs\Exporter\OtlpHttpExporter(
                    $config['endpoint'] ?? 'http://localhost:4318/v1/logs',
                    $config['headers'] ?? []
                );
            
            default:
                if (class_exists($exporterType)) {
                    return new $exporterType($config);
                }
                throw new \InvalidArgumentException("Unknown exporter type: $exporterType");
        }
    }

    public function shutdown(): void
    {
        if ($this->loggerProvider) {
            $this->loggerProvider->shutdown();
        }
    }

    public function getName(): string
    {
        return 'OpenTelemetry Driver';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }
}
```

## Driver Manager

```php
<?php
namespace superbig\audit\services;

use Craft;
use superbig\audit\drivers\AuditDriverInterface;
use superbig\audit\models\AuditModel;
use yii\base\Component;

/**
 * Driver manager for handling multiple audit drivers
 */
class DriverManager extends Component
{
    /** @var AuditDriverInterface[] */
    protected array $drivers = [];

    /** @var callable|null */
    protected $snapshotFilter = null;

    public function init(): void
    {
        parent::init();
        $this->initializeDrivers();
    }

    protected function initializeDrivers(): void
    {
        $settings = \superbig\audit\Audit::$plugin->getSettings();
        $driversConfig = $settings->drivers ?? [];

        foreach ($driversConfig as $name => $config) {
            if (empty($config['class'])) {
                continue;
            }

            try {
                $driver = Craft::createObject($config['class']);
                
                if (!$driver instanceof AuditDriverInterface) {
                    Craft::error("Driver $name does not implement AuditDriverInterface", 'audit-drivers');
                    continue;
                }

                $driver->init($config);
                
                if ($driver->isEnabled()) {
                    $this->drivers[$name] = $driver;
                    Craft::info("Initialized driver: {$driver->getName()}", 'audit-drivers');
                }
            } catch (\Exception $e) {
                Craft::error("Failed to initialize driver $name: " . $e->getMessage(), 'audit-drivers');
            }
        }

        // Initialize snapshot filter if configured
        if (isset($settings->snapshotFilter) && is_callable($settings->snapshotFilter)) {
            $this->snapshotFilter = $settings->snapshotFilter;
        }
    }

    /**
     * Distribute audit log to all enabled drivers
     * 
     * @param AuditModel $model
     * @return void
     */
    public function distributeLog(AuditModel $model): void
    {
        if (empty($this->drivers)) {
            return;
        }

        // Filter snapshot data for external drivers
        $context = $this->filterSnapshot($model);

        foreach ($this->drivers as $name => $driver) {
            try {
                $driver->log($model, $context);
            } catch (\Exception $e) {
                Craft::error(
                    "Driver $name failed to log: " . $e->getMessage(),
                    'audit-drivers'
                );
            }
        }
    }

    /**
     * Filter snapshot data for external logging
     * 
     * @param AuditModel $model
     * @return array
     */
    protected function filterSnapshot(AuditModel $model): array
    {
        $snapshot = $model->snapshot ?? [];

        // Apply custom filter if configured
        if ($this->snapshotFilter) {
            $snapshot = call_user_func($this->snapshotFilter, $snapshot, $model);
        } else {
            // Default filtering: remove large/sensitive data
            $snapshot = $this->defaultSnapshotFilter($snapshot);
        }

        return $snapshot;
    }

    /**
     * Default snapshot filtering logic
     * 
     * @param array $snapshot
     * @return array
     */
    protected function defaultSnapshotFilter(array $snapshot): array
    {
        $filtered = [];

        // List of safe attributes to include
        $allowedKeys = [
            'elementId',
            'elementType',
            'elementTypeLabel',
            'title',
            'userId',
            'uriParts',
            'template',
            'handle',
            'version',
        ];

        foreach ($allowedKeys as $key) {
            if (isset($snapshot[$key])) {
                $filtered[$key] = $snapshot[$key];
            }
        }

        // Don't include full content/field data by default
        // Can be overridden with custom filter
        return $filtered;
    }

    /**
     * Shutdown all drivers gracefully
     */
    public function shutdown(): void
    {
        foreach ($this->drivers as $driver) {
            try {
                $driver->shutdown();
            } catch (\Exception $e) {
                Craft::error(
                    "Error shutting down driver {$driver->getName()}: " . $e->getMessage(),
                    'audit-drivers'
                );
            }
        }
    }

    /**
     * Get list of active drivers
     * 
     * @return AuditDriverInterface[]
     */
    public function getActiveDrivers(): array
    {
        return $this->drivers;
    }
}
```

## Configuration Examples

### Full Configuration with Multiple Drivers

```php
<?php
// config/audit.php

return [
    // Existing settings
    'pruneDays' => 30,
    'enabled' => true,
    'logElementEvents' => true,
    
    // Driver configuration
    'drivers' => [
        // File driver for ELK stack
        'file' => [
            'class' => \superbig\audit\drivers\FileAuditDriver::class,
            'enabled' => true,
            'path' => '@storage/logs/audit',
            'filename' => 'audit-{date}.log',
            'category' => 'audit',
            'format' => 'json',
        ],
        
        // CloudWatch for AWS infrastructure
        'cloudwatch' => [
            'class' => \superbig\audit\drivers\CloudWatchAuditDriver::class,
            'enabled' => getenv('ENVIRONMENT') === 'production',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => '$CLOUDWATCH_ACCESS_KEY',
                'secret' => '$CLOUDWATCH_SECRET_KEY',
            ],
            'logGroup' => '/craft/audit',
            'logStream' => 'production-{hostname}',
            'batchSize' => 10,
        ],
        
        // HyperDX for observability
        'hyperdx' => [
            'class' => \superbig\audit\drivers\HyperDXAuditDriver::class,
            'enabled' => true,
            'apiKey' => '$HYPERDX_API_KEY',
            'service' => 'craft-cms',
            'environment' => 'production',
        ],
        
        // Axiom for analytics
        'axiom' => [
            'class' => \superbig\audit\drivers\AxiomAuditDriver::class,
            'enabled' => false, // Disabled by default
            'apiToken' => '$AXIOM_API_TOKEN',
            'dataset' => 'craft-audit',
        ],
        
        // OpenTelemetry for standards-based observability
        'opentelemetry' => [
            'class' => \superbig\audit\drivers\OpenTelemetryAuditDriver::class,
            'enabled' => false,
            'exporter' => 'otlp',
            'endpoint' => 'http://localhost:4318/v1/logs',
            'serviceName' => 'craft-audit',
        ],
    ],
    
    // Custom snapshot filter to control what data is sent to external drivers
    'snapshotFilter' => function(array $snapshot, $model) {
        // Only include safe metadata, exclude sensitive field content
        $filtered = [
            'elementId' => $snapshot['elementId'] ?? null,
            'elementType' => $snapshot['elementType'] ?? null,
            'elementTypeLabel' => $snapshot['elementTypeLabel'] ?? null,
            'title' => $snapshot['title'] ?? null,
        ];
        
        // Add specific fields based on element type
        if (isset($snapshot['elementType'])) {
            switch ($snapshot['elementType']) {
                case 'craft\\elements\\Entry':
                    $filtered['section'] = $snapshot['section'] ?? null;
                    break;
                case 'craft\\elements\\User':
                    $filtered['username'] = $snapshot['username'] ?? null;
                    break;
            }
        }
        
        return $filtered;
    },
];
```

### Minimal File-Only Configuration (Original Request)

```php
<?php
// config/audit.php

return [
    'enabled' => true,
    'logElementEvents' => true,
    
    'drivers' => [
        'file' => [
            'class' => \superbig\audit\drivers\FileAuditDriver::class,
            'enabled' => true,
            'path' => '@storage/logs/audit',
            'category' => 'audit',
            'format' => 'json',
        ],
    ],
    
    // Simple filter to exclude field content
    'snapshotFilter' => function($snapshot, $model) {
        unset($snapshot['content']);
        return $snapshot;
    },
];
```

### Environment-Specific Configuration

```php
<?php
// config/audit.php

return [
    'enabled' => true,
    
    'drivers' => [
        'file' => [
            'class' => \superbig\audit\drivers\FileAuditDriver::class,
            'enabled' => getenv('ENVIRONMENT') === 'development',
            'path' => '@storage/logs/audit',
            'format' => 'json',
        ],
        
        'cloudwatch' => [
            'class' => \superbig\audit\drivers\CloudWatchAuditDriver::class,
            'enabled' => in_array(getenv('ENVIRONMENT'), ['staging', 'production']),
            'region' => getenv('AWS_REGION') ?: 'us-east-1',
            'credentials' => [
                'key' => '$AWS_ACCESS_KEY_ID',
                'secret' => '$AWS_SECRET_ACCESS_KEY',
            ],
            'logGroup' => '/craft/audit/' . getenv('ENVIRONMENT'),
            'logStream' => getenv('ENVIRONMENT') . '-{hostname}',
        ],
    ],
];
```

## Integration with Existing Code

### Modified AuditService._saveRecord()

```php
public function _saveRecord(AuditModel &$model, $unique = true)
{
    try {
        // ... existing database save logic ...
        
        if (!$record->save()) {
            // ... existing error handling ...
        }

        $model->id = $record->id;

        // NEW: Distribute to external drivers
        if (Audit::$plugin->has('driverManager')) {
            Audit::$plugin->driverManager->distributeLog($model);
        }

        return true;
    } catch (Exception $e) {
        // ... existing error handling ...
    }
}
```

### Modified Audit Plugin Initialization

```php
public function init(): void
{
    parent::init();
    self::$plugin = $this;

    // ... existing initialization ...

    $this->setComponents([
        'auditService' => AuditService::class,
        'geo' => Audit_GeoService::class,
        'driverManager' => DriverManager::class, // NEW
    ]);

    // ... rest of initialization ...
}

// Add shutdown handler
public function beforeUnload(): void
{
    if ($this->has('driverManager')) {
        $this->driverManager->shutdown();
    }
    parent::beforeUnload();
}
```

### Settings Model Updates

```php
class Settings extends Model
{
    // ... existing properties ...

    /**
     * Driver configurations
     * @var array
     */
    public array $drivers = [];

    /**
     * Custom snapshot filter callable
     * @var callable|null
     */
    public $snapshotFilter = null;

    // ... rest of model ...
}
```

## Key Features

### 1. **Multiple Simultaneous Drivers**
- Configure multiple drivers that all receive audit logs
- Each driver operates independently
- Failed drivers don't affect database logging or other drivers

### 2. **Snapshot Filtering**
- Global filter function to control what data is sent to external drivers
- Prevents logging large serialized snapshots (addresses original concern)
- Customizable per-environment or per-element-type

### 3. **Batch Support**
- Drivers can implement batching for performance
- CloudWatch, HyperDX, and Axiom examples show batching
- Automatic flush on shutdown

### 4. **Environment Variables**
- All sensitive credentials use environment variables
- Easy to configure per environment
- Follows 12-factor app principles

### 5. **Error Isolation**
- Driver failures don't disrupt main audit logging
- Errors logged to separate category
- Graceful degradation

### 6. **Standards-Based**
- OpenTelemetry driver for vendor-neutral observability
- File driver outputs JSON for ELK/Logstash parsing
- CloudWatch follows AWS best practices

## Limitations & Considerations

### 1. **Pruning**
As noted in the original issue, pruning won't work for external drivers:
- Database pruning only affects local records
- External services have their own retention policies
- Consider service-specific TTL/retention settings

### 2. **Performance**
- External API calls add latency
- Batching recommended for high-volume sites
- Consider async queue for driver calls in future enhancement

### 3. **Data Privacy**
- Snapshot filtering is crucial for GDPR/privacy
- Don't log PII to external services unless compliant
- Consider encryption for sensitive data

### 4. **Dependencies**
Additional packages needed:
```json
{
    "require": {
        "aws/aws-sdk-php": "^3.0", // for CloudWatch
        "guzzlehttp/guzzle": "^7.0", // for HTTP drivers
        "open-telemetry/sdk": "^1.0" // for OpenTelemetry
    }
}
```

Make dependencies optional with `suggest` in composer.json.

## Testing Strategy

```php
// Example test
class DriverManagerTest extends TestCase
{
    public function testMultipleDriversReceiveLogs()
    {
        $manager = new DriverManager();
        $manager->drivers = [
            'file' => new FileAuditDriver(),
            'cloudwatch' => new CloudWatchAuditDriver(),
        ];
        
        $model = new AuditModel();
        $model->event = 'test-event';
        
        $manager->distributeLog($model);
        
        // Assert both drivers received the log
    }
    
    public function testSnapshotFiltering()
    {
        $manager = new DriverManager();
        $manager->snapshotFilter = function($snapshot) {
            unset($snapshot['content']);
            return $snapshot;
        };
        
        $model = new AuditModel();
        $model->snapshot = ['title' => 'Test', 'content' => 'Large data'];
        
        $filtered = $manager->filterSnapshot($model);
        
        $this->assertArrayHasKey('title', $filtered);
        $this->assertArrayNotHasKey('content', $filtered);
    }
}
```

## Migration Path

1. **Phase 1**: Implement driver interface and manager
2. **Phase 2**: Implement file driver (original request)
3. **Phase 3**: Implement CloudWatch driver
4. **Phase 4**: Implement other drivers as needed
5. **Phase 5**: Add async queue support for performance

Each phase maintains backward compatibility.

## Conclusion

This driver-based architecture provides:
- ✅ File logging for ELK stack (original request)
- ✅ Support for multiple modern observability platforms
- ✅ Multiple simultaneous drivers
- ✅ Snapshot filtering to avoid logging large data
- ✅ Extensible interface for custom drivers
- ✅ Environment-aware configuration
- ✅ Graceful error handling
- ✅ Standards-based approach (OpenTelemetry)

The design allows users to start simple (file logging only) and expand to sophisticated multi-platform observability as needed.
