# Fonts

All fonts are under the SIL Open Font License 1.1 (see the LICENSE files). They come from Fontsource (`@fontsource/*`), already split into the `latin`, `arabic` and `devanagari` subsets.

## Smaller cuts

`theme.json` declares each full file first and the smaller cut after it, with a narrower `unicode-range`. Browsers download the small file for text it covers, and the full file only when a page uses a character outside it, so text never falls back to a system font.

| Cut | Covers |
|---|---|
| `bricolage-grotesque-basic-{700,800}` | Headings: ASCII, quotes, `©`, `·`, `×`, `…` and arrows |
| `noto-sans-arabic-used-{400,600}`, `noto-nastaliq-urdu-used-400` | The Arabic and Urdu characters the site uses today |
| `noto-sans-devanagari-used-{400,600}` | The Hindi characters the site uses today |

To rebuild after big copy changes (needs `pip install fonttools brotli`):

```
# Headings
pyftsubset bricolage-grotesque-latin-800-normal.woff2 \
  --unicodes="U+0020-007E,U+00A0,U+00A9,U+00B7,U+00D7,U+2018-201D,U+2022,U+2026,U+2190-2193" \
  --layout-features='kern,liga,calt,ccmp,locl,mark,mkmk' --flavor=woff2 \
  --output-file=bricolage-grotesque-basic-800-normal.woff2

# Script fonts: pass the characters your pages use, then copy the same list into the
# matching unicodeRange entries in theme.json.
pyftsubset noto-sans-arabic-arabic-400-normal.woff2 --text-file=arabic-chars.txt \
  --layout-features='*' --flavor=woff2 --output-file=noto-sans-arabic-used-400-normal.woff2
```

Bump `Version` in `style.css` afterwards so browsers fetch the new files.
