"""Builds the Elementor JSON shipped with the MyRepairCo Site Kit plugin.

    python3 dev/build.py

Writes data/kit-settings.json and templates/{home,header,footer}.json.
Placeholders resolved by the plugin at import time:
    {{asset:file.png}}     -> media library URL of assets/images/file.png
    {{asset_id:file.png}}  -> its attachment ID
    {{menu}}               -> slug of the "MyRepairCo Main" menu
"""
import json
import os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# --------------------------------------------------------------- brand tokens
RED, RED_DARK, DARK, TEXT = '#C62828', '#A31F1F', '#1B1B1F', '#4A4A4A'
LIGHT, WHITE, BLUSH = '#F7F3EF', '#FFFFFF', '#F9D9D5'

PHONE_LABEL, PHONE_URL = '+1 123 456 7890', 'tel:+11234567890'
EMAIL = 'info@example.com'
BOOK_URL = '/customer-portal/'
UPLOADS = 'https://myrepairco.com/wp-content/uploads/2026/05/'
IMG_ROOF = UPLOADS + 'selective-focus-of-handsome-handyman-repairing-roof-with-coworker.jpg'
IMG_VARNISH = UPLOADS + 'handyman-varnishing-wooden-planks-outside.jpg'
IMG_FURNITURE = UPLOADS + 'african-american-man-assembling-furniture-at-home.jpg'
IMG_ELECTRIC = UPLOADS + 'electrician-changes-the-light-bulb-handyman.jpg'

# Global references (Site Settings > Global Colors).
G_RED = 'globals/colors?id=primary'
G_DARK = 'globals/colors?id=secondary'
G_TEXT = 'globals/colors?id=text'
G_RED_DARK = 'globals/colors?id=accent'
G_LIGHT = 'globals/colors?id=mrcligh'
G_WHITE = 'globals/colors?id=mrcwhit'
G_BLUSH = 'globals/colors?id=mrcblus'

# ------------------------------------------------------------------- helpers
_counter = [0]


def eid():
    _counter[0] += 1
    return format(0xA10000 + _counter[0], 'x')[-7:].rjust(7, 'a')


def px(v, unit='px'):
    return {'unit': unit, 'size': v, 'sizes': []}


def box(t, r, b, l, unit='px'):
    return {'unit': unit, 'top': str(t), 'right': str(r), 'bottom': str(b), 'left': str(l), 'isLinked': False}


def gap(v):
    return {'unit': 'px', 'size': v, 'column': str(v), 'row': str(v), 'isLinked': True}


def link(url, external=False):
    return {'url': url, 'is_external': 'on' if external else '', 'nofollow': '', 'custom_attributes': ''}


def icon(name, lib='fa-solid'):
    return {'value': name, 'library': lib}


def img(url):
    return {'url': url, 'id': '', 'alt': '', 'source': 'library'}


def asset(name):
    return {'url': '{{asset:%s}}' % name, 'id': '{{asset_id:%s}}' % name, 'alt': '', 'source': 'library'}


def container(children, inner=True, **s):
    return {'id': eid(), 'elType': 'container', 'settings': s, 'elements': list(children), 'isInner': inner}


def section(children, pad=(90, 20, 90, 20), bg=None, cls='', gap_px=24, **extra):
    """Top-level boxed section; content is centered in a column."""
    s = {'content_width': 'boxed', 'flex_direction': 'column', 'flex_align_items': 'center',
         'flex_gap': gap(gap_px), 'padding': box(*pad), 'padding_mobile': box(60, 16, 60, 16)}
    if bg:
        s['background_background'] = 'classic'
        s['__globals__'] = {'background_color': bg}
    if cls:
        s['css_classes'] = cls
    s.update(extra)
    return container(children, inner=False, **s)


def row(children, gap_px=30, justify='center', align='stretch', wrap='wrap', cls='', **extra):
    s = {'content_width': 'full', 'flex_direction': 'row', 'flex_wrap': wrap, 'flex_gap': gap(gap_px),
         'flex_justify_content': justify, 'flex_align_items': align, 'padding': box(0, 0, 0, 0)}
    if cls:
        s['css_classes'] = cls
    s.update(extra)
    return container(children, **s)


def col(children, w=50, wt=None, wm=100, cls='', align='flex-start', justify='flex-start', **extra):
    s = {'content_width': 'full', 'flex_direction': 'column', 'width': px(w, '%'),
         'width_tablet': px(wt if wt is not None else w, '%'), 'width_mobile': px(wm, '%'),
         'flex_align_items': align, 'flex_justify_content': justify, 'flex_gap': gap(16)}
    if cls:
        s['css_classes'] = cls
    s.update(extra)
    return container(children, **s)


def widget(kind, **s):
    return {'id': eid(), 'elType': 'widget', 'widgetType': kind, 'settings': s, 'elements': []}


