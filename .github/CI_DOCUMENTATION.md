# CI/CD Pipeline Documentation

## Overview

The GWN WiFi Portal uses GitHub Actions for automated testing, Docker build verification, and controlled production deployment workflows. This keeps code quality checks and deployment paths repeatable across pushes, pull requests, and manual release operations.

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

## Deployment Workflows

Production deployment is intentionally split between an SSH-first path and an FTPS fallback path:

- **SSH deploy (`.github/workflows/ssh-deploy.yml`) is the primary design target.**
- **FTPS deploy (`.github/workflows/ftp-deploy.yml`) is manual fallback only.**
- **Remote migration (`.github/workflows/remote-migrate-accommodation.yml`) intentionally remains manual/FTPS for now.**
- The temporary push trigger on `test/ssh-primary-20260403` exists so the SSH workflow can be validated before this non-default-branch workflow is merged to `main`. Keep it until the SSH deploy has been confirmed green end-to-end from GitHub Actions.

### 3. SSH Deploy (`.github/workflows/ssh-deploy.yml`) - Primary target

**Triggers:**
- Push to `main`
- Manual trigger (`workflow_dispatch`)
- Push to `test/ssh-primary-20260403` while end-to-end SSH deploy is being validated on GitHub Actions

**Jobs:**
1. **Write `.env` from secret** - Requires `PRODUCTION_ENV_FILE`
2. **Set up SSH** - Installs the private key and adds the host to `known_hosts` with `ssh-keyscan`
3. **Create deploy archive** - Builds a `.tar.gz` locally with the same exclusions previously used by `rsync`; `rsync` is not available on the shared host
4. **Upload archive to remote** - Copies the archive to the remote home directory via `scp`
5. **Extract archive on remote** - Ensures the target directory exists, extracts the archive into `/home/joxicaxs/student.joxicraft.co.za`, then removes the remote temp archive
6. **Clean up local archive** - Removes the local `.tar.gz` (runs on success and failure)

**Protected paths:**
- `uploads/`
- `public/uploads/`
- `.well-known/`
- `.ftpquota`

**Required secrets:**
- `PRODUCTION_ENV_FILE` - Complete production `.env` file stored as a multiline GitHub secret
- `SSH_PRIVATE_KEY` - Private key used by GitHub Actions for deployment
- `SSH_USERNAME` - SSH user on the target host

**Current rollout status:**
- SSH public-key authentication now succeeds (AUTH_OK confirmed with the rotated key).
- `rsync` is not installed on the shared host, so the workflow uses `tar` + `scp` instead.
- Auth is confirmed; the remaining gate is a successful end-to-end workflow run on GitHub Actions before the test-branch trigger is removed.

### 4. FTPS Deploy (`.github/workflows/ftp-deploy.yml`) - Manual fallback

**Triggers:**
- Manual trigger only (`workflow_dispatch`)

**Jobs:**
1. **Write `.env` from secret** - Uses the same `PRODUCTION_ENV_FILE` secret as SSH deploy
2. **Stage deploy files** - Builds a filtered staging directory before upload
3. **Deploy via explicit FTPS** - Uses `lftp` with retry logic and the same deploy exclusions as the SSH workflow

**Required secrets:**
- `PRODUCTION_ENV_FILE` - Complete production `.env` file
- `FTP_USERNAME` - FTPS username
- `FTP_PASSWORD` - FTPS password

**Operational note:**
- This workflow is intentionally manual so the stable FTPS path remains available as a fallback.
- Hostname checking is disabled for FTPS because the provider certificate does not match `ftp.joxicraft.co.za`; certificate validity is still verified.

### 5. Remote Migrate - Accommodation (`.github/workflows/remote-migrate-accommodation.yml`) - Intentionally FTPS/manual

**Triggers:**
- Manual trigger only (`workflow_dispatch`)

**Behavior:**
- Uploads a one-time PHP migration script over explicit FTPS
- Executes it over HTTPS with a random token
- Deletes the script afterward

**Required secrets:**
- `FTP_USERNAME`
- `FTP_PASSWORD`

**Why this stays on FTPS for now:**
- Remote migration is intentionally left on the proven FTPS/manual path until the SSH deployment path works end-to-end.

## Deployment Secrets

| Secret | Used by | Purpose |
|--------|---------|---------|
| `PRODUCTION_ENV_FILE` | SSH deploy, FTPS deploy | Writes the production `.env` file during deployment |
| `SSH_PRIVATE_KEY` | SSH deploy | Authenticates the GitHub Actions runner to the deployment host |
| `SSH_USERNAME` | SSH deploy | SSH user for the deployment host |
| `FTP_USERNAME` | FTPS deploy, remote migration | Authenticates FTPS fallback operations |
| `FTP_PASSWORD` | FTPS deploy, remote migration | Authenticates FTPS fallback operations |

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
5. **Deployment credentials** - Keep SSH and FTPS credentials in GitHub Secrets only; never hardcode them in workflow files or tracked environment files

## Continuous Improvement

- Monitor workflow run times in Actions tab
- Optimize slow steps with better caching
- Add integration tests as application grows
- Remove the temporary SSH test-branch trigger only after GitHub Actions successfully completes an end-to-end deploy with the tar+scp path on `test/ssh-primary-20260403`
- Migrate remote deployment steps away from FTPS only after the SSH path is proven end-to-end

---

**Last Updated:** 2026-02-07
**Maintained By:** GWN WiFi Portal Team
