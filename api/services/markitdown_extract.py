"""
CLI wrapper for MarkItDown extraction.
Usage: python markitdown_extract.py <file_path_or_url>
Outputs JSON to stdout: {"success": true, "text": "..."} or {"success": false, "error": "..."}
"""
import sys
import json

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No file path or URL provided"}))
        sys.exit(1)

    source = sys.argv[1]

    try:
        from markitdown import MarkItDown
        md = MarkItDown()
        result = md.convert(source)
        text = result.text_content or ""
        print(json.dumps({"success": True, "text": text}, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}, ensure_ascii=False))
        sys.exit(1)

if __name__ == "__main__":
    main()
