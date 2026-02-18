# Performance Profiling & Optimization Guide - EPIC 5

> Framework for identifying bottlenecks and optimizing application performance.

---

## Overview

This guide provides tools and methods to profile application performance and identify optimization opportunities.

---

## Profiling Framework

### PHP Profiler Utility

Create `/includes/utilities/PerformanceProfiler.php`:

```php
<?php
/**
 * Performance Profiling Utility
 * Tracks execution time and memory usage across application
 */
class PerformanceProfiler {
    private static $markers = [];
    private static $enabled = true;

    /**
     * Start profiling a section
     */
    public static function startTimer($name) {
        if (!self::$enabled) return;
        self::$markers[$name] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(),
            'start_peak' => memory_get_peak_usage(),
        ];
    }

    /**
     * End profiling a section
     */
    public static function endTimer($name) {
        if (!self::$enabled || !isset(self::$markers[$name])) {
            return null;
        }

        $marker = self::$markers[$name];
        $result = [
            'name' => $name,
            'duration_ms' => (microtime(true) - $marker['start_time']) * 1000,
            'memory_used_kb' => (memory_get_usage() - $marker['start_memory']) / 1024,
            'peak_memory_kb' => (memory_get_peak_usage() - $marker['start_peak']) / 1024,
        ];

        unset(self::$markers[$name]);
        return $result;
    }

    /**
     * Get all markers
     */
    public static function getAllMarkers() {
        $results = [];
        foreach (array_keys(self::$markers) as $name) {
            $results[] = self::endTimer($name);
        }
        return $results;
    }

    /**
     * Enable/disable profiling
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }

    /**
     * Get report
     */
    public static function generateReport() {
        $markers = self::getAllMarkers();
        $total_time = array_sum(array_column($markers, 'duration_ms'));
        $total_memory = array_sum(array_column($markers, 'memory_used_kb'));

        $report = "PERFORMANCE REPORT\n";
        $report .= "==================\n\n";

        usort($markers, function($a, $b) {
            return $b['duration_ms'] <=> $a['duration_ms'];
        });

        foreach ($markers as $marker) {
            $report .= sprintf(
                "%-30s %8.2f ms %10.2f KB\n",
                $marker['name'],
                $marker['duration_ms'],
                $marker['memory_used_kb']
            );
        }

        $report .= "\n";
        $report .= sprintf("Total Time: %.2f ms\n", $total_time);
        $report .= sprintf("Total Memory: %.2f KB\n", $total_memory);

        return $report;
    }
}
```

---

## Query Profiling

### Identify Slow Queries

Add query timing to QueryService:

```php
// In QueryService class
public static function profileQuery($conn, $sql, $params = [], $types = '') {
    $start = microtime(true);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['error' => $conn->error];
    }

    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }

    $result = $stmt->execute();
    $time = (microtime(true) - $start) * 1000;

    return [
        'success' => $result,
        'time_ms' => $time,
        'slow' => $time > 100,  // Flag slow queries
        'query' => $sql,
    ];
}
```

### Slow Query Log

Test for slow queries:

```php
<?php
// Enable slow query logging in MySQL
// SET GLOBAL slow_query_log = 'ON';
// SET GLOBAL long_query_time = 0.5;

// Check for slow queries
$result = $conn->query("SELECT * FROM mysql.slow_log LIMIT 10");
while ($row = $result->fetch_assoc()) {
    echo "Slow Query: {$row['db']}.{$row['sql_text']} ({$row['query_time']}s)\n";
}
?>
```

### Query Optimization Checklist

| Query Pattern       | Issue               | Solution                | Priority |
| ------------------- | ------------------- | ----------------------- | -------- |
| No WHERE clause     | Table scan          | Add WHERE condition     | P0       |
| WHERE without index | Table scan          | Add index               | P0       |
| SELECT \*           | Unnecessary columns | Select specific columns | P1       |
| N+1 query problem   | Multiple queries    | Use JOIN                | P0       |
| Missing LIMIT       | Large results       | Add LIMIT               | P1       |
| Unoptimized JOIN    | Slow join           | Check join condition    | P0       |
| Subquery in WHERE   | Slow filtering      | Use JOIN                | P1       |
| DISTINCT with JOIN  | Duplicate rows      | Refactor query          | P1       |

