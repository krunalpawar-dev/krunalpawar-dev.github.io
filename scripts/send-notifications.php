<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if(!$config['mail_enabled']||!filter_var($config['mail_from'],FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Configure MAIL_ENABLED=true and a verified MAIL_FROM before sending notifications.\n");exit(1);}
$lock=fopen($config['storage'].'/notifications.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Another notification worker is running.\n");exit(1);}
$pending=$db->query('SELECT * FROM leads WHERE mail_status="pending" ORDER BY id LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$accepted=0;
foreach($pending as $lead){if(send_lead_email($lead)){$db->prepare('UPDATE leads SET mail_status="accepted" WHERE id=?')->execute([$lead['id']]);$accepted++;}else{fwrite(STDERR,"Mail transport did not accept enquiry #".$lead['id'].". It remains pending.\n");}}
flock($lock,LOCK_UN);fclose($lock);
echo $accepted." notification(s) accepted by the mail transport.\n";
