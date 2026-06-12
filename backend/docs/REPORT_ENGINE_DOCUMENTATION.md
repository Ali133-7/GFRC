# 📊 Dynamic Report Generation Engine

## Enterprise Architecture Documentation

---

## 🎯 Overview

The Dynamic Report Generation Engine is a production-grade, metadata-driven reporting system designed for enterprise-scale government financial platforms. It enables users to create, execute, and export custom reports without coding.

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        PRESENTATION LAYER                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │ Report      │  │ Dashboard   │  │ Export      │             │
│  │ Builder UI  │  │ Widgets     │  │ Viewer      │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                         API LAYER                                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │ Report      │  │ Chart       │  │ Export      │             │
│  │ Controller  │  │ Controller  │  │ Controller  │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      SERVICE LAYER                               │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │ Report      │  │ Report      │  │ Permission  │             │
│  │ Engine      │  │ Exporter    │  │ Guard       │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │ Query       │  │ Metrics     │  │ Cache       │             │
│  │ Builder     │  │ Engine      │  │ Manager     │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      DATA LAYER                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │ Reports     │  │ Executions  │  │ Permissions │             │
│  │ (Metadata)  │  │ (Audit)     │  │ (RBAC)      │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📦 Core Components

### 1. Report Engine (`ReportEngine.php`)

**Responsibilities:**
- Execute report queries dynamically
- Build SQL from metadata
- Apply filters, aggregations, groupings
- Calculate metrics (SUM, COUNT, AVG, MIN, MAX)
- Manage caching
- Audit logging

**Key Methods:**
```php
executeReport($reportId, $filters, $options)
buildQuery($report, $filters)
calculateAggregations($report, $filters)
generateChartData($reportId, $filters, $chartId)
```

---

### 2. Query Builder

**Security Features:**
- ✅ Parameterized queries (SQL injection prevention)
- ✅ Whitelist-based field validation
- ✅ Type-safe filter operators
- ✅ Row-level security
- ✅ Field-level permissions

**Supported Operations:**
```sql
SELECT, WHERE, GROUP BY, ORDER BY, LIMIT, OFFSET
JOIN (INNER, LEFT, RIGHT)
Aggregations: SUM, COUNT, AVG, MIN, MAX
Custom expressions: (field1 - field2 + field3)
```

---

### 3. Metrics Engine

**Standard Aggregations:**
| Type | Description | Example |
|------|-------------|---------|
| SUM | Sum of values | Total revenue |
| COUNT | Count rows | Number of transactions |
| AVG | Average value | Average payment |
| MIN | Minimum value | Lowest transaction |
| MAX | Maximum value | Highest transaction |

**Custom Expressions:**
```json
{
  "expression": {
    "fields": ["amount", "discount", "tax"],
    "operation": "custom",
    "formula": "(amount - discount + tax)"
  }
}
```

---

## 🗄️ Database Schema

### Core Tables

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `reports` | Report definitions | name, data_source, configuration, visibility |
| `report_fields` | Field configurations | field_name, field_type, formatting |
| `report_filters` | Filter definitions | filter_type, operator, default_value |
| `report_aggregations` | Metrics | aggregation_type, expression |
| `report_groupings` | GROUP BY fields | field_name, show_subtotals |
| `report_charts` | Visualizations | chart_type, configuration |
| `report_executions` | Audit log | filters_applied, execution_time |
| `report_permissions` | Access control | permissionable_type, permission_type |

---

## 🔐 Security & Permissions

### Permission Levels

```
VIEW      - Can see report in list
EXECUTE   - Can run report with filters
EXPORT    - Can export to PDF/Excel/JSON
EDIT      - Can modify report definition
DELETE    - Can delete report
```

### Visibility Types

| Type | Access |
|------|--------|
| `private` | Creator only |
| `shared` | Creator + explicitly shared users |
| `public` | All authenticated users |
| `role` | Specific roles only |
| `department` | Specific departments only |

### Field-Level Security

```json
{
  "field_restrictions": {
    "hidden_fields": ["salary", "bonus"],
    "masked_fields": ["ssn", "bank_account"]
  }
}
```

---

## 📡 API Endpoints

### Report Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports` | List all reports |
| GET | `/api/v1/reports/{id}` | Get report definition |
| POST | `/api/v1/reports` | Create new report |
| PUT | `/api/v1/reports/{id}` | Update report |
| DELETE | `/api/v1/reports/{id}` | Delete report |
| POST | `/api/v1/reports/{id}/publish` | Publish report |
| POST | `/api/v1/reports/{id}/clone` | Clone report |

