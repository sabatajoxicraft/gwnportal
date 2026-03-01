#!/bin/sh
set -e

SMTP_HOST="${SMTP_HOST:-student.joxicraft.co.za}"
SMTP_PORT="${SMTP_PORT:-465}"
SMTP_USER="${SMTP_USER:-donotreply@student.joxicraft.co.za}"
SMTP_FROM="${SMTP_FROM:-donotreply@student.joxicraft.co.za}"
SMTP_PASSWORD="${SMTP_PASSWORD:-}"

cat > /etc/msmtprc <<EOF
defaults
auth           on
tls            on
tls_starttls   off

account        default
host           ${SMTP_HOST}
port           ${SMTP_PORT}
from           ${SMTP_FROM}
user           ${SMTP_USER}
password       ${SMTP_PASSWORD}
EOF

chmod 600 /etc/msmtprc

exec docker-php-entrypoint "$@"
