<?php
declare(strict_types=1);
start_session();header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
$hash=$config['admin_hash'];
if(!$hash&&is_file($config['storage'].'/admin-password.hash'))$hash=trim(file_get_contents($config['storage'].'/admin-password.hash'));
if(isset($_SESSION['admin_at']) && time()-$_SESSION['admin_at']>1800){unset($_SESSION['admin']);session_regenerate_id(true);}
$notice='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST' && !verify_csrf()){http_response_code(403);$error='Your session expired. Please try again.';}
if(!$error&&($_POST['action']??'')==='login'){
 if(!throttle('login:'.($_SERVER['REMOTE_ADDR']??'unknown'),5,900)){http_response_code(429);$error='Too many attempts. Please try again in 15 minutes.';}
 elseif($hash && is_string($_POST['password']??null)&&password_verify($_POST['password'],$hash)){
  session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['admin_at']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: /admin',true,303);exit;
 }else{$error='Unable to sign in. Please check your password.';}
}
if(!$error&&($_POST['action']??'')==='logout'){$_SESSION=[];session_regenerate_id(true);header('Location: /admin',true,303);exit;}
$authenticated=($_SESSION['admin']??false)===true;
if($authenticated)$_SESSION['admin_at']=time();
$definitions=[
'projects'=>['title'=>'text','description'=>'textarea','short'=>'text','industry'=>'text','theme'=>'theme','icon'=>'text','architecture'=>'text','categories'=>'lines','technologies'=>'lines','features'=>'lines','problem'=>'textarea','solution'=>'textarea','contribution'=>'textarea','impact'=>'textarea','related_services'=>'lines','screenshots'=>'screenshots'],
'services'=>['title'=>'text','description'=>'textarea','icon'=>'text','features'=>'lines','problem'=>'textarea','approach'=>'textarea'],
'posts'=>['title'=>'text','description'=>'textarea','category'=>'text','date'=>'date','read_time'=>'text','sections'=>'sections'],
'technologies'=>['title'=>'text','group'=>'text','description'=>'textarea'],
'faqs'=>['title'=>'text','answer'=>'textarea'],
'testimonials'=>['title'=>'text','company'=>'text','quote'=>'textarea'],
'project_categories'=>['title'=>'text'],'blog_categories'=>['title'=>'text']];
$collection=is_string($_GET['collection']??null)?$_GET['collection']:'leads';
if($collection!=='leads'&&!isset($definitions[$collection]))$collection='leads';
$editing=null;$editingSlug=is_string($_GET['edit']??null)?$_GET['edit']:null;
if($authenticated&&$collection!=='leads'&&$editingSlug!==null){
 foreach(records($collection,true) as $r)if($r['slug']===$editingSlug)$editing=$r;
 if($editingSlug==='new')$editing=['slug'=>'','published'=>false];
 if(!$editing){http_response_code(404);$error='Content record not found.';}
}
if($authenticated&&!$error&&$_SERVER['REQUEST_METHOD']==='POST'){
 $action=$_POST['action']??'';
 if($action==='upload'&&$collection==='projects'){
  $upload=$_FILES['screenshot']??null;
  if(!$upload||$upload['error']!==UPLOAD_ERR_OK||$upload['size']>5*1024*1024){$error='Choose a PNG, JPEG or WebP screenshot smaller than 5 MB.';}
  else {
   $size=@getimagesize($upload['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
   if(!$size||$size[0]*$size[1]>12000000||!in_array($mime,['image/png','image/jpeg','image/webp'],true))$error='Use a valid PNG, JPEG or WebP image of no more than 12 megapixels.';
   else{
    $source=@imagecreatefromstring(file_get_contents($upload['tmp_name']));
    if(!$source)$error='This image could not be decoded.';
    else{
     $width=min(1800,$size[0]);$height=max(1,(int)round($size[1]*$width/$size[0]));$dest=imagecreatetruecolor($width,$height);
     imagealphablending($dest,false);imagesavealpha($dest,true);imagecopyresampled($dest,$source,0,0,0,0,$width,$height,$size[0],$size[1]);
     $dir=$config['storage'].'/media';if(!is_dir($dir))mkdir($dir,0700,true);$name=bin2hex(random_bytes(16)).'.webp';
     if(imagewebp($dest,$dir.'/'.$name,85))$notice='Screenshot uploaded. Add this path and a caption to the Screenshots field, then save: /media/'.$name;
     else $error='Unable to save the screenshot. Check private storage permissions.';
     imagedestroy($source);imagedestroy($dest);
    }
   }
  }
 }
 if($action==='save'&&isset($definitions[$collection])&&$editing){
  $slug=is_string($_POST['slug']??null)?trim($_POST['slug']):'';
  if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)||strlen($slug)>150)$error='Use a URL slug containing lowercase letters, numbers and hyphens (up to 150 characters).';
  if($editingSlug!=='new'&&$editingSlug!==$slug)$error='The published URL is stable. Keep the existing slug when editing.';
  $data=['slug'=>$slug,'published'=>isset($_POST['published'])];
  foreach($definitions[$collection] as $field=>$type){
   $value=is_string($_POST[$field]??null)?trim($_POST[$field]):'';
   if(mb_strlen($value)>50000)$error='A content field is too long (maximum 50,000 characters).';
   if($type==='lines')$data[$field]=array_values(array_filter(array_map('trim',explode("\n",$value))));
   elseif($type==='screenshots'){
    $data[$field]=[];
    foreach(array_filter(explode("\n",$value)) as $line){
     [$src,$alt]=array_pad(explode('|',$line,2),2,'');$src=trim($src);$alt=trim($alt);
     $validAsset=preg_match('~^/assets/[a-zA-Z0-9/_ .-]+\.(png|jpe?g|webp)$~',$src)&&!str_contains($src,'..')&&is_file(dirname(__DIR__).$src);
     $validUpload=preg_match('~^/media/[a-f0-9]{32}\.webp$~',$src)&&is_file($config['storage'].$src);
     if((!$validAsset&&!$validUpload)||$alt==='')$error='Each screenshot needs an existing /assets/ or uploaded /media/ image path and descriptive caption, separated by |.';
     else $data[$field][]=['src'=>$src,'alt'=>$alt];
    }
   }elseif($type==='sections'){
    $data[$field]=[];
    foreach(preg_split('/\r?\n---\r?\n/',$value) as $block){
     [$heading,$body]=array_pad(explode("\n",trim($block),2),2,'');
     if(!trim($heading)||!trim($body))$error='Each article section needs a heading on its first line and body text below.';
     $data[$field][]=['heading'=>trim($heading),'body'=>trim($body)];
    }
   }else $data[$field]=$value;
   if($field!=='screenshots'&&($value===''||($type==='lines'&&!$data[$field])))$error='Please complete all content fields. Only screenshots and SEO overrides are optional.';
  }
  if($collection==='projects'&&!in_array($data['theme']??'',['medical','treeva','education','crm','hrms','warehouse'],true))$error='Choose a valid project visual theme.';
  if($collection==='posts'){
   $date=DateTimeImmutable::createFromFormat('!Y-m-d',$data['date']);
   if(!$date||$date->format('Y-m-d')!==$data['date'])$error='Enter a valid publication date.';
   $data['modified']=date('Y-m-d');
  }
  if($collection==='testimonials'&&$data['published']&&($_POST['permission']??'')!=='yes')$error='Confirm the testimonial is genuine and you have permission before publishing.';
  foreach(['seo_title','seo_description'] as $key){$value=is_string($_POST[$key]??null)?trim($_POST[$key]):'';if($value!=='')$data[$key]=mb_substr($value,0,$key==='seo_title'?120:320);}
  $editing=$data;
  if(!$error){
   try{
    if($editingSlug==='new'){$q=$db->prepare('INSERT INTO content(collection,slug,data) VALUES(?,?,?)');$q->execute([$collection,$slug,json_encode($data,JSON_UNESCAPED_UNICODE)]);}
    else{$q=$db->prepare('UPDATE content SET data=? WHERE collection=? AND slug=?');$q->execute([json_encode($data,JSON_UNESCAPED_UNICODE),$collection,$slug]);}
    header('Location: /admin?collection='.urlencode($collection).'&saved=1',true,303);exit;
   }catch(PDOException $exception){$error=$exception->getCode()==='23000'?'That URL slug already exists. Choose a different one.':'Unable to save this record. Please try again.';}
  }
 }
 if($action==='delete'&&isset($definitions[$collection])&&is_string($_POST['slug']??null)){$db->prepare('DELETE FROM content WHERE collection=? AND slug=?')->execute([$collection,$_POST['slug']]);header('Location: /admin?collection='.urlencode($collection).'&deleted=1',true,303);exit;}
 if($action==='lead-status'&&$collection==='leads'&&in_array($_POST['status']??'',['new','contacted','qualified','closed'],true)){$db->prepare('UPDATE leads SET status=? WHERE id=?')->execute([$_POST['status'],(int)($_POST['id']??0)]);header('Location: /admin?lead='.(int)($_POST['id']??0),true,303);exit;}
 if($action==='delete-lead'&&$collection==='leads'){$db->prepare('DELETE FROM leads WHERE id=?')->execute([(int)($_POST['id']??0)]);header('Location: /admin?deleted=1',true,303);exit;}
}
function field_value(array $data,string $field,string $type): string {
 $value=$data[$field]??'';
 if($type==='lines')return is_array($value)?implode("\n",$value):'';
 if($type==='screenshots')return is_array($value)?implode("\n",array_map(fn($s)=>$s['src'].' | '.$s['alt'],$value)):'';
 if($type==='sections')return is_array($value)?implode("\n---\n",array_map(fn($s)=>$s['heading']."\n".$s['body'],$value)):'';
 return (string)$value;
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Content manager | Krunal Pawar</title><link rel="stylesheet" href="/assets/css/portfolio.css?v=1"><script src="/assets/js/portfolio.js?v=1" defer></script></head><body><a class="skip-link" href="#main">Skip to content</a><main id="main" class="<?=$authenticated?'admin-main':'admin-login'?>">
<?php if(!$authenticated):?><a class="brand" href="/"><span class="monogram">k<span>.</span></span><span>Krunal Pawar</span></a><h1 class="section">Content manager</h1><?php if(!$hash):?><div class="admin-notice">The content manager is locked until an administrator password is configured. See the setup instructions in the project README.</div><?php else:?><?php if($error):?><div class="error-summary" role="alert"><?=e($error)?></div><?php endif;?><form method="post" class="admin-form"><?=csrf()?><input type="hidden" name="action" value="login"><div class="field"><label for="password">Administrator password</label><input type="password" name="password" id="password" required autocomplete="current-password"></div><button class="button">Sign in <?=icon('arrow')?></button></form><?php endif;?>
<?php else:?><header class="admin-header"><div><span class="eyebrow">KRUNAL PAWAR / CONTENT MANAGER</span><h1><?=e(ucwords(str_replace('_',' ',$collection)))?></h1></div><div class="button-row"><a class="button secondary" href="/" target="_blank" rel="noopener">View website ↗</a><form method="post"><?=csrf()?><input type="hidden" name="action" value="logout"><button class="button secondary">Sign out</button></form></div></header><nav class="admin-nav" aria-label="Content collections"><?php foreach(array_merge(['leads'],array_keys($definitions)) as $tab):?><a class="filter <?=$collection===$tab?'selected':''?>" href="/admin?collection=<?=e($tab)?>"><?=e(ucwords(str_replace('_',' ',$tab)))?></a><?php endforeach;?></nav>
<?php if($error):?><div class="error-summary" role="alert"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="admin-notice" role="status"><?=e($notice)?></div><?php endif;?><?php if(isset($_GET['saved'])):?><div class="admin-notice" role="status">Content saved. Published records are now visible on the website.</div><?php endif;?><?php if(isset($_GET['deleted'])):?><div class="admin-notice" role="status">Record deleted.</div><?php endif;?>
<?php if($collection==='leads'):?>
<?php if(!$config['mail_enabled']):?><div class="admin-notice">Email delivery is not configured. Enquiries are safely stored below. Configure MAIL_ENABLED and MAIL_FROM with your hosting mail service, then run the notification worker to send pending alerts.</div><?php endif;?>
<?php $lead=null;if(isset($_GET['lead'])){$q=$db->prepare('SELECT * FROM leads WHERE id=?');$q->execute([(int)$_GET['lead']]);$lead=$q->fetch(PDO::FETCH_ASSOC);if(!$lead)echo '<div class="error-summary">Enquiry not found.</div>';}if($lead):?><article class="admin-lead"><h2>Enquiry KP-<?=e($lead['id'])?></h2><dl><?php foreach($lead as $key=>$value):?><dt><?=e(ucwords(str_replace('_',' ',$key)))?></dt><dd><?=e($value)?></dd><?php endforeach;?></dl><form method="post" class="button-row"><?=csrf()?><input type="hidden" name="action" value="lead-status"><input type="hidden" name="id" value="<?=e($lead['id'])?>"><label for="status">Enquiry status</label><select name="status" id="status"><?php foreach(['new','contacted','qualified','closed'] as $status):?><option <?=$lead['status']===$status?'selected':''?>><?=$status?></option><?php endforeach;?></select><button class="button">Update status</button></form><form method="post" data-confirm-delete class="section"><?=csrf()?><input type="hidden" name="action" value="delete-lead"><input type="hidden" name="id" value="<?=e($lead['id'])?>"><button class="button danger">Delete enquiry permanently</button></form></article>
<?php else:$pageNumber=max(1,(int)($_GET['page']??1));$total=(int)$db->query('SELECT COUNT(*) FROM leads')->fetchColumn();$q=$db->prepare('SELECT id,name,company,project_type,status,created_at,mail_status FROM leads ORDER BY id DESC LIMIT 30 OFFSET ?');$q->bindValue(1,($pageNumber-1)*30,PDO::PARAM_INT);$q->execute();$leads=$q->fetchAll(PDO::FETCH_ASSOC);?><p><?=e($total)?> enquiries · newest first</p><div class="table-scroll"><table class="admin-table"><thead><tr><th>Reference / name</th><th>Project</th><th>Status</th><th>Notification</th><th>Received</th></tr></thead><tbody><?php foreach($leads as $lead):?><tr><td><a href="/admin?lead=<?=e($lead['id'])?>">KP-<?=e($lead['id'])?> · <?=e($lead['name'])?></a><br><?=e($lead['company'])?></td><td><?=e($lead['project_type'])?></td><td><?=e($lead['status'])?></td><td><?=e($lead['mail_status'])?></td><td><?=e($lead['created_at'])?></td></tr><?php endforeach;?><?php if(!$leads):?><tr><td colspan="5">No enquiries yet. New project enquiries will appear here.</td></tr><?php endif;?></tbody></table></div><div class="button-row section"><?php if($pageNumber>1):?><a class="button secondary" href="/admin?page=<?=$pageNumber-1?>">Previous</a><?php endif;?><?php if($pageNumber*30<$total):?><a class="button secondary" href="/admin?page=<?=$pageNumber+1?>">Next</a><?php endif;?></div><?php endif;?>
<?php elseif($editing):?><?php if($collection==='projects'):?><details class="admin-notice"><summary>Upload an approved project screenshot</summary><form method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="action" value="upload"><div class="field"><label for="screenshot">PNG, JPEG or WebP · maximum 5 MB / 12 megapixels</label><input id="screenshot" type="file" name="screenshot" accept="image/png,image/jpeg,image/webp" required></div><button class="button secondary">Upload screenshot</button></form><p>Upload before editing the fields below. Images are optimized as WebP and stored outside the web root.</p></details><?php endif;?><form method="post" class="admin-form"><?=csrf()?><input type="hidden" name="action" value="save"><div class="field"><label for="slug">URL slug</label><input name="slug" id="slug" value="<?=e($editing['slug'])?>" required pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="150" <?=$editingSlug!=='new'?'readonly':''?>><small>Stable, lowercase URL. Example: custom-crm-development</small></div><?php foreach($definitions[$collection] as $field=>$type):?><div class="field"><label for="<?=e($field)?>"><?=e(ucwords(str_replace('_',' ',$field)))?></label><?php if(in_array($type,['textarea','lines','screenshots','sections'])):?><textarea id="<?=e($field)?>" name="<?=e($field)?>" rows="<?=$type==='sections'?16:5?>" <?=$field!=='screenshots'?'required':''?>><?=e(field_value($editing,$field,$type))?></textarea><?php if($type==='lines'):?><small>One item per line. Categories must match category titles; related services use their URL slugs.</small><?php elseif($type==='screenshots'):?><small>One image per line: /assets/path/image.png | Descriptive caption. Use approved screenshots only.</small><?php elseif($type==='sections'):?><small>Heading on the first line, body below. Separate sections with a line containing three hyphens (---). Text is safely rendered without HTML.</small><?php endif;?><?php elseif($type==='theme'):?><select id="theme" name="theme"><?php foreach(['medical','treeva','education','crm','hrms','warehouse'] as $theme):?><option <?=($editing['theme']??'')===$theme?'selected':''?>><?=$theme?></option><?php endforeach;?></select><?php else:?><input type="<?=$type==='date'?'date':'text'?>" id="<?=e($field)?>" name="<?=e($field)?>" value="<?=e(field_value($editing,$field,$type))?>" required><?php endif;?></div><?php endforeach;?><h2>SEO overrides</h2><p>Leave these blank to use the title and description above.</p><div class="field"><label for="seo_title">SEO title</label><input id="seo_title" name="seo_title" maxlength="120" value="<?=e($editing['seo_title']??'')?>"></div><div class="field"><label for="seo_description">Meta description</label><textarea id="seo_description" name="seo_description" maxlength="320"><?=e($editing['seo_description']??'')?></textarea></div><label class="checkbox-label"><input type="checkbox" name="published" <?=($editing['published']??false)?'checked':''?>>Published — visible on the public website</label><?php if($collection==='testimonials'):?><label class="checkbox-label"><input type="checkbox" name="permission" value="yes">This is a genuine testimonial and I have permission to publish it.</label><?php endif;?><div class="button-row"><button class="button">Save content <?=icon('check')?></button><a class="button secondary" href="/admin?collection=<?=e($collection)?>">Cancel</a></div></form>
<?php else:?><div class="button-row"><a class="button" href="/admin?collection=<?=e($collection)?>&edit=new">Add <?=e(rtrim(str_replace('_',' ',$collection),'s'))?> +</a></div><div class="table-scroll section"><table class="admin-table"><thead><tr><th>Title</th><th>URL slug</th><th>Visibility</th><th>Actions</th></tr></thead><tbody><?php foreach(records($collection,true) as $r):?><tr><td><a href="/admin?collection=<?=e($collection)?>&edit=<?=e($r['slug'])?>"><?=e($r['title'])?></a></td><td><?=e($r['slug'])?></td><td><?=($r['published']??true)?'Published':'Draft'?></td><td><a href="/admin?collection=<?=e($collection)?>&edit=<?=e($r['slug'])?>">Edit</a> · <form method="post" class="inline-form" data-confirm-delete><?=csrf()?><input type="hidden" name="action" value="delete"><input type="hidden" name="slug" value="<?=e($r['slug'])?>"><button>Delete</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;?><?php endif;?></main></body></html>
