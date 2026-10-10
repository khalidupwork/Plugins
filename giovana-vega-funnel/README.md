# Giovana Vega funnel – Elementor pages

## 1. Lead magnet page – `giovanavega.com/start-check/`

`start-check-elementor.json` is an Elementor page template built only with native
Elementor widgets: Container, Heading, Text Editor, Image, Divider, Button, Form (Pro),
Accordion and Testimonial. All colours and fonts (Playfair Display + Montserrat) are set
on each widget, **not** in Site Settings → Global Colors/Fonts, so importing it changes
nothing else on the site. The little page CSS (placeholder colour, focus ring, accordion
spacing) sits in one HTML widget in the top bar and is scoped to the `.gv-lp` class
that only this page's containers use.

### Requirements
- Elementor + **Elementor Pro** (for the Form widget and its redirect).
- Flexbox Container enabled (Elementor → Settings → Features → *Flexbox Container: Active*;
  it is on by default on current versions).

### Install (about 10 minutes)
1. **Upload the images first.** Media → Add New → upload the 7 `gv-*.webp` files
   (sent with this template). Upload them in **October 2026** so they land in
   `/wp-content/uploads/2026/10/`, which is where the template looks for them. If the
   site does not use month folders, just pick each image again in its Image widget after step 3.
   Add alt text in the Media Library (e.g. "Giovana Vega", "Investing-Start Check mockup").
2. Templates → Saved Templates → **Import Templates** → choose `start-check-elementor.json`.
3. Pages → Add New → title *Start Check*, slug **`start-check`** → Edit with Elementor →
   folder icon → My Templates → *Start Check – Lead Magnet* → Insert.
4. Page settings (gear icon, bottom left) → **Page Layout: Elementor Canvas** (removes the
   theme header/footer so the page has no menu to leak traffic) → Hide Title on.
5. Publish, then check the Responsive Mode preview (desktop / tablet / mobile).

### Form
- Two forms (hero + final section), fields *First name* (`name`) and *Email* (`email`).
- Actions after submit: **Redirect → `https://www.giovanavega.com/earning-isnt-enough/`**
  (the tripwire page). Create at least a placeholder page at that slug before testing.
- Elementor Pro also stores every submission under Elementor → Submissions.
- MailerLite step (later): open each Form → Actions After Submit → add **MailerLite**
  *before* Redirect, choose the "Investing-Start Check" group, map `name` → Name and
  `email` → Email. Do this on **both** forms.

### Things to confirm with the client
- Privacy Policy / Terms links point to `/privacy-policy/` and `/terms-and-conditions/` –
  change in the footer and consent Text Editor widgets if the slugs differ.
- Testimonials used: Marianne, Maria, Rosy (from `Testimonials_.docx`).
- Box green used: `#EAF3E9` (matches the example design). The other palette sheet shows
  `#D4F3E8` – change it on the two green sections if that is the final one.
- Copy says "Free 9-page PDF"; the design mock says "4-page" – the copy wording is used.

## Regenerating the JSON

```
python3 build_start_check.py
```

`gv_elementor.py` holds the brand colours, fonts and widget builders, so the tripwire
pages (€17 / €27 / €47) can reuse the same look.
