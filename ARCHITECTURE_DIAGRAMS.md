# Driver Architecture Diagram

## System Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                         Craft CMS                               │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │              AuditService (Existing)                      │ │
│  │                                                           │ │
│  │  - onSaveElement()                                        │ │
│  │  - onDeleteElement()                                      │ │
│  │  - onLogin()                                              │ │
│  │  - _saveRecord() ◄─────────────────┐                     │ │
│  └────────────────┬──────────────────┘│                     │ │
│                   │                    │                     │ │
│                   │                    │                     │ │
│                   ▼                    │                     │ │
│  ┌─────────────────────────────────┐  │                     │ │
│  │   Database (Primary Storage)    │  │                     │ │
│  │   ┌─────────────────────────┐   │  │                     │ │
│  │   │   audit_log table       │   │  │                     │ │
│  │   │   - id                  │   │  │                     │ │
│  │   │   - event               │   │  │                     │ │
│  │   │   - userId              │   │  │                     │ │
│  │   │   - elementId           │   │  │                     │ │
│  │   │   - snapshot            │   │  │                     │ │
│  │   │   - ...                 │   │  │                     │ │
│  │   └─────────────────────────┘   │  │                     │ │
│  └─────────────────────────────────┘  │                     │ │
│                                        │                     │ │
│                   ┌────────────────────┘                     │ │
│                   │ NEW: Driver Distribution                 │ │
│                   ▼                                          │ │
│  ┌────────────────────────────────────────────────────────┐ │ │
│  │           DriverManager (NEW)                          │ │ │
│  │                                                        │ │ │
│  │  - distributeLog(model)                               │ │ │
│  │  - filterSnapshot(model)                              │ │ │
│  │  - shutdown()                                         │ │ │
│  │                                                        │ │ │
│  │  ┌──────────────────────────────────────────────────┐ │ │ │
│  │  │  Snapshot Filter (Configurable)                  │ │ │ │
│  │  │  - Remove large/sensitive data                   │ │ │ │
│  │  │  - Keep only safe metadata                       │ │ │ │
│  │  └──────────────────────────────────────────────────┘ │ │ │
│  └───────┬────────────┬──────────────┬─────────────┬─────┘ │ │
│          │            │              │             │       │ │
│          │            │              │             │       │ │
└──────────┼────────────┼──────────────┼─────────────┼───────┘ │
           │            │              │             │         │
           │            │              │             │         │
           ▼            ▼              ▼             ▼         │
    ┌──────────┐ ┌──────────┐  ┌──────────┐  ┌──────────┐   │
    │   File   │ │CloudWatch│  │ HyperDX  │  │  Axiom   │   │
    │  Driver  │ │  Driver  │  │  Driver  │  │  Driver  │   │
    └────┬─────┘ └────┬─────┘  └────┬─────┘  └────┬─────┘   │
         │            │              │             │         │
         │            │              │             │         │
         ▼            ▼              ▼             ▼         │
    ┌──────────┐ ┌──────────┐  ┌──────────┐  ┌──────────┐   │
    │   Log    │ │   AWS    │  │ HyperDX  │  │  Axiom   │   │
    │   File   │ │CloudWatch│  │   API    │  │   API    │   │
    │ (JSON)   │ │   Logs   │  │          │  │          │   │
    └────┬─────┘ └──────────┘  └──────────┘  └──────────┘   │
         │                                                    │
         ▼                                                    │
    ┌──────────┐                                             │
    │Filebeat/ │                                             │
    │Logstash  │                                             │
    └────┬─────┘                                             │
         │                                                    │
         ▼                                                    │
    ┌──────────┐                                             │
    │   ELK    │                                             │
    │  Stack   │                                             │
    └──────────┘                                             │
```

## Driver Interface Architecture

```
┌────────────────────────────────────────────────────────────┐
│         AuditDriverInterface (Interface)                   │
│                                                            │
│  + init(config: array): void                              │
│  + isEnabled(): bool                                      │
│  + log(model: AuditModel, context: array): bool           │
│  + logBatch(entries: array): bool                         │
│  + getName(): string                                      │
│  + getVersion(): string                                   │
│  + shutdown(): void                                       │
└───────────────────────┬────────────────────────────────────┘
                        │
                        │ implements
                        │
        ┌───────────────┴───────────────┐
        │                               │
        ▼                               ▼
┌────────────────────┐       ┌──────────────────────┐
│ BaseAuditDriver    │       │ Custom Driver        │
│  (Abstract)        │       │  (User-defined)      │
│                    │       │                      │
│ + formatLogEntry() │       │ + implement all      │
│ + logError()       │       │   interface methods  │
│ + default logBatch()│       │                      │
└────────┬───────────┘       └──────────────────────┘
         │
         │ extends
         │
         ├────────────┬────────────┬────────────┬─────────────┐
         │            │            │            │             │
         ▼            ▼            ▼            ▼             ▼