def heading(text, tag='h2', align='center', cls='', **extra):
    s = {'title': text, 'header_size': tag, 'align': align}
    if cls:
        s['_css_classes'] = cls
    s.update(extra)
    return widget('heading', **s)


def eyebrow(text, align='center'):
    return heading(text, 'h6', align, cls='mrc-eyebrow')


def text(html, align='center', **extra):
    return widget('text-editor', editor=html if html.startswith('<') else '<p>%s</p>' % html, align=align, **extra)


def button(label, url=BOOK_URL, align='center', cls='', **extra):
    s = {'text': label, 'link': link(url), 'align': align, 'size': 'md'}
    if cls:
        s['_css_classes'] = cls
    s.update(extra)
    return widget('button', **s)


def image(src, align='center', width=None, **extra):
    s = {'image': src, 'image_size': 'full', 'align': align}
    if width:
        s['width'] = px(width, '%' if width <= 100 else 'px')
    s.update(extra)
    return widget('image', **s)


def icon_box(ico, title, desc, position='top', align='center', tag='h3'):
    return widget('icon-box', selected_icon=icon(ico), view='stacked', shape='circle',
                  title_text=title, description_text=desc, position=position, title_size=tag,
                  text_align=align, icon_size=px(30), icon_padding=px(22, 'px'),
                  __globals__={'primary_color': G_RED, 'secondary_color': G_WHITE})


def icon_list(items, inline=False, ico='fas fa-check-circle', cls='', **extra):
    s = {'icon_list': [{'_id': eid(), 'text': t, 'selected_icon': icon(i or ico, 'fa-solid'),
                        **({'link': link(u)} if u else {})}
                       for t, i, u in [(x if isinstance(x, tuple) else (x, None, None)) for x in items]],
         'view': 'inline' if inline else 'traditional',
         '__globals__': {'icon_color': G_RED}}
    if cls:
        s['_css_classes'] = cls
    s.update(extra)
    return widget('icon-list', **s)


def counter(n, suffix, title):
    return widget('counter', starting_number=0, ending_number=n, suffix=suffix, title=title,
                  __globals__={'number_color': G_RED, 'typography_number_typography': 'globals/typography?id=primary',
                               'typography_title_typography': 'globals/typography?id=secondary'})


# ------------------------------------------------------------------ home page
def hero():
    return section([
        eyebrow('Professional · Trustworthy · Same-Day'),
        heading('Our handyman services are all under one roof.', 'h1'),
        text("It's your home. Not just any handyman will do. Book a background-verified technician for "
             "same-day repairs, track them live on your phone, and relax &mdash; every job is backed by "
             "our one-year guarantee."),
        row([button('Schedule a Booking'),
             button('Call ' + PHONE_LABEL, PHONE_URL, cls='mrc-btn-outline-light')], gap_px=16),
        icon_list(['Background-checked techs', 'Same-day service', 'Live tracking'],
                  inline=True, cls='mrc-trust', icon_align='center'),
    ], cls='mrc-on-dark mrc-narrow mrc-hero', pad=(150, 20, 180, 20), padding_mobile=box(100, 16, 130, 16),
        min_height=px(640), flex_justify_content='center',
        background_background='classic', background_image=asset('hero-technician-handshake.jpg'),
        background_position='center center', background_size='cover', background_repeat='no-repeat',
        background_overlay_background='classic', background_overlay_color=DARK,
        background_overlay_opacity=px(0.68),
        shape_divider_bottom='waves', shape_divider_bottom_color=WHITE, shape_divider_bottom_height=px(60))


def promo_strip():
    return section([
        row([
            widget('icon', selected_icon=icon('fas fa-shipping-fast'), view='stacked', shape='circle',
                   size=px(30), icon_padding=px(20), _flex_size='none',
                   __globals__={'primary_color': G_RED, 'secondary_color': G_WHITE}),
            col([heading('Need it fixed today? Same-day technicians are near you.', 'h4', 'left',
                         align_mobile='center'),
                 text('Small jobs, big relief: leaky faucets, loose outlets, wobbly doors and more. '
                      'Book now and get a verified tech at your door &mdash; often within hours.', 'left',
                      align_mobile='center')],
                w=100, wt=100, flex_gap=gap(6), align_mobile='center'),
            button('Book a Tech', _flex_size='none'),
        ], align='center', gap_px=28, wrap='nowrap', cls='mrc-strip',
            flex_direction_mobile='column', padding=box(28, 36, 28, 36), padding_mobile=box(28, 22, 28, 22)),
    ], pad=(10, 20, 40, 20), padding_mobile=box(10, 16, 30, 16))


