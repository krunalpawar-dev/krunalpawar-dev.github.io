"""Build the public portfolio for the existing GitHub Pages branch deployment.

Uses an isolated PHP renderer only at build time. The published HTML needs no PHP.
Never reads private CMS data or sends enquiry notifications.
"""
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlsplit, urlunsplit
from urllib.request import urlopen
from urllib.error import HTTPError

ROOT = Path(__file__).resolve().parents[1]
ORIGIN = 'https://krunal02101999.github.io'
BASE = '/portfolio'
PUBLIC = ORIGIN + BASE
FORM = 'https://formspree.io/f/xdkeodjk'  # Same endpoint as the original live portfolio.

def build():
    data = json.loads((ROOT / 'app/content.json').read_text(encoding='utf-8'))
    routes = ['/', '/about', '/services', '/projects', '/technologies', '/blog', '/contact', '/privacy']
    for collection, prefix in [('projects', 'projects'), ('services', 'services'), ('posts', 'blog')]:
        routes += [f'/{prefix}/{row["slug"]}' for row in data[collection] if row.get('published', True)]
    php = subprocess.check_output([shutil.which('php'), '-r', 'echo PHP_BINARY;'], text=True).strip()
    with tempfile.TemporaryDirectory(prefix='portfolio-pages-build-') as temporary:
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        env = dict(os.environ, APP_URL=PUBLIC, STORAGE_PATH=temporary, MAIL_ENABLED='false', ADMIN_PASSWORD_HASH='')
        with open(Path(temporary) / 'render.log', 'w+', encoding='utf-8', newline='\n') as log:
            process = subprocess.Popen([php, '-S', f'127.0.0.1:{port}', 'router.php'], cwd=ROOT, env=env, stdout=log, stderr=log)
            local = f'http://127.0.0.1:{port}'
            try:
                for _ in range(80):
                    try:
                        with urlopen(local, timeout=1) as response:
                            if response.status == 200:
                                break
                    except OSError:
                        time.sleep(.1)
                else:
                    raise RuntimeError('PHP renderer did not start.')

                def rewrite(match):
                    attribute, value = match.groups()
                    parsed = urlsplit(value)
                    path = parsed.path
                    if path in routes and path != '/':
                        path += '/'
                    return f'{attribute}="{urlunsplit(("", "", BASE + path, parsed.query, parsed.fragment))}"'

                for route in routes:
                    with urlopen(local + route) as response:
                        html = response.read().decode('utf-8').replace('\r\n', '\n')
                    if route == '/contact':
                        html = html.replace('action="/contact"', f'action="{FORM}"')
                        html = re.sub(r'<input type="hidden" name="csrf" value="[^"]+">', '', html)
                        html = html.replace('name="website"', 'name="_gotcha"')
                        html = html.replace('<div class="form-grid">', '<input type="hidden" name="_subject" value="New portfolio project enquiry"><div class="form-grid">', 1)
                        html = html.replace('No obligation. Just a useful first conversation.', 'Your enquiry is securely submitted through Formspree. No obligation—just a useful first conversation.')
                    if route == '/privacy':
                        html = html.replace('Enquiries are stored in a private application database. A notification may also be sent to the developer’s email account. Hosting and email providers process this information as needed to operate those services.', 'This GitHub Pages website sends enquiries to Formspree, the same form service used by the previous portfolio. Formspree processes your submission for delivery to the developer. GitHub Pages does not run the private application database or content manager. You can also contact the developer directly by email or WhatsApp.')
                        html = html.replace('The contact form and content manager use an essential session cookie for request protection and sign-in. The site does not include advertising trackers. Short-lived, hashed network identifiers are used to limit automated form abuse.', 'This static portfolio does not use application sign-in or session cookies. It does not include advertising trackers. When you submit the contact form, Formspree may apply its own anti-spam and service protections.')
                    html = re.sub(r'(href|src|action)="(/[^"\s]*)"', rewrite, html)
                    # Keep canonical, social and structured-data URLs aligned with directory pages.
                    for public_route in sorted(routes, key=len, reverse=True):
                        if public_route != '/':
                            html = html.replace(PUBLIC + public_route + '"', PUBLIC + public_route + '/"')
                    destination = ROOT / route.strip('/') / 'index.html' if route != '/' else ROOT / 'index.html'
                    destination.parent.mkdir(parents=True, exist_ok=True)
                    destination.write_text(html, encoding='utf-8', newline='\n')
                try:
                    urlopen(local + '/missing-page')
                    raise RuntimeError('Missing page must return 404.')
                except HTTPError as error:
                    if error.code != 404:
                        raise
                    html = error.read().decode('utf-8').replace('\r\n', '\n')
                    html = re.sub(r'(href|src|action)="(/[^"\s]*)"', rewrite, html)
                    (ROOT / '404.html').write_text(html, encoding='utf-8', newline='\n')
            finally:
                process.terminate()
                process.wait(timeout=10)
            log.flush()
            log.seek(0)
            if re.search(r'PHP (Warning|Fatal error|Parse error)', log.read()):
                raise RuntimeError('The renderer emitted PHP diagnostics.')
    sitemap = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
    sitemap += ''.join(f'<url><loc>{PUBLIC}{route.rstrip("/")}/</loc></url>\n' for route in routes)
    (ROOT / 'sitemap.xml').write_text(sitemap + '</urlset>\n', encoding='utf-8', newline='\n')
    (ROOT / 'robots.txt').write_text(f'User-agent: *\nAllow: {BASE}/\nDisallow: {BASE}/app/\nDisallow: {BASE}/scripts/\nDisallow: {BASE}/tests/\nSitemap: {PUBLIC}/sitemap.xml\n', encoding='utf-8', newline='\n')
    (ROOT / '.nojekyll').write_text('', encoding='utf-8', newline='\n')
    print(f'Built {len(routes)} public HTML pages, 404 page and sitemap for {PUBLIC}/')

if __name__ == '__main__':
    build()
