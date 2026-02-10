# CI/CD Pipeline Documentation

## Overview

The GWN WiFi Portal uses GitHub Actions for automated testing and Docker build verification. This ensures code quality and deployment readiness on every push and pull request.

## Workflows

### 1. CI Workflow (`.github/workflows/ci.yml`)

**Triggers:**
- Push to any branch
- Pull requests to any branch

**Jobs:**

#### PHP 8.2 Tests
- **Environment:** Ubuntu latest with MySQL 8.0 service
- **Steps:**
  1. **Checkout code** - Fetches repository code
  2. **Setup PHP 8.2** - Installs PHP with required extensions (mysqli, pdo, pdo_mysql)
  3. **Verify PHP version** - Confirms correct PHP version
  4. **Check MySQL connection** - Tests database connectivity
  5. **Create .env file** - Sets up test environment variables
  6. **Lint PHP files** - Validates syntax of all PHP files
  7. **Initialize database schema** - Applies schema from `db/schema.sql`
  8. **Verify database tables** - Confirms tables were created
  9. **Security check** - Detects hardcoded credentials
  10. **Check SQL injection** - Identifies potential SQL injection points

#### Lint Summary
- Aggregates results from test job
- Reports overall pass/fail status

### 2. Docker Build & Test (`.github/workflows/docker.yml`)

**Triggers:**
- Push to main/master/develop branches
- Pull requests to main/master/develop branches

**Jobs:**

#### Docker Build Test
- **Environment:** Ubuntu latest with Docker Buildx
- **Steps:**
  1. **Checkout code** - Fetches repository code
  2. **Set up Docker Buildx** - Enables advanced Docker build features
  3. **Build Docker image** - Builds from Dockerfile with layer caching
  4. **Create .env file** - Sets up test environment variables
  5. **Start Docker Compose** - Launches all services (app, db, phpmyadmin)
  6. **Check container status** - Displays running containers and logs
  7. **Verify MySQL health** - Confirms database is operational
  8. **Verify database creation** - Checks gwn_wifi_system database exists
  9. **Test application endpoint** - Verifies app responds on port 3040 (with retries)
  10. **Test PHPMyAdmin endpoint** - Verifies PHPMyAdmin on port 3041
  11. **Verify Apache config** - Checks Apache configuration validity
  12. **Check file permissions** - Confirms proper file ownership
  13. **Stop services** - Cleans up containers and volumes

#### Docker Security Scan
- Uses Trivy to scan Docker image for vulnerabilities
- Reports CRITICAL and HIGH severity issues
- Non-blocking (informational only)

#### Build Summary
- Aggregates results from docker-build and security-scan jobs
- Reports overall build status

## Environment Variables for CI

The workflows use test environment variables based on `.env.example`:

```bash
# Database (matches docker-compose.yml)
DB_ROOT_PASSWORD=rootpassword
DB_USER=gwn_user
DB_PASSWORD=gwn_password

# GWN API (test values)
DEFAULT_URL=https://test-gwn-manager.example.com
ID=test_app_id
Key=test_secret_key
ALLOWED_DEVICES=3

# Twilio (test values)
TWILIO_ACCOUNT_SID=ACtest
TWILIO_AUTH_TOKEN=test_token
TWILIO_PHONE_NUMBER=+1234567890
# ... (see workflow files for complete list)
```

## Caching

### Docker Build Cache
- Uses GitHub Actions cache for Docker layers
- Significantly speeds up subsequent builds
- Cache key: `type=gha`

## Status Badges

Add these badges to your README.md (replace `YOUR_USERNAME` with your GitHub username):

```markdown
[![CI](https://github.com/YOUR_USERNAME/gwn-portal/actions/workflows/ci.yml/badge.svg)](https://github.com/YOUR_USERNAME/gwn-portal/actions/workflows/ci.yml)
[![Docker Build & Test](https://github.com/YOUR_USERNAME/gwn-portal/actions/workflows/docker.yml/badge.svg)](https://github.com/YOUR_USERNAME/gwn-portal/actions/workflows/docker.yml)
```

## Running Workflows Locally

### Test PHP Lint
```bash
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```

### Test Docker Build
```bash
docker build -t gwn-portal:test .
docker-compose up -d
curl http://localhost:3040/
docker-compose down -v
```

### Test Database Schema
```bash
# Start MySQL
docker-compose up -d gwn-db

# Wait for health check
sleep 15

# Apply schema
docker-compose exec -T gwn-db mysql -ugwn_user -pgwn_password gwn_wifi_system < db/schema.sql

# Clean up
docker-compose down -v
```

## Troubleshooting

### Workflow Fails on MySQL Connection
- Check MySQL service health timeout
- Verify database credentials match docker-compose.yml
- Increase sleep time after service startup

### Application Health Check Fails
- Review application container logs in workflow output
- Check Apache configuration in Dockerfile
- Verify database connection in includes/config.php

### PHP Lint Errors
- Run `php -l <file>` locally to debug syntax errors
- Ensure PHP 8.2 compatibility

### Docker Build Cache Issues
- Clear cache by re-running workflow
- Check Dockerfile for build errors
- Review Docker build logs in Actions tab

## Extending the Workflows

### Add Composer Dependencies
If you add a `composer.json` file:

```yaml
- name: Install Composer dependencies
  run: composer install --no-dev --prefer-dist --no-progress --no-interaction

- name: Cache Composer packages
  uses: actions/cache@v3
  with:
    path: vendor
    key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
    restore-keys: ${{ runner.os }}-composer-
```

### Add PHPUnit Tests
```yaml
- name: Run PHPUnit tests
  run: vendor/bin/phpunit --configuration phpunit.xml
```

### Add Code Coverage
```yaml
- name: Setup PHP with coverage
  uses: shivammathur/setup-php@v2
  with:
    php-version: '8.2'
    coverage: xdebug

- name: Generate coverage report
  run: vendor/bin/phpunit --coverage-clover coverage.xml

- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v3
  with:
    file: ./coverage.xml
```

## Security Considerations

1. **Never commit real credentials** - Workflows use test values only
2. **Secrets management** - Use GitHub Secrets for production deployments
3. **Dependency scanning** - Trivy scans for known vulnerabilities
4. **Code analysis** - Custom security checks detect common issues

## Continuous Improvement

- Monitor workflow run times in Actions tab
- Optimize slow steps with better caching
- Add integration tests as application grows
- Consider adding deployment workflow for production

---

**Last Updated:** 2026-02-07
**Maintained By:** GWN WiFi Portal Team