def contact_today():
    return section([
        row([
            image(asset('myrepairco-mascot.png'), width=150, width_mobile=px(120, 'px'), _flex_size='none'),
            col([heading('Contact us today!', 'h2', 'left', align_mobile='center',
                         __globals__={'title_color': G_RED}),
                 text('Real people, real technicians. Tell us what needs fixing and we will get a verified '
                      'tech to your door.', 'left', align_mobile='center'),
                 button('Book Free Estimate', align='left', align_mobile='center')],
                flex_gap=gap(14), justify='center', align_mobile='center',
                width=px(560, 'px'), width_tablet=px(480, 'px'), width_mobile=px(100, '%')),
        ], align='center', gap_px=32, wrap='nowrap', flex_direction_mobile='column'),
    ], pad=(30, 20, 80, 20))


WHY = [
    ('fas fa-user-shield', 'Verified Professionals',
     'Our team of certified, background-checked professionals ensures quality and safety in every job. '
     'Your home is always in capable, trustworthy hands.'),
    ('fas fa-bolt', 'Same-Day Dispatch',
     'Tell us what needs fixing and we send the nearest available technician &mdash; often the same day.'),
    ('fas fa-map-marked-alt', 'Real-Time Tracking',
     'Watch your technician on the map, see their photo and ETA, and get a text when they are on the way.'),
    ('fas fa-medal', '16+ Years of Experience',
     'Our skilled team tackles every project with precision and care, from quick repairs to bigger fixes.'),
    ('fas fa-clock', '24/7 Services',
     "We're here when you need us, any time of day or night, for urgent fixes and planned projects."),
    ('fas fa-shield-alt', 'One-Year Guarantee',
     "We're not happy until the work's done right &mdash; and every job is backed by a one-year guarantee."),
]


def why_choose_us():
    return section([
        eyebrow('Why Choose Us'),
        heading('Why Choose Us?'),
        text('Your reliable partner for quality, convenience, and care in all handyman services.'),
        row([col([icon_box(*w)], w=31, wt=47, cls='mrc-card', align='center') for w in WHY]),
        row([col([counter(600, '+', 'Projects Completed')], w=22, wt=47),
             col([counter(16, '+', 'Years of Experience')], w=22, wt=47),
             col([counter(24, '/7', 'Service Availability')], w=22, wt=47),
             col([counter(1, '-Year', 'Work Guarantee')], w=22, wt=47)], cls='mrc-stats'),
        button('Schedule a Booking'),
    ], bg=G_LIGHT, shape_divider_top='waves', shape_divider_top_color=WHITE,
        shape_divider_top_height=px(60), pad=(120, 20, 90, 20))


STEPS = [
    ('fas fa-mobile-alt', '1. Book in minutes', 'Open the booking page on your phone or computer and tell us what needs fixing.'),
    ('fas fa-list-ul', '2. Choose your service & time', 'Pick the service, add a photo or note, and choose "Today" or a time that works for you.'),
    ('fas fa-user-check', '3. Get matched with a verified tech', 'We dispatch the nearest background-checked technician and send you their name and photo.'),
    ('fas fa-map-marker-alt', '4. Track them to your door', 'Follow your technician live on the map and get a text with the arrival time. Pay when the job is done.'),
]


def how_it_works():
    return section([
        row([
            col([image(asset('phone-booking-animated.webp'), width=90, _css_classes='mrc-float')], w=45, wt=100, align='center'),
            col([eyebrow('Our Process', 'left'),
                 heading('How It Works?', 'h2', 'left'),
                 text('From booking to completion, our streamlined process ensures hassle-free, efficient '
                      "handyman services. Just follow the steps below, and we'll handle the rest.", 'left'),
                 *[icon_box(*s, position='left', align='left', tag='h4') for s in STEPS],
                 button('Book a Tech Now', align='left', align_mobile='center')],
                w=50, wt=100, justify='center'),
        ], align='center', gap_px=50),
    ])


def live_tracking():
    return section([
        row([
            col([eyebrow('Like Uber, for home repairs', 'left'),
                 heading('Track your technician in real time', 'h2', 'left'),
                 text('No more waiting around all day. As soon as your job is accepted you can see exactly '
                      'where your technician is and when they will arrive.', 'left'),
                 icon_list(['Live map with your technician\'s location',
                            'Name, photo and background-check badge before they arrive',
                            'Text alerts when your tech is on the way and has arrived',
                            'Upfront pricing &mdash; approve the estimate before work starts']),
                 button('Schedule a Booking', align='left', align_mobile='center')],
                w=50, wt=100, justify='center'),
            col([image(asset('phone-tracking-animated.webp'), width=90, _css_classes='mrc-float')], w=45, wt=100, align='center'),
        ], align='center', gap_px=50),
    ], bg=G_LIGHT)


