#!/usr/bin/env python3
"""One-time CC0 preview photo import, intentionally NOT called by web requests.

The resulting four small WebP assets are committed with the installer.
No user browsing history/IP address ever leaves the user's own server.
"""
from __future__ import annotations

from hashlib import md5
from io import BytesIO
from pathlib import Path
from urllib.parse import quote
from urllib.request import Request, urlopen
import time

from PIL import Image, ImageOps, UnidentifiedImageError

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "public" / "assets" / "install-demo"
SOURCES = {
    "mountains": "Mountains Landscape.jpg",
    "forest": "Landscape of a wild forest.jpg",
    "architecture": "Modern architecture build.jpg",
    "flowers": "Flowers, images.jpg",
}
USER_AGENT = "DendrilaCMS-PreviewImporter/1.0 (CC0 static image packaging; https://github.com/jasmin-abernathy/dendrila-cms)"
MAX_TRANSFER = 9_000_000
MAX_OUTPUT = 320_000


def commons_thumb(filename: str, width: int = 1200) -> str:
    # Wikimedia's documented upload bucket uses MD5(name) prefixes.
    key = md5(filename.replace(" ", "_").encode("utf-8")).hexdigest()
    name = quote(filename.replace(" ", "_"))
    return f"https://upload.wikimedia.org/wikipedia/commons/thumb/{key[0]}/{key[:2]}/{name}/{width}px-{name}"


def fetch_image(filename: str) -> bytes:
    candidate_urls = [
        commons_thumb(filename),
        "https://commons.wikimedia.org/wiki/Special:Redirect/file/"
        + quote(filename.replace(" ", "_")) + "?width=1200",
    ]
    failure = None
    for url in candidate_urls:
        for attempt in range(3):
            try:
                request = Request(url, headers={"User-Agent": USER_AGENT, "Accept": "image/jpeg,image/*"})
                with urlopen(request, timeout=30) as response:
                    payload = response.read(MAX_TRANSFER + 1)
                    if len(payload) > MAX_TRANSFER or len(payload) < 1000:
                        raise ValueError("unexpected image size")
                    content_type = response.headers.get("Content-Type", "").lower()
                    if "image/" not in content_type:
                        raise ValueError("unexpected media type")
                    return payload
            except Exception as exc:
                failure = exc
                if attempt < 2:
                    time.sleep(2 * (attempt + 1))
    raise RuntimeError("Cannot retrieve Wikimedia Commons CC0 image: " + filename) from failure


def process(payload: bytes) -> bytes:
    try:
        with Image.open(BytesIO(payload)) as original:
            if original.format not in ("JPEG", "PNG", "WEBP"):
                raise ValueError("unexpected image format")
            if original.width * original.height > 45_000_000:
                raise ValueError("image too large to decode")
            image = ImageOps.exif_transpose(original).convert("RGB")
            image.thumbnail((1200, 900), Image.Resampling.LANCZOS)
            quality = 76
            while quality >= 56:
                buffer = BytesIO()
                image.save(buffer, format="WEBP", quality=quality, method=5)
                output = buffer.getvalue()
                if len(output) <= MAX_OUTPUT:
                    return output
                quality -= 5
    except (OSError, UnidentifiedImageError) as exc:
        raise ValueError("malformed image") from exc
    raise ValueError("WebP still too large")


def main() -> None:
    OUTPUT.mkdir(parents=True, exist_ok=True)
    for stem, filename in SOURCES.items():
        destination = OUTPUT / (stem + ".webp")
        if destination.is_file() and destination.stat().st_size > 1024:
            print("KEEP", destination.relative_to(ROOT))
            continue
        data = process(fetch_image(filename))
        destination.write_bytes(data)
        print("CREATED", destination.relative_to(ROOT), len(data), "bytes")


if __name__ == "__main__":
    main()
