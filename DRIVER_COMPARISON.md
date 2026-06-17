# Driver Comparison Matrix

## Overview

This document provides a detailed comparison of all proposed audit log drivers to help with implementation prioritization and decision-making.

## Quick Comparison Table

| Feature | File | CloudWatch | HyperDX | Axiom | OpenTelemetry |
|---------|------|------------|---------|-------|---------------|
| **Original Request** | ✅ Yes | ❌ No | ❌ No | ❌ No | ❌ No |
| **Implementation Complexity** | ⭐ Simple | ⭐⭐⭐ Complex | ⭐⭐ Medium | ⭐⭐ Medium | ⭐⭐⭐⭐ Very Complex |
| **External Dependencies** | None | aws-sdk-php | guzzlehttp | guzzlehttp | opentelemetry/sdk |
| **Setup Complexity** | ⭐ Easy | ⭐⭐⭐ Hard | ⭐⭐ Medium | ⭐⭐ Medium | ⭐⭐⭐⭐ Very Hard |
| **Cost** | Free | Paid (AWS) | Paid/Free tier | Paid/Free tier | Varies by backend |
| **Batching Support** | ❌ No | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes |
| **Self-Hosted Option** | ✅ Yes | ❌ No | Limited | ❌ No | ✅ Yes |
| **ELK Integration** | ✅ Native | Via CloudWatch | Via API | Via API | Via Collector |
| **Real-Time** | ✅ Yes | ~1-5s delay | ~1-2s delay | ~1-2s delay | Configurable |
| **Query Capability** | Limited | Good | Excellent | Excellent | Depends on backend |
| **Retention Control** | Manual | Automated | Automated | Automated | Depends on backend |
| **Vendor Lock-in** | ✅ None | ⚠️ AWS only | ⚠️ HyperDX only | ⚠️ Axiom only | ✅ None (standard) |
| **Production Readiness** | ✅ High | ✅ High | ✅ High | ✅ High | ⚠️ Medium |

**Legend**: ⭐ = Complexity level (fewer stars = simpler)

## Detailed Comparison

### 1. File Driver 🏆 RECOMMENDED FOR MVP

**Purpose**: Write audit logs to local file for pickup by log shippers (Filebeat, Logstash)

