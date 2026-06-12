# 🚀 Report Engine - Quick Start Guide

## ✅ Installation Complete

All dependencies are now installed and configured:
- ✅ PHPSpreadsheet (Excel export)
- ✅ GD Extension (image processing)
- ✅ Zip Extension (Excel file creation)
- ✅ Report Engine Service
- ✅ API Controllers
- ✅ Database Migrations

---

## 📋 Step-by-Step Setup

### 1. Run Database Migrations

```bash
cd C:\Users\Ali\Desktop\CCS\backend
php artisan migrate
```

This creates 8 tables:
- `reports` - Main report definitions
- `report_fields` - Field configurations
- `report_filters` - Filter definitions
- `report_aggregations` - Metrics
- `report_groupings` - GROUP BY fields
- `report_charts` - Chart configurations
- `report_executions` - Audit log
- `report_permissions` - Access control

---

### 2. Create Your First Report

**Option A: Via API**

```bash
POST http://localhost:8000/api/v1/reports
Authorization: Bearer {your-token}
Content-Type: application/json

{
  "name": "Daily Revenue Report",
  "name_ar": "تقرير الإيرادات اليومية",
  "data_source": "receipts",
  "visibility": "shared",
  "fields": [
    {
      "field_name": "transaction_number",
      "field_label": "Transaction Number",
      "field_label_ar": "رقم المعاملة",
      "field_type": "string",
      "is_visible": true,
      "is_filterable": true,
      "is_sortable": true,
      "sort_order": 1
    },
    {
      "field_name": "amount",
      "field_label": "Amount",
      "field_label_ar": "المبلغ",
      "field_type": "currency",
      "is_visible": true,
      "is_filterable": false,
      "is_sortable": true,
      "sort_order": 2,
      "formatting": {
        "type": "currency",
        "decimals": 3
      }
    },
    {
      "field_name": "created_at",
      "field_label": "Date",
      "field_label_ar": "التاريخ",
      "field_type": "datetime",
      "is_visible": true,
      "is_filterable": true,
      "is_sortable": true,
      "sort_order": 3,
      "formatting": {
        "type": "datetime",
        "format": "Y-m-d H:i"
      }
    }
  ],
  "filters": [
    {
      "filter_name": "date_range",
      "filter_label": "Date Range",
      "filter_label_ar": "الفترة الزمنية",
      "field_name": "created_at",
      "filter_type": "date_range",
      "operator": "between",
      "is_required": true,
      "sort_order": 1
    }
  ],
  "aggregations": [
    {
      "field_name": "amount",
      "aggregation_type": "SUM",
      "alias": "Total Revenue",
      "alias_ar": "إجمالي الإيرادات",
      "format": "currency",
      "decimal_places": 3,
      "sort_order": 1
    },
    {
      "field_name": "*",
      "aggregation_type": "COUNT",
      "alias": "Total Transactions",
      "alias_ar": "إجمالي المعاملات",
      "format": "number",
      "decimal_places": 0,
      "sort_order": 2
    }
  ]
}
```

**Option B: Use Example File**

```bash
# Copy the example report definition
$example = Get-Content "docs\example_report_definition.json" | ConvertFrom-Json

# POST to API
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/reports" `
  -Method POST `
  -Headers @{
    "Authorization" = "Bearer {your-token}"
    "Content-Type" = "application/json"
  } `
  -Body ($example | ConvertTo-Json -Depth 10)
```

---

### 3. Execute the Report

```bash
POST http://localhost:8000/api/v1/reports/{report-id}/execute
Authorization: Bearer {your-token}
Content-Type: application/json

{
  "filters": {
    "date_range": {
      "start": "2026-06-01",
      "end": "2026-06-30"
    }
  },
  "page": 1,
  "per_page": 50
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "report": {
      "id": "uuid",
      "name": "Daily Revenue Report",
      "code": "RPT_DAILY_REVENUE_001"
    },
    "data": [
      {
        "transaction_number": "TXN001",
        "amount": "150.000",
        "created_at": "2026-06-15 10:30"
      }
    ],
    "aggregations": [
      {
        "field": "amount",
        "type": "SUM",
        "alias": "Total Revenue",
        "value": 150000.500,
        "format": "currency"
      }
    ],
    "pagination": {
      "total": 1000,
      "per_page": 50,
      "current_page": 1,
      "last_page": 20
    },
    "meta": {
      "execution_time_ms": 245.5,
      "rows_returned": 50,
      "from_cache": false
    }
  }
}
```

---

### 4. Export Report

**Export to Excel:**
```bash
POST http://localhost:8000/api/v1/reports/{report-id}/export
Authorization: Bearer {your-token}

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

**Export to CSV:**
```bash
{
  "format": "csv"
}
```

**Export to PDF:**
```bash
{
  "format": "pdf"
}
```

**Response:**
```json
{
  "success": true,
  "download_url": "http://localhost:8000/api/v1/reports/download/Daily_Revenue_20260612_143022_abc123.xlsx",
  "filename": "Daily_Revenue_20260612_143022_abc123.xlsx",
  "format": "excel",
  "size": 45678
}
```

---

### 5. Get Chart Data

