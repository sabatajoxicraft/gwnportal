Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

docker-compose exec -T gwn-app php fresh_signature.php
