<header class="services-hero">
    <img class="services-hero-background" src="/assets/img/services-hero.webp" width="1752" height="897" alt="" fetchpriority="high" decoding="async">
    <div class="container services-hero-content">
        <div class="services-hero-copy">
            <span class="eyebrow">DEVELOPMENT SERVICES</span>
            <h1>What would you like<br><span class="muted-heading">to manage better?</span></h1>
            <p>From customer follow-ups to staff attendance and stock records, I build software around the way your business works. Choose a service below—or describe your problem and I’ll help you decide.</p>
            <a class="button light" href="/contact">Help me choose a solution <?=icon('diagonal')?></a>
            <div class="services-highlights" aria-label="Development service approach">
                <span><?=icon('grid')?> Plan the workflow</span>
                <span><?=icon('code')?> Build the solution</span>
                <span><?=icon('shield')?> Support it reliably</span>
            </div>
        </div>
    </div>
</header>
<div class="container">
    <section class="listing-section service-detail-grid services-listing" aria-label="Development services">
        <?php foreach(records('services') as $s):?>
            <article class="service-card" id="<?=e($s['slug'])?>">
                <span class="service-icon"><?=icon($s['icon'])?></span>
                <h2><a href="/services/<?=e($s['slug'])?>"><?=e($s['title'])?></a></h2>
                <p><?=e($s['description'])?></p>
                <ul><?php foreach($s['features'] as $f):?><li><?=e($f)?></li><?php endforeach;?></ul>
                <a class="text-link" href="/services/<?=e($s['slug'])?>">Explore this service <?=icon('arrow')?></a>
            </article>
        <?php endforeach;?>
    </section>
</div>
<?php cta('A specific challenge?<br>Let’s work through it.');?>
