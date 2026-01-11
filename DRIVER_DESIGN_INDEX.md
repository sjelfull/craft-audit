# Driver-Based Logging System Design - Index

## 📋 Overview

This directory contains the complete design documentation for implementing a driver-based logging system for the Craft Audit plugin, addressing feature request [PLU-25] for optional logging to external services beyond the database.

## 📁 Documentation Files

### 1. [DRIVER_INTERFACE_SUMMARY.md](./DRIVER_INTERFACE_SUMMARY.md) - **START HERE**
   - **What**: Executive summary with key decisions and architecture overview
   - **Who**: Project maintainers, decision-makers
   - **Size**: ~360 lines, 10-minute read
   - **Contains**: 
     - Quick overview of the solution
     - Driver comparison table
     - Simple configuration examples
     - Implementation checklist
     - Questions for maintainer

### 2. [DRIVER_INTERFACE_DESIGN.md](./DRIVER_INTERFACE_DESIGN.md) - **DETAILED SPEC**
   - **What**: Complete technical specification with production-ready code examples
   - **Who**: Developers implementing the feature
   - **Size**: ~1,370 lines, 45-minute read
   - **Contains**:
     - Complete interface definitions
     - 5 fully-implemented driver examples:
       - FileAuditDriver (for ELK stack)
       - CloudWatchAuditDriver (AWS)
       - HyperDXAuditDriver (modern observability)
       - AxiomAuditDriver (analytics)
       - OpenTelemetryAuditDriver (standards-based)
     - DriverManager service implementation
     - Snapshot filtering examples
     - Configuration examples (simple → advanced)
     - Integration points in existing code
     - Testing strategy
     - Migration path

### 3. [ARCHITECTURE_DIAGRAMS.md](./ARCHITECTURE_DIAGRAMS.md) - **VISUAL GUIDE**
   - **What**: ASCII diagrams showing system architecture and data flow
   - **Who**: All stakeholders (visual learners)
   - **Size**: ~390 lines, 15-minute browse
   - **Contains**:
     - System flow diagram
     - Driver interface hierarchy
     - Configuration flow
     - Logging event data flow
     - Snapshot filtering visualization
     - Error handling flow (isolation)
     - Shutdown flow (batch flushing)

## 🎯 Original Issue: [PLU-25]

**Request**: Add support for optional logging to file for ELK stack integration

**Concerns raised**:
- Don't log whole serialized snapshot (too large)
- Pruning won't work for file logs
- Need to be optional (disabled by default)

**Our solution**: 
- Extensible driver system supporting multiple destinations
- Configurable snapshot filtering
- Opt-in via configuration
- Documentation about pruning limitations

## 🚀 Quick Start for Reviewers

### For Decision-Makers (5 minutes)
1. Read: [DRIVER_INTERFACE_SUMMARY.md](./DRIVER_INTERFACE_SUMMARY.md) - Sections 1-3
2. Review: Driver comparison table
3. Check: "Questions for Maintainer" section

### For Technical Reviewers (20 minutes)
1. Read: [DRIVER_INTERFACE_SUMMARY.md](./DRIVER_INTERFACE_SUMMARY.md) - Full document
2. Browse: [ARCHITECTURE_DIAGRAMS.md](./ARCHITECTURE_DIAGRAMS.md) - Visual overview
3. Scan: [DRIVER_INTERFACE_DESIGN.md](./DRIVER_INTERFACE_DESIGN.md) - Interface section

### For Implementers (60 minutes)
1. Read: [DRIVER_INTERFACE_DESIGN.md](./DRIVER_INTERFACE_DESIGN.md) - Complete document
2. Study: Code examples for FileAuditDriver (simplest implementation)
3. Reference: [ARCHITECTURE_DIAGRAMS.md](./ARCHITECTURE_DIAGRAMS.md) - Data flow diagrams
4. Review: "Integration with Existing Code" section

## 🏗️ Architecture at a Glance

```
AuditService._saveRecord()
    ↓
    Save to Database (existing, unchanged)
    ↓
    DriverManager.distributeLog() [NEW]
    ↓
    ├─→ FileDriver → Log File → ELK Stack
    ├─→ CloudWatchDriver → AWS CloudWatch
    ├─→ HyperDXDriver → HyperDX API
    ├─→ AxiomDriver → Axiom API
    └─→ OpenTelemetryDriver → OTLP Endpoint
```

## 📝 Key Design Decisions

### ✅ Interface-Based Design
- `AuditDriverInterface` defines contract
- `BaseAuditDriver` provides common functionality
- Easy to extend with custom drivers

### ✅ Multiple Simultaneous Drivers
- Enable file logging + CloudWatch + HyperDX at same time
- Each driver operates independently
- Failed drivers don't affect others or database

### ✅ Snapshot Filtering
- Global filter function removes sensitive data
- Configurable per environment
- Default implementation keeps only safe metadata

### ✅ Configuration-Driven
- Enable/disable via config file
- No code changes needed
- Environment-aware (dev vs. production)

### ✅ Backward Compatible
- Opt-in feature
- No breaking changes
- Existing functionality unchanged

## 🎓 Driver Examples Explained

