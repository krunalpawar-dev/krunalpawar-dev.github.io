const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('#main-nav');
toggle?.addEventListener('click', () => { const open = toggle.getAttribute('aria-expanded') !== 'true'; toggle.setAttribute('aria-expanded', String(open)); toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation'); nav.classList.toggle('is-open', open); });
document.addEventListener('keydown', e => { if(e.key === 'Escape' && toggle) { toggle.setAttribute('aria-expanded','false'); nav.classList.remove('is-open'); } });
document.querySelectorAll('[data-filter-form]').forEach(form => {
 const buttons = form.querySelectorAll('[data-filter]');
 const cards = document.querySelectorAll('[data-categories]');
 const status = document.querySelector('[data-filter-status]');
 const apply = value => { let count=0; cards.forEach(card => { const visible = value === 'All' || card.dataset.categories.split('|').includes(value); card.hidden = !visible; if(visible)count++; }); buttons.forEach(button => { const selected=button.dataset.filter===value; button.classList.toggle('selected', selected); button.setAttribute('aria-pressed', String(selected)); }); if(status)status.textContent=count ? `${count} ${count === 1 ? 'result' : 'results'}` : 'No entries in this category yet. Explore another category.'; };
 buttons.forEach(button => button.addEventListener('click', e => { e.preventDefault(); apply(button.dataset.filter); const url=new URL(location.href); button.dataset.filter==='All'?url.searchParams.delete('category'):url.searchParams.set('category',button.dataset.filter);history.replaceState(null,'',url); }));
});
document.querySelectorAll('[data-confirm-delete]').forEach(form => form.addEventListener('submit',e=>{if(!confirm('Delete this content record? This cannot be undone.'))e.preventDefault();}));
const inquiry=document.querySelector('#inquiry-form');
inquiry?.addEventListener('submit',()=>{if(inquiry.checkValidity()){const button=inquiry.querySelector('[type=submit]');button.disabled=true;button.textContent='Sending your enquiry…';}});
