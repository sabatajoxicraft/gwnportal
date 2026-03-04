<?php
require '/var/www/html/includes/config.php';
require '/var/www/html/includes/services/QueryService.php';
$conn=getDbConnection();
$owners=$conn->query("SELECT u.id FROM users u JOIN roles r ON u.role_id=r.id WHERE r.name='owner' OR r.NAME='owner' ORDER BY u.id LIMIT 1");
$row=$owners?$owners->fetch_assoc():null;
if(!$row){echo 'NO_OWNER'; exit;}
$oid=(int)$row['id'];
$rows=QueryService::getUserAccommodations($conn,$oid,'owner');
echo 'OWNER_ID=' . $oid . PHP_EOL;
foreach($rows as $r){
  echo ($r['name'] ?? $r['NAME'] ?? 'unknown') . ' | ' . ($r['address'] ?? 'NO_ADDRESS') . PHP_EOL;
}
?>
