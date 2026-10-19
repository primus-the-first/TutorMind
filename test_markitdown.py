import time
from markitdown import MarkItDown

md = MarkItDown()

PDF_PATH = r"C:\Users\Primus Aeternus\Desktop\NHP_12.07.2020.pdf-13072020-FINAL.pdf"
YOUTUBE_URL = "https://www.youtube.com/watch?v=Mg9Tr3qLXks"
OUT_DIR = r"c:\xampp\htdocs\TutorMind"

print("TEST 1: PDF extraction")
t0 = time.time()
try:
    result = md.convert(PDF_PATH)
    elapsed = time.time() - t0
    text = result.text_content
    out_path = OUT_DIR + r"\nhp_extracted.md"
    with open(out_path, "w", encoding="utf-8") as f:
        f.write(text)
    print(f"Time: {elapsed:.2f}s | Words: {len(text.split()):,} | Saved: {out_path}")
except Exception as e:
    print(f"ERROR: {e}")

print("TEST 2: YouTube transcript")
t0 = time.time()
try:
    result = md.convert(YOUTUBE_URL)
    elapsed = time.time() - t0
    text = result.text_content
    out_path = OUT_DIR + r"\youtube_transcript.md"
    with open(out_path, "w", encoding="utf-8") as f:
        f.write(text)
    print(f"Time: {elapsed:.2f}s | Words: {len(text.split()):,} | Saved: {out_path}")
except Exception as e:
    print(f"ERROR: {e}")
