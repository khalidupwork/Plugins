"""Small helpers that emit Elementor (Flexbox Container) JSON.

Shared by the Giovana Vega funnel pages so the lead magnet page and the
tripwire pages use the same colours, fonts and building blocks. Every style is
set on the element itself (no Elementor Global Colors / Fonts), so importing a
page never changes anything else on the site.
"""

import hashlib
import json

# Brand
GREEN = "#0F3F32"        # titles
BOX_GREEN = "#EAF3E9"    # soft boxes / highlight bands
CREAM = "#F8F5EF"        # alternate section background
WHITE = "#FFFFFF"
GOLD = "#D4AF7C"         # accents, lines, icons
GOLD_TEXT = "#A8834B"    # gold for text on light backgrounds (readable contrast)
BURGUNDY = "#7A1F2B"     # buttons
BURGUNDY_DARK = "#5E1620"
TEXT = "#333333"
MUTED = "#6B6B6B"
LINE = "#E4DED2"

SERIF = "Playfair Display"
SANS = "Montserrat"

# Images are uploaded to the Media Library first (see README) so the template
# import can pick them up from these URLs.
UPLOADS = "https://www.giovanavega.com/wp-content/uploads/2026/10/"

_counter = [0]


def _id():
    _counter[0] += 1
    return hashlib.md5(f"gv-{_counter[0]}".encode()).hexdigest()[:7]


def px(size, unit="px"):
    return {"unit": unit, "size": size, "sizes": []}


def box(t, r=None, b=None, l=None, unit="px"):
    r = t if r is None else r
    b = t if b is None else b
    l = r if l is None else l
    return {"unit": unit, "top": str(t), "right": str(r), "bottom": str(b),
            "left": str(l), "isLinked": len({t, r, b, l}) == 1}


def gap(size):
    return {"unit": "px", "size": size, "column": str(size), "row": str(size), "isLinked": True}


def typo(prefix, family, size, tablet=None, mobile=None, weight="400", lh=None,
         style=None, ls=None, transform=None):
    s = {f"{prefix}_typography": "custom",
         f"{prefix}_font_family": family,
         f"{prefix}_font_size": px(size),
         f"{prefix}_font_weight": weight}
    if tablet:
        s[f"{prefix}_font_size_tablet"] = px(tablet)
    if mobile:
        s[f"{prefix}_font_size_mobile"] = px(mobile)
    if lh:
        s[f"{prefix}_line_height"] = px(lh, "em")
    if style:
        s[f"{prefix}_font_style"] = style
    if ls is not None:
        s[f"{prefix}_letter_spacing"] = px(ls)
    if transform:
        s[f"{prefix}_text_transform"] = transform
    return s


def image_ref(filename):
    return {"url": UPLOADS + filename, "id": "", "alt": "", "source": "library"}


# ---------------------------------------------------------------- containers

def container(children, *, inner=True, row=False, width=None, width_tablet=100,
              boxed=None, gap_px=20, align=None, justify=None, pad=None,
              pad_tablet=None, pad_mobile=None, bg=None, radius=None, border=None,
              shadow=False, stack_tablet=True, css=None, anchor=None, extra=None):
    s = {"flex_direction": "row" if row else "column",
         "flex_gap": gap(gap_px)}
    if row:
        s["flex_direction_mobile"] = "column"
        if stack_tablet:
            s["flex_direction_tablet"] = "column"
    if boxed:
        s["content_width"] = "boxed"
        s["boxed_width"] = px(boxed)
    else:
        s["content_width"] = "full"
    if width:
        s["width"] = px(width, "%")
        s["width_tablet"] = px(width_tablet, "%")
        s["width_mobile"] = px(100, "%")
    if align:
        s["flex_align_items"] = align
    if justify:
        s["flex_justify_content"] = justify
    s["padding"] = pad or box(0)
    if pad_tablet:
        s["padding_tablet"] = pad_tablet
    if pad_mobile:
        s["padding_mobile"] = pad_mobile
    if bg:
        s["background_background"] = "classic"
        s["background_color"] = bg
    if radius:
        s["border_radius"] = box(radius)
    if border:
        s["border_border"] = "solid"
        s["border_width"] = box(1)
        s["border_color"] = border
    if shadow:
        s["box_shadow_box_shadow_type"] = "yes"
        s["box_shadow_box_shadow"] = {"horizontal": 0, "vertical": 14, "blur": 40, "spread": 0,
                                      "color": "rgba(15,63,50,0.10)"}
    classes = ["gv-lp"] + ([css] if css else [])
    s["css_classes"] = " ".join(classes)
    if anchor:
        s["_element_id"] = anchor
    if extra:
        s.update(extra)
    return {"id": _id(), "elType": "container", "isInner": inner,
            "settings": s, "elements": children}


