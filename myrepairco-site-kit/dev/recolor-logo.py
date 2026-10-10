import sys
from PIL import Image, ImageDraw
src, out_dir = sys.argv[1], sys.argv[2]
im = Image.open(src).convert('RGB')
W, H = im.size
px = im.load()
RED = (198, 40, 40)  # #C62828
for y in range(H):
    for x in range(285, W):
        r, g, b = px[x, y]
        if b > r + 25:  # bluish text pixel (incl. anti-aliasing)
            a = max(0.0, min(1.0, (255 - r) / 220))
            px[x, y] = tuple(int(255 * (1 - a) + c * a) for c in RED)
im.save(f'{out_dir}/myrepairco-logo-red.png')
# transparent version: flood fill near-white background from the edges
rgba = im.convert('RGBA')
mask = Image.new('L', (W, H), 0)
seed = Image.new('RGB', (W + 2, H + 2), (255, 255, 255)); seed.paste(im, (1, 1))
ImageDraw.floodfill(seed, (0, 0), (255, 0, 255), thresh=40)
sp = seed.load(); ap = rgba.load()
for y in range(H):
    for x in range(W):
        if sp[x + 1, y + 1] == (255, 0, 255):
            r, g, b, _ = ap[x, y]
            ap[x, y] = (r, g, b, 0)
rgba.save(f'{out_dir}/myrepairco-logo-red-transparent.png')
rgba.crop((0, 0, 285, H)).save(f'{out_dir}/myrepairco-mascot.png')