┌──────────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌──────────┐
│ FileAudit    │ │CloudWatch│ │HyperDX  │ │ Axiom   │ │OpenTelem-│
│ Driver       │ │Audit     │ │Audit    │ │Audit    │ │etryAudit │
│              │ │Driver    │ │Driver   │ │Driver   │ │Driver    │
│              │ │          │ │         │ │         │ │          │
│ - JSON/text  │ │ - AWS SDK│ │ - HTTP  │ │ - HTTP  │ │ - OTLP   │
│ - Craft log  │ │ - Batch  │ │ - Batch │ │ - Batch │ │ - Std    │
│ - Rotation   │ │          │ │         │ │         │ │          │
└──────────────┘ └─────────┘ └─────────┘ └─────────┘ └──────────┘
```

## Configuration Flow

```
┌──────────────────────────────────────────────────────────────┐
│  config/audit.php                                            │
│                                                              │
│  return [                                                    │
│    'pruneDays' => 30,                                       │
│    'enabled' => true,                                       │
│                                                              │
│    'drivers' => [                                           │
│      'file' => [                                            │
│        'class' => FileAuditDriver::class,                  │
│        'enabled' => true,                                   │
│        'format' => 'json',                                  │
│        ...                                                  │
│      ],                                                     │
│      'cloudwatch' => [                                      │
│        'class' => CloudWatchAuditDriver::class,            │
│        'enabled' => getenv('ENV') === 'production',        │
│        ...                                                  │
│      ],                                                     │
│    ],                                                       │
│                                                              │
│    'snapshotFilter' => function($snapshot, $model) {       │
│      unset($snapshot['content']);                          │
│      return $snapshot;                                      │
│    },                                                       │
│  ];                                                         │
└──────────┬───────────────────────────────────────────────────┘
           │
           │ loaded by
           ▼
┌──────────────────────────────────────────────────────────────┐
│  Settings Model                                              │
│                                                              │
│  public array $drivers = [];                                │
│  public $snapshotFilter = null;                             │
└──────────┬───────────────────────────────────────────────────┘
           │
           │ used by
           ▼
┌──────────────────────────────────────────────────────────────┐
│  DriverManager::init()                                       │
│                                                              │
│  1. Read $settings->drivers config                          │
│  2. For each driver config:                                 │
│     a. Create driver instance                               │
│     b. Call driver->init(config)                            │
│     c. Check driver->isEnabled()                            │
│     d. Store active drivers                                 │
│  3. Setup snapshotFilter                                    │
└──────────────────────────────────────────────────────────────┘
```

## Data Flow - Logging Event

```
User Action (e.g., Save Entry)
        │
        ▼
┌──────────────────────────────────┐
│ AuditService::onSaveElement()    │
│                                  │
│ 1. Create AuditModel             │
│ 2. Add snapshot data             │
│ 3. Call _saveRecord(model)       │
└──────────┬───────────────────────┘
           │
           ▼
┌──────────────────────────────────┐
│ AuditService::_saveRecord()      │
│                                  │
│ 1. Save to database ✓            │
│ 2. Get record ID                 │
│ 3. Distribute to drivers → → → →│
└──────────┬───────────────────────┘
           │
           ▼