### File Driver (Simplest - Original Request)
```php
'drivers' => [
    'file' => [
        'class' => FileAuditDriver::class,
        'enabled' => true,
        'format' => 'json',  // For ELK/Logstash
    ],
]
```
**Use case**: Send logs to ELK stack via Filebeat/Logstash

### CloudWatch Driver (Production AWS)
```php
'cloudwatch' => [
    'class' => CloudWatchAuditDriver::class,
    'enabled' => getenv('ENV') === 'production',
    'logGroup' => '/craft/audit',
    'batchSize' => 10,
]
```
**Use case**: Centralized logging in AWS infrastructure

### HyperDX Driver (Modern Observability)
```php
'hyperdx' => [
    'class' => HyperDXAuditDriver::class,
    'apiKey' => '$HYPERDX_API_KEY',
    'service' => 'craft-cms',
]
```
**Use case**: Full observability with modern platform

### Axiom Driver (Analytics)
```php
'axiom' => [
    'class' => AxiomAuditDriver::class,
    'dataset' => 'craft-audit',
    'batchSize' => 1000,
]
```
**Use case**: High-volume log analytics

### OpenTelemetry Driver (Standards-Based)
```php
'opentelemetry' => [
    'class' => OpenTelemetryAuditDriver::class,
    'exporter' => 'otlp',
    'endpoint' => 'http://collector:4318/v1/logs',
]
```
**Use case**: Vendor-neutral observability (works with many platforms)

## 🛠️ Implementation Checklist

### Phase 1: Core Infrastructure (MVP)
- [ ] Create `src/drivers/AuditDriverInterface.php`
- [ ] Create `src/drivers/BaseAuditDriver.php`
- [ ] Create `src/services/DriverManager.php`
- [ ] Modify `src/Audit.php` to register DriverManager
- [ ] Modify `src/services/AuditService.php` (_saveRecord method)
- [ ] Update `src/models/Settings.php` (add drivers config)
- [ ] Add unit tests

### Phase 2: File Driver (Original Request)
- [ ] Create `src/drivers/FileAuditDriver.php`
- [ ] Add configuration examples to `src/config.php`
- [ ] Add file driver tests
- [ ] Update README.md with file logging examples
- [ ] Test with Logstash/ELK stack

### Phase 3: Cloud Drivers (Optional)
- [ ] Create `src/drivers/CloudWatchAuditDriver.php`
- [ ] Create `src/drivers/HyperDXAuditDriver.php`
- [ ] Create `src/drivers/AxiomAuditDriver.php`
- [ ] Add driver tests
- [ ] Update composer.json (suggest section)

### Phase 4: Standards Support (Optional)
- [ ] Create `src/drivers/OpenTelemetryAuditDriver.php`
- [ ] Add advanced examples
- [ ] Create DRIVERS.md documentation

### Phase 5: Polish
- [ ] Add snapshot filter examples
- [ ] Create migration guide
- [ ] Performance testing
- [ ] Security review

## 🔐 Security Considerations

### ✅ Snapshot Filtering
**Problem**: Don't want to send sensitive field data to external services

**Solution**: Configurable filter function
```php
'snapshotFilter' => function($snapshot, $model) {
    // Remove sensitive fields
    unset($snapshot['content']);
    unset($snapshot['customSensitiveField']);
    return $snapshot;
}
```

### ✅ Environment Variables
**Problem**: Don't want API keys in config files

**Solution**: Use Craft's environment variable parsing
```php
'apiKey' => '$HYPERDX_API_KEY',  // Uses getenv()
'secret' => '$AWS_SECRET_KEY',
```

### ✅ Error Isolation
**Problem**: Failed driver shouldn't break audit logging

**Solution**: Try-catch around each driver, continue on error
```php
foreach ($drivers as $driver) {
    try {
        $driver->log($model, $context);
    } catch (Exception $e) {
        Craft::error($e->getMessage());
        // Continue with other drivers
    }
}
```

## ❓ Questions for Maintainer

See [DRIVER_INTERFACE_SUMMARY.md](./DRIVER_INTERFACE_SUMMARY.md) "Questions for Maintainer" section for:

1. Implementation scope (all drivers vs. incremental)
2. Dependency strategy (bundled vs. separate packages)
3. UI configuration (settings screen vs. config file only)
4. Testing requirements
5. Documentation approach

## 📚 Related Documentation

- **Original Issue**: PLU-25 - Support for optional logging to file
- **Craft Documentation**: https://craftcms.com/docs
- **Craft Logging**: https://craftcms.com/docs/4.x/extend/logging.html

## 🤝 Contributing

This is a design proposal. To contribute:

1. Review the documentation files
2. Provide feedback on GitHub issue PLU-25
3. Suggest improvements or additional drivers
4. Share production use cases

## 📞 Support

For questions about this design:
- Comment on the GitHub pull request
- Reference specific sections in your feedback
- Suggest alternative approaches with rationale

---

**Status**: 📄 Design Phase - Awaiting Review and Approval

**Created**: 2026-01-11

**Last Updated**: 2026-01-11

**Design by**: GitHub Copilot (based on issue [PLU-25] and maintainer comments)
