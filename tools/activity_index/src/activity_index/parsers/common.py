from __future__ import annotations

import html
import re
from datetime import datetime

DRAW_DATE_PATTERN = re.compile(r"(20\d{2})[-/年](\d{1,2})[-/月](\d{1,2})")
DRAW_TIME_PATTERN = re.compile(r"(20\d{2})[-/年](\d{1,2})[-/月](\d{1,2})\s*(?:开奖|开奖日)")
PRIZE_LINE_PATTERN = re.compile(r"(?:奖品|奖项)[:：]\s*(.+)")
TAG_BREAK_PATTERN = re.compile(r"<\s*(?:br|/p|/div|/li|li|p|div)[^>]*>", re.IGNORECASE)
TAG_PATTERN = re.compile(r"<[^>]+>")
SPACE_PATTERN = re.compile(r"[ \t\u3000]+")
PRIZE_MARKER_PATTERN = re.compile(r"(?:奖品|奖项)[:：]")
URL_PATTERN = re.compile(r"https?://\S+")


def normalize_article_text(text: str) -> str:
    if text.strip() == "":
        return ""

    normalized = html.unescape(text)
    normalized = TAG_BREAK_PATTERN.sub("\n", normalized)
    normalized = TAG_PATTERN.sub(" ", normalized)
    normalized = normalized.replace("\r\n", "\n").replace("\r", "\n")

    lines: list[str] = []
    for raw_line in normalized.split("\n"):
        line = SPACE_PATTERN.sub(" ", raw_line).strip()
        if line:
            lines.append(line)

    return "\n".join(lines)


def resolve_update_time(payload: dict[str, object]) -> str:
    update_time = str(payload.get("update_time", "")).strip()
    if update_time != "":
        return update_time

    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def extract_draw_time(title: str) -> str:
    match = DRAW_TIME_PATTERN.search(title)
    if match is None:
        return ""

    year, month, day = (int(match.group(index)) for index in range(1, 4))
    return datetime(year, month, day).strftime("%Y-%m-%d 00:00:00")


def extract_end_of_day_timestamp(text: str) -> int:
    match = DRAW_DATE_PATTERN.search(text)
    if match is None:
        return 0

    year, month, day = (int(match.group(index)) for index in range(1, 4))
    return int(datetime(year, month, day, 23, 59, 59).timestamp())


def extract_last_prize(segment: str) -> str:
    markers = list(PRIZE_MARKER_PATTERN.finditer(segment))
    if not markers:
        return ""

    prize = segment[markers[-1].end():]
    return _clean_prize_text(prize)


def extract_all_prizes(text: str) -> list[str]:
    markers = list(PRIZE_MARKER_PATTERN.finditer(text))
    if not markers:
        return []

    prizes: list[str] = []
    for index, marker in enumerate(markers):
        start = marker.end()
        end = markers[index + 1].start() if index + 1 < len(markers) else len(text)
        cleaned = _clean_prize_text(text[start:end])
        if cleaned and cleaned not in prizes:
            prizes.append(cleaned)

    return prizes


def _clean_prize_text(text: str) -> str:
    cleaned = URL_PATTERN.sub("", text)
    cleaned = re.sub(r"https?://", "", cleaned)
    cleaned = re.sub(r"business_id=\d+", "", cleaned)
    cleaned = re.sub(r"(?:动态|链接|地址)[:：]", "", cleaned)
    cleaned = re.sub(r"^[\[\]0-9、. ]+", "", cleaned)
    cleaned = SPACE_PATTERN.sub(" ", cleaned)
    return cleaned.strip(" ：:|")
