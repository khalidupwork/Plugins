"""Builds the Elementor template for giovanavega.com/start-check/ (free lead magnet).

    python3 build_start_check.py   ->  start-check-elementor.json
"""

import os

from gv_elementor import (BOX_GREEN, BURGUNDY, CREAM, GOLD, GOLD_TEXT, GREEN, LINE, MUTED,
                          SANS, SERIF, WHITE, accordion, box, button, container, divider,
                          dump, eyebrow, heading, html, image, optin_form, section, template,
                          text, testimonial)

TRIPWIRE_URL = "https://www.giovanavega.com/earning-isnt-enough/"
PRIVACY_URL = "https://www.giovanavega.com/privacy-policy/"
TERMS_URL = "https://www.giovanavega.com/terms-and-conditions/"
FORM_ANCHOR = "start-check-form"

MOCKUP_TABLET = "gv-start-check-mockup-tablet.webp"
MOCKUP_PAGES = "gv-start-check-mockup-pages.webp"
PORTRAIT = "gv-giovana-vega-portrait.webp"
LOUNGE = "gv-giovana-vega-lounge.webp"

# Page-only CSS. Everything is scoped to .gv-lp (set on this page's containers),
# so nothing else on the site is affected.
PAGE_CSS = """<style>
html{scroll-behavior:smooth}
.gv-lp{overflow-x:clip}
.gv-lp .elementor-field-group .elementor-field::placeholder{color:#8A8A8A;opacity:1}
.gv-lp .elementor-field-group .elementor-field:focus{border-color:#0F3F32!important;box-shadow:0 0 0 3px rgba(15,63,50,.12);outline:none}
.gv-lp .elementor-field-group .elementor-field{min-height:50px}
.gv-lp .elementor-form .elementor-button{letter-spacing:1px}
.gv-lp .elementor-message{font-family:Montserrat,sans-serif;font-size:14px}
.gv-lp p:last-child{margin-bottom:0}
.gv-lp .gv-pull{font-family:"Playfair Display",serif;font-style:italic;color:#0F3F32;font-size:1.3em;line-height:1.4}
.gv-lp .gv-pull-light{font-family:"Playfair Display",serif;font-style:italic;color:#D4AF7C;font-size:1.3em;line-height:1.4}
.gv-lp .gv-quote-list p{margin:0 0 6px}
.gv-lp .gv-step{border-top:3px solid #D4AF7C}
.gv-lp .elementor-testimonial-content{margin-bottom:22px}
.gv-lp .elementor-tab-title a{color:inherit}
.gv-lp .elementor-accordion .elementor-accordion-item{border-radius:6px;overflow:hidden;margin-bottom:12px;border:1px solid #E4DED2!important}
.gv-lp .gv-footer a{color:#0F3F32;text-decoration:underline;text-underline-offset:3px}
@media (max-width:767px){.gv-lp .elementor-accordion .elementor-tab-title{padding:16px 18px}}
</style>"""


def form_card(form_id, dark=False):
    """White card: title, form, microcopy."""
    return container([
        heading("Why Haven’t You Started Investing Yet?", "h2", size=24, tablet=24, mobile=22,
                align="center", lh=1.25),
        eyebrow("The Investing-Start Check", color=GOLD_TEXT, align="center"),
        optin_form(form_id, TRIPWIRE_URL),
        text("<p>Free 9-page PDF. Sent directly to your inbox.</p>", size=13, tablet=13,
             mobile=13, color=MUTED, align="center", lh=1.5),
    ], bg=WHITE, radius=12, shadow=True, gap_px=14, pad=box(34, 32),
        pad_mobile=box(26, 20), border=None if dark else LINE, anchor=None)