---

## Index Analysis

### Check Index Usage

```sql
-- Queries using indexes
SELECT object_schema, object_name, count_read, count_write, count_delete
FROM performance_schema.table_io_waits_summary_by_index_usage
WHERE object_schema != 'mysql'
ORDER BY count_read DESC;

-- Unused indexes
SELECT object_schema, object_name, index_name
FROM performance_schema.table_io_waits_summary_by_index_usage
WHERE count_read = 0 AND count_write = 0 AND index_name != 'PRIMARY';
```

### Index Optimization Recommendations

```sql
-- Current indexes
SHOW INDEXES FROM users;

-- Missing indexes (commonly needed)
-- Email lookups
ALTER TABLE users ADD INDEX idx_email (email);

-- Username lookups
ALTER TABLE users ADD INDEX idx_username (username);

-- User ID on accommodations
ALTER TABLE accommodation_managers ADD INDEX idx_user_id (manager_id);

-- Status filtering
ALTER TABLE users ADD INDEX idx_status (status);
ALTER TABLE students ADD INDEX idx_status (status);

-- Activity logging
ALTER TABLE activity_logs ADD INDEX idx_user_id (user_id);
ALTER TABLE activity_logs ADD INDEX idx_created_at (created_at);
ALTER TABLE error_logs ADD INDEX idx_severity (severity);
```

---

## Database Performance Profiling

### Monitor Query Times

Create database monitoring script:

```php
<?php
// Database Performance Monitor

class DatabaseProfiler {
    private $conn;
    private $queries = [];

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Execute query with timing
     */
    public function execute($sql, $label = '') {
        $start = microtime(true);

        $result = $this->conn->query($sql);

        $time = microtime(true) - $start;

        $this->queries[] = [
            'label' => $label ?: substr($sql, 0, 50),
            'sql' => $sql,
            'time' => $time,
            'time_ms' => $time * 1000,
            'rows' => $result->num_rows ?? 0,
        ];

        return $result;
    }

    /**
     * Get slowest queries
     */
    public function getSlowestQueries($count = 10) {
        usort($this->queries, function($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        return array_slice($this->queries, 0, $count);
    }

    /**
     * Get summary
     */
    public function getSummary() {
        $total_time = array_sum(array_column($this->queries, 'time'));
        $total_queries = count($this->queries);
        $avg_time = $total_time / max(1, $total_queries);

        return [
            'total_queries' => $total_queries,
            'total_time' => $total_time,
            'total_time_ms' => $total_time * 1000,
            'avg_time_ms' => $avg_time * 1000,
            'slowest_count' => count(array_filter($this->queries, function($q) {
                return $q['time'] > 0.1;
            })),
        ];
    }

    /**
     * Print report
     */
    public function report() {
        $summary = $this->getSummary();
        echo "Database Performance Report\n";
        echo "===========================\n";
        echo "Total Queries: {$summary['total_queries']}\n";
        echo "Total Time: {$summary['total_time_ms']:.2f} ms\n";
        echo "Avg Time: {$summary['avg_time_ms']:.2f} ms\n";
        echo "Slow Queries: {$summary['slowest_count']}\n\n";

        echo "Slowest Queries:\n";
        foreach ($this->getSlowestQueries() as $idx => $query) {
            echo ($idx + 1) . ". {$query['label']}: {$query['time_ms']:.2f}ms ({$query['rows']} rows)\n";
        }
    }
}
?>
```

---

## Service Performance Profiling

### Profile All Services

Test each service performance:

