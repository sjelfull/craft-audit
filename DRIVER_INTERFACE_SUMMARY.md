# Driver Interface Design - Executive Summary

## Overview

This document summarizes the proposed driver-based architecture for adding optional logging to external services beyond the database, addressing issue [PLU-25].

## Quick Links

- Full Design Document: [DRIVER_INTERFACE_DESIGN.md](./DRIVER_INTERFACE_DESIGN.md)
- Original Issue: PLU-25 - Support for optional logging to file

## Key Design Principles

### 1. **Extensible Driver Interface**
```php
interface AuditDriverInterface {
    public function init(array $config): void;
    public function isEnabled(): bool;
    public function log(AuditModel $model, array $context = []): bool;
    public function logBatch(array $entries): bool;
    public function getName(): string;
    public function getVersion(): string;
    public function shutdown(): void;
}
```

### 2. **Multiple Simultaneous Drivers**
Users can enable multiple drivers at once:
```php
'drivers' => [
    'file' => [...],        // For ELK stack
    'cloudwatch' => [...],  // For AWS
    'hyperdx' => [...],     // For observability
]
```

### 3. **Snapshot Filtering**
Addresses the concern about logging large serialized snapshots:
```php
'snapshotFilter' => function(array $snapshot, $model) {
    // Only include safe metadata
    unset($snapshot['content']);
    return $snapshot;
}
```

## Driver Examples Provided

| Driver | Purpose | Key Features |
|--------|---------|--------------|
| **File** | ELK stack integration (original request) | JSON/text output, rotation, Craft logging |
| **CloudWatch** | AWS infrastructure | Batching, auto log group/stream creation |
| **HyperDX** | Modern observability | OTLP format, service tagging |
| **Axiom** | Analytics platform | High-volume batching, dataset ingestion |
| **OpenTelemetry** | Vendor-neutral standard | OTLP protocol, multiple exporters |

## Simple Configuration Example

Minimal setup for file logging (addresses original request):

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
    
    'snapshotFilter' => function($snapshot, $model) {
        unset($snapshot['content']); // Don't log full content
        return $snapshot;
    },
];
```

## Advanced Configuration Example

Multi-driver setup for production:

```php
'drivers' => [
    'file' => [
        'class' => FileAuditDriver::class,
        'enabled' => true,
        'format' => 'json',
    ],
    'cloudwatch' => [
        'class' => CloudWatchAuditDriver::class,
        'enabled' => getenv('ENVIRONMENT') === 'production',
        'region' => 'us-east-1',
        'credentials' => [
            'key' => '$CLOUDWATCH_ACCESS_KEY',
            'secret' => '$CLOUDWATCH_SECRET_KEY',
        ],
        'logGroup' => '/craft/audit',
        'batchSize' => 10,
    ],
]
```

## Key Benefits

### ✅ Addresses Original Request
- File logging with configurable format (JSON for ELK)
- Uses Craft's native logging (`Craft::info()`)
- Optional, disabled by default
- Configurable category and level

### ✅ Extensible Architecture
- Easy to add new drivers
- Multiple drivers can run simultaneously
- Each driver operates independently
- Failed drivers don't affect database logging

### ✅ Data Protection
- Snapshot filtering prevents logging sensitive data
- Customizable per environment
- Only logs safe metadata by default

### ✅ Production Ready
- Error isolation and handling
- Batching support for high volume
- Environment-aware configuration
- Graceful shutdown handling

## Implementation Changes Required

### 1. New Components

```
src/
├── drivers/
│   ├── AuditDriverInterface.php
│   ├── BaseAuditDriver.php
│   ├── FileAuditDriver.php
│   ├── CloudWatchAuditDriver.php
│   ├── HyperDXAuditDriver.php
│   ├── AxiomAuditDriver.php
│   └── OpenTelemetryAuditDriver.php
└── services/
    └── DriverManager.php
