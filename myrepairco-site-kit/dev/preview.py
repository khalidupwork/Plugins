"""Rough static preview of the Elementor JSON (no WordPress needed).

    python3 dev/preview.py > /tmp/preview.html

It approximates containers, widgets and the kit's Theme Style closely enough to
check layout and content. It is not a pixel-perfect Elementor render.
"""
import html
import json
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
kit = json.load(open(os.path.join(ROOT, 'data/kit-settings.json')))
COLORS = {c['_id']: c['color'] for c in kit['system_colors'] + kit['custom_colors']}
ICON_GLYPH = {'check-circle': '✔', 'times-circle': '✖'}


def color(settings, key):
    ref = settings.get('__globals__', {}).get(key)
    if ref:
        return COLORS[ref.split('id=')[1]]
    return settings.get(key)


def asset(v):
    m = re.match(r'\{\{asset:(.+)\}\}', v or '')
    return '../../assets/images/' + m.group(1) if m else v


def dim(d):
    return ' '.join('%s%s' % (d[k] or 0, d['unit']) for k in ('top', 'right', 'bottom', 'left'))


def size(d):
    return '%s%s' % (d['size'], d['unit']) if d and d.get('size') not in (None, '') else None


def render_container(el, depth):
    s = el['settings']
    css = ['display:flex', 'flex-direction:%s' % s.get('flex_direction', 'column'),
           'flex-wrap:%s' % s.get('flex_wrap', 'nowrap'), 'position:relative', 'box-sizing:border-box']
    if s.get('flex_gap'):
        css.append('gap:%spx' % s['flex_gap']['size'])
    for k, p in (('flex_justify_content', 'justify-content'), ('flex_align_items', 'align-items')):
        if s.get(k):
            css.append('%s:%s' % (p, s[k]))
    if s.get('padding'):
        css.append('padding:' + dim(s['padding']))
    if el['isInner']:
        css.append('width:' + (size(s.get('width')) or '100%'))
    bg = color(s, 'background_color')
    if bg:
        css.append('background:' + bg)
    if s.get('background_image'):
        css.append('background:linear-gradient(rgba(27,27,31,.72),rgba(27,27,31,.72)),url(%s) center/cover'
                   % s['background_image']['url'])
    if s.get('min_height'):
        css.append('min-height:' + size(s['min_height']))
    cls = ['e-con', s.get('css_classes', ''), 'dm-col' if s.get('flex_direction_mobile') == 'column' else '',
           'fx-' + s.get('_flex_size', '')]
    if s.get('width_tablet'):
        cls.append('wt%d' % round(s['width_tablet']['size']))
    if s.get('width_mobile'):
        cls.append('wm%d' % round(s['width_mobile']['size']))
    inner = ''.join(render(c, depth + 1) for c in el['elements'])
    boxed = s.get('content_width') == 'boxed'
    if boxed:
        maxw = size(s.get('boxed_width')) or '1200px'
        inner = '<div class="e-con-inner" style="display:flex;flex-direction:inherit;flex-wrap:inherit;' \
                'gap:inherit;align-items:inherit;justify-content:inherit;width:100%%;max-width:%s;margin:0 auto">%s</div>' \
                % (maxw, inner)
    shapes = ''
    for side in ('top', 'bottom'):
        if s.get('shape_divider_' + side):
            shapes += '<div class="shape shape-%s" style="background:%s"></div>' % (
                side, s.get('shape_divider_%s_color' % side))
    return '<div class="%s" style="%s">%s%s</div>' % (' '.join(cls), ';'.join(css), shapes, inner)


def render_widget(el):
    s, t = el['settings'], el['widgetType']
    cls = 'w elementor-widget-%s %s fx-%s' % (t, s.get('_css_classes', ''), s.get('_flex_size', ''))
    align = {'left': 'left', 'right': 'right', 'center': 'center'}.get(s.get('align'), 'left')
    if t == 'heading':
        c = color(s, 'title_color')
        tag = s['header_size'] if s['header_size'] != 'p' else 'p'
        return '<div class="%s" style="text-align:%s"><%s class="elementor-heading-title" %s>%s</%s></div>' % (
            cls, align, tag, 'style="color:%s"' % c if c else '', s['title'], tag)
    if t == 'text-editor':
        return '<div class="%s" style="text-align:%s">%s</div>' % (cls, align, s['editor'])
    if t == 'button':
        j = {'left': 'flex-start', 'right': 'flex-end'}.get(s.get('align'), 'center')
        return '<div class="%s" style="display:flex;justify-content:%s"><a class="elementor-button">%s</a></div>' % (
            cls, j, s['text'])
    if t == 'image':
        w = size(s.get('width')) or '100%'
        return '<div class="%s" style="text-align:%s"><img src="%s" style="max-width:100%%;width:%s"></div>' % (
            cls, align, asset(s['image']['url']), w)
    if t in ('icon-box',):
        left = s.get('position') == 'left'
        return ('<div class="%s ib %s" style="text-align:%s"><span class="ic">●</span><div><h%s class="ibt">%s</h%s>'
                '<p>%s</p></div></div>') % (cls, 'ib-left' if left else '', s.get('text_align', 'center'),
                                            s['title_size'][1], s['title_text'], s['title_size'][1], s['description_text'])
    if t == 'icon':
        name = s['selected_icon']['value'].split('fa-')[-1]
        if s.get('view') == 'stacked':
            return '<div class="%s"><span class="ic" style="margin:0">●</span></div>' % cls
        return '<div class="%s" style="text-align:center;font-size:28px;color:%s">%s</div>' % (
            cls, color(s, 'primary_color'), ICON_GLYPH.get(name, '●'))
    if t == 'icon-list':
        items = ''.join('<li><span style="color:%s">●</span> %s</li>' % (COLORS['primary'], i['text'])
                        for i in s['icon_list'])
        return '<ul class="%s il %s">%s</ul>' % (cls, 'il-inline' if s.get('view') == 'inline' else '', items)
    if t == 'counter':
        return '<div class="%s" style="text-align:center"><div class="cn">%s%s</div><div>%s</div></div>' % (
            cls, s['ending_number'], s['suffix'], s['title'])
    if t == 'testimonial':
        return '<div class="%s"><p>%s</p><strong>%s</strong><br><small>%s</small></div>' % (
            cls, s['testimonial_content'], s['testimonial_name'], s['testimonial_job'])
    if t == 'nav-menu':
        return '<nav class="%s">Home · Services · Pricing · Tech Team · Customer Portal · FAQ · Contact Us</nav>' % cls
    if t == 'social-icons':
        return '<div class="%s" style="text-align:center">' % cls + ' '.join(
            '<span class="soc">●</span>' for _ in s['social_icon_list']) + '</div>'
    if t == 'form':
        return '<div class="%s"><input placeholder="Email address"><a class="elementor-button">%s</a></div>' % (
            cls, s['button_text'])
    if t == 'posts':
        return '<div class="%s posts">%s</div>' % (cls, ''.join('<div class="post">Blog post %d</div>' % i for i in (1, 2, 3)))
    return '<div class="%s">[%s]</div>' % (cls, t)