SERVICES = [
    ('fas fa-faucet', 'Plumbing', 'Leaky faucets, clogged drains, running toilets and fixture installs.'),
    ('fas fa-bolt', 'Electrical', 'Outlets, switches, light fixtures, ceiling fans and breakers.'),
    ('fas fa-tools', 'Handyman Repairs', 'Doors, cabinets, drywall patches, caulking and the to-do list.'),
    ('fas fa-blender', 'Appliance Repair', 'Washers, dryers, dishwashers, fridges, ovens and disposals.'),
    ('fas fa-fan', 'HVAC', 'Thermostats, filters, vents and heating & cooling check-ups.'),
    ('fas fa-paint-roller', 'Painting', 'Interior touch-ups, accent walls, trim, doors and cabinets.'),
    ('fas fa-couch', 'Furniture Assembly', 'Beds, desks, shelves and flat-pack furniture put together right.'),
    ('fas fa-tv', 'Mounting & Install', 'TVs, shelves, curtain rods, mirrors, art and smart devices.'),
]


def services():
    return section([
        eyebrow('Our Services'),
        heading('A one-stop shop for home repair and maintenance needs'),
        text("Comprehensive solutions tailored to your home's unique needs, offering quality and reliability "
             "across a wide range of handyman tasks. From quick fixes to in-depth repairs, we're here to make "
             'your space better, safer, and more comfortable.'),
        row([col([icon_box(*s, tag='h4')], w=22.5, wt=47, cls='mrc-card', align='center') for s in SERVICES],
            gap_px=24),
        button('View All Services', '/services/'),
    ])


COMPARE = ['Same-day technician dispatch', 'Background-verified technicians', 'Real-time GPS tracking',
           'Upfront pricing before work starts', 'One-year work guarantee', 'Book, track & pay in one place']


def compare_row(label):
    def mark(ok, cls):
        ic = 'fas fa-check-circle' if ok else 'fas fa-times-circle'
        glob = {'primary_color': G_WHITE if ok else G_TEXT}
        return col([widget('icon', selected_icon=icon(ic), size=px(26), __globals__=glob)],
                   w=24, wm=30, align='center', justify='center', cls=cls, padding=box(18, 8, 18, 8),
                   padding_mobile=box(16, 4, 16, 4))
    return row([col([heading(label, 'h5', 'left')], w=52, wm=40, justify='center', cls='mrc-label',
                    padding=box(18, 16, 18, 28), padding_mobile=box(16, 8, 16, 14)),
                mark(True, 'mrc-brand-col'), mark(False, 'mrc-others-col')],
               gap_px=0, wrap='nowrap', align='stretch')


def comparison():
    head = row([col([heading('What you get', 'h6', 'left', cls='mrc-muted', hide_mobile='hidden-mobile')], w=52, wm=40, justify='center',
                    padding=box(22, 16, 22, 28), padding_mobile=box(18, 8, 18, 14)),
                col([heading('MyRepairCo', 'h5', 'center')], w=24, wm=30, cls='mrc-brand-col',
                    align='center', justify='center', padding=box(22, 8, 22, 8), padding_mobile=box(18, 4, 18, 4)),
                col([heading('Others', 'h5', 'center')], w=24, wm=30, cls='mrc-others-col',
                    align='center', justify='center', padding=box(22, 8, 22, 8), padding_mobile=box(18, 4, 18, 4))],
               gap_px=0, wrap='nowrap', align='stretch')
    return section([
        eyebrow('Compare'),
        heading('Why homeowners switch to MyRepairCo'),
        text('Everything you need to get small jobs done fast &mdash; in one place.'),
        container([head, *[compare_row(c) for c in COMPARE]], content_width='full', css_classes='mrc-compare',
                  width=px(920, 'px'), width_tablet=px(100, '%'), width_mobile=px(100, '%'),
                  flex_direction='column', flex_gap=gap(0), padding=box(0, 0, 0, 0),
                  margin=box(16, 0, 0, 0)),
    ], bg=G_LIGHT, pad=(90, 20, 100, 20))


PROJECTS = [(IMG_ROOF, 'Roof Repair', 'Exterior'), (IMG_VARNISH, 'Deck Staining', 'Carpentry'),
            (IMG_FURNITURE, 'Furniture Assembly', 'Handyman'), (IMG_ELECTRIC, 'Lighting Install', 'Electrical')]


def recent_projects():
    cards = [col([image(img(u), _css_classes='mrc-photo'),
                  heading(cat, 'h6', 'left', cls='mrc-muted mrc-small'),
                  heading(title, 'h4', 'left')],
                 w=23, wt=47, wm=100, cls='mrc-project', flex_gap=gap(10)) for u, title, cat in PROJECTS]
    return section([
        eyebrow('Recent Projects'),
        heading('Inspiration for your next project'),
        text('A few of the jobs our verified technicians finished for homeowners like you.'),
        row(cards, gap_px=28, margin=box(16, 0, 8, 0)),
        button('View All Services', '/services/'),
    ], pad=(90, 20, 100, 20))