def build():
    content = []

    # ---------------------------------------------------------------- top bar
    content.append(section([
        heading("GIOVANA VEGA", "p", size=18, tablet=18, mobile=16, ls=4, lh=1.2,
                align="center"),
        heading("Investing with clarity", "p", size=10, tablet=10, mobile=10, family=SANS,
                weight="500", color=GOLD_TEXT, ls=3, transform="uppercase", align="center",
                lh=1.2),
        html(PAGE_CSS, out_of_flow=True),
    ], bg=WHITE, pad=(18, 24), pad_tablet=(16, 24), pad_mobile=(14, 18), gap_px=4,
        align="center", extra={"border_border": "solid", "border_width": box(0, 0, 1, 0),
                               "border_color": LINE}))

    # ------------------------------------------------------------------- hero
    hero_left = container([
        eyebrow("Free 5-minute Investing-Start Check", align="left", align_mobile="center"),
        heading("You’ve Read. Watched. Researched.", "h1", size=52, tablet=46, mobile=34,
                lh=1.12, align="left", align_mobile="center"),
        heading("So Why Haven’t You Started Investing Yet?", "p", size=40, tablet=36,
                mobile=27, weight="500", style="italic", color=GOLD_TEXT, lh=1.2,
                align="left", align_mobile="center"),
        divider(align="left", align_mobile="center"),
        text("<p><strong>You know you want to invest.</strong></p>"
             "<p>You’ve read the articles. Watched the videos. Maybe listened to podcasts and "
             "followed people who seem to know exactly what they’re doing.</p>"
             "<p>You’re responsible with your money.<br>You earn. You save. You think about "
             "your future.</p>"
             "<p><strong>So why are you still waiting to invest?</strong></p>"
             "<p>Take this free 5-minute check and discover what keeps happening between:</p>"
             "<p class=\"gv-pull\">“I want to start investing.”<br>"
             "<span style=\"font-family:Montserrat,sans-serif;font-style:normal;font-size:15px;"
             "color:#6B6B6B\">and</span><br>“I still haven’t started.”</p>",
             align_mobile="center"),
    ], width=54, gap_px=16)

    hero_right = container([
        image(MOCKUP_TABLET, max_px=430),
        form_card("start_check_top"),
    ], width=46, gap_px=6, align="stretch", anchor="optin")

    content.append(section([
        container([hero_left, hero_right], row=True, gap_px=56, align="center"),
    ], bg=CREAM, pad=(70, 24), pad_tablet=(56, 32), pad_mobile=(40, 18)))

    # ------------------------------------------------------- the familiar loop
    content.append(section([
        container([
            heading("You’re not ignoring your financial future.", "h2", size=40, tablet=34,
                    mobile=28, align="center"),
            text("<p>Actually, you may have been thinking about it for quite a while.</p>",
                 align="center", size=18),
            divider(align="center"),
            text("<p>Maybe you’ve searched online.<br>Read about investing.<br>Watched videos."
                 "<br>Asked someone you trust.<br>Compared different opinions.</p>"
                 "<p>And every time you think:</p>"
                 "<p class=\"gv-pull\">“Okay. I really should start.”</p>"
                 "<p>…something happens.</p>"
                 "<p>Another question comes up.</p>"
                 "<p>Someone says something different.</p>"
                 "<p>You find something else you think you should understand first.</p>"
                 "<p>So you do what feels responsible.</p>"
                 "<p>You research a little more.</p>"
                 "<p><strong>And somehow, starting becomes waiting again.</strong></p>",
                 align="center", size=18),
            heading("Sound familiar?", "p", size=30, tablet=28, mobile=24, weight="500",
                    style="italic", color=BURGUNDY, align="center"),
        ], gap_px=18, extra={"width": {"unit": "px", "size": 760, "sizes": []},
                             "width_tablet": {"unit": "%", "size": 100, "sizes": []},
                             "width_mobile": {"unit": "%", "size": 100, "sizes": []}}),
    ], bg=WHITE, align="center"))

    # ------------------------------------------------------ reframe (green box)
    content.append(section([
        container([
            container([
                heading("What if the problem isn’t that you haven’t learned enough?", "h2",
                        size=38, tablet=32, mobile=27, lh=1.22, align="left",
                        align_mobile="center"),
            ], width=45, gap_px=0),
            container([
                text("<p>The Investing-Start Check gives you five minutes to step away from all "
                     "the information and look at something you may not have looked at "
                     "before:</p>"
                     "<p class=\"gv-pull\">What actually happens when YOU try to start?</p>",
                     size=18, align_mobile="center"),
            ], width=55, gap_px=0, extra={"border_border": "solid",
                                          "border_width": box(0, 0, 0, 2),
                                          "border_width_tablet": box(0),
                                          "border_color": GOLD,
                                          "padding": box(0, 0, 0, 40),
                                          "padding_tablet": box(0)}),
        ], row=True, gap_px=48, align="center"),
        container([
            heading("No test. No score. No investing quiz.", "p", size=22, tablet=22,
                    mobile=20, align="center", lh=1.3),
            text("<p>Just three simple steps to look at your own experience.</p>",
                 align="center", color=MUTED),
        ], gap_px=6, pad=box(36, 0, 0, 0)),
    ], bg=BOX_GREEN, pad=(80, 24)))

    # --------------------------------------------------------------- 3 steps
    def step(num, title, body):
        return container([
            heading(num, "p", size=34, tablet=32, mobile=30, color=GOLD_TEXT, lh=1,
                    align="left"),
            heading(title, "h3", size=21, tablet=21, mobile=20, lh=1.3, family=SERIF,
                    align="left", transform="uppercase", ls=0.5),
            text(body, size=16, tablet=16, mobile=16, css="gv-quote-list"),
        ], width=33.33, bg=CREAM, radius=10, gap_px=12, pad=box(34, 30),
            pad_mobile=box(28, 22), css="gv-step", align="stretch")

    content.append(section([
        container([
            step("01.", "Look at what you’ve already done",
                 "<p><strong>You may be further along than you think.</strong></p>"
                 "<p>Look at how long investing has been on your mind and what you’ve already "
                 "done to prepare yourself.</p>"),
            step("02.", "Find the moment you stop",
                 "<p>Think back to the last time you genuinely thought:</p>"
                 "<p class=\"gv-pull\">“Okay. I’m going to do this.”</p>"
                 "<p>Then finish one simple sentence:</p>"
                 "<p class=\"gv-pull\">“I was going to start, but then…”</p>"
                 "<p>Your answer may tell you more than you expect.</p>"),
            step("03.", "See what keeps repeating",
                 "<p>Now bring your answers together.</p>"
                 "<p>Do you keep going back to research?</p>"
                 "<p>Do different opinions make you less certain?</p>"
                 "<p>Do you get stuck because you don’t know what matters first?</p>"
                 "<p>Or do you keep waiting until you finally feel completely ready?</p>"),
        ], row=True, gap_px=26, align="stretch"),
        container([
            text("<p><strong>There are no right or wrong answers.</strong></p>"
                 "<p>Just five minutes to notice what happens when you try to move forward.</p>",
                 align="center", size=18),
        ], gap_px=0, pad=box(40, 0, 0, 0)),
    ], bg=WHITE))

    # ------------------------------------------- you don't need to solve it all
    content.append(section([
        container([
            container([image(MOCKUP_PAGES, max_px=560)], width=48, align="center"),
            container([
                heading("You don’t need to solve everything today.", "h2", size=38, tablet=34,
                        mobile=28, align="left", align_mobile="center"),
                divider(align="left", align_mobile="center"),
                text("<p>The purpose of this check isn’t to tell you what you should invest "
                     "in.</p>"
                     "<p>And it isn’t another list of things you need to learn.</p>"
                     "<p>It’s simply an opportunity to step away from all the information for "
                     "five minutes and look at your own experience.</p>"
                     "<p>Because sometimes the most useful question isn’t another question "
                     "about investing.</p><p>It’s:</p>"
                     "<p class=\"gv-pull\">“What happens when I actually try to start?”</p>"
                     "<p><strong>Start there.</strong></p>", align_mobile="center"),
                button("SEND ME THE INVESTING-START CHECK", f"#{FORM_ANCHOR}"),
            ], width=52, gap_px=16),
        ], row=True, gap_px=56, align="center"),
    ], bg=CREAM))

    # ------------------------------------------------------------------ about
    content.append(section([
        container([
            container([image(PORTRAIT, radius=12, max_px=440, shadow=True)], width=42,
                      align="center"),
            container([
                heading("Hi, I’m Giovana.", "h2", size=42, tablet=36, mobile=30,
                        align="left", align_mobile="center"),
                text("<p>I’m an author, TEDx speaker and award-winning financial educator with "
                     "more than 15 years of experience in investing and financial "
                     "education.</p>"
                     "<p>Through my work, I've worked with women in more than 10 countries and "
                     "inspired over 10,000 women to think differently about money and their "
                     "financial future.</p>"
                     "<p>And although their lives and experiences are different, I’ve heard "
                     "versions of the same question again and again:</p>"
                     "<p class=\"gv-pull\">“I know I want to invest. So why haven’t I "
                     "started?”</p>"
                     "<p>That question stayed with me.</p>"
                     "<p>Because I believe investing isn’t something reserved for a certain "
                     "type of person.</p>"
                     "<p>Women can learn it, understand it and make informed decisions for "
                     "themselves.</p>"
                     "<p><strong>The Investing-Start Check is a simple place to "
                     "begin.</strong></p>", align_mobile="center"),
                divider(align="left", align_mobile="center"),
                heading("Giovana Vega", "p", size=26, tablet=26, mobile=24, align="left",
                        align_mobile="center", style="italic"),
                text("<p>Author | TEDx Speaker | Award-Winning Financial Educator<br>"
                     "Founder, Trading for Women | Creator, W.I.M. Method™</p>",
                     size=14, tablet=14, mobile=13, color=MUTED, lh=1.6,
                     align_mobile="center"),
            ], width=58, gap_px=14),
        ], row=True, gap_px=64, align="center"),
    ], bg=WHITE))

    # ----------------------------------------------------------- testimonials
    content.append(section([
        container([
            image(LOUNGE, radius=12, shadow=True),
            text("<p><strong style=\"color:#0F3F32\">Giovana Vega</strong> &nbsp;·&nbsp; "
                 "Author | TEDx Speaker | Award-Winning Financial Educator | 15+ Years in "
                 "Investing</p>", size=14, tablet=14, mobile=13, color=MUTED, align="center",
                 lh=1.6),
        ], gap_px=14, extra={"width": {"unit": "px", "size": 900, "sizes": []},
                             "width_tablet": {"unit": "%", "size": 100, "sizes": []},
                             "width_mobile": {"unit": "%", "size": 100, "sizes": []}}),
        heading("Learning about money feels different when someone makes it understandable.",
                "h2", size=36, tablet=32, mobile=26, align="center", lh=1.25,
                max_width=820, extra={"_margin": box(40, 0, 10, 0)}),
        container([
            container([testimonial(
                "Giovana provides excellent insight into the dynamics of the financial market. "
                "This session was made special by Giovana’s expertise and passion for "
                "empowering people. Opening my eyes! Thank you so much, Giovana.",
                "Marianne J Jansen",
                "Visual artist, Teacher of Drawing, Painting and Sculpture, Netherlands",
                "gv-testimonial-marianne.webp")],
                width=33.33, bg=WHITE, radius=12, pad=box(34, 26), border=LINE),
            container([testimonial(
                "I’ve attended one of Giovana’s trainings and also know her as a leader. She is "
                "authentic, knowledgeable and has a wonderful way of making financial topics "
                "feel approachable. Giovana creates an environment where women feel "
                "comfortable asking questions, learning and building confidence in their own "
                "decisions. Her passion for helping women grow really comes through in the way "
                "she teaches.",
                "Maria Cavali", "AI Automation & Expert in Social Media Growth",
                "gv-testimonial-maria.webp")],
                width=33.33, bg=WHITE, radius=12, pad=box(34, 26), border=LINE),
            container([testimonial(
                "As an entrepreneur, I was used to working hard for my income, but I also "
                "wanted to learn more about managing my money and planning for the future. "
                "Through Giovana’s training, I learned more about investing and began to "
                "understand how it can be considered alongside saving as part of a broader "
                "financial picture. What I especially appreciated was Giovana’s way of "
                "teaching. Numbers aren’t naturally my thing, but she made the subject "
                "interesting and easy to understand. The training gave me a new perspective on "
                "how I think about my money and my future. Thank you, Giovana!",
                "Rosy Carruitero", "Entrepreneur", "gv-testimonial-rosy.webp")],
                width=33.33, bg=WHITE, radius=12, pad=box(34, 26), border=LINE),
        ], row=True, gap_px=24, align="stretch"),
    ], bg=BOX_GREEN, align="center", gap_px=20))

    # ---------------------------------------------------------- final opt-in
    content.append(section([
        container([
            container([
                heading("Before you search for another answer, look at your own.", "h2",
                        size=42, tablet=36, mobile=29, color=WHITE, align="left",
                        align_mobile="center", lh=1.2),
                divider(align="left", align_mobile="center"),
                text("<p>You already know you want to invest.</p>"
                     "<p>And you've probably spent enough time thinking about it to know that "
                     "wanting to start isn't the same as starting.</p>"
                     "<p><strong>So give yourself five minutes.</strong></p>"
                     "<p>Look at the part that’s easy to miss:</p>"
                     "<p class=\"gv-pull-light\">What happens when you actually try to "
                     "start?</p>", color="#E9EFEC", align_mobile="center", size=18),
            ], width=52, gap_px=18),
            container([
                image(MOCKUP_TABLET, max_px=360),
                form_card("start_check_bottom", dark=True),
            ], width=48, gap_px=6, align="stretch"),
        ], row=True, gap_px=56, align="center"),
    ], bg=GREEN, anchor=FORM_ANCHOR))

    # -------------------------------------------------------------------- FAQ
    content.append(section([
        container([
            heading("A few things you may be wondering", "h2", size=38, tablet=34, mobile=28,
                    align="center"),
            accordion([
                ("Is it really free and how long does it take?",
                 "<p>Yes. It’s completely free and takes about five minutes. Enter your first "
                 "name and email address, and I’ll send it directly to your inbox.</p>"),
                ("Will you tell me what I should invest in?",
                 "<p>No. The Investing-Start Check doesn’t recommend investments or tell you "
                 "what to buy. It helps you look at your own starting point and what happens "
                 "when you try to move forward.</p>"),
                ("Is this financial or investment advice?",
                 "<p>No. The Investing-Start Check is educational and reflective in nature. It "
                 "does not provide personalized investment, financial, legal or tax advice, or "
                 "recommend specific investments or investment platforms. Investing involves "
                 "risk, and investment decisions remain your own.</p>"),
            ]),
            button("SEND ME THE INVESTING-START CHECK", f"#{FORM_ANCHOR}", align="center"),
        ], gap_px=28, extra={"width": {"unit": "px", "size": 820, "sizes": []},
                             "width_tablet": {"unit": "%", "size": 100, "sizes": []},
                             "width_mobile": {"unit": "%", "size": 100, "sizes": []}}),
    ], bg=WHITE, align="center"))

    # ----------------------------------------------------- consent + footer
    content.append(section([
        container([
            text("<p>By entering your email, you’ll receive the Investing-Start Check and "
                 "occasional educational emails from me about money, investing and long-term "
                 "financial well-being. You can unsubscribe at any time.</p>"
                 f"<p>Privacy: Your information will be handled in accordance with our "
                 f"<a href=\"{PRIVACY_URL}\">Privacy Policy</a> and will not be sold to third "
                 f"parties.</p>", size=13, tablet=13, mobile=13, color=MUTED, align="center",
                 lh=1.65),
            divider(color=LINE, width=100, align="center"),
            heading("Giovana Vega – Trading for Women", "p", size=18, tablet=18, mobile=17,
                    align="center"),
            text(f"<p><a href=\"{PRIVACY_URL}\">Privacy Policy</a> &nbsp;|&nbsp; "
                 f"<a href=\"{TERMS_URL}\">Terms &amp; Conditions</a></p>"
                 "<p>© 2026 Giovana Vega. All rights reserved.</p>",
                 size=13, tablet=13, mobile=13, color=MUTED, align="center", lh=1.8),
        ], gap_px=14, css="gv-footer", extra={
            "width": {"unit": "px", "size": 820, "sizes": []},
            "width_tablet": {"unit": "%", "size": 100, "sizes": []},
            "width_mobile": {"unit": "%", "size": 100, "sizes": []}}),
    ], bg=CREAM, pad=(50, 24), pad_tablet=(44, 32), pad_mobile=(40, 18), align="center"))

    return template("Start Check – Lead Magnet", content, {
        "template": "elementor_canvas",
        "hide_title": "yes",
        "background_background": "classic",
        "background_color": WHITE,
    })


if __name__ == "__main__":
    here = os.path.dirname(os.path.abspath(__file__))
    dump(build(), os.path.join(here, "start-check-elementor.json"))
    print("written start-check-elementor.json")