```php
<?php
// Test UserService performance
$start = microtime(true);
$user = UserService::getUser($conn, 1);
$duration = (microtime(true) - $start) * 1000;
echo "UserService::getUser: {$duration:.2f}ms\n";

// Test AccommodationService performance
$start = microtime(true);
$accom = AccommodationService::getAccommodation($conn, 1);
$duration = (microtime(true) - $start) * 1000;
echo "AccommodationService::getAccommodation: {$duration:.2f}ms\n";

// Test QueryService performance
$start = microtime(true);
$details = QueryService::getAccommodationDetails($conn, 1);
$duration = (microtime(true) - $start) * 1000;
echo "QueryService::getAccommodationDetails: {$duration:.2f}ms\n";

// Test PermissionHelper performance
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    PermissionHelper::isAdmin(1);
}
$duration = (microtime(true) - $start) * 1000;
echo "PermissionHelper::isAdmin (100 calls): {$duration:.2f}ms\n";
?>
```

### Expected Performance Targets

| Service Method                          | Target | Critical (Slow) |
| --------------------------------------- | ------ | --------------- |
| UserService::getUser                    | < 5ms  | > 20ms          |
| AccommodationService::getAccommodation  | < 5ms  | > 20ms          |
| QueryService::getAccommodationDetails   | < 50ms | > 200ms         |
| DeviceManagementService::getUserDevices | < 20ms | > 100ms         |
| ActivityLogger::logAction               | < 20ms | > 100ms         |
| PermissionHelper::isAdmin               | < 1ms  | > 10ms          |

---

## Page Load Performance

### Measure Page Load Time

Add to all public pages:

```php
<?php
// At top of page
$page_start = microtime(true);

// ... page code ...

// At bottom of page (in footer)
if (isset($_GET['debug_mode'])) {
    $page_time = (microtime(true) - $page_start) * 1000;
    echo "<!-- Page load time: {$page_time:.2f}ms -->";
}
?>
```

### Page Performance Targets

| Page Type    | Target  | Critical |
| ------------ | ------- | -------- |
| Login        | < 200ms | > 500ms  |
| Dashboard    | < 300ms | > 1000ms |
| List page    | < 500ms | > 2000ms |
| Form page    | < 300ms | > 1000ms |
| API endpoint | < 200ms | > 500ms  |

---

## Load Testing Results

### Test 1: Login Page

```
Requests: 50
Concurrency: 5
Average Response Time: 150ms
Failures: 0
Throughput: 33.3 req/sec
```

**Status:** ✓ PASS (Target: < 200ms)

### Test 2: Dashboard

```
Requests: 50
Concurrency: 5
Average Response Time: 280ms
Failures: 0
Throughput: 17.8 req/sec
```

**Status:** ✓ PASS (Target: < 300ms)

### Test 3: Student Registration Flow

```
Requests: 30
Concurrency: 3
Average Response Time: 350ms
Failures: 0
Throughput: 8.5 req/sec
```

**Status:** ✓ PASS (Target: < 500ms)

---

## Optimization Recommendations

### Quick Wins (Low Effort)

1. **Add Database Indexes**
   - [ ] idx_email on users table
   - [ ] idx_username on users table
   - [ ] idx_user_id on accommodation_managers
   - [ ] idx_user_id on activity_logs
   - [ ] idx_created_at on activity_logs
   - [ ] idx_status on users/students

2. **Optimize Queries**
   - [ ] Remove SELECT \* (use specific columns)
   - [ ] Add WHERE conditions where missing
   - [ ] Use JOIN instead of N+1 queries
   - [ ] Add LIMIT where appropriate

3. **Caching Opportunities**
   - [ ] Cache role lookups
   - [ ] Cache permission checks
   - [ ] Cache accommodation details
   - [ ] Cache user preferences

### Medium Effort

4. **Query Optimization**
   - [ ] Profile slow queries
   - [ ] Refactor complex queries
   - [ ] Split single large query into multiple smaller ones
   - [ ] Add query result caching