def about():
    return section([
        row([
            col([image(img(IMG_FURNITURE), _css_classes='mrc-photo')], w=45, wt=100),
            col([eyebrow('About Us', 'left'),
                 heading('Clients love our service because nobody does it better.', 'h2', 'left'),
                 text('We pride ourselves on delivering top-tier handyman services with a focus on quality, '
                      'reliability, and customer satisfaction. Our team of skilled, background-checked '
                      'professionals is committed to making your home a better place.', 'left'),
                 icon_list(['Dedicated to quality handyman work.', 'Home repairs handled with care.',
                            'Accurate fix, satisfaction guaranteed.', 'Save money on your repair projects.']),
                 button('More About Us', '/tech-team/', align='left', align_mobile='center')],
                w=50, wt=100, justify='center'),
        ], align='center', gap_px=50),
    ], bg=G_LIGHT)


REVIEWS = [
    'Sample review &mdash; replace with a real customer review. The technician arrived the same day, '
    'fixed our leaking faucet in under an hour and cleaned up after.',
    'Sample review &mdash; replace with a real customer review. Being able to see the tech on the map '
    'meant I did not have to wait around all afternoon.',
    'Sample review &mdash; replace with a real customer review. Friendly, on time and the price was '
    'exactly what they quoted up front.',
]


def testimonials():
    cards = [col([heading('★★★★★', 'h5', 'left', cls='mrc-stars'),
                  text(r, 'left'),
                  heading('Customer Name', 'h5', 'left'),
                  text('Homeowner', 'left', _css_classes='mrc-small')],
                 w=31, wt=100, cls='mrc-card', flex_gap=gap(12)) for r in REVIEWS]
    return section([
        eyebrow('Testimonials'),
        heading('Why Trust Us?'),
        text('Customer experiences that highlight our dedication and skill'),
        row(cards, gap_px=24),
    ], bg=G_RED, cls='mrc-on-dark mrc-on-red', pad=(130, 20, 130, 20),
        shape_divider_top='waves', shape_divider_top_color=LIGHT, shape_divider_top_height=px(60),
        shape_divider_bottom='waves', shape_divider_bottom_color=DARK, shape_divider_bottom_height=px(60))


def cta_band():
    return section([
        row([
            col([heading('Get a verified tech at your door today', 'h2', 'left'),
                 text('Book in minutes, track your technician live and pay only when the job is done.', 'left'),
                 row([button('Schedule a Booking', cls='mrc-btn-light'),
                      button('Call ' + PHONE_LABEL, PHONE_URL, cls='mrc-btn-outline-light')],
                     justify='flex-start', gap_px=16)],
                w=55, wt=100, justify='center'),
            col([image(asset('phone-tracking-animated.webp'), width=80, _css_classes='mrc-float')], w=40, wt=100, align='center'),
        ], align='center', gap_px=40),
    ], bg=G_DARK, cls='mrc-on-dark', pad=(70, 20, 70, 20))


def hiring():
    return section([
        heading('Hiring Skilled Technicians'),
        text('Are you a skilled handyman, plumber, electrician or appliance technician looking for flexible, '
             'well-paid work? Join our tech team &mdash; apply through the link below.'),
        button('Apply Today', '/tech-team/'),
    ], cls='mrc-narrow')


def blog():
    return section([
        eyebrow('Our Blog'),
        heading('Our Latest Posts'),
        widget('posts', _skin='cards', cards_columns='3', cards_columns_tablet='2', cards_columns_mobile='1',
               cards_posts_per_page=3, cards_show_excerpt='yes', cards_meta_data=['date'],
               cards_read_more_text='Read More »'),
    ], pad=(40, 20, 110, 20))


def home():
    return [hero(), promo_strip(), contact_today(), why_choose_us(), how_it_works(), live_tracking(),
            services(), comparison(), recent_projects(), about(), testimonials(), cta_band(), hiring(), blog()]