def section(children, *, bg=WHITE, pad=(90, 24), pad_tablet=(70, 32), pad_mobile=(56, 18),
            boxed=1180, **kw):
    """Full-width outer band with a boxed inner area."""
    return container(children, inner=False, boxed=boxed, bg=bg,
                     pad=box(pad[0], pad[1]), pad_tablet=box(pad_tablet[0], pad_tablet[1]),
                     pad_mobile=box(pad_mobile[0], pad_mobile[1]), **kw)


# ------------------------------------------------------------------- widgets

def widget(kind, settings):
    return {"id": _id(), "elType": "widget", "widgetType": kind,
            "settings": settings, "elements": []}


def heading(text, tag="h2", *, size=40, tablet=34, mobile=28, color=GREEN, family=SERIF,
            weight="700", align="left", align_mobile=None, lh=1.2, style=None, ls=None,
            transform=None, max_width=None, extra=None):
    s = {"title": text, "header_size": tag, "align": align, "title_color": color}
    if align_mobile:
        s["align_mobile"] = align_mobile
    s.update(typo("typography", family, size, tablet, mobile, weight, lh, style, ls, transform))
    if max_width:
        s["_element_width"] = "initial"
        s["_element_custom_width"] = px(max_width)
    if extra:
        s.update(extra)
    return widget("heading", s)


def eyebrow(text, color=BURGUNDY, align="left", align_mobile=None):
    return heading(text, "p", size=13, tablet=13, mobile=12, color=color, family=SANS,
                   weight="600", align=align, align_mobile=align_mobile, lh=1.5, ls=2.2,
                   transform="uppercase")


def text(html, *, size=17, tablet=16, mobile=16, color=TEXT, align="left", align_mobile=None,
         lh=1.75, weight="400", family=SANS, style=None, css=None):
    s = {"editor": html, "align": align, "text_color": color}
    if align_mobile:
        s["align_mobile"] = align_mobile
    s.update(typo("typography", family, size, tablet, mobile, weight, lh, style))
    if css:
        s["_css_classes"] = css
    return widget("text-editor", s)


def image(filename, *, width=100, align="center", radius=0, max_px=None, shadow=False):
    s = {"image": image_ref(filename), "image_size": "full", "align": align,
         "width": px(width, "%")}
    if max_px:
        s["width"] = px(max_px)
        s["width_mobile"] = px(100, "%")
        s["space"] = px(100, "%")
    if radius:
        s["image_border_radius"] = box(radius)
    if shadow:
        s["image_box_shadow_box_shadow_type"] = "yes"
        s["image_box_shadow_box_shadow"] = {"horizontal": 0, "vertical": 18, "blur": 45,
                                            "spread": 0, "color": "rgba(15,63,50,0.16)"}
    return widget("image", s)


def button(label, url, *, align="left", align_mobile="justify", bg=BURGUNDY, hover=BURGUNDY_DARK,
           color=WHITE):
    s = {"text": label, "link": {"url": url, "is_external": "", "nofollow": ""},
         "align": align, "align_mobile": align_mobile, "size": "lg",
         "selected_icon": {"value": "fas fa-arrow-right", "library": "fa-solid"},
         "icon_align": "right", "icon_indent": px(10),
         "background_color": bg, "button_text_color": color,
         "button_background_hover_color": hover, "hover_color": color,
         "border_radius": box(6), "text_padding": box(18, 34)}
    s.update(typo("typography", SANS, 15, 15, 14, "600", 1.3, ls=1.2))
    return widget("button", s)


