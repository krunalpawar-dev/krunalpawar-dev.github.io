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
text('<link href="https://krunalpawar-dev.github.io/">krunalpawar-dev.github.io</link> | <link href="https://www.linkedin.com/in/krunalmpawar">LinkedIn: krunalmpawar</link>', 'contact')
story.extend([Spacer(1, 15), HRFlowable(width='100%', thickness=1, color=colors.HexColor('#dce2ef'))])
text('PROFILE', 'section')
text('Software developer working professionally since 2022, building custom web applications and business management systems. Work spans backend development, user interfaces, databases, APIs and deployment.')
text('TECHNICAL SKILLS', 'section')
text('<b>Backend &amp; data:</b> Laravel, PHP, MySQL, REST APIs, Redis and queues.')
text('<b>Frontend:</b> Vue.js, JavaScript, jQuery, Livewire, Inertia.js, Tailwind CSS, Bootstrap and Vite.')
text('<b>Delivery &amp; integrations:</b> Git, Linux servers, server deployment, AWS S3, payment gateways and third-party API integrations.')
text('SELECTED PROJECT EXPERIENCE', 'section')
projects = [
('Bharat Medical Hall - Back Office & Business Management System', 'Baripada, Odisha | Full-Stack Laravel Developer. Independently developed the complete website and back-office platform: architecture, database design, backend, business logic and user interface.'),
('Ten connected business modules', 'Orders, employee attendance, leave, payroll and salary, payslips, projects and tasks, challans, suppliers, customers and expenses. Centralizes daily operations to reduce manual work and improve administrative control.'),
('KumbhSnaan - Digital Gateway to Nashik Simhastha Kumbh', 'Developed a spiritual booking platform for devotees worldwide. Features include Digital Snaan and Sankalp bookings, photo and prayer submission, preferred date and package selection, personalized ritual videos, digital certificates and sacred offerings.'),
('Treeva Healthcare - Website & Clinic Management System', 'Full-Stack Laravel Developer. Independently built the public website and clinic admin panel, including enquiries, appointments, patient records, services, billing, invoices, expenses, staff and role-based access. Technologies: Laravel, PHP, MySQL, JavaScript, Bootstrap/Tailwind CSS.'),
]
text('Additional services available: custom CRM, inventory, warehouse and SaaS applications.', 'small')

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