def render(el, depth=0):
    return render_container(el, depth) if el['elType'] == 'container' else render_widget(el)


def theme_css():
    def typ(p):
        f = kit.get(p + '_typography_font_family')
        return 'font-family:%s;font-weight:%s;font-size:%s' % (f, kit[p + '_typography_font_weight'],
                                                               size(kit[p + '_typography_font_size']))
    out = [':root{%s}' % ''.join('--e-global-color-%s:%s;' % kv for kv in COLORS.items())]
    out += ['body{margin:0;%s;color:%s;line-height:1.65}' % (typ('body'), COLORS['text'])]
    for i in range(1, 7):
        out.append('h%d{%s;color:%s;margin:0;line-height:1.2}' % (i, typ('h%d' % i), COLORS['secondary']))
    out.append('.elementor-button{display:inline-block;%s;text-transform:uppercase;background:%s;color:#fff;'
               'padding:14px 28px;border:2px solid %s;border-radius:6px;text-decoration:none}' % (typ('button'), COLORS['primary'], COLORS['primary']))
    return '\n'.join(out)


EXTRA = """
*{box-sizing:border-box} .fx-grow{flex:1 1 0!important;min-width:0} .fx-none{flex:0 0 auto!important} p{margin:0} img{display:block;margin:0 auto}
.w{max-width:100%} .e-con[style*='direction:row']>.w,.e-con-inner[style]>.w{flex:0 1 auto} .w-image[style*="left"] img{margin:0} .w-nav-menu{width:auto;flex-grow:1;text-align:right;font-weight:600}
.shape{position:absolute;left:0;right:0;height:40px;-webkit-mask:radial-gradient(circle at 50% 0,#000 18px,transparent 19px) 0 0/60px 40px repeat-x}
.shape-top{top:0}.shape-bottom{bottom:0;transform:scaleY(-1)}
.ic{display:inline-flex;width:74px;height:74px;border-radius:50%;background:#C62828;color:#fff;align-items:center;justify-content:center;font-size:26px;margin-bottom:14px}
.ib-left{display:flex;gap:18px;text-align:left}.ib-left .ic{flex:none;width:56px;height:56px}.ibt{margin-bottom:6px}
.il{list-style:none;padding:0;margin:0}.il li{margin:6px 0}.il-inline li{display:inline-block;margin-right:20px}
.cn{font:800 48px Montserrat;color:#C62828}
.soc{display:inline-flex;width:40px;height:40px;border-radius:50%;background:#C62828;color:#fff;align-items:center;justify-content:center}
.posts{display:flex;gap:24px}.post{flex:1;height:220px;background:#fff;border-radius:12px;padding:20px}
input{padding:12px;border:1px solid #ddd;border-radius:6px;width:100%;margin-bottom:10px}
@media(max-width:1024px){""" + ''.join('.wt%d{width:%d%%!important}' % (n, n) for n in (12, 24, 47, 58, 100)) + """}
@media(max-width:767px){""" + ''.join('.wm%d{width:%d%%!important}' % (n, n) for n in (25, 27, 30, 40, 46, 50, 100)) + """
 .posts{flex-direction:column} .dm-col{flex-direction:column!important;flex-wrap:wrap!important} .dm-col>.e-con{width:100%!important} h1{font-size:34px} h2{font-size:28px}}
"""

if __name__ == '__main__':
    parts = []
    for name in ('header', 'home', 'footer'):
        parts += [render(e) for e in json.load(open(os.path.join(ROOT, 'templates/%s.json' % name)))['content']]
    print('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
          '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">'
          '<style>%s\n%s\n%s</style></head><body><div class="elementor">%s</div></body></html>'
          % (theme_css(), EXTRA, kit['custom_css'], '\n'.join(parts)))
