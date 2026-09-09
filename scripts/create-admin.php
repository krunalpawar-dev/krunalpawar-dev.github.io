<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require dirname(__DIR__).'/app/config.php';
fwrite(STDERR,"Enter a new administrator password (at least 14 characters): ");
$password=rtrim(fgets(STDIN),"\r\n");
if(strlen($password)<14){fwrite(STDERR,"Password must be at least 14 characters.\n");exit(1);}
if(!is_dir($config['storage']))mkdir($config['storage'],0700,true);
file_put_contents($config['storage'].'/admin-password.hash',password_hash($password,PASSWORD_DEFAULT),LOCK_EX);
@chmod($config['storage'].'/admin-password.hash',0600);
fwrite(STDOUT,"Administrator password saved in private storage. Open /admin to sign in.\n");
