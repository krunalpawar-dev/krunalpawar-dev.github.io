"""Validate the actual static artifacts served by GitHub Pages, including subpath URLs."""
import json
from html.parser import HTMLParser
from pathlib import Path
import re
import unittest
from urllib.parse import urlsplit, unquote
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
BASE = ''
ORIGIN = 'https://krunalpawar-dev.github.io'

class PageParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.urls = []
        self.ids = set()
        self.canonical = None
        self.h1 = 0
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'h1': self.h1 += 1
        if 'id' in attrs: self.ids.add(attrs['id'])
        for attr in ('href', 'src', 'action'):
            if attrs.get(attr): self.urls.append(attrs[attr])
        if tag == 'link' and attrs.get('rel') == 'canonical': self.canonical = attrs['href']

class PagesTests(unittest.TestCase):
    def test_all_public_pages_and_links(self):
        sitemap = ET.parse(ROOT / 'sitemap.xml')
        urls = [el.text for el in sitemap.iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')]
        self.assertEqual(len(urls), 29)
        for url in urls:
            with self.subTest(url=url):
                self.assertTrue(url.startswith(ORIGIN + BASE + '/'))
                self.assertTrue(url.endswith('/'))
                relative = urlsplit(url).path.removeprefix(BASE).strip('/')
                path = ROOT / relative / 'index.html'
                html = path.read_text(encoding='utf-8')
                self.assertNotIn('<?php', html)
                self.assertNotIn('portfolio.test', html)
                self.assertNotIn('name="csrf"', html)
                self.assertNotIn('Run locally', html)
                page = PageParser(); page.feed(html)
                self.assertEqual(page.h1, 1)
                self.assertEqual(page.canonical, url)
                json.loads(re.search(r'<script type="application/ld\+json">(.*?)</script>', html).group(1))
                for target in page.urls:
                    parsed = urlsplit(target)
                    if parsed.scheme or parsed.netloc: continue
                    if target.startswith('#'):
                        self.assertIn(parsed.fragment, page.ids)
                        continue
                    self.assertTrue(parsed.path.startswith(BASE + '/'), target)
                    file = ROOT / unquote(parsed.path.removeprefix(BASE + '/'))
                    if file.is_dir(): file /= 'index.html'
                    self.assertTrue(file.is_file(), target)
        self.assertTrue((ROOT / '.nojekyll').is_file())
        self.assertTrue((ROOT / '404.html').is_file())

    def test_only_confirmed_project_is_published(self):
        import json
        content = json.loads((ROOT / 'app/content.json').read_text(encoding='utf-8'))
        self.assertEqual(len(content['projects']), 3)
        project = content['projects'][0]
        self.assertEqual(len(project['features']), 10)
        self.assertIn('Full-Stack Laravel Developer', project['contribution'])
        kumbh = content['projects'][1]
        self.assertEqual(kumbh['slug'], 'kumbhsnaan-digital-ritual-booking-platform')
        self.assertIn('Devotee photo upload', kumbh['features'])
        self.assertEqual(len(kumbh['screenshots']), 2)
        self.assertEqual(kumbh['technologies'], [])
        treeva = content['projects'][2]
        self.assertEqual(treeva['slug'], 'treeva-healthcare-management-system')
        self.assertEqual(sum(f.startswith('Website: ') for f in treeva['features']), 6)
        self.assertEqual(sum(f.startswith('Admin panel: ') for f in treeva['features']), 10)
        self.assertIn('Full-Stack Laravel Developer', treeva['contribution'])
        page = (ROOT / 'projects' / treeva['slug'] / 'index.html').read_text(encoding='utf-8')
        self.assertIn('Public website features', page)
        self.assertIn('Admin panel features', page)
        for slug in ['education-management-saas',
                     'crm-business-management-platform', 'hrms-payroll-system', 'warehouse-management-system']:
            for prefix in ['', 'portfolio/']:
                self.assertFalse((ROOT / f'{prefix}projects/{slug}/index.html').exists())

    def test_previous_project_urls_redirect_to_root(self):
        for route in ['', 'about', 'contact', 'projects/bharat-medical-backoffice-management-system']:
            html = (ROOT / 'portfolio' / route / 'index.html').read_text(encoding='utf-8')
            target = ORIGIN + '/' + (route + '/' if route else '')
            self.assertIn('http-equiv="refresh"', html)
            self.assertIn('url=' + target, html)
            self.assertIn('rel="canonical" href="' + target + '"', html)

    def test_contact_uses_existing_working_service(self):
        html = (ROOT / 'contact/index.html').read_text(encoding='utf-8')
        self.assertIn('action="https://formspree.io/f/xdkeodjk"', html)
        self.assertIn('name="_gotcha"', html)
        self.assertNotIn('name="csrf"', html)
        self.assertIn('mailto:kmpawar0004@gmail.com', html)
        privacy = (ROOT / 'privacy/index.html').read_text(encoding='utf-8')
        self.assertIn('Formspree', privacy)
        self.assertNotIn('Enquiries are stored in a private application database.', privacy)

if __name__ == '__main__': unittest.main(verbosity=2)