# ------------------------------------------------------------ header / footer
def header():
    top = container([
        icon_list([(PHONE_LABEL, 'fas fa-phone-alt', PHONE_URL), (EMAIL, 'fas fa-envelope', 'mailto:' + EMAIL)],
                  inline=True, cls='mrc-on-dark'),
        heading('Mon – Sat: 8 am – 8 pm', 'p', 'right', cls='mrc-on-dark mrc-small', hide_mobile='hidden-mobile'),
    ], inner=False, content_width='boxed', flex_direction='row', flex_justify_content='space-between',
        flex_align_items='center', flex_wrap='wrap', padding=box(8, 20, 8, 20),
        background_background='classic', __globals__={'background_color': G_DARK})
    main = container([
        image(asset('myrepairco-logo-red-transparent.png'), 'left', width=260, link_to='custom',
              link=link('/'), width_mobile=px(170, 'px')),
        widget('nav-menu', menu='{{menu}}', layout='horizontal', align_items='right', pointer='underline',
               dropdown='tablet', toggle='burger', full_width='stretch',
               __globals__={'color_menu_item_hover': G_RED, 'pointer_color_menu_item_hover': G_RED,
                            'color_menu_item_active': G_RED, 'pointer_color_menu_item_active': G_RED},
               ),
        button('Schedule a Booking', hide_mobile='hidden-mobile'),
    ], inner=False, content_width='boxed', flex_direction='row', flex_justify_content='space-between',
        flex_align_items='center', flex_wrap='nowrap', flex_gap=gap(24), padding=box(14, 20, 14, 20),
        css_classes='mrc-header-main')
    return [top, main]


def footer():
    return [
        section([
            image(asset('myrepairco-logo-red-transparent.png'), width=380, width_mobile=px(280, 'px')),
            widget('nav-menu', menu='{{menu}}', layout='horizontal', align_items='center', pointer='none',
                   dropdown='none', _css_classes='mrc-footer-menu',
                   __globals__={'color_menu_item_hover': G_RED}),
            button('Speak With Us', PHONE_URL),
            row([
                col([heading('Contact Info', 'h5', 'left'),
                     icon_list([('324 King Street, FL, USA', 'fas fa-map-marker-alt', None),
                                (PHONE_LABEL, 'fas fa-phone-alt', PHONE_URL),
                                (EMAIL, 'fas fa-envelope', 'mailto:' + EMAIL)])], w=30, wt=47),
                col([heading('Open Hours', 'h5', 'left'),
                     icon_list([('Mon – Sat: 8 am – 8 pm', 'far fa-clock', None),
                                ('Sunday: Closed', 'far fa-calendar-times', None),
                                ('24/7 emergency service', 'fas fa-bolt', None)])], w=30, wt=47),
                col([heading('Newsletter', 'h5', 'left'),
                     text('Subscribe to our newsletter to get our latest updates and news.', 'left'),
                     widget('form', form_name='Newsletter', show_labels='',
                            form_fields=[{'_id': eid(), 'custom_id': 'email', 'field_type': 'email',
                                          'field_label': 'Email address', 'placeholder': 'Email address',
                                          'required': 'true', 'width': '65', 'width_tablet': '65',
                                          'width_mobile': '65'}],
                            input_size='md', button_size='md', column_gap=px(0), row_gap=px(0),
                            button_text='Subscribe', button_width='35', button_width_tablet='35',
                            button_width_mobile='35', submit_actions=['email'], _css_classes='mrc-inline-form',
                            email_subject='New newsletter subscriber', success_message='Thanks for subscribing!')],
                    w=32, wt=100),
            ], gap_px=40, justify='space-between', padding=box(30, 0, 10, 0)),
            widget('social-icons', shape='circle', align='center', icon_color='custom',
                   social_icon_list=[{'_id': eid(), 'social_icon': icon('fab fa-' + n, 'fa-brands'), 'link': link('#', True)}
                                     for n in ('facebook-f', 'x-twitter', 'youtube', 'linkedin-in', 'instagram')],
                   __globals__={'icon_primary_color': G_RED, 'icon_secondary_color': G_WHITE}),
        ], bg=G_LIGHT, pad=(110, 20, 40, 20), shape_divider_top='waves', shape_divider_top_color=WHITE,
            shape_divider_top_height=px(60)),
        container([
            heading('© 2026 My Repair Co. All rights reserved.', 'p', 'left', cls='mrc-on-dark mrc-small'),
            icon_list([('Terms &amp; Conditions', 'fas fa-angle-right', '#'), ('Privacy Policy', 'fas fa-angle-right', '#')],
                      inline=True, cls='mrc-on-dark mrc-small'),
        ], inner=False, content_width='boxed', flex_direction='row', flex_justify_content='space-between',
            flex_align_items='center', flex_wrap='wrap', padding=box(16, 20, 16, 20),
            background_background='classic', __globals__={'background_color': G_DARK}),
    ]


