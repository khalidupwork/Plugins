# Click Mat Kar theme — build progress

Fresh theme (not based on the v0.9.0 zip). Source of truth: the 2026-10-10 master handoff
(Master Plan, Brand Guide V2, Homepage UX/Sitemap, Technical QA, brand tokens, analytics event map).

## Done
- [x] Theme skeleton: `style.css`, `functions.php`, `inc/` (setup, games CPT + catalog, template tags, installer)
- [x] Design system CSS (`assets/css/main.css`): brand tokens, hard borders/offset shadows, buttons, pills, cards, wordmark, reduced-motion
- [x] Header (sticky, mobile menu) + footer
- [x] Homepage, all 12 sections from the UX map (hero with playable mini shop, ticker, game grid, featured swipe demo,
      chaos level, how it works, lab/upcoming, results, manifesto, challenge, final CTA)
- [x] `cmk_game` CPT, catalog of 7 games (only Shop Like You're Rich is live; rest are Drafts → "Soon" cards, never links)
- [x] Appearance > Click Mat Kar 1-click setup (pages, front page, privacy page, games, menus, permalinks; idempotent)
- [x] Analytics helper `CMKUI.track()` → dataLayer / gtag / plausible + `cmk:track` DOM event
- [x] Currency localisation (PKR / INR / USD via timezone, `?cur=usd|pkr|inr` override)
- [x] QA: homepage at 1440 / 1024 / 390 / 320, no console errors, no page-level horizontal scroll
- [x] `single-cmk_game.php` + game shell (`template-parts/game/shop.php`) + "cooking" fallback for non-live games
- [x] Shop Like You're Rich engine (`assets/js/game-shop.js`): 24 products, 6 categories, sticky budget HUD, fly-to-cart,
      reactions, cart drawer (remove items, Esc to close), fake checkout → Financial IQ → `/result/?r=…`
- [x] `/result/` (`page-result.php` + `assets/js/result.js`): animated result card, IQ tier verdict, share (Web Share,
      WhatsApp, X, copy link), 1080×1920 story-card PNG download, challenge link, play again / play another, empty state,
      owner vs. visitor view ("Accept the challenge")
- [x] Challenge flow: `?challenge=` banner on the game page (`challenge_open`)
- [x] Server-side OG title for shared results ("I wasted Rs … Financial IQ: 40/100"), result pages `noindex`
- [x] `/games/` library (`archive-cmk_game.php`) with Everything / Play now / Cooking filter
- [x] `/about/` (`page-about.php`) "WTF is this?" page, styled 404
- [x] All analytics events from the event map wired: page_view, game_view, game_start, product_add, cart_open,
      game_complete, result_share, challenge_create, challenge_open, play_another (+ cta_click)
- [x] Runtime QA on real WordPress 6.8.3: 1-click setup (fresh + re-run), homepage/game/result/library/about/404 at
      1440/1024/390/320, full flow add → cart → checkout → result → story PNG → visitor → challenge, no console/PHP errors


## v1.0.0-alpha.2 — all games launched
- [x] All 7 games playable. Data packs in `assets/js/games-data.js`; two shared engines:
      shop (`game-shop.js`: Shop Like You're Rich, Plan a Crazy Wedding, Spend $1 Billion, Dream Lifestyle) and
      quiz (`game-quiz.js`: Bad Decisions, Find Your Red Flags, What's Your Price?)
- [x] Result page, story card, OG title and challenge banner read labels/tiers from each game's pack
- [x] Existing installs upgrade themselves: on a new theme version, `cmk_maybe_upgrade()` publishes launched games,
      sets engines and replaces old "Coming soon" kickers (no need to re-run setup)
- [x] No "Soon"/"Cooking" anywhere; homepage "More nonsense is cooking" replaced by a "Spin the wheel" random-game machine
- [x] Homepage layout fixes: 7-card grid (featured first card), featured amount box no longer wraps, manifesto text
      centred, ticker contained (no sideways scroll), hero stickers repositioned, results/challenge sections clipped
- [x] QA: every game played end-to-end at 390 and 1440 (play → result → story PNG → challenge link), homepage at
      1280/1024/390/320, no console errors, no page-level horizontal scroll

## v1.0.0-alpha.3 — engagement pass (parody brands)
- [x] 90 products across the 4 shop games rewritten with desi parody brands (Gucchi, Rolax, Lambo-Ghanta, iFone, Hermes
      Burkin, Rolls Rice, Bala-ji-aga…) and rough real-world USD prices converted per currency (PKR 280, INR 88)
- [x] Per-currency budgets: Shop Rs 25 Crore / ₹10 Crore / $1M; Wedding Rs 7 Crore / ₹2 Crore / $250k;
      Dream Life Rs 1.4 Arab / ₹40 Crore / $5M; $1 Billion stays in USD
- [x] "= 1,493 plates of biryani" comparison line on every product, in reactions, cart total, result card,
      share text and the story PNG (chai/biryani/Honda 70 · cutting chai/biryani/Activa · coffee/pizza/used Corolla)
- [x] E-commerce parody badges (Bestseller, Only 1 left, Influencer pick, Ammi disapproved, Pre-order)
- [x] Budget milestone toasts at 25/50/75/95%, light haptic buzz on add (mobile, off with reduced motion)
- [x] Product image slots: drop `{product-id}.webp` into `assets/img/products/` and it replaces the emoji automatically;
      brief with spec + AI prompt per product in `assets/img/products/README.md`
- [x] Hero mini shop + featured swipe demo use the parody products

## v1.0.0-alpha.4 — every visual reacts (`assets/js/fx.js`)
- [x] 3D tilt + moving shine on game cards, chaos cards, steps, products, phones, mini items, result cards, spin machine
      (desktop pointer); press-squish on touch
- [x] Drag & throw with spring-back: hero stickers, tags ("Poke the shop", "Bad idea"…), chat bubbles, result stamp,
      product badges, section pills; tap gives a random line
- [x] "Click mat kar" easter egg: clicks on empty space pop "Mana kiya tha!", after 5 a counter pill appears
      ("7 clicks. Concerning. Play →") linking to the game
- [x] Magnetic big CTAs, hero depth parallax (stickers/phone/cursor), bouncy wordmark letters, logo "press" sequence on click
- [x] Ticker speeds up with scroll velocity; section headings slide in; card emojis jump on hover
- [x] All of it is off for prefers-reduced-motion; tilt/magnet only for fine pointers

## Next (in order)
- [ ] Produce the 90 product images from `assets/img/products/README.md` (start with the 29 in Shop Like You're Rich)
- [ ] Site icon / OG share image (1200×630) + per-result OG image (needs server-side render or a static set by IQ tier)
- [ ] Customizer/settings: analytics ID field (GA4 / Plausible) — `CMKUI.track` already pushes to dataLayer/gtag/plausible
- [ ] Optional micro-sounds (off by default) and haptics on mobile add-to-cart
- [ ] Before release: rename version to 1.0.0, zip `click-mat-kar/` (zips are git-ignored), install on staging,
      run setup, repeat the 4-width QA

## Local test rig (cloud session)
WordPress 6.8.3 from GitHub mirror + SQLite integration v2.2.2 in the scratchpad, theme symlinked, `php -S localhost:8080`
with a router; Playwright screenshots at 1440/1024/390/320.