┌────────────────────────────────────────────────┐
│ DriverManager::distributeLog(model)            │
│                                                │
│ 1. Filter snapshot:                            │
│    - Call snapshotFilter(model)                │
│    - Remove sensitive/large data               │
│    - Keep safe metadata                        │
│                                                │
│ 2. For each active driver:                    │
│    - driver.log(model, filteredContext)        │
│    - Catch & log errors (don't throw)         │
└────┬──────────┬──────────┬──────────┬──────────┘
     │          │          │          │
     │          │          │          │
     ▼          ▼          ▼          ▼
┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐
│ File   │ │CloudWch│ │HyperDX │ │ Axiom  │
│ Driver │ │ Driver │ │ Driver │ │ Driver │
│        │ │        │ │        │ │        │
│ Write  │ │ Batch  │ │ HTTP   │ │ HTTP   │
│ to Log │ │ Buffer │ │ POST   │ │ POST   │
└────────┘ └────────┘ └────────┘ └────────┘
     │          │          │          │
     │          │          │          │
     ▼          ▼          ▼          ▼
┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐
│  Log   │ │  AWS   │ │HyperDX │ │ Axiom  │
│  File  │ │CloudWch│ │Platform│ │Platform│
└────────┘ └────────┘ └────────┘ └────────┘

All steps happen synchronously
Errors in drivers don't affect database save
```

## Snapshot Filtering Example

```
┌─────────────────────────────────────────────────────┐
│  Original Snapshot (from AuditModel)                │
│                                                     │
│  {                                                  │
│    "elementId": 123,                               │
│    "elementType": "craft\\elements\\Entry",        │
│    "elementTypeLabel": "Entry",                    │
│    "title": "My Blog Post",                        │
│    "userId": 5,                                    │
│    "content": {                  ◄── LARGE DATA    │
│      "field1": "...",                              │
│      "field2": "...",                              │
│      "richText": "...5000 chars...",              │
│      "matrix": [ /* huge array */ ]                │
│    },                                              │
│    "snapshot": { /* internal state */ }            │
│  }                                                  │
└──────────────────┬──────────────────────────────────┘
                   │
                   │ Apply snapshotFilter
                   │
                   ▼
┌─────────────────────────────────────────────────────┐
│  Filtered Context (sent to drivers)                 │
│                                                     │
│  {                                                  │
│    "elementId": 123,                               │
│    "elementType": "craft\\elements\\Entry",        │
│    "elementTypeLabel": "Entry",                    │
│    "title": "My Blog Post",                        │
│    "userId": 5                                     │
│  }                                                  │
│                                                     │
│  ✓ Safe metadata only                              │
│  ✓ No field content                                │
│  ✓ Small, indexable                                │
└─────────────────────────────────────────────────────┘
```

## Error Handling Flow

```
┌──────────────────────────────────────────────────┐
│  AuditService::_saveRecord()                     │
│                                                  │
│  try {                                           │
│    1. Save to database                           │
│    2. Call driverManager.distributeLog()         │
│    return true                                   │
│  } catch (Exception $e) {                        │
│    Craft::error(...)                            │
│    return false                                  │
│  }                                               │
└──────────────────┬───────────────────────────────┘
                   │
                   │ Database save succeeded
                   │
                   ▼
┌──────────────────────────────────────────────────┐
│  DriverManager::distributeLog()                  │
│                                                  │
│  foreach (drivers as driver) {                   │
│    try {                                         │
│      driver.log(model, context)                  │
│    } catch (Exception $e) {                      │
│      // Log error but continue  ◄── ISOLATED    │
│      Craft::error(...)                          │
│    }                                             │
│  }                                               │
└──────────┬────────────┬──────────────────────────┘
           │            │
           │            │ Each driver isolated
           │            │
           ▼            ▼
    ┌──────────┐  ┌──────────┐
    │ Driver 1 │  │ Driver 2 │
    │ Success  │  │ Failed   │
    └──────────┘  └────┬─────┘
                       │
                       │ Error logged
                       │ Other drivers continue
                       ▼
                  ┌──────────┐
                  │ Driver 3 │
                  │ Success  │
                  └──────────┘

Result: Database save always succeeds
        Drivers are best-effort
        No cascading failures
```

## Shutdown Flow

```
┌──────────────────────────────────────────────────┐
│  Application Shutdown                            │
└──────────────────┬───────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────┐
│  Audit::beforeUnload()                           │
│                                                  │
│  if (has('driverManager')) {                     │
│    driverManager->shutdown()                     │
│  }                                               │
└──────────────────┬───────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────┐
│  DriverManager::shutdown()                       │
│                                                  │
│  foreach (drivers as driver) {                   │
│    try {                                         │
│      driver->shutdown()                          │
│    } catch (Exception $e) {                      │
│      Craft::error(...)                          │
│    }                                             │
│  }                                               │
└──────────┬────────┬────────┬────────────────────┘
           │        │        │
           ▼        ▼        ▼
    ┌──────────┬──────────┬──────────┐
    │CloudWatch│ HyperDX  │  Axiom   │
    │  Driver  │  Driver  │  Driver  │
    │          │          │          │
    │ Flush    │ Flush    │ Flush    │
    │ Buffer   │ Buffer   │ Buffer   │
    └────┬─────┴────┬─────┴────┬─────┘
         │          │          │
         ▼          ▼          ▼
    ┌──────────┬──────────┬──────────┐
    │   AWS    │ HyperDX  │  Axiom   │
    │CloudWatch│   API    │   API    │
    │   Logs   │          │          │
    └──────────┴──────────┴──────────┘

All buffered logs are flushed
Graceful cleanup
```

## Key Design Principles Illustrated

1. **Single Responsibility**: Each driver handles one destination
2. **Open/Closed**: Easy to add new drivers, no modification needed
3. **Dependency Inversion**: Depend on interface, not concrete classes
4. **Error Isolation**: Driver failures don't cascade
5. **Configuration-Driven**: No code changes to enable/disable drivers
6. **Data Protection**: Snapshot filtering before external transmission
7. **Performance**: Batching support for high-volume scenarios
8. **Graceful Degradation**: Continue with available drivers if some fail
