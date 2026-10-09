# Fonts

All fonts are under the SIL Open Font License 1.1 (see the LICENSE files). Headings use Plus Jakarta Sans (600, 700, 800, Latin). Body text uses Inter 4.0 (400, 500, 600, 700, Latin), cut from the official OTF files with `pyftsubset` (Latin range, woff2). The other fonts come from Fontsource (`@fontsource/*`), already split into the `latin`, `arabic` and `devanagari` subsets.

## Smaller cuts

`theme.json` declares each full file first and the smaller cut after it, with a narrower `unicode-range`. Browsers download the small file for text it covers, and the full file only when a page uses a character outside it, so text never falls back to a system font.

| Cut | Covers |
|---|---|
| `noto-sans-arabic-used-{400,600}` | The Arabic characters the site uses today |
| `noto-sans-devanagari-used-{400,600}` | The Hindi characters the site uses today |

To rebuild after big copy changes (needs `pip install fonttools brotli`):

```
# Script fonts: pass the characters your pages use, then copy the same list into the
# matching unicodeRange entries in theme.json.
pyftsubset noto-sans-arabic-arabic-400-normal.woff2 --text-file=arabic-chars.txt \
  --layout-features='*' --flavor=woff2 --output-file=noto-sans-arabic-used-400-normal.woff2
```

Bump `Version` in `style.css` afterwards so browsers fetch the new files.
