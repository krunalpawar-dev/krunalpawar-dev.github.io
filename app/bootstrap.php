<?php
declare(strict_types=1);
$config = require __DIR__ . '/config.php';
date_default_timezone_set('Asia/Kolkata');
if (!is_dir($config['storage']) && !mkdir($config['storage'], 0700, true) && !is_dir($config['storage'])) {
    throw new RuntimeException('Cannot create private storage.');
}
$db = new PDO('sqlite:' . $config['storage'] . '/portfolio.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
$db->exec('CREATE TABLE IF NOT EXISTS content (collection TEXT NOT NULL, slug TEXT NOT NULL, data TEXT NOT NULL, PRIMARY KEY(collection, slug));
CREATE TABLE IF NOT EXISTS leads (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, company TEXT, email TEXT NOT NULL, phone TEXT, project_type TEXT, budget TEXT, description TEXT, contact_method TEXT, status TEXT DEFAULT "new", created_at TEXT NOT NULL, mail_status TEXT DEFAULT "pending");
CREATE TABLE IF NOT EXISTS rate_limits (key TEXT PRIMARY KEY, attempts INTEGER NOT NULL, expires INTEGER NOT NULL);');
$count = (int)$db->query('SELECT COUNT(*) FROM content')->fetchColumn();
if (!$count) {
    $seed = json_decode(file_get_contents(__DIR__.'/content.json'), true, 512, JSON_THROW_ON_ERROR);
    $db->beginTransaction();
    $insert = $db->prepare('INSERT OR IGNORE INTO content(collection,slug,data) VALUES(?,?,?)');
    foreach ($seed as $collection => $records) foreach ($records as $record) $insert->execute([$collection, $record['slug'], json_encode($record, JSON_UNESCAPED_UNICODE)]);
    $db->commit();
}
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function records(string $collection, bool $all = false): array {
    global $db;
    $q = $db->prepare('SELECT data FROM content WHERE collection=? ORDER BY rowid'); $q->execute([$collection]);
    $rows = array_map(fn($r) => json_decode($r, true), $q->fetchAll(PDO::FETCH_COLUMN));
    return $all ? $rows : array_values(array_filter($rows, fn($r) => ($r['published'] ?? true) === true));
}
function record(string $collection, string $slug): ?array { foreach (records($collection) as $r) if ($r['slug'] === $slug) return $r; return null; }
function icon(string $name, string $class = ''): string {
    $paths = ['arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>','diagonal'=>'<path d="M6 18 18 6M6 6h12v12"/>','code'=>'<path d="m8 7-5 5 5 5m8-10 5 5-5 5m-3-14-2 18"/>','layers'=>'<path d="m12 3 10 5-10 5L2 8Zm-10 9 10 5 10-5M2 16l10 5 10-5"/>','grid'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>','link'=>'<path d="m10 13 4-4m-6 7-2 2a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0m4 8 4-4a4 4 0 0 0-6-6l-2 2" transform="translate(2 0)"/>','check'=>'<path d="m5 12 4 4L19 6"/>','globe'=>'<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>','mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>','menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>','shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-5"/>','terminal'=>'<path d="m5 7 5 5-5 5m8 0h6"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>'];
    return '<svg class="icon '.e($class).'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['code']).'</svg>';
}
function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('portfolio_session');
        session_start(['cookie_httponly'=>true, 'cookie_secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'cookie_samesite'=>'Lax', 'use_strict_mode'=>true]);
    }
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf(): string { start_session(); return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function verify_csrf(): bool { start_session(); return is_string($_POST['csrf'] ?? null) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }
function throttle(string $key, int $limit, int $seconds): bool {
    global $db; $now=time();
    $db->prepare('DELETE FROM rate_limits WHERE expires < ?')->execute([$now]);
    $key = hash('sha256', $key);
    $db->prepare('INSERT INTO rate_limits(key,attempts,expires) VALUES(?,1,?) ON CONFLICT(key) DO UPDATE SET attempts=attempts+1')->execute([$key, $now+$seconds]);
    $q=$db->prepare('SELECT attempts FROM rate_limits WHERE key=?');$q->execute([$key]);return (int)$q->fetchColumn() <= $limit;
}
function send_lead_email(array $lead): bool {
    global $config;
    if (!$config['mail_enabled'] || !filter_var($config['mail_from'], FILTER_VALIDATE_EMAIL)) return false;
    $body="New project enquiry #".$lead['id']."\n\n";
    foreach (['name','company','email','phone','project_type','budget','description','contact_method'] as $key) $body.=ucwords(str_replace('_',' ',$key)).': '.$lead[$key]."\n\n";
    return @mail($config['email'], 'New portfolio project enquiry #'.$lead['id'], $body, ['From'=>$config['mail_from'],'Reply-To'=>$lead['email'],'Content-Type'=>'text/plain; charset=UTF-8']);
}
function project_visual(array $p): void {
    $themes=['medical'=>'Healthcare operations','treeva'=>'Connected patient care','education'=>'One platform. Many institutions.','crm'=>'A clearer sales process','hrms'=>'People. Policies. Payroll.','warehouse'=>'Every movement, connected.'];
    echo '<div class="project-visual theme-'.e($p['theme']).'" aria-label="'.e($p['title']).' system diagram"><div class="visual-top"><span class="mini-brand">'.icon($p['icon']).' '.e($p['short']).'</span><span class="visual-caption">SYSTEM OVERVIEW</span></div><div class="diagram-title">'.e($themes[$p['theme']] ?? $p['title']).'</div><div class="flow-diagram">';
    foreach (array_slice($p['features'],0,3) as $i=>$f) echo '<div><span class="node-number">0'.($i+1).'</span><span>'.e($f).'</span>'.icon('check').'</div>';
    echo '</div><span class="diagram-note">'.e($p['industry']).' / '.e($p['architecture']).'</span></div>';
}
function project_card(array $p): void { echo '<article class="project-card" data-categories="'.e(implode('|',$p['categories'])).'">'; project_visual($p); echo '<div class="project-card-body"><div class="eyebrow">'.e($p['industry']).'</div><h3><a href="/projects/'.e($p['slug']).'">'.e($p['title']).icon('diagonal').'</a></h3><p>'.e($p['description']).'</p><div class="tags">'; foreach($p['technologies'] as $t) echo '<span>'.e($t).'</span>'; echo '</div><a class="text-link" href="/projects/'.e($p['slug']).'">See what the system does '.icon('arrow').'</a></div></article>'; }
function cta(string $heading = 'Have a project in mind?<br>Let’s build it.'): void { echo '<section class="cta-band container"><div><span class="eyebrow">YOUR NEXT CHAPTER</span><h2>'.$heading.'</h2><p>Tell me what is difficult to manage today. We’ll discuss possible solutions, the next steps and a quotation.</p></div><a class="button light" href="/contact">Get a free consultation '.icon('diagonal').'</a></section>'; }