### Report Execution

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/reports/{id}/execute` | Execute report |
| GET | `/api/v1/reports/{id}/chart/{chartId}` | Get chart data |
| POST | `/api/v1/reports/{id}/export` | Export report |
| GET | `/api/v1/reports/{id}/executions` | Execution history |

### Utilities

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports/fields/available` | Get available fields |
| GET | `/api/v1/reports/download/{filename}` | Download export |

---

## 📊 Example Usage

### Create Report

```bash
POST /api/v1/reports
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Monthly Revenue",
  "data_source": "receipts",
  "fields": [...],
  "filters": [...],
  "aggregations": [...],
  "charts": [...]
}
```

### Execute Report

```bash
POST /api/v1/reports/{id}/execute
Content-Type: application/json

{
  "filters": {
    "date_range": {
      "start": "2026-06-01",
      "end": "2026-06-30"
    },
    "status": "completed"
  },
  "page": 1,
  "per_page": 50
}
```

### Export Report

```bash
POST /api/v1/reports/{id}/export
Content-Type: application/json

{
  "format": "excel",
  "filters": {
    "date_range": {
      "start": "2026-06-01",
      "end": "2026-06-30"
    }
  }
}
```

---

## ⚡ Performance Optimization

### Caching Strategy

```php
// Cache key structure
"report:{reportId}:{filterHash}"

// TTL based on report type
System reports: 3600s (1 hour)
Analytics: 300s (5 minutes)
Custom: 60s (1 minute)
```

### Query Optimization

```sql
-- Indexed columns
CREATE INDEX idx_receipts_created_at ON receipts(created_at);
CREATE INDEX idx_receipts_register_id ON receipts(register_id);
CREATE INDEX idx_receipts_status ON receipts(status);

-- Composite indexes for common filters
CREATE INDEX idx_receipts_date_status 
ON receipts(created_at, status);
```

### Pagination

```
Max rows per query: 10,000
Default page size: 50
Max page size: 100
Cursor-based pagination for large datasets
```

---

## 🧪 Testing Strategy

### Unit Tests
```php
ReportEngineTest::test_execute_report_with_filters()
ReportEngineTest::test_calculate_aggregations()
QueryBuilderTest::test_sql_injection_prevention()
PermissionTest::test_field_level_restrictions()
```

### Integration Tests
```php
ReportApiTest::test_create_and_execute_report()
ExportTest::test_pdf_generation()
CacheTest::test_report_caching()
```

### Performance Tests
```bash
# Load testing with 1000 concurrent users
ab -n 10000 -c 1000 http://localhost/api/v1/reports/{id}/execute

# Expected: <500ms response time for cached reports
# Expected: <2000ms response time for uncached reports
```

---

## 📈 Monitoring & Observability

### Metrics to Track

```
- Report execution count
- Average execution time
- Cache hit ratio
- Export success rate
- Permission denial rate
- Error rate by report type
```

### Logging

```php
// Audit log for every execution
ReportExecution::create([
    'report_id' => $id,
    'user_id' => auth()->id(),
    'filters_applied' => $filters,
    'execution_time_ms' => $time,
    'ip_address' => request()->ip(),
]);
```

---

## 🚀 Deployment Checklist

- [ ] Run migrations
- [ ] Configure cache driver (Redis recommended)
- [ ] Set up database indexes
- [ ] Configure rate limiting
- [ ] Set up monitoring alerts
- [ ] Test with production data volume
- [ ] Verify permissions for all roles
- [ ] Test export functionality (PDF, Excel)
- [ ] Load test with expected concurrent users
- [ ] Document system reports for users

---

## 📚 Best Practices

### DO ✅
- Use parameterized queries
- Implement field-level permissions
- Cache frequently executed reports
- Add database indexes for filter fields
- Log all report executions
- Validate filter inputs
- Use pagination for large datasets

### DON'T ❌
- Hardcode SQL queries
- Allow unrestricted field access
- Execute reports without caching
- Skip audit logging
- Allow unlimited row returns
- Trust user input without validation

---

## 🔧 Extensibility

### Adding Custom Aggregations

```php
class CustomAggregation extends Aggregation
{
    public function calculate($query, $field)
    {
        // Custom logic here
        return $query->selectRaw("SUM({$field}) * 1.15"); // Add 15% tax
    }
}
```

### Adding Custom Chart Types

```php
class RadarChart extends ChartType
{
    public function render($data, $config)
    {
        // Custom rendering logic
    }
}
```

### Adding Data Sources

```php
class ElasticsearchDataSource extends DataSource
{
    public function query($definition)
    {
        // Elasticsearch-specific query logic
    }
}
```

---

## 📞 Support

For enterprise support, contact:
- Technical Lead: [email]
- Documentation: /docs/reports
- Issue Tracker: /issues

---

**Version:** 1.0.0  
**Last Updated:** 2026-06-12  
**Status:** Production Ready ✅
