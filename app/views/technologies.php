<?php
$tech=records('technologies');
$techIcons=[
    'Laravel'=>['asset'=>'laravel.svg','class'=>'laravel'],
    'PHP'=>['asset'=>'php.svg','class'=>'php'],
    'REST APIs'=>['icon'=>'link','class'=>'api'],
    'Node.js'=>['asset'=>'nodedotjs.svg','class'=>'nodejs'],
    'Vue.js'=>['asset'=>'vuedotjs.svg','class'=>'vue'],
    'JavaScript'=>['asset'=>'javascript.svg','class'=>'javascript'],
    'Tailwind CSS'=>['asset'=>'tailwindcss.svg','class'=>'tailwind'],
    'Bootstrap'=>['asset'=>'bootstrap.svg','class'=>'bootstrap'],
    'Livewire'=>['asset'=>'livewire.svg','class'=>'livewire'],
    'Inertia.js'=>['asset'=>'inertia.svg','class'=>'inertia'],
    'jQuery'=>['asset'=>'jquery.svg','class'=>'jquery'],
    'Vite'=>['asset'=>'vite.svg','class'=>'vite'],
    'MySQL'=>['asset'=>'mysql.svg','class'=>'mysql'],
    'Redis'=>['asset'=>'redis.svg','class'=>'redis'],
    'Queues'=>['icon'=>'queue','class'=>'queues'],
    'AWS S3'=>['icon'=>'cloud','class'=>'s3'],
    'Linux Servers'=>['asset'=>'linux.svg','class'=>'linux'],
    'Git'=>['asset'=>'git.svg','class'=>'git'],
    'OpenAI Codex'=>['icon'=>'terminal','class'=>'codex'],
    'Claude'=>['asset'=>'claude.svg','class'=>'claude'],
];
?>
<header class="technology-hero">
    <img class="technology-hero-background" src="/assets/img/technology-stack-hero.webp" width="1672" height="941" alt="" fetchpriority="high" decoding="async">
    <div class="container technology-hero-content">
        <div class="technology-hero-copy">
            <span class="eyebrow">MY TECHNOLOGY STACK</span>
            <h1>The right tools.<br><span class="muted-heading">For the right reasons.</span></h1>
            <p>A practical stack for complete web applications, from the data underneath to the interface your team uses every day.</p>
            <div class="technology-highlights" aria-label="Technology approach">
                <span><?=icon('layers')?> Full-stack delivery</span>
                <span><?=icon('shield')?> Maintainable systems</span>
                <span><?=icon('terminal')?> Practical tooling</span>
            </div>
        </div>
    </div>
</header>
<div class="container">
    <section class="listing-section technology-list" aria-labelledby="technology-list-heading">
        <div class="technology-list-intro">
            <span class="eyebrow">TOOLS I WORK WITH</span>
            <h2 id="technology-list-heading">A connected stack, not a collection of buzzwords.</h2>
        </div>
        <?php foreach(array_unique(array_column($tech,'group')) as $group):?>
            <div class="stack-group">
                <h2><?=e($group)?></h2>
                <div class="stack-grid">
                    <?php foreach($tech as $t):if($t['group']!==$group)continue;$badge=$techIcons[$t['title']]??['icon'=>'code','class'=>'default'];?>
                        <article class="stack-card">
                            <div class="stack-card-header">
                                <span class="stack-card-icon tech-<?=e($badge['class'])?>" aria-hidden="true"><?php if(isset($badge['asset'])):?><img src="/assets/img/technologies/<?=e($badge['asset'])?>" alt="" width="25" height="25" loading="lazy"><?php else:?><?=icon($badge['icon'])?><?php endif;?></span>
                                <h3><?=e($t['title'])?></h3>
                            </div>
                            <p><?=e($t['description'])?></p>
                        </article>
                    <?php endforeach;?>
                </div>
            </div>
        <?php endforeach;?>
    </section>
</div>
<?php cta('Your requirements first.<br>The technology follows.');?>