# -------------------------------------------------------------- site settings
CUSTOM_CSS = """
/* MyRepairCo helper classes - add them under Advanced > CSS Classes. */
.mrc-card{background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(27,27,31,.08);padding:34px 28px;transition:transform .2s,box-shadow .2s}
.mrc-card:hover{transform:translateY(-4px);box-shadow:0 18px 40px rgba(27,27,31,.14)}
.mrc-strip{background:#fff;border-radius:16px;border:1px solid rgba(27,27,31,.06);box-shadow:0 10px 30px rgba(27,27,31,.08)}
.mrc-on-dark .elementor-heading-title,.mrc-on-dark .elementor-widget-text-editor,.mrc-on-dark .elementor-icon-list-text,.mrc-on-dark .elementor-icon-list-icon i{color:#fff}
.mrc-on-dark .mrc-card .elementor-heading-title{color:var(--e-global-color-secondary)}
.mrc-on-dark .mrc-card .elementor-widget-text-editor{color:var(--e-global-color-text)}
.mrc-on-red .mrc-eyebrow .elementor-heading-title{background:#fff;color:var(--e-global-color-primary)}
.mrc-on-dark .mrc-card .mrc-stars .elementor-heading-title,.mrc-stars .elementor-heading-title{color:var(--e-global-color-primary);letter-spacing:3px}
.mrc-narrow > .e-con-inner{max-width:860px}
.mrc-eyebrow .elementor-heading-title{display:inline-block;background:var(--e-global-color-primary);color:#fff;padding:7px 16px;border-radius:30px;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;line-height:1.2}
.mrc-small .elementor-heading-title,.mrc-small .elementor-icon-list-text,.mrc-small.elementor-widget-text-editor{font-size:14px}
.mrc-muted .elementor-heading-title{color:var(--e-global-color-text);text-transform:uppercase;letter-spacing:1.5px}
.elementor .mrc-btn-light .elementor-button{background:#fff;border-color:#fff;color:var(--e-global-color-primary)}
.elementor .mrc-btn-light .elementor-button:hover{background:var(--e-global-color-secondary);border-color:var(--e-global-color-secondary);color:#fff}
.elementor .mrc-btn-outline .elementor-button{background:transparent;border-color:var(--e-global-color-primary);color:var(--e-global-color-primary)}
.elementor .mrc-btn-outline .elementor-button:hover{background:var(--e-global-color-primary);color:#fff}
.elementor .mrc-btn-outline-light .elementor-button{background:transparent;border-color:#fff;color:#fff}
.elementor .mrc-btn-outline-light .elementor-button:hover{background:#fff;color:var(--e-global-color-primary)}
.mrc-trust .elementor-icon-list-text{font-weight:600;color:var(--e-global-color-secondary)}
.mrc-on-dark .mrc-trust .elementor-icon-list-text{color:#fff}
.mrc-photo img{border-radius:16px;aspect-ratio:4/3;object-fit:cover;width:100%}
.mrc-photo-hero img{border-radius:20px;aspect-ratio:auto;box-shadow:0 24px 50px rgba(27,27,31,.18)}
.mrc-stats .elementor-counter-title{color:var(--e-global-color-secondary)}
.mrc-float img{animation:mrc-float 5s ease-in-out infinite}
@keyframes mrc-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
@media(prefers-reduced-motion:reduce){.mrc-float img{animation:none}}
.mrc-header-main{box-shadow:0 4px 18px rgba(27,27,31,.08)}
.mrc-footer-menu .elementor-nav-menu--main .elementor-item{text-transform:uppercase;font-weight:700}
.mrc-compare{background:#fff;border-radius:20px;box-shadow:0 14px 40px rgba(27,27,31,.08)}
.mrc-compare .mrc-label,.mrc-compare .mrc-others-col{border-bottom:1px solid rgba(27,27,31,.07)}
.mrc-compare .mrc-brand-col{background:var(--e-global-color-primary);box-shadow:0 0 0 1px var(--e-global-color-primary)}
.mrc-compare .mrc-brand-col .elementor-heading-title{color:#fff}
.mrc-compare .mrc-others-col .elementor-heading-title{color:var(--e-global-color-text)}
.mrc-compare > .e-con:first-child .mrc-brand-col{border-radius:16px 16px 0 0;margin-top:-14px;padding-top:36px!important}
.mrc-compare > .e-con:last-child .mrc-brand-col{border-radius:0 0 16px 16px;margin-bottom:-14px;padding-bottom:32px!important}
.mrc-compare > .e-con:last-child .mrc-label,.mrc-compare > .e-con:last-child .mrc-others-col{border-bottom:0}
@media(max-width:767px){.mrc-compare .mrc-brand-col .elementor-heading-title,.mrc-compare .mrc-others-col .elementor-heading-title{font-size:14px}}
.mrc-project .elementor-heading-title{margin:0}
.mrc-project .mrc-photo img{transition:transform .3s}
.mrc-project:hover .mrc-photo img{transform:scale(1.03)}
.mrc-inline-form .elementor-field-group:not(.elementor-field-type-submit) .elementor-field{border-radius:6px 0 0 6px;min-height:52px}
.mrc-inline-form .elementor-field-type-submit .elementor-button{border-radius:0 6px 6px 0;min-height:52px;padding-left:12px;padding-right:12px}
"""


