#!/usr/bin/env python3
"""
Extract mascots from a composite image and export 1024x1024 PNGs with transparent backgrounds.
Usage: python tools/extract_mascots.py path/to/mascots-composite.png output_dir
"""
import sys
import os
from pathlib import Path
import cv2
import numpy as np
from PIL import Image

NAMES = ['orbit','jetto','nomio','trailix','guidee','voyaj','paki','roami','dasho','lumi']


def make_transparent_crop(img, mask):
    # img may already include alpha from the composite image.
    if img.shape[2] == 4:
        b, g, r, a = cv2.split(img)
        # Keep any existing alpha, but strengthen it with our mask so edges stay soft.
        alpha = cv2.max(a, mask)
    else:
        b, g, r = cv2.split(img)
        alpha = mask
    rgba = cv2.merge([r, g, b, alpha])
    return rgba


def remove_bg_panel(rgba):
    # Use GrabCut to isolate the mascot from the square backdrop panel.
    bgr = rgba[:, :, :3].copy()
    alpha = rgba[:, :, 3].copy()
    h, w = alpha.shape

    mask = np.full((h, w), cv2.GC_PR_BGD, dtype=np.uint8)
    border = max(8, int(min(h, w) * 0.12))
    mask[:border, :] = cv2.GC_BGD
    mask[-border:, :] = cv2.GC_BGD
    mask[:, :border] = cv2.GC_BGD
    mask[:, -border:] = cv2.GC_BGD

    fg_w = int(w * 0.56)
    fg_h = int(h * 0.72)
    fg_mask = np.zeros((h, w), dtype=np.uint8)
    cv2.ellipse(fg_mask, (w // 2, h // 2), (max(10, fg_w // 2), max(10, fg_h // 2)), 0, 0, 360, 255, -1)
    mask[fg_mask > 0] = cv2.GC_PR_FGD

    bgd_model = np.zeros((1, 65), np.float64)
    fgd_model = np.zeros((1, 65), np.float64)
    cv2.grabCut(bgr, mask, None, bgd_model, fgd_model, 5, cv2.GC_INIT_WITH_MASK)

    subject = np.where((mask == cv2.GC_FGD) | (mask == cv2.GC_PR_FGD), 255, 0).astype(np.uint8)
    alpha = cv2.min(alpha, subject)

    # Clean the outer fringe and keep the silhouette crisp.
    alpha = cv2.morphologyEx(alpha, cv2.MORPH_OPEN, np.ones((3, 3), np.uint8), iterations=1)
    alpha = cv2.GaussianBlur(alpha, (3, 3), 0)

    return cv2.merge([bgr[:, :, 2], bgr[:, :, 1], bgr[:, :, 0], alpha])


def ensure_dir(path):
    Path(path).mkdir(parents=True, exist_ok=True)


def main():
    if len(sys.argv) < 3:
        print("Usage: python tools/extract_mascots.py composite.png out_dir")
        sys.exit(1)
    in_path = Path(sys.argv[1])
    out_dir = Path(sys.argv[2])
    ensure_dir(out_dir)

    if not in_path.exists():
        print(f"Input not found: {in_path}")
        sys.exit(2)

    img = cv2.imread(str(in_path), cv2.IMREAD_UNCHANGED)
    if img is None:
        print("Failed to read image.")
        sys.exit(3)

    h, w = img.shape[:2]
    print(f"Loaded {in_path} ({w}x{h})")

    source_bgr = img if img.shape[2] == 3 else img[:, :, :3]
    gray = cv2.cvtColor(source_bgr, cv2.COLOR_BGR2GRAY)
    # Increase contrast
    gray = cv2.equalizeHist(gray)

    # The composite is a clean 5x2 sheet; use fixed regions to avoid grabbing labels or neighbors.
    regions = [
        ('orbit',   (0,   0, 220, 170)),
        ('jetto',   (160, 0, 390, 170)),
        ('nomio',   (350, 0, 580, 170)),
        ('trailix', (525, 0, 760, 170)),
        ('guidee',  (730, 0, 952, 170)),
        ('voyaj',   (0,  250, 215, 465)),
        ('paki',    (165,250, 395, 465)),
        ('roami',   (345,250, 590, 465)),
        ('dasho',   (530,250, 780, 465)),
        ('lumi',    (730,250, 952, 465)),
    ]

    # First pass: find mascot bounds inside each region.
    boxes = []
    crops = []
    for name, (x0, y0, x1, y1) in regions:
        crop = img[y0:y1, x0:x1].copy()
        crop_source = crop if crop.shape[2] == 3 else crop[:, :, :3]
        crop_gray = cv2.cvtColor(crop_source, cv2.COLOR_BGR2GRAY)

        # Dark background, bright mascots: detect the foreground and soften the mask.
        blur = cv2.GaussianBlur(crop_gray, (7, 7), 0)
        _, m = cv2.threshold(blur, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        if np.mean(crop_gray[:10, :10]) > 180:
            m = 255 - m
        kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5))
        m = cv2.morphologyEx(m, cv2.MORPH_CLOSE, kernel, iterations=2)
        m = cv2.morphologyEx(m, cv2.MORPH_OPEN, kernel, iterations=1)
        m = cv2.GaussianBlur(m, (9, 9), 0)
        m = np.clip(m, 0, 255).astype(np.uint8)

        contours, _ = cv2.findContours(m, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        if contours:
            c = max(contours, key=cv2.contourArea)
            bx, by, bw, bh = cv2.boundingRect(c)
        else:
            bx, by, bw, bh = 0, 0, crop.shape[1], crop.shape[0]

        # Expand slightly to preserve glow/shadows.
        pad = int(max(bw, bh) * 0.18)
        bx0 = max(0, bx - pad)
        by0 = max(0, by - pad)
        bx1 = min(crop.shape[1], bx + bw + pad)
        by1 = min(crop.shape[0], by + bh + pad)

        boxes.append((name, (bx0, by0, bx1, by1), crop, m))

    widths = [b[1][2] - b[1][0] for b in boxes]
    heights = [b[1][3] - b[1][1] for b in boxes]
    max_dim = max(max(widths), max(heights))
    print(f"Max bbox dim: {max_dim}")

    target_inner = int(1024 * 0.74)
    scale_factor = target_inner / max_dim if max_dim > 0 else 1.0
    print(f"Scale factor to target inner: {scale_factor:.3f}")

    exports = []
    for idx, (name, box, crop, mask) in enumerate(boxes):
        bx0, by0, bx1, by1 = box
        tight_crop = crop[by0:by1, bx0:bx1].copy()
        tight_mask = mask[by0:by1, bx0:bx1].copy()

        rgba = make_transparent_crop(tight_crop, tight_mask)
        rgba = remove_bg_panel(rgba)

        h_c, w_c = rgba.shape[:2]
        new_w = max(1, int(w_c * scale_factor))
        new_h = max(1, int(h_c * scale_factor))
        max_allowed = 1024 - 120
        if max(new_w, new_h) > max_allowed:
            fit = max_allowed / max(new_w, new_h)
            new_w = max(1, int(new_w * fit))
            new_h = max(1, int(new_h * fit))
        rgba_resized = cv2.resize(rgba, (new_w, new_h), interpolation=cv2.INTER_AREA)

        canvas = np.zeros((1024, 1024, 4), dtype=np.uint8)
        cx = (1024 - new_w) // 2
        cy = (1024 - new_h) // 2
        canvas[cy:cy + new_h, cx:cx + new_w] = rgba_resized

        out_path = out_dir / f"{name}.png"
        Image.fromarray(canvas).save(str(out_path))
        print(f"Saved {out_path} (cropped size {w_c}x{h_c} -> {new_w}x{new_h})")
        exports.append(out_path)

    # Create preview sheet
    cols = 5
    thumb = 360
    sheet_w = cols * thumb
    rows = (len(exports)+cols-1)//cols
    sheet_h = rows * thumb
    sheet = Image.new('RGBA', (sheet_w, sheet_h), (0,0,0,0))
    for idx, p in enumerate(exports):
        im = Image.open(p).convert('RGBA')
        im_thumb = im.resize((thumb, thumb), Image.LANCZOS)
        x = (idx % cols) * thumb
        y = (idx // cols) * thumb
        sheet.paste(im_thumb, (x,y), im_thumb)
    preview_path = out_dir / 'preview_sheet.png'
    sheet.save(str(preview_path))
    print(f"Saved preview sheet: {preview_path}")

if __name__ == '__main__':
    main()
