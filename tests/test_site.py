"""Isolated HTTP integration checks. No external services or real emails are used."""
import base64
from contextlib import contextmanager
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]

class SiteTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='portfolio-test-')
        cls.storage = Path(cls.temp.name)
        cls.php = subprocess.check_output([shutil.which('php'), '-r', 'echo PHP_BINARY;'], text=True).strip()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        cls.origin = f'http://127.0.0.1:{port}'
        env = os.environ.copy()
        env.update(STORAGE_PATH=str(cls.storage), APP_URL=cls.origin, MAIL_ENABLED='false', ADMIN_PASSWORD_HASH='', TEST_ADMIN_PASS='Test-only-password-479!')
        password_hash = subprocess.check_output([cls.php, '-r', "echo password_hash(getenv('TEST_ADMIN_PASS'), PASSWORD_DEFAULT);"], env=env, text=True)
        (cls.storage / 'admin-password.hash').write_text(password_hash)
        cls.log = open(cls.storage / 'server.log', 'w+', encoding='utf-8')
        cls.server = subprocess.Popen([cls.php, '-S', f'127.0.0.1:{port}', 'router.php'], cwd=ROOT, env=env, stdout=cls.log, stderr=cls.log)
        for _ in range(60):
            try:
                with urllib.request.urlopen(cls.origin, timeout=1) as response:
                    if response.status == 200:
                        break
            except (OSError, urllib.error.URLError):
                time.sleep(.1)
        else:
            raise RuntimeError('The isolated PHP server did not start.')
        cls.seed = json.loads((ROOT / 'app/content.json').read_text(encoding='utf-8'))

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=10)
        cls.log.flush()
        cls.log.seek(0)
        log = cls.log.read()
        cls.log.close()
        try:
            if re.search(r'PHP (Warning|Fatal error|Parse error|Deprecated)', log):
                raise AssertionError('PHP diagnostics were emitted:\n' + log)
        finally:
            cls.temp.cleanup()

    def setUp(self):
        self.jar = http.cookiejar.CookieJar()
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        with self.db() as db:
            db.execute('DELETE FROM rate_limits')
            db.execute('DELETE FROM leads')

    @contextmanager
    def db(self):
        connection = sqlite3.connect(self.storage / 'portfolio.sqlite')
        try:
            with connection:
                yield connection
        finally:
            connection.close()

    def request(self, path, data=None, headers=None):
        body = urllib.parse.urlencode(data).encode() if isinstance(data, dict) else data
        req = urllib.request.Request(self.origin + path, data=body, headers=headers or {})
        try:
            response = self.client.open(req, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            payload = response.read()
            return response.status, payload.decode('utf-8', errors='replace'), response.headers

    def csrf(self, html):
        match = re.search(r'name="csrf" value="([a-f0-9]+)"', html)
        self.assertIsNotNone(match)
        return match.group(1)

    def login(self):
        status, html, _ = self.request('/admin')
        self.assertEqual(status, 200)
        status, html, _ = self.request('/admin', {'csrf': self.csrf(html), 'action': 'login', 'password': 'Test-only-password-479!'})
        self.assertEqual(status, 200)
        self.assertIn('Sign out', html)
        return html

    def enquiry(self, token):
        return {'csrf': token, 'name': 'Integration Test', 'company': 'Test company', 'email': 'nobody@example.test', 'phone': '', 'project_type': 'CRM', 'budget': 'Let’s discuss', 'description': 'A test CRM enquiry used only by the isolated integration suite.', 'contact_method': 'Email', 'consent': 'yes', 'website': ''}

    def test_public_routes_metadata_links_and_sitemap(self):
        routes = ['/', '/about', '/services', '/projects', '/technologies', '/blog', '/contact', '/privacy']
        for collection, prefix in [('projects','projects'), ('services','services'), ('posts','blog')]:
            routes += [f'/{prefix}/{item["slug"]}' for item in self.seed[collection]]
        titles, descriptions, links = set(), set(), set()
        for route in routes:
            with self.subTest(route=route):
                status, html, headers = self.request(route)
                self.assertEqual(status, 200)
                self.assertEqual(len(re.findall(r'<h1(?:\s|>)', html)), 1)
                title = re.search(r'<title>(.*?)</title>', html).group(1)
                description = re.search(r'<meta name="description" content="([^"]+)"', html).group(1)
                self.assertNotIn(title, titles)
                self.assertNotIn(description, descriptions)
                titles.add(title); descriptions.add(description)
                self.assertIn(f'rel="canonical" href="{self.origin}{route}"', html)
                self.assertIn('property="og:title"', html)
                self.assertIn('name="twitter:description"', html)
                schemas = json.loads(re.search(r'<script type="application/ld\+json">(.*?)</script>', html).group(1))
                types = {s['@type'] for s in schemas}
                self.assertTrue({'Person', 'WebSite'} <= types)
                if route.startswith('/blog/'):
                    self.assertIn('Article', types)
                    self.assertIn('In this article', html)
                if route.startswith('/services/'):
                    self.assertIn('Service', types)
                if route.startswith('/projects/'):
                    self.assertIn('CreativeWork', types)
                self.assertIn('nosniff', headers.get('X-Content-Type-Options', ''))
                self.assertNotRegex(html, r'(Warning:|Fatal error:|Parse error:)')
                links.update(re.findall(r'(?:href|src)="(/[^"#]*)', html))
        for link in links:
            with self.subTest(link=link):
                status, _, _ = self.request(link)
                self.assertEqual(status, 200)
        status, xml, _ = self.request('/sitemap.xml')
        self.assertEqual(status, 200)
        root = ET.fromstring(xml)
        sitemap_urls = {el.text for el in root.iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')}
        self.assertEqual(len(sitemap_urls), 27)
        self.assertTrue({self.origin + r for r in routes if r != '/privacy'} <= sitemap_urls)
        _, robots, _ = self.request('/robots.txt')
        self.assertIn('Disallow: /admin', robots)
        self.assertIn(self.origin + '/sitemap.xml', robots)

    def test_missing_private_and_legacy_routes(self):
        for path in ['/missing-page', '/app/content.json', '/scripts/create-admin.php', '/.git/config', '/tests/test_site.py', '/media/not-an-image.webp']:
            with self.subTest(path=path):
                status, html, _ = self.request(path)
                self.assertEqual(status, 404)
                self.assertIn('noindex', html)
        for path in ['/index.html', '/crm.html', '/forms/contact.php']:
            status, _, _ = self.request(path)
            self.assertEqual(status, 200)

    def test_project_and_blog_filters_without_javascript(self):
        _, html, _ = self.request('/projects?category=HRMS')
        self.assertIn('1 case study', html)
        self.assertEqual(len(re.findall(r'<article hidden class="project-card"', html)), 1)
        _, html, _ = self.request('/blog?category=PHP')
        self.assertIn('No articles in this category yet', html)
        self.assertIn('aria-pressed="true">PHP', html)
        _, html, _ = self.request('/contact?service=crm-development')
        self.assertIn('<option value="CRM" selected>Customers, sales &amp; follow-ups</option>', html)

    def test_enquiry_persistence_and_replay(self):
        _, html, headers = self.request('/contact')
        self.assertEqual(headers.get('Cache-Control'), 'no-store')
        data = self.enquiry(self.csrf(html))
        status, html, _ = self.request('/contact', data)
        self.assertEqual(status, 200)
        self.assertIn('Your enquiry is in.', html)
        with self.db() as db:
            row = db.execute('SELECT name,email,mail_status FROM leads').fetchone()
            self.assertEqual(row, ('Integration Test', 'nobody@example.test', 'pending'))
        _, html, _ = self.request('/contact')
        self.assertNotIn('Your enquiry is in.', html)
        status, _, _ = self.request('/contact', data)
        self.assertEqual(status, 422)
        with self.db() as db:
            self.assertEqual(db.execute('SELECT COUNT(*) FROM leads').fetchone()[0], 1)

    def test_enquiry_validation_csrf_and_rate_limit(self):
        _, html, _ = self.request('/contact')
        token = self.csrf(html)
        for mutation in [{'email': 'not-an-email'}, {'csrf': 'forged'}, {'description': 'short'}, {'contact_method': 'Phone', 'phone': ''}, {'consent': ''}]:
            data = self.enquiry(token); data.update(mutation)
            status, _, _ = self.request('/contact', data)
            self.assertEqual(status, 422)
        status, html, _ = self.request('/contact', self.enquiry(token))
        self.assertEqual(status, 429)
        self.assertIn('Too many enquiries', html)
        with self.db() as db:
            self.assertEqual(db.execute('SELECT COUNT(*) FROM leads').fetchone()[0], 0)

    def test_admin_auth_and_csrf(self):
        status, html, headers = self.request('/admin?collection=projects')
        self.assertNotIn('Bharat Medical Hall', html)
        self.assertIn('Administrator password', html)
        self.assertIn('noindex', headers.get('X-Robots-Tag', ''))
        self.login()
        _, html, _ = self.request('/admin?collection=projects')
        self.assertIn('Bharat Medical Hall', html)
        status, _, _ = self.request('/admin?collection=projects', {'action': 'delete', 'slug': self.seed['projects'][0]['slug'], 'csrf': 'forged'})
        self.assertEqual(status, 403)
        status, _, _ = self.request('/projects/' + self.seed['projects'][0]['slug'])
        self.assertEqual(status, 200)

    def test_admin_draft_publish_edit_delete_and_escape(self):
        self.login()
        route = '/admin?collection=posts&edit=new'
        _, html, _ = self.request(route)
        fields = {'csrf': self.csrf(html), 'action': 'save', 'slug': 'integration-test-article', 'title': 'An isolated test article', 'description': 'An isolated test article description.', 'category': 'Laravel', 'date': '2026-09-09', 'read_time': '3 min read', 'sections': 'Test heading\n<script>alert(1)</script> should be escaped.', 'seo_title': 'Unique test SEO title', 'seo_description': 'Unique test SEO description'}
        status, html, _ = self.request(route, fields)
        self.assertEqual(status, 200)
        self.assertIn('Content saved', html)
        self.assertEqual(self.request('/blog/integration-test-article')[0], 404)
        self.assertNotIn('integration-test-article', self.request('/sitemap.xml')[1])
        route = '/admin?collection=posts&edit=integration-test-article'
        _, html, _ = self.request(route)
        fields.update(csrf=self.csrf(html), published='on')
        self.request(route, fields)
        status, html, _ = self.request('/blog/integration-test-article')
        self.assertEqual(status, 200)
        self.assertIn('Unique test SEO title', html)
        self.assertIn('&lt;script&gt;alert(1)&lt;/script&gt;', html)
        self.assertIn('integration-test-article', self.request('/sitemap.xml')[1])
        fields['title'] = 'An updated test article'
        self.request(route, fields)
        self.assertIn('An updated test article', self.request('/blog/integration-test-article')[1])
        self.request('/admin?collection=posts', {'csrf': fields['csrf'], 'action': 'delete', 'slug': fields['slug']})
        self.assertEqual(self.request('/blog/integration-test-article')[0], 404)

    def test_lead_management(self):
        _, html, _ = self.request('/contact')
        self.request('/contact', self.enquiry(self.csrf(html)))
        html = self.login()
        with self.db() as db:
            lead_id = db.execute('SELECT id FROM leads').fetchone()[0]
        _, html, _ = self.request(f'/admin?lead={lead_id}')
        token = self.csrf(html)
        self.request(f'/admin?lead={lead_id}', {'csrf': token, 'action': 'lead-status', 'id': lead_id, 'status': 'qualified'})
        with self.db() as db:
            self.assertEqual(db.execute('SELECT status FROM leads').fetchone()[0], 'qualified')
        self.request('/admin', {'csrf': token, 'action': 'delete-lead', 'id': lead_id})
        with self.db() as db:
            self.assertEqual(db.execute('SELECT COUNT(*) FROM leads').fetchone()[0], 0)

    def test_screenshot_upload(self):
        self.login()
        route = '/admin?collection=projects&edit=' + self.seed['projects'][0]['slug']
        _, html, _ = self.request(route)
        token = self.csrf(html)
        image = (ROOT / 'assets/img/my-profile-img.jpg').read_bytes()
        boundary = 'PortfolioIntegrationBoundary'
        chunks = []
        for key, value in [('csrf', token), ('action', 'upload')]:
            chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="screenshot"; filename="test.jpg"\r\nContent-Type: image/jpeg\r\n\r\n'.encode() + image + b'\r\n')
        chunks.append(f'--{boundary}--\r\n'.encode())
        status, html, _ = self.request(route, b''.join(chunks), {'Content-Type': f'multipart/form-data; boundary={boundary}'})
        self.assertEqual(status, 200)
        self.assertIn('Screenshot uploaded', html)
        media = re.search(r'/media/[a-f0-9]{32}\.webp', html).group(0)
        status, _, headers = self.request(media)
        self.assertEqual(status, 200)
        self.assertEqual(headers.get('Content-Type'), 'image/webp')
        self.assertTrue((self.storage / media.lstrip('/')).is_file())

if __name__ == '__main__':
    unittest.main(verbosity=2)