def heading_type(size, size_t, size_m, weight='800'):
    return {'typography': 'custom', 'font_family': 'Montserrat', 'font_weight': weight,
            'font_size': px(size), 'font_size_tablet': px(size_t), 'font_size_mobile': px(size_m),
            'line_height': px(1.2, 'em')}


def flat(prefix, d):
    return {'%s_typography_%s' % (prefix, k): v for k, v in d.items()}


def kit_settings():
    s = {
        'system_colors': [
            {'_id': 'primary', 'title': 'Brand Red', 'color': RED},
            {'_id': 'secondary', 'title': 'Dark', 'color': DARK},
            {'_id': 'text', 'title': 'Body Text', 'color': TEXT},
            {'_id': 'accent', 'title': 'Red Hover', 'color': RED_DARK},
        ],
        'custom_colors': [
            {'_id': 'mrcligh', 'title': 'Light Background', 'color': LIGHT},
            {'_id': 'mrcwhit', 'title': 'White', 'color': WHITE},
            {'_id': 'mrcblus', 'title': 'Blush', 'color': BLUSH},
        ],
        'system_typography': [
            {'_id': 'primary', 'title': 'Headings', 'typography_typography': 'custom',
             'typography_font_family': 'Montserrat', 'typography_font_weight': '800'},
            {'_id': 'secondary', 'title': 'Sub Headings', 'typography_typography': 'custom',
             'typography_font_family': 'Montserrat', 'typography_font_weight': '700'},
            {'_id': 'text', 'title': 'Body', 'typography_typography': 'custom',
             'typography_font_family': 'Open Sans', 'typography_font_weight': '400'},
            {'_id': 'accent', 'title': 'Buttons', 'typography_typography': 'custom',
             'typography_font_family': 'Montserrat', 'typography_font_weight': '700'},
        ],
        'custom_typography': [],
        # Theme Style
        'body_background_background': 'classic',
        **flat('body', {'typography': 'custom', 'font_family': 'Open Sans', 'font_weight': '400',
                        'font_size': px(17), 'font_size_mobile': px(16), 'line_height': px(1.65, 'em')}),
        **flat('h1', heading_type(54, 42, 34)),
        **flat('h2', heading_type(42, 34, 28)),
        **flat('h3', heading_type(22, 21, 20, '700')),
        **flat('h4', heading_type(19, 18, 18, '700')),
        **flat('h5', heading_type(17, 16, 16, '700')),
        **flat('h6', heading_type(14, 14, 13, '700')),
        **flat('button', {'typography': 'custom', 'font_family': 'Montserrat', 'font_weight': '700',
                          'font_size': px(15), 'text_transform': 'uppercase', 'letter_spacing': px(0.5)}),
        'button_background_background': 'classic',
        'button_hover_background_background': 'classic',
        'button_border_border': 'solid',
        'button_border_width': box(2, 2, 2, 2),
        'button_border_radius': box(6, 6, 6, 6),
        'button_padding': box(16, 30, 16, 30),
        'form_field_border_border': 'solid',
        'form_field_border_width': box(1, 1, 1, 1),
        'form_field_border_radius': box(6, 6, 6, 6),
        'form_field_padding': box(12, 16, 12, 16),
        'container_width': px(1200),
        'custom_css': CUSTOM_CSS.strip(),
        '__globals__': {
            'body_background_color': G_WHITE,
            'body_color': G_TEXT,
            'link_normal_color': G_RED,
            'link_hover_color': G_RED_DARK,
            **{'h%d_color' % i: G_DARK for i in range(1, 7)},
            'button_text_color': G_WHITE,
            'button_background_color': G_RED,
            'button_hover_text_color': G_WHITE,
            'button_hover_background_color': G_RED_DARK,
            'button_border_color': G_RED,
            'button_hover_border_color': G_RED_DARK,
            'form_field_border_color': G_LIGHT,
            'form_field_background_color': G_WHITE,
        },
    }
    return s


def template(title, kind, content, page_settings=None):
    return {'version': '0.4', 'title': title, 'type': kind, 'content': content,
            'page_settings': page_settings or {}}


def write(rel, data):
    path = os.path.join(ROOT, rel)
    with open(path, 'w') as f:
        json.dump(data, f, indent=1, ensure_ascii=False)
        f.write('\n')
    print('wrote', rel)


if __name__ == '__main__':
    write('data/kit-settings.json', kit_settings())
    write('templates/home.json', template('MyRepairCo Home', 'page', home(),
                                          {'template': 'elementor_header_footer', 'hide_title': 'yes'}))
    write('templates/header.json', template('MyRepairCo Header', 'header', header()))
    write('templates/footer.json', template('MyRepairCo Footer', 'footer', footer()))
