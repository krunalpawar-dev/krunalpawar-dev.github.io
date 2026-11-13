<?php
$categories=array_column(records('project_categories'),'title');
$selected=is_string($_GET['category']??null)&&in_array($_GET['category'],$categories)?$_GET['category']:'All';
$projectRecords=projects_by_weight();
$visibleCount=count(array_filter($projectRecords,fn($p)=>$selected==='All'||in_array($selected,$p['categories'])));
?>
<header class="projects-hero">
    <img class="projects-hero-background" src="/assets/img/projects-hero.webp" width="1752" height="897" alt="" fetchpriority="high" decoding="async">
    <div class="container projects-hero-content">
        <div class="projects-hero-copy">
            <span class="eyebrow">PROJECTS & CASE STUDIES</span>
            <h1>Software for real<br><span class="muted-heading">business tasks.</span></h1>
            <p>Explore completed work and clearly marked concept systems across CRM, e-commerce, healthcare, HR, inventory and SaaS. Every entry focuses on the business problem, the workflow and the practical value software can create.</p>
            <div class="projects-highlights" aria-label="Project portfolio highlights">
                <span><?=icon('check')?> Completed work</span>
                <span><?=icon('layers')?> Clear concepts</span>
                <span><?=icon('grid')?> Business-focused systems</span>
            </div>
        </div>
    </div>
</header>
<div class="container">
    <section class="listing-section projects-listing" aria-label="Projects">
        <form class="filters" method="get" data-filter-form>
            <?php foreach(array_merge(['All'],$categories) as $cat):?>
                <button class="filter <?=$selected===$cat?'selected':''?>" type="submit" name="category" value="<?=e($cat)?>" data-filter="<?=e($cat)?>" aria-pressed="<?=$selected===$cat?'true':'false'?>"><?=e(['All'=>'All projects','SaaS'=>'Online products','CRM'=>'Customers & sales','HRMS'=>'Staff & payroll','ERP'=>'Business operations','E-commerce'=>'Online stores','Education'=>'Education','Inventory'=>'Stock & warehouses','Concept'=>'Concept projects','API'=>'Connected systems'][$cat]??$cat)?></button>
            <?php endforeach;?>
        </form>
        <p class="result-status" data-filter-status aria-live="polite"><?=$visibleCount?$visibleCount.($visibleCount===1?' project':' projects'):'No projects in this category yet. Explore another category.'?></p>
        <div class="project-grid"><?php foreach($projectRecords as $p){ob_start();project_card($p);$html=ob_get_clean();if($selected!=='All'&&!in_array($selected,$p['categories']))$html=str_replace('<article class=','<article hidden class=',$html);echo $html;}?></div>
    </section>
    <section class="section prose">
        <h2>Have a workflow like one of these?</h2>
        <p>Completed projects show delivered work. Concept projects are self-directed demonstrations of how I would approach other high-value business systems. Tell me what your team manages today, and we can shape the right version for you.</p>
        <a class="button secondary" href="/contact">Discuss your requirements <?=icon('arrow')?></a>
    </section>
</div>
<?php cta('Something similar in mind?<br>Let’s make it yours.');?>
