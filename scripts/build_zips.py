#!/usr/bin/env python3
"""Build WordPress-safe plugin ZIPs with forward-slash paths."""

from __future__ import annotations

import pathlib
import tempfile
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "dist"
EXTERNAL = pathlib.Path(tempfile.gettempdir()) / "knd-sync-dist"


def normalize_lf(path: pathlib.Path) -> None:
    if path.suffix.lower() not in {".php", ".js", ".css", ".txt", ".md"}:
        return
    data = path.read_bytes()
    if data.startswith(b"\xef\xbb\xbf"):
        data = data[3:]
    text = data.decode("utf-8").replace("\r\n", "\n").replace("\r", "\n")
    path.write_bytes(text.encode("utf-8"))


def build(plugin_name: str) -> None:
    src = ROOT / plugin_name
    for path in src.rglob("*"):
        if path.is_file() and "tests" not in path.parts:
            normalize_lf(path)

    OUT_DIR.mkdir(exist_ok=True)
    EXTERNAL.mkdir(exist_ok=True)

    for zip_path in (OUT_DIR / f"{plugin_name}.zip", EXTERNAL / f"{plugin_name}.zip"):
        if zip_path.exists():
            zip_path.unlink()

        with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
            for file in sorted(src.rglob("*")):
                if not file.is_file() or "tests" in file.parts:
                    continue
                rel = file.relative_to(src).as_posix()
                arcname = f"{plugin_name}/{rel}"
                info = zipfile.ZipInfo(filename=arcname)
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = 0o644 << 16
                zf.writestr(info, file.read_bytes())

        with zipfile.ZipFile(zip_path, "r") as zf:
            names = zf.namelist()
            assert all("\\" not in name for name in names), names
            main = f"{plugin_name}/{plugin_name}.php"
            assert main in names, names
            data = zf.read(main)
            assert data.startswith(b"<?php"), data[:20]
            assert b"Plugin Name:" in data[:4096]
            print(f"OK {zip_path} ({zip_path.stat().st_size} bytes) entries={len(names)}")


if __name__ == "__main__":
    build("knd-sync-receiver")
    build("knd-sync-sender")
    print("EXTERNAL_DIR", EXTERNAL)
