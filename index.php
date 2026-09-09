<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; font-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';
if(preg_match('~^/media/([a-f0-9]{32}\.webp)$~',$path,$media)){
 $file=$config['storage'].'/media/'.$media[1];
 if(!is_file($file)){http_response_code(404);exit;}
 header('Content-Type: image/webp');header('Cache-Control: public, max-age=31536000, immutable');header('Content-Length: '.filesize($file));readfile($file);exit;
}
if ($path === '/index.php' || $path === '/index.html') {header('Location: /', true, 301);exit;}
$redirects=['/crm.html'=>'/projects/crm-business-management-platform','/portfolio-details.html'=>'/projects','/service-details.html'=>'/services','/starter-page.html'=>'/','/forms/contact.php'=>'/contact','/dsshoppy.html'=>'/projects','/kakubaa.html'=>'/projects','/sterileair.html'=>'/projects','/e-commerce.html'=>'/projects','/header.html'=>'/'];
if(isset($redirects[$path])){header('Location: '.$redirects[$path],true,301);exit;}
if ($path==='/admin' || str_starts_with($path,'/admin/')) {require __DIR__.'/app/admin.php';exit;}
if ($path==='/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    $urls=['/','/about','/services','/projects','/technologies','/blog','/contact'];
    foreach(['projects'=>'projects','services'=>'services','posts'=>'blog'] as $collection=>$prefix) foreach(records($collection) as $r) $urls[]='/'.$prefix.'/'.$r['slug'];
    foreach($urls as $u) echo '<url><loc>'.e($config['origin'].$u).'</loc></url>'; echo '</urlset>';exit;
}
if ($path==='/robots.txt') {header('Content-Type: text/plain');echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /app/\nDisallow: /scripts/\nDisallow: /tests/\nSitemap: ".$config['origin']."/sitemap.xml\n";exit;}
$page=trim($path,'/') ?: 'home'; $item=null;
$meta=[
'home'=>['Custom Business Software & Laravel Developer','Manage customers, staff, stock and daily operations with custom software by Krunal Pawar. Get a free consultation for your business in India or worldwide.'],
'about'=>['About Krunal Pawar — Software Developer','Meet Krunal Pawar, a software developer with around 3 years of experience building Laravel applications, SaaS products, APIs and custom business systems.'],
'services'=>['Laravel & Custom Software Development Services','Custom Laravel, SaaS, CRM, HRMS, ERP, healthcare, inventory and warehouse development services for businesses in India and worldwide.'],
'projects'=>['Software Projects & Case Studies','Explore Krunal Pawar’s work in healthcare, SaaS, CRM, HRMS and warehouse management. Read the business problem, solution and contribution behind each project.'],
'technologies'=>['Laravel, PHP, Vue.js & Technology Expertise','How Krunal Pawar uses Laravel, PHP, MySQL, Vue.js, APIs, Redis and deployment tools to build and maintain complete business applications.'],
'blog'=>['Insights on Custom Software & SaaS Development','Practical articles on custom CRM development, SaaS planning, HRMS, warehouse systems and choosing software that fits your business.'],
'contact'=>['Discuss Your Project — Contact Krunal Pawar','Request a free project discussion for custom web applications, Laravel, SaaS, CRM, HRMS, ERP or application improvements. Work with Krunal Pawar remotely.'],
'privacy'=>['Project Enquiry Privacy Notice','How project enquiry information is used, stored and handled when you contact Krunal Pawar about software development.']];
if(preg_match('~^(projects|services|blog)/([a-z0-9-]+)$~',$page,$m)) {
    $item=record($m[1]==='blog'?'posts':$m[1],$m[2]);
    if($item){$page=['projects'=>'project','services'=>'service','blog'=>'article'][$m[1]];$meta[$page]=[$item['seo_title']??$item['title'],$item['seo_description']??$item['description']];}
}
if(!isset($meta[$page])){$page='404';http_response_code(404);$meta[$page]=['Page not found','This page could not be found. Explore projects and software development services by Krunal Pawar.'];}
$errors=[];$old=[];$success=false;
if($page==='contact'){require __DIR__.'/app/contact-handler.php';}
[$title,$description]=$meta[$page];
$canonical=$config['origin'].$path;
$schema=[['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'Krunal Pawar — Software Developer','url'=>$config['origin'].'/'],['@context'=>'https://schema.org','@type'=>'Person','@id'=>$config['origin'].'/#person','name'=>$config['name'],'jobTitle'=>'Software Developer','url'=>$config['origin'].'/about','sameAs'=>[$config['linkedin']],'knowsAbout'=>['Laravel','PHP','SaaS development','Custom business software']]];
if($page==='service')$schema[]=['@context'=>'https://schema.org','@type'=>'Service','name'=>$item['title'],'description'=>$description,'url'=>$canonical,'provider'=>['@id'=>$config['origin'].'/#person'],'areaServed'=>['India','Worldwide']];
if($page==='project')$schema[]=['@context'=>'https://schema.org','@type'=>'CreativeWork','name'=>$item['title'],'description'=>$description,'url'=>$canonical,'creator'=>['@id'=>$config['origin'].'/#person']];
if($page==='article')$schema[]=['@context'=>'https://schema.org','@type'=>'Article','headline'=>$item['title'],'description'=>$description,'datePublished'=>$item['date'].'T09:00:00+05:30','dateModified'=>($item['modified']??$item['date']).'T09:00:00+05:30','mainEntityOfPage'=>$canonical,'author'=>['@type'=>'Person','name'=>$config['name'],'url'=>$config['origin'].'/about']];
if($page==='home')$schema[]=['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>array_map(fn($f)=>['@type'=>'Question','name'=>$f['title'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$f['answer']]],records('faqs'))];
if($page!=='home' && $page!=='404'){
 $crumbs=[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$config['origin'].'/']];
 $segments=explode('/',trim($path,'/'));
 if(count($segments)>1)$crumbs[]=['@type'=>'ListItem','position'=>2,'name'=>ucfirst($segments[0]),'item'=>$config['origin'].'/'.$segments[0]];
 $crumbs[]=['@type'=>'ListItem','position'=>count($crumbs)+1,'name'=>$item['title']??ucfirst($page),'item'=>$canonical];
 $schema[]=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$crumbs];
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($title)?> | Krunal Pawar</title><meta name="description" content="<?=e($description)?>"><meta name="author" content="Krunal Pawar"><meta name="robots" content="<?=$page==='404'?'noindex, follow':'index, follow'?>"><link rel="canonical" href="<?=e($canonical)?>"><meta property="og:type" content="<?=$page==='article'?'article':'website'?>"><meta property="og:title" content="<?=e($title)?>"><meta property="og:description" content="<?=e($description)?>"><meta property="og:url" content="<?=e($canonical)?>"><meta property="og:site_name" content="Krunal Pawar"><meta name="twitter:card" content="summary"><meta name="twitter:title" content="<?=e($title)?>"><meta name="twitter:description" content="<?=e($description)?>"><meta name="theme-color" content="#f7f8fa"><link rel="icon" href="/assets/img/monogram.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/portfolio.css?v=1"><link rel="stylesheet" href="/assets/css/business.css?v=1"><script src="/assets/js/portfolio.js?v=2" defer></script><script type="application/ld+json"><?=json_encode($schema,JSON_HEX_TAG|JSON_HEX_AMP|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script></head>
<body><a class="skip-link" href="#main">Skip to content</a>
<header class="site-header"><div class="container nav-wrap"><a class="brand" href="/" aria-label="Krunal Pawar home"><span class="monogram">k<span>.</span></span><span>Krunal Pawar<span class="brand-role">SOFTWARE DEVELOPER</span></span></a><button class="menu-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Open navigation"><?=icon('menu')?></button><nav id="main-nav" aria-label="Main navigation"><?php foreach(['home'=>'Home','about'=>'About','services'=>'Services','projects'=>'Projects','technologies'=>'Technology','blog'=>'Insights'] as $key=>$label): $active=$page===$key||($key==='projects'&&$page==='project')||($key==='services'&&$page==='service')||($key==='blog'&&$page==='article');?><a href="<?=$key==='home'?'/':'/'.$key?>" <?=$active?'aria-current="page"':''?>><?=$label?></a><?php endforeach;?></nav><a class="button nav-cta" href="/contact">Let’s talk <?=icon('diagonal')?></a></div></header>
<main id="main">
<?php if($page!=='home'):?><div class="container breadcrumbs"><a href="/">Home</a><span>/</span><?php if($item):?><a href="/<?=e(explode('/',trim($path,'/'))[0])?>"><?=e(ucfirst(explode('/',trim($path,'/'))[0]))?></a><span>/</span><?php endif;?><span aria-current="page"><?=e($item['title']??ucfirst($page))?></span></div><?php endif;?>
<?php require __DIR__.'/app/views/'.(in_array($page,['home','about','services','projects','technologies','blog','contact','project','service','article','privacy','404'])?$page:'404').'.php';?>
</main>
<footer class="site-footer"><div class="container footer-grid"><div><a class="brand" href="/"><span class="monogram">k<span>.</span></span><span>Krunal Pawar</span></a><p>Custom software.<br>Real business solutions.</p><span class="remote"><?=icon('globe')?> Based in India. Working worldwide.</span></div><div><h2>Explore</h2><a href="/about">About me</a><a href="/projects">Selected work</a><a href="/technologies">Technology stack</a><a href="/blog">Insights</a></div><div><h2>What I build</h2><a href="/services/laravel-development">Laravel applications</a><a href="/services/saas-development">SaaS platforms</a><a href="/services/crm-development">Business software</a><a href="/services/api-development-integration">APIs & integrations</a></div><div><h2>Let’s connect</h2><a href="mailto:<?=e($config['email'])?>"><?=e($config['email'])?></a><a href="tel:<?=e($config['phone_uri'])?>"><?=e($config['phone'])?></a><a href="<?=e($config['linkedin'])?>" target="_blank" rel="noopener noreferrer">LinkedIn ↗</a><a href="https://wa.me/916351716007" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a></div></div><div class="container footer-bottom"><span>© <?=date('Y')?> Krunal Pawar. Built with purpose.</span><a href="/privacy">Privacy</a><a href="#main">Back to top ↑</a></div></footer>
</body></html>
