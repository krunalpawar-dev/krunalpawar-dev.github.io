<?php
start_session();
header('Cache-Control: no-store');
$types=['Web Application','SaaS Platform','CRM','HRMS','ERP','Healthcare System','Inventory System','Warehouse System','API Integration','Existing Project Improvement','Other'];
$methods=['Email','Phone','WhatsApp'];
$typeLabels=['Web Application'=>'Software for my daily business tasks','SaaS Platform'=>'An online product customers subscribe to','CRM'=>'Customers, sales & follow-ups','HRMS'=>'Staff, attendance & payroll','ERP'=>'Connected business operations','Healthcare System'=>'Patients, appointments & billing','Inventory System'=>'Stock & orders','Warehouse System'=>'Warehouse operations','API Integration'=>'Connect my existing tools','Existing Project Improvement'=>'Fix or improve my current software','Other'=>'Not sure yet — help me decide'];
$budgets=['Let’s discuss','Under ₹1 lakh','₹1–3 lakh','₹3–8 lakh','₹8 lakh+','USD budget — specify in description','Other currency — specify in description'];
$serviceTypes=['custom-web-application-development'=>'Web Application','laravel-development'=>'Web Application','saas-development'=>'SaaS Platform','crm-development'=>'CRM','hrms-development'=>'HRMS','erp-development'=>'ERP','healthcare-management-system-development'=>'Healthcare System','inventory-management-system-development'=>'Inventory System','warehouse-management-system-development'=>'Warehouse System','api-development-integration'=>'API Integration','laravel-maintenance'=>'Existing Project Improvement','admin-panel-development'=>'Web Application'];
if($_SERVER['REQUEST_METHOD']==='GET'&&is_string($_GET['service']??null)&&isset($serviceTypes[$_GET['service']]))$old['project_type']=$serviceTypes[$_GET['service']];
$success=isset($_SESSION['lead_success']);$reference=$_SESSION['lead_success']??null;unset($_SESSION['lead_success']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 $success=false;
 foreach(['name','company','email','phone','project_type','budget','description','contact_method','website'] as $key)$old[$key]=is_string($_POST[$key]??null)?trim($_POST[$key]):'';
 if(!verify_csrf())$errors[]='Your session has expired. Please submit the form again.';
 if(!throttle('contact:'.($_SERVER['REMOTE_ADDR']??'unknown'),5,3600)){$errors[]='Too many enquiries have been submitted. Please try again in an hour or contact me by email.';http_response_code(429);}
 if($old['website']!=='')$errors[]='Unable to submit this enquiry. Please contact me by email.';
 if(mb_strlen($old['name'])<2||mb_strlen($old['name'])>100)$errors[]='Please enter your name (2–100 characters).';
 if($old['email']!==''&&(!filter_var($old['email'],FILTER_VALIDATE_EMAIL)||strlen($old['email'])>254||preg_match('/[\r\n]/',$old['email'])))$errors[]='Please enter a valid email address.';
 if(mb_strlen($old['company'])>150)$errors[]='Company name must be 150 characters or fewer.';
 if(!preg_match('/^\+?[0-9 ()\-.]{7,30}$/',$old['phone']))$errors[]='Please enter a valid phone number, including your country code.';
 if($old['project_type']!==''&&!in_array($old['project_type'],$types,true))$errors[]='Please choose a project type.';
 if($old['budget']!==''&&!in_array($old['budget'],$budgets,true))$errors[]='Please choose a budget range.';
 if(mb_strlen($old['description'])>10000)$errors[]='Please keep your project description within 10,000 characters.';
 if($old['contact_method']!==''&&!in_array($old['contact_method'],$methods,true))$errors[]='Please choose a preferred contact method.';
 if(in_array($old['contact_method'],['Phone','WhatsApp'])&&$old['phone']==='')$errors[]='Please provide a phone number for your preferred contact method.';
 if(!$errors){
  try{
   $values=array_intersect_key($old,array_flip(['name','company','email','phone','project_type','budget','description','contact_method']));
   $values['created_at']=gmdate('c');
   $query=$db->prepare('INSERT INTO leads(name,company,email,phone,project_type,budget,description,contact_method,created_at) VALUES(:name,:company,:email,:phone,:project_type,:budget,:description,:contact_method,:created_at)');$query->execute($values);
   $id=(int)$db->lastInsertId();
   // Coordinate with the retry worker to avoid concurrently sending the same alert.
   $notificationLock=fopen($config['storage'].'/notifications.lock','c');
   if($notificationLock && flock($notificationLock,LOCK_EX|LOCK_NB)){
    $pending=$db->prepare('SELECT mail_status FROM leads WHERE id=?');$pending->execute([$id]);
    if($pending->fetchColumn()==='pending'&&send_lead_email(array_merge($values,['id'=>$id])))$db->prepare('UPDATE leads SET mail_status="accepted" WHERE id=?')->execute([$id]);
    flock($notificationLock,LOCK_UN);
   }
   if($notificationLock)fclose($notificationLock);
   $_SESSION['lead_success']=$id;$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: /contact',true,303);exit;
  }catch(Throwable $exception){error_log('Portfolio enquiry failed: '.$exception->getMessage());$errors[]='We couldn’t save your enquiry. Please try again, or email '.$config['email'].'.';http_response_code(503);}
 }elseif(http_response_code()!==429){http_response_code(422);}
}