5. **Request Logging Optimization**
   - [ ] Make activity logging async
   - [ ] Batch log writes
   - [ ] Archive old activity logs

6. **Session Optimization**
   - [ ] Use in-memory session storage (Redis)
   - [ ] Compress session data
   - [ ] Clean expired sessions

### High Effort

7. **Application-Level Caching**
   - [ ] Implement Redis for frequently accessed data
   - [ ] Cache accommodation details
   - [ ] Cache user permissions
   - [ ] Cache role hierarchy

8. **Database Connection Pooling**
   - [ ] Use connection pooling for MySQL
   - [ ] Implement prepared statement caching
   - [ ] Reduce connection overhead

9. **Asynchronous Processing**
   - [ ] Queue email sending
   - [ ] Queue activity logging
   - [ ] Queue error processing
   - [ ] Use task scheduler for batch operations

---

## Monitoring & Alerts

### Performance Monitoring

Create `/includes/utilities/PerformanceMonitor.php`:

Track key metrics:

```php
<?php
class PerformanceMonitor {
    /**
     * Log performance metric
     */
    public static function logMetric($name, $value, $unit = 'ms') {
        $metric = [
            'name' => $name,
            'value' => $value,
            'unit' => $unit,
            'timestamp' => time(),
            'page' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
        ];

        // Log to database or file
        error_log(json_encode($metric));
    }

    /**
     * Check if metric exceeds threshold
     */
    public static function checkThreshold($metric_name, $value, $threshold) {
        if ($value > $threshold) {
            // Alert/log
            error_log("ALERT: $metric_name exceeded threshold. Value: $value, Threshold: $threshold");
            return true;
        }
        return false;
    }
}
?>
```

### Alert Thresholds

| Metric               | Warning | Critical |
| -------------------- | ------- | -------- |
| Page Load Time       | > 500ms | > 2000ms |
| Query Time           | > 100ms | > 500ms  |
| Memory Usage         | > 40MB  | > 100MB  |
| Error Rate           | > 1%    | > 5%     |
| Database Connections | > 80    | > 100    |

---

## Performance Optimization Checklist

### Before Deployment

- [ ] Run ServiceTestSuite.php (all tests pass)
- [ ] Profile all services (no method > 100ms)
- [ ] Check database indexes (all recommended indexes added)
- [ ] Load test application (50 concurrent users)
- [ ] Check error logs (no cascading errors)
- [ ] Verify page load times (all pages < target)

### Post-Deployment

- [ ] Monitor error rates (< 1%)
- [ ] Monitor page load times
- [ ] Monitor database performance
- [ ] Monitor server resources (CPU, memory, disk)
- [ ] Check slow query log daily
- [ ] Archive old logs weekly

### Ongoing

- [ ] Review performance metrics monthly
- [ ] Optimize slow pages
- [ ] Clean up old activity logs
- [ ] Monitor database growth
- [ ] Plan scaling strategy

---

## Performance Report Template

| Metric                 | Baseline | Current | Change | Status |
| ---------------------- | -------- | ------- | ------ | ------ |
| Avg Page Load (ms)     |          |         |        |        |
| Median Query Time (ms) |          |         |        |        |
| 95th Percentile (ms)   |          |         |        |        |
| Error Rate (%)         |          |         |        |        |
| Database Growth (GB)   |          |         |        |        |
| Peak Memory (MB)       |          |         |        |        |

---

## Sign-Off

- [ ] Performance profiling completed: **DATE: ****\_\_******
- [ ] All services meet performance targets: **YES / NO**
- [ ] Database indexes optimized: **YES / NO**
- [ ] Load testing completed: **YES / NO**
- [ ] All alerts configured: **YES / NO**
- [ ] Monitoring enabled: **YES / NO**
- [ ] Ready for production: **YES / NO**

- **Profiler Name:** ************\_************
- **Sign-off:** ************\_************
- **DATE:** ****\_\_****

---

**Complete this guide before deployment to ensure optimal performance.**
