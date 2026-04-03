# 🚀 CI/CD Quick Reference

## Workflows at a Glance

| Workflow | Trigger | Duration | Purpose |
|----------|---------|----------|---------|
| **CI** | Any branch push/PR | ~3-5 min | PHP lint, DB init, security |
| **Docker** | main/develop push/PR | ~5-7 min | Build test, health checks |
| **SSH Deploy** | main push/manual + temporary test branch push | ~2-3 min | Production deploy target (primary design) |
| **FTPS Deploy** | Manual only | ~3-5 min | Production deploy fallback |
| **Remote Migrate** | Manual only | ~1-2 min | FTPS upload + HTTP execution for live migrations |

## Status Check URLs

Replace `YOUR_USERNAME` with your GitHub username:

```
https://github.com/YOUR_USERNAME/gwn-portal/actions/workflows/ci.yml
https://github.com/YOUR_USERNAME/gwn-portal/actions/workflows/docker.yml
https://github.com/YOUR_USERNAME/gwn-portal/actions/workflows/ssh-deploy.yml
https://github.com/YOUR_USERNAME/gwn-portal/actions/workflows/ftp-deploy.yml
https://github.com/YOUR_USERNAME/gwn-portal/actions/workflows/remote-migrate-accommodation.yml
```

## Local Testing Commands

### Quick PHP Lint
```bash
php -l setup_db.php
php -l includes/config.php
```

### Test All PHP Files
```bash
# Windows PowerShell
Get-ChildItem -Path . -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }

# Linux/Mac
find . -name "*.php" -exec php -l {} \;
```

### Docker Build Test
```bash
# Full test
docker-compose up -d
sleep 10
curl http://localhost:3040/
docker-compose down -v

# Quick build test
docker build -t gwn-portal:test .
```

### Database Schema Test
```bash
docker-compose up -d gwn-db
sleep 15
docker-compose exec gwn-db mysql -ugwn_user -pgwn_password gwn_wifi_system < db/schema.sql
docker-compose down -v
```

## Common Issues & Fixes

| Issue | Fix |
|-------|-----|
| MySQL connection failed | Wait longer (increase sleep time) |
| PHP syntax error | Run `php -l <file>` locally |
| Docker build failed | Check Dockerfile syntax |
| Application timeout | Check Apache/PHP errors in logs |
| Port 3040 unavailable | Stop local services: `docker-compose down` |

## Workflow File Locations

```
.github/
├── workflows/
│   ├── ci.yml                              # Main CI workflow
│   ├── docker.yml                          # Docker build test
│   ├── ssh-deploy.yml                      # SSH-primary deploy workflow
│   ├── ftp-deploy.yml                      # Manual FTPS fallback workflow
│   └── remote-migrate-accommodation.yml    # Manual FTPS migration workflow
├── CI_DOCUMENTATION.md  # Full documentation
└── M0.5-T2-COMPLETION.md # Task completion report
```

## Deployment Notes

- SSH deploy is the primary design target.
- FTPS deploy is the manual fallback.
- Remote migration intentionally remains FTPS/manual for now.
- Required GitHub secrets: `PRODUCTION_ENV_FILE`, `SSH_PRIVATE_KEY`, `SSH_USERNAME`, `FTP_USERNAME`, `FTP_PASSWORD`.
- The temporary trigger on `test/ssh-primary-20260403` remains until a successful end-to-end SSH deploy is confirmed from GitHub Actions.
- SSH auth is confirmed working (AUTH_OK with rotated key). Current workflow uses `tar`+`scp` because `rsync` is not installed on the shared host.

## Key Metrics

- **PHP Files Linted:** All .php files (50+ files)
- **Security Checks:** 2 (credentials, SQL injection)
- **Docker Services Tested:** 3 (MySQL, App, PHPMyAdmin)
- **Health Check Retries:** 10 attempts (50 sec max wait)
- **Cache Efficiency:** 50-80% faster builds

## Next Actions

1. ✅ Commit workflow files
2. ✅ Push to GitHub
3. ✅ Check Actions tab
4. ✅ Update README badges with your username
5. ✅ Fix any failures reported by CI

## Support

- **Documentation:** `.github/CI_DOCUMENTATION.md`
- **GitHub Actions Docs:** https://docs.github.com/actions
- **Docker Compose Docs:** https://docs.docker.com/compose/

---

**Quick Start:** Pushes run CI automatically; production deployment uses SSH+tar/scp on `main` with manual FTPS fallback. 🎉