```

### 2. Modified Files

**src/Audit.php** - Register DriverManager component:
```php
$this->setComponents([
    'auditService' => AuditService::class,
    'geo' => Audit_GeoService::class,
    'driverManager' => DriverManager::class, // NEW
]);
```

**src/services/AuditService.php** - Add driver distribution in `_saveRecord()`:
```php
public function _saveRecord(AuditModel &$model, $unique = true)
{
    // ... existing database save logic ...
    
    $model->id = $record->id;

    // NEW: Distribute to external drivers
    if (Audit::$plugin->has('driverManager')) {
        Audit::$plugin->driverManager->distributeLog($model);
    }

    return true;
}
```

**src/models/Settings.php** - Add driver configuration:
```php
public array $drivers = [];
public $snapshotFilter = null;
```

### 3. Configuration File

**src/config.php** - Add driver examples:
```php
'drivers' => [
    // Driver configurations
],
'snapshotFilter' => null,
```

## Limitations Addressed

### ❌ Pruning Won't Work for External Drivers
- Each external service has its own retention policies
- Document this limitation clearly
- Recommend service-specific TTL settings

### ❌ Performance Considerations
- External API calls add latency
- Mitigated with batching support
- Future: Add async queue support

### ❌ Additional Dependencies
- Drivers require specific packages (AWS SDK, Guzzle, OpenTelemetry)
- Use Composer's `suggest` for optional dependencies
- Each driver checks for required classes

## Migration Path

### Phase 1: Core Infrastructure (MVP)
- Implement `AuditDriverInterface`
- Implement `BaseAuditDriver`
- Implement `DriverManager`
- Add integration to `AuditService`

### Phase 2: File Driver (Original Request)
- Implement `FileAuditDriver`
- Add configuration example
- Update documentation

### Phase 3: Cloud Drivers
- Implement `CloudWatchAuditDriver`
- Implement `HyperDXAuditDriver`
- Implement `AxiomAuditDriver`

### Phase 4: Standards Support
- Implement `OpenTelemetryAuditDriver`
- Add advanced examples

### Phase 5: Performance Enhancements
- Add async queue support
- Optimize batching
- Add performance metrics

## Testing Strategy

```php
// Unit tests for each driver
class FileAuditDriverTest extends TestCase { ... }
class CloudWatchAuditDriverTest extends TestCase { ... }

// Integration tests
class DriverManagerTest extends TestCase {
    public function testMultipleDriversReceiveLogs() { ... }
    public function testSnapshotFiltering() { ... }
    public function testDriverFailureIsolation() { ... }
}

// End-to-end tests
class AuditServiceIntegrationTest extends TestCase {
    public function testLogDistributionToDrivers() { ... }
}
```

## Backward Compatibility

### ✅ Fully Backward Compatible
- No changes to existing functionality
- Drivers are opt-in via configuration
- Default behavior unchanged (database only)
- No breaking changes to API

## Documentation Updates Needed

1. **README.md** - Add drivers section
2. **config.php** - Add driver examples
3. **New: DRIVERS.md** - Driver development guide
4. **New: EXAMPLES.md** - Configuration examples

## Composer Dependencies (Optional)

```json
{
    "require": {
        "guzzlehttp/guzzle": "^7.0"  // For HTTP-based drivers
    },
    "suggest": {
        "aws/aws-sdk-php": "Required for CloudWatch driver",
        "open-telemetry/sdk": "Required for OpenTelemetry driver"
    }
}
```

## Example Use Cases

### Use Case 1: Simple File Logging
```php
'drivers' => [
    'file' => [
        'class' => FileAuditDriver::class,
        'enabled' => true,
        'format' => 'json',
    ],
]
```
**Result**: Audit logs written to file for Logstash/Filebeat pickup

### Use Case 2: Development + Production
```php
'drivers' => [
    'file' => [
        'enabled' => getenv('ENV') === 'development',
    ],
    'cloudwatch' => [
        'enabled' => getenv('ENV') === 'production',
    ],
]
```
**Result**: Files locally, CloudWatch in production

### Use Case 3: Multi-Cloud
```php
'drivers' => [
    'cloudwatch' => [...],  // Primary cloud
    'axiom' => [...],       // Analytics
    'file' => [...],        // Local backup
]
```
**Result**: Redundant logging across multiple services

## Questions for Maintainer

1. **Scope**: Implement all drivers or start with File only?
   - Recommendation: Start with File (MVP), add others incrementally

2. **Dependencies**: Bundle drivers or separate packages?
   - Recommendation: All in core, use `suggest` in composer.json

3. **Configuration**: Add UI settings or config file only?
   - Recommendation: Config file first, UI in future version

4. **Testing**: Unit tests for all drivers?
   - Recommendation: Mock external services, integration tests optional

5. **Documentation**: Separate file or in README?
   - Recommendation: Separate DRIVERS.md for detailed docs

## Next Steps

1. **Review** this design document
2. **Decide** on implementation scope (all drivers vs. incremental)
3. **Approve** architecture approach
4. **Create** implementation issues/tasks
5. **Implement** in phases per migration path

## Contact

For questions or feedback on this design:
- See full details in [DRIVER_INTERFACE_DESIGN.md](./DRIVER_INTERFACE_DESIGN.md)
- Refer to original issue PLU-25
- Review code examples in design document

---

**Note**: This is a design proposal document. No code has been implemented yet. All code shown is example/suggested implementation.
