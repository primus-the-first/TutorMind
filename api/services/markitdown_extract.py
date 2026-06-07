"""
CLI wrapper for MarkItDown extraction, with dedicated YouTube transcript support.
Usage: python markitdown_extract.py <file_path_or_url>
Outputs JSON to stdout: {"success": true, "text": "..."} or {"success": false, "error": "..."}
"""
import sys
import json
import re


def extract_youtube_id(url):
    m = re.search(r'(?:youtube\.com/watch\?.*v=|youtu\.be/)([a-zA-Z0-9_-]{11})', url)
    return m.group(1) if m else None


def get_youtube_transcript(video_id):
    """Returns (text, None) on success, (None, error_msg) on failure."""
    try:
        from youtube_transcript_api import YouTubeTranscriptApi
        try:
            segments = YouTubeTranscriptApi.get_transcript(video_id, languages=['en', 'en-US', 'en-GB'])
        except Exception:
            # Try any available language
            transcript_list = YouTubeTranscriptApi.list_transcripts(video_id)
            first = next(iter(transcript_list))
            segments = first.fetch()
        text = " ".join(seg['text'] for seg in segments)
        return text, None
    except Exception as e:
        return None, str(e)


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No file path or URL provided"}))
        sys.exit(1)

    source = sys.argv[1]

    yt_id = extract_youtube_id(source)
    if yt_id:
        text, err = get_youtube_transcript(yt_id)
        if text:
            print(json.dumps({"success": True, "text": text}, ensure_ascii=False))
        else:
            print(json.dumps({"success": False, "error": f"No transcript available: {err}"}, ensure_ascii=False))
        return

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
