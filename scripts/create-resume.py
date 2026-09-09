"""Create the temporary resume from the owner's supplied portfolio information."""
from pathlib import Path
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, HRFlowable

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / 'assets' / 'documents' / 'krunal-pawar-resume.pdf'
OUTPUT.parent.mkdir(parents=True, exist_ok=True)
BLUE = colors.HexColor('#273bd8')
TEXT = colors.HexColor('#19202d')
MUTED = colors.HexColor('#596579')
styles = {
    'name': ParagraphStyle('name', fontName='Helvetica-Bold', fontSize=28, leading=33, textColor=TEXT, spaceAfter=5),
    'role': ParagraphStyle('role', fontName='Helvetica', fontSize=12, leading=17, textColor=BLUE, spaceAfter=10),
    'contact': ParagraphStyle('contact', fontName='Helvetica', fontSize=9, leading=14, textColor=MUTED),
    'section': ParagraphStyle('section', fontName='Helvetica-Bold', fontSize=10, leading=14, textColor=BLUE, spaceBefore=17, spaceAfter=8),
    'body': ParagraphStyle('body', fontName='Helvetica', fontSize=10, leading=15, textColor=TEXT, spaceAfter=6),
    'small': ParagraphStyle('small', fontName='Helvetica', fontSize=9, leading=13, textColor=MUTED, spaceAfter=5),
    'draft': ParagraphStyle('draft', fontName='Helvetica-Bold', fontSize=8, leading=12, textColor=BLUE, spaceAfter=17),
}
story = []
def text(value, style='body'):
    story.append(Paragraph(value, styles[style]))

text('TEMPORARY RESUME / DRAFT PROFILE', 'draft')
text('Krunal Pawar', 'name')
text('Software Developer | Laravel &amp; Custom Business Applications', 'role')
text('India | Available for remote collaboration', 'contact')
text('<link href="mailto:kmpawar0004@gmail.com">kmpawar0004@gmail.com</link> | +91 63517 16007', 'contact')
text('<link href="https://krunal02101999.github.io/portfolio/">krunal02101999.github.io/portfolio</link> | <link href="https://www.linkedin.com/in/krunal-pawar-dev">LinkedIn: krunal-pawar-dev</link>', 'contact')
story.extend([Spacer(1, 15), HRFlowable(width='100%', thickness=1, color=colors.HexColor('#dce2ef'))])
text('PROFILE', 'section')
text('Software developer with around 3 years of professional experience building custom web applications and business management systems. Work spans backend development, user interfaces, databases, APIs and deployment, with experience in SaaS and multi-tenant applications.')
text('TECHNICAL SKILLS', 'section')
text('<b>Backend &amp; data:</b> Laravel, PHP, MySQL, REST APIs, Redis and queues.')
text('<b>Frontend:</b> Vue.js, JavaScript, jQuery, Livewire, Inertia.js, Tailwind CSS, Bootstrap and Vite.')
text('<b>Delivery &amp; integrations:</b> Git, Linux servers, server deployment, AWS S3, payment gateways and third-party API integrations.')
text('SELECTED PROJECT EXPERIENCE', 'section')
projects = [
('Bharat Medical Hall - Back Office Management', 'Order management, employee records, attendance and role-based administrative access for medical retail operations.'),
('Treeva Healthcare Management System', 'A public-facing healthcare website and administration platform for enquiries, appointments, patient records, billing and staff.'),
('Education Management SaaS', 'Institutional administration covering students, staff, attendance, fees and user roles, with a multi-tenant application architecture.'),
('CRM & Business Management Platform', 'Lead and customer management, tasks, follow-ups, activity tracking and sales process functionality.'),
('HRMS & Payroll System', 'Employee records, biometric attendance, shifts, leave, timesheets, salary templates and payslip generation.'),
('Warehouse Management System', 'Inbound, putaway, storage, retrieval, inventory, cycle counting, barcode support and ERP/TMS integrations.'),
]
for title, description in projects:
    text('<b>' + title.replace('&', '&amp;') + '</b>', 'body')
    text(description, 'small')
text('DRAFT NOTE', 'section')
text('Temporary profile prepared from the supplied portfolio information. Employment history, exact dates and education will be added when the final resume is provided.', 'small')

def footer(canvas, doc):
    canvas.setFont('Helvetica', 8)
    canvas.setFillColor(MUTED)
    canvas.drawString(44, 27, 'Krunal Pawar | Temporary resume - replace with the final approved version')
    canvas.drawRightString(A4[0]-44, 27, str(doc.page))

SimpleDocTemplate(str(OUTPUT), pagesize=A4, rightMargin=44, leftMargin=44, topMargin=35, bottomMargin=42, title='Krunal Pawar - Temporary Resume', author='Krunal Pawar').build(story, onFirstPage=footer, onLaterPages=footer)
print(OUTPUT)
