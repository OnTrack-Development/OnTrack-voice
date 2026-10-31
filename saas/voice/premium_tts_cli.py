#!/usr/bin/env python3
"""Offline Chatterbox Multilingual Arabic voice-presets adapter (development only).
Usage:
  python3 premium_tts_cli.py --voice calm --asset-private-dir /private/voices --verify-only
  python3 premium_tts_cli.py --voice calm --asset-private-dir /private/voices \
      --model-dir /private/models/chatterbox-mtl --text "أهلاً بحضرتك" \
      --out /private/output/test.wav
Loads only hash-pinned PT voice files and locally supplied model weights, with no network.
Never invoke from an HTTP request handler; run on an isolated TTS worker with quotas.
"""
import argparse
import hashlib
import os
import pathlib
import sys

VOICES = {
    "calm": ("AYO-calm.pt", "10b536280f44431af3a42b2b3ae68ab0e60a44ca116c417b9a9ba9a12bbb8105"),
    "excited": ("AYO-excited.pt", "77b67c9d95dbf2ea8b50fa88778c1d83eb917a0e9d8eeb3be14f055f9b9e38cf"),
    "sleepy": ("AYO-sleepy.pt", "fef549851711566631866708f135cf3d512b55c3b98766431bd54742250aeb1e"),
}
MAX_ASSET_BYTES = 512_000
MAX_TEXT_LENGTH = 300

def checked_asset(root: str, voice: str) -> pathlib.Path:
    name, digest = VOICES[voice]
    folder = pathlib.Path(root).resolve(strict=True)
    target = folder / name
    if target.is_symlink() or not target.is_file() or target.resolve().parent != folder:
        raise ValueError("voice file not in private asset directory")
    if target.stat().st_size > MAX_ASSET_BYTES:
        raise ValueError("voice preset too large")
    if hashlib.sha256(target.read_bytes()).hexdigest() != digest:
        raise ValueError("voice preset SHA256 mismatch")
    return target

def synthesize(asset: pathlib.Path, model_dir: str, text: str, out: str, device: str) -> None:
    if not text.strip() or len(text) > MAX_TEXT_LENGTH:
        raise ValueError("text length out of range")
    if device not in ("cpu", "cuda", "mps"):
        raise ValueError("unsupported device")
    # No untrusted pickle code execution: Chatterbox's Conditionals.load uses weights_only=True.
    # No network/model download: from_local requires vetted weights in an existing directory.
    from chatterbox.mtl_tts import ChatterboxMultilingualTTS, Conditionals
    import soundfile as sf
    model_path = pathlib.Path(model_dir).resolve(strict=True)
    if not model_path.is_dir():
        raise ValueError("model directory unavailable")
    model = ChatterboxMultilingualTTS.from_local(model_path, device=device)
    model.conds = Conditionals.load(asset, map_location="cpu").to(device)
    audio = model.generate(text, language_id="ar")
    output = pathlib.Path(out)
    output.parent.resolve(strict=True)
    if output.is_symlink() or output.suffix.lower() != ".wav":
        raise ValueError("output must be a private WAV file")
    with open(output, "xb") as binary:
        sf.write(binary, audio.squeeze().detach().cpu().numpy(), model.sr, format="WAV")

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--voice", required=True, choices=VOICES)
    parser.add_argument("--asset-private-dir", required=True)
    parser.add_argument("--verify-only", action="store_true")
    parser.add_argument("--model-dir")
    parser.add_argument("--text")
    parser.add_argument("--out")
    parser.add_argument("--device", default="cpu", choices=["cpu", "cuda", "mps"])
    args = parser.parse_args()
    asset = checked_asset(args.asset_private_dir, args.voice)
    if args.verify_only:
        print("AYO preset SHA256 verified:", args.voice)
        return
    if not args.model_dir or not args.text or not args.out:
        parser.error("--model-dir, --text and --out are required for synthesis")
    synthesize(asset, args.model_dir, args.text, args.out, args.device)
    print("Generated Arabic Chatterbox sample for offline QA only")

if __name__ == "__main__":
    main()
