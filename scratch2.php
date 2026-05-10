<?php
$ch = curl_init('http://localhost/jimpitan-web/api/admin_dashboard.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo substr($res, 0, 1000); // just look at the first 1000 chars to see if tunggakan has id_warga
?>