```bash
GET http://localhost:8000/api/v1/reports/{report-id}/chart/1
Authorization: Bearer {your-token}

{
  "filters": {
    "date_range": {
      "start": "2026-06-01",
      "end": "2026-06-30"
    }
  }
}
```

**Response (Chart.js compatible):**
```json
{
  "success": true,
  "data": {
    "chart": {
      "id": 1,
      "name": "Daily Revenue Trend",
      "type": "line"
    },
    "data": {
      "labels": ["2026-06-01", "2026-06-02", "2026-06-03"],
      "datasets": [{
        "label": "Revenue",
        "data": [15000, 18000, 22000],
        "borderColor": "rgba(54, 162, 235, 1)",
        "backgroundColor": "rgba(54, 162, 235, 0.6)"
      }]
    }
  }
}
```

---

## 🔧 Available API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports` | List all reports |
| POST | `/api/v1/reports` | Create report |
| GET | `/api/v1/reports/{id}` | Get report definition |
| PUT | `/api/v1/reports/{id}` | Update report |
| DELETE | `/api/v1/reports/{id}` | Delete report |
| POST | `/api/v1/reports/{id}/execute` | Execute report |
| POST | `/api/v1/reports/{id}/export` | Export report |
| GET | `/api/v1/reports/{id}/chart/{chartId}` | Get chart data |
| POST | `/api/v1/reports/{id}/publish` | Publish report |
| POST | `/api/v1/reports/{id}/clone` | Clone report |
| GET | `/api/v1/reports/{id}/executions` | Execution history |
| GET | `/api/v1/reports/fields/available` | Get available fields |

---

## 📊 Example Frontend Integration (React)

```typescript
// Execute a report
const executeReport = async (reportId: string, filters: any) => {
  const response = await client.post(`/api/v1/reports/${reportId}/execute`, {
    filters,
    page: 1,
    per_page: 50,
  });
  
  return response.data.data;
};

// Export to Excel
const exportReport = async (reportId: string, format: string) => {
  const response = await client.post(`/api/v1/reports/${reportId}/export`, {
    format,
    filters: {
      date_range: {
        start: '2026-06-01',
        end: '2026-06-30',
      },
    },
  });
  
  // Download the file
  window.open(response.data.download_url, '_blank');
};

// Get chart data
const getChartData = async (reportId: string, chartId: number) => {
  const response = await client.get(
    `/api/v1/reports/${reportId}/chart/${chartId}`,
    {
      params: {
        filters: {
          date_range: {
            start: '2026-06-01',
            end: '2026-06-30',
          },
        },
      },
    }
  );
  
  return response.data.data;
};
```

---

## 🎯 Best Practices

### 1. Caching
```json
{
  "use_cache": true
}
```
- Cached reports execute 10x faster
- Cache TTL: 5 minutes (custom), 1 hour (system)
- Cache key includes filter hash

### 2. Pagination
```json
{
  "page": 1,
  "per_page": 50  // Max: 100
}
```
- Always use pagination for large datasets
- Max 10,000 rows per query

### 3. Filtering
```json
{
  "filters": {
    "date_range": {
      "start": "2026-06-01",
      "end": "2026-06-30"
    },
    "status": "completed"
  }
}
```
- Use indexed fields for filters
- Date ranges are most common

### 4. Aggregations
```json
{
  "aggregations": [
    {
      "field_name": "amount",
      "aggregation_type": "SUM",
      "alias": "Total Revenue"
    }
  ]
}
```
- Calculate aggregations separately from main query
- Use appropriate decimal places

---

## 🔐 Permissions

### Report Visibility

| Type | Who Can Access |
|------|----------------|
| `private` | Creator only |
| `shared` | Creator + shared users |
| `public` | All authenticated users |
| `role` | Specific roles |
| `department` | Specific departments |

### Permission Types

- `view` - See in list
- `execute` - Run report
- `export` - Export to file
- `edit` - Modify definition
- `delete` - Delete report

---

## 📈 Performance Tips

### 1. Database Indexes
```sql
CREATE INDEX idx_receipts_created_at ON receipts(created_at);
CREATE INDEX idx_receipts_status ON receipts(status);
CREATE INDEX idx_receipts_register_id ON receipts(register_id);
```

### 2. Use Cache
```json
{
  "use_cache": true
}
```

### 3. Limit Fields
```json
{
  "fields": [
    {"field_name": "amount"},
    {"field_name": "created_at"}
    // Only select needed fields
  ]
}
```

### 4. Pagination
```json
{
  "per_page": 50,  // Don't fetch all at once
  "page": 1
}
```

---

## 🐛 Troubleshooting

### Issue: "Report not found"
**Solution:** Check visibility and permissions

### Issue: "Export failed"
**Solution:** Verify temp directory exists and is writable
```bash
mkdir storage\app\temp
chmod 755 storage\app\temp
```

### Issue: "Slow query"
**Solution:** Add database indexes for filter fields

### Issue: "Cache miss"
**Solution:** Check Redis connection or file cache permissions

---

## 📞 Need Help?

- **Documentation:** `/backend/docs/REPORT_ENGINE_DOCUMENTATION.md`
- **Example Report:** `/backend/docs/example_report_definition.json`
- **API Routes:** `php artisan route:list --path=reports`

---

**Happy Reporting! 📊**