#### Pros ✅
- **Addresses original request** (PLU-25)
- **Zero external dependencies** (uses Craft's native logging)
- **Simplest implementation** (~100 lines of code)
- **No external service required** (works offline)
- **Easy to test** (just check file output)
- **Perfect for ELK stack** (JSON format ready for Logstash)
- **No cost** (just disk space)
- **Immediate availability** (no network latency)

#### Cons ❌
- **Disk space management** required
- **No built-in search** (need ELK stack)
- **Limited retention** (manual pruning needed)
- **Single server** (no cross-server aggregation without ELK)
- **No real-time alerts** (without external tools)

#### Use Cases
- Development/staging environments
- ELK stack integration (original request)
- Backup logging
- Offline environments
- Cost-sensitive deployments

#### Implementation Effort
- **Code**: ~100 lines
- **Time**: 2-4 hours
- **Testing**: Simple (file I/O)
- **Documentation**: Minimal

#### Recommendation
⭐⭐⭐⭐⭐ **IMPLEMENT FIRST** - Addresses original request, simplest, no dependencies

---

### 2. CloudWatch Driver ☁️

**Purpose**: Send audit logs to AWS CloudWatch Logs for AWS infrastructure

#### Pros ✅
- **Native AWS integration** (works with AWS ecosystem)
- **Managed service** (no infrastructure to maintain)
- **Good search/query** (CloudWatch Insights)
- **Automated retention** (configurable)
- **IAM integration** (secure authentication)
- **Batching support** (efficient)
- **Alerting** (CloudWatch Alarms)
- **Cross-region** (multi-region support)

#### Cons ❌
- **AWS dependency** (requires AWS account)
- **Cost** (pay per GB ingested + storage)
- **Complex setup** (IAM roles, policies)
- **Large dependency** (aws-sdk-php)
- **Vendor lock-in** (AWS only)
- **Learning curve** (AWS knowledge required)

#### Use Cases
- Production deployments on AWS
- Multi-service AWS architectures
- Compliance requirements (AWS audit)
- Organizations already using CloudWatch

#### Implementation Effort
- **Code**: ~250 lines
- **Time**: 1-2 days
- **Testing**: Medium (mock AWS SDK)
- **Documentation**: Extensive (IAM setup)

#### Cost Estimation
- Ingestion: $0.50/GB
- Storage: $0.03/GB/month
- Example: 1GB/day = ~$15-20/month

#### Recommendation
⭐⭐⭐ **IMPLEMENT IF**: Users request AWS integration

---

### 3. HyperDX Driver 🔍

**Purpose**: Modern observability platform with excellent search and visualization

#### Pros ✅
- **Modern UI** (excellent developer experience)
- **Fast search** (near real-time)
- **Good free tier** (generous limits)
- **OTLP support** (standards-based)
- **Easy setup** (just API key)
- **Batching** (efficient)
- **Good documentation**
- **Active development**

#### Cons ❌
- **External service** (requires internet)
- **Newer platform** (less mature than others)
- **Vendor lock-in** (HyperDX specific)
- **Cost at scale** (can get expensive)
- **HTTP dependency** (guzzlehttp required)

#### Use Cases
- Modern development teams
- Startups with free tier
- Need good search/visualization
- Cross-platform logging

#### Implementation Effort
- **Code**: ~200 lines
- **Time**: 4-6 hours
- **Testing**: Medium (mock HTTP)
- **Documentation**: Moderate

#### Cost Estimation
- Free tier: 500MB/day
- Paid: $0.30/GB
- Example: 1GB/day = ~$9/month

#### Recommendation
⭐⭐⭐ **IMPLEMENT IF**: Users want modern observability

---

### 4. Axiom Driver 📊

**Purpose**: High-performance analytics platform for log data

#### Pros ✅
- **High performance** (optimized for analytics)
- **Large batch support** (1000+ events)
- **Good free tier** (500MB/day)
- **Fast ingestion** (efficient API)
- **Query language** (APL - powerful)
- **Retention** (long retention periods)
- **Dashboards** (built-in visualization)

#### Cons ❌
- **External service** (requires internet)
- **Learning curve** (APL query language)
- **Vendor lock-in** (Axiom specific)
- **Cost at scale** (usage-based pricing)
- **HTTP dependency** (guzzlehttp required)

#### Use Cases
- Analytics-focused use cases
- High-volume logging
- Long retention requirements
- Query-heavy workloads

#### Implementation Effort
- **Code**: ~180 lines
- **Time**: 4-6 hours
- **Testing**: Medium (mock HTTP)
- **Documentation**: Moderate

#### Cost Estimation
- Free tier: 500MB/day
- Paid: $0.25/GB
- Example: 1GB/day = ~$7.50/month

#### Recommendation
⭐⭐ **IMPLEMENT IF**: Users need analytics features

---

### 5. OpenTelemetry Driver 🌐

**Purpose**: Standards-based observability for vendor-neutral logging

#### Pros ✅
- **Vendor neutral** (works with many backends)
- **Industry standard** (CNCF project)
- **Flexible** (multiple exporters)
- **Future-proof** (standard protocol)
- **Rich context** (attributes, resources)
- **Self-hosted option** (OTel Collector)
- **Wide adoption** (many tools support it)

#### Cons ❌
- **Complex setup** (requires OTel Collector)
- **Large dependency** (opentelemetry/sdk)
- **Configuration** (more setup required)
- **Learning curve** (OTLP concepts)
- **Resource usage** (heavier than simple HTTP)
- **Debugging** (harder to troubleshoot)

#### Use Cases
- Multi-vendor environments
- Standards-focused organizations
- Self-hosted observability
- Already using OpenTelemetry

#### Implementation Effort
- **Code**: ~300 lines
- **Time**: 2-3 days
- **Testing**: Complex (mock OTLP)
- **Documentation**: Extensive

#### Recommendation
⭐⭐ **IMPLEMENT IF**: Users want vendor neutrality or already use OpenTelemetry

---

## Implementation Priority Recommendation

### Phase 1: MVP (Week 1-2)
1. **File Driver** ⭐⭐⭐⭐⭐
   - Addresses original request
   - Simplest implementation
   - No dependencies
   - Immediate value

### Phase 2: Cloud Services (Week 3-6)
2. **CloudWatch Driver** ⭐⭐⭐
   - Many Craft sites on AWS
   - Production-ready
   - Good ROI

3. **HyperDX Driver** ⭐⭐⭐
   - Modern alternative
   - Good free tier
   - Developer-friendly

### Phase 3: Advanced (Week 7-10)
4. **Axiom Driver** ⭐⭐
   - Analytics use cases
   - High performance
   - Optional enhancement

5. **OpenTelemetry Driver** ⭐⭐
   - Standards compliance
   - Vendor neutrality
   - Future-proofing

### Phase 4: Polish (Week 11-12)
- Documentation updates
- Performance optimization
- Security review
- Community feedback

## Deployment Scenarios

### Scenario 1: Small Site (1-10 users)
**Recommendation**: File Driver only
- **Why**: Simple, free, sufficient
- **Configuration**: Log to file → manual review when needed
- **Cost**: $0/month

### Scenario 2: Medium Site on AWS (10-100 users)
**Recommendation**: File + CloudWatch
- **Why**: AWS integration, good search, alerts
- **Configuration**: File for backup, CloudWatch for production
- **Cost**: ~$10-30/month

### Scenario 3: Large Site (100+ users)
**Recommendation**: File + CloudWatch + HyperDX
- **Why**: Redundancy, different use cases
- **Configuration**: 
  - File: Local backup
  - CloudWatch: AWS integration, alerting
  - HyperDX: Developer access, fast search
- **Cost**: ~$30-100/month

### Scenario 4: Enterprise Multi-Cloud
**Recommendation**: File + OpenTelemetry
- **Why**: Vendor neutral, flexible backends
- **Configuration**: File backup + OTLP to collector → multiple backends
- **Cost**: Varies by backend choice

### Scenario 5: Startup/Free Tier
**Recommendation**: File + HyperDX
- **Why**: Free tier coverage, modern UI
- **Configuration**: File primary, HyperDX for search/alerts
- **Cost**: $0-9/month

## Decision Matrix

### Choose File Driver When:
- ✅ Budget is limited ($0)
- ✅ Already using ELK stack
- ✅ Need simple backup logging
- ✅ Offline/air-gapped environment
- ✅ Development/staging only

### Choose CloudWatch Driver When:
- ✅ Running on AWS infrastructure
- ✅ Need AWS ecosystem integration
- ✅ Want managed service
- ✅ Have budget for AWS services
- ✅ Need compliance/audit trail

### Choose HyperDX Driver When:
- ✅ Want modern developer experience
- ✅ Need fast, easy search
- ✅ Qualify for free tier
- ✅ Small to medium logging volume
- ✅ Like OTLP-based solutions

### Choose Axiom Driver When:
- ✅ Need analytics on logs
- ✅ Have high logging volume
- ✅ Need long retention
- ✅ Want powerful query language
- ✅ Like generous free tier

### Choose OpenTelemetry Driver When:
- ✅ Want vendor neutrality
- ✅ Already using OpenTelemetry
- ✅ Need flexibility in backends
- ✅ Standards compliance required
- ✅ Self-hosted preference

## Cost Comparison (1GB/day = 30GB/month)

| Driver | Setup Cost | Monthly Cost | Annual Cost |
|--------|------------|--------------|-------------|
| **File** | $0 | $0* | $0* |
| **CloudWatch** | $0 | ~$15-20 | ~$180-240 |
| **HyperDX** | $0 | ~$0-9** | ~$0-108** |
| **Axiom** | $0 | ~$0-7.50** | ~$0-90** |
| **OpenTelemetry** | $0-500*** | Varies | Varies |

\* Disk space only  
\*\* Free tier covers 500MB/day (15GB/month)  
\*\*\* Self-hosted infrastructure cost

## Community Value Assessment

### File Driver
- **User Demand**: 🔥🔥🔥🔥🔥 Very High (original request)
- **Use Cases**: 🌍 Universal (all users can benefit)
- **Maintenance**: 💚 Low (simple, stable)
- **Documentation**: 📚 Easy (basic file logging)

### CloudWatch Driver
- **User Demand**: 🔥🔥🔥🔥 High (many AWS users)
- **Use Cases**: ☁️ AWS-specific (30-40% of users)
- **Maintenance**: 💛 Medium (AWS SDK updates)
- **Documentation**: 📚📚 Moderate (AWS setup)

### HyperDX Driver
- **User Demand**: 🔥🔥🔥 Medium (modern developers)
- **Use Cases**: 🎯 Targeted (tech-savvy teams)
- **Maintenance**: 💚 Low (stable API)
- **Documentation**: 📚 Easy (simple setup)

### Axiom Driver
- **User Demand**: 🔥🔥 Medium-Low (analytics users)
- **Use Cases**: 📊 Niche (analytics-focused)
- **Maintenance**: 💚 Low (stable API)
- **Documentation**: 📚 Easy (similar to HyperDX)

### OpenTelemetry Driver
- **User Demand**: 🔥🔥 Medium-Low (standards-focused)
- **Use Cases**: 🏢 Enterprise (large organizations)
- **Maintenance**: ❤️ High (complex, evolving standard)
- **Documentation**: 📚📚📚 Extensive (OTLP setup)

## Final Recommendation

### Minimal Viable Product (MVP)
**Implement**: File Driver only
- **Time**: 1-2 weeks
- **Effort**: Low
- **Value**: High
- **Risk**: Very Low

### Recommended Full Implementation
**Phase 1**: File Driver (Week 1-2)
**Phase 2**: CloudWatch Driver (Week 3-4)
**Phase 3**: Documentation & Polish (Week 5-6)
**Future**: HyperDX, Axiom, OpenTelemetry (based on user demand)

### Reasoning
1. **File Driver** solves original request (PLU-25)
2. **CloudWatch Driver** covers largest production use case (AWS)
3. **Other drivers** can be added based on actual user requests
4. **Focus on quality** over quantity
5. **Minimize maintenance burden**

---

## Appendix: Technical Specifications

### File Driver
- **Dependencies**: None (uses Craft's Logger)
- **Code Size**: ~100 lines
- **Test Coverage**: >90% achievable
- **Performance**: Synchronous write (negligible impact)

### CloudWatch Driver
- **Dependencies**: aws/aws-sdk-php (~20MB)
- **Code Size**: ~250 lines
- **Test Coverage**: ~80% (mock AWS)
- **Performance**: Async batch (5-10ms per batch)

### HyperDX Driver
- **Dependencies**: guzzlehttp/guzzle (~2MB)
- **Code Size**: ~200 lines
- **Test Coverage**: ~85% (mock HTTP)
- **Performance**: Async batch (5-10ms per batch)

### Axiom Driver
- **Dependencies**: guzzlehttp/guzzle (~2MB)
- **Code Size**: ~180 lines
- **Test Coverage**: ~85% (mock HTTP)
- **Performance**: Async batch (5-10ms per batch)

### OpenTelemetry Driver
- **Dependencies**: open-telemetry/sdk (~15MB)
- **Code Size**: ~300 lines
- **Test Coverage**: ~70% (complex mocking)
- **Performance**: Varies by exporter

---

**Last Updated**: 2026-01-11

**Maintainer**: Review this comparison when deciding implementation priorities

**Community**: Use this to understand trade-offs and request specific drivers