def optin_form(form_id, redirect_url):
    """Elementor Pro Form: first name + email, redirects after submit."""
    s = {
        "form_name": "Investing-Start Check",
        "form_id": form_id,
        "form_fields": [
            {"_id": _id(), "custom_id": "name", "field_type": "text", "field_label": "First name",
             "placeholder": "First name", "required": "true", "width": "100"},
            {"_id": _id(), "custom_id": "email", "field_type": "email",
             "field_label": "Email address", "placeholder": "Email address",
             "required": "true", "width": "100"},
        ],
        "input_size": "md",
        "show_labels": "",
        "button_text": "SEND ME THE INVESTING-START CHECK",
        "button_size": "md",
        "button_width": "100",
        "selected_button_icon": {"value": "fas fa-arrow-right", "library": "fa-solid"},
        "button_icon_align": "right",
        "button_icon_indent": px(8),
        "submit_actions": ["redirect"],
        "redirect_to": redirect_url,
        "success_message": "Thank you! Your Investing-Start Check is on its way to your inbox.",
        "error_message": "Something went wrong. Please try again.",
        "required_field_message": "Please fill in this field.",
        "invalid_message": "Please enter a valid email address.",
        "custom_messages": "yes",
        "column_gap": px(12),
        "row_gap": px(12),
        "field_text_color": TEXT,
        "field_background_color": WHITE,
        "field_border_color": "#D9D3C7",
        "field_border_width": box(1),
        "field_border_radius": box(6),
        "button_background_color": BURGUNDY,
        "button_text_color": WHITE,
        "button_background_hover_color": BURGUNDY_DARK,
        "button_hover_color": WHITE,
        "button_border_radius": box(6),
        "button_text_padding": box(17, 20),
    }
    s.update(typo("field_typography", SANS, 15, 15, 15, "400", 1.4))
    s.update(typo("button_typography", SANS, 14, 14, 13, "600", 1.35, ls=1))
    return widget("form", s)


def accordion(items):
    s = {
        "tabs": [{"_id": _id(), "tab_title": q, "tab_content": a} for q, a in items],
        "selected_icon": {"value": "fas fa-plus", "library": "fa-solid"},
        "selected_active_icon": {"value": "fas fa-minus", "library": "fa-solid"},
        "icon_align": "right",
        "title_html_tag": "h3",
        "border_width": px(1),
        "border_color": LINE,
        "title_background": WHITE,
        "title_color": GREEN,
        "tab_active_color": BURGUNDY,
        "title_padding": box(20, 24),
        "icon_color": GOLD_TEXT,
        "icon_active_color": BURGUNDY,
        "content_background_color": WHITE,
        "content_color": TEXT,
        "content_padding": box(4, 24, 22, 24),
    }
    s.update(typo("title_typography", SANS, 17, 17, 15, "600", 1.4))
    s.update(typo("content_typography", SANS, 16, 16, 15, "400", 1.7))
    return widget("accordion", s)


def testimonial(quote_html, name, job, photo):
    s = {
        "testimonial_content": quote_html,
        "testimonial_image": image_ref(photo),
        "testimonial_image_size": "thumbnail",
        "testimonial_name": name,
        "testimonial_job": job,
        "testimonial_image_position": "top",
        "testimonial_alignment": "center",
        "content_content_color": TEXT,
        "image_size": px(84),
        "image_border_radius": box(50, unit="%"),
        "name_text_color": GREEN,
        "job_text_color": MUTED,
    }
    s.update(typo("content_typography", SANS, 15, 15, 15, "400", 1.75, style="italic"))
    s.update(typo("name_typography", SERIF, 19, 19, 18, "700", 1.3))
    s.update(typo("job_typography", SANS, 13, 13, 13, "400", 1.5))
    return widget("testimonial", s)


def divider(color=GOLD, width=70, align="left", align_mobile=None):
    s = {"style": "solid", "weight": px(2), "color": color, "width": px(width),
         "align": align, "gap": px(4)}
    if align_mobile:
        s["align_mobile"] = align_mobile
    return widget("divider", s)


def html(code, *, out_of_flow=False):
    s = {"html": code}
    if out_of_flow:
        # keep the <style> widget from adding a flex gap
        s["_position"] = "absolute"
        s["_element_width"] = "initial"
        s["_element_custom_width"] = px(1)
    return widget("html", s)


def template(title, content, page_settings=None):
    return {"version": "0.4", "title": title, "type": "page",
            "page_settings": page_settings or {}, "content": content}


def dump(tpl, path):
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(tpl, fh, ensure_ascii=False, indent=1)
