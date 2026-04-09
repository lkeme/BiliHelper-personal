from __future__ import annotations

import re
from datetime import datetime
from urllib.parse import parse_qs, urlparse, urlunparse

from activity_index.collectors.space_dynamic import extract_urls
from activity_index.models.article import SpaceArticle
from activity_index.models.activity_lottery import build_activity_lottery_record

ACTIVITY_URL_PATTERN = (
    r"https?://(?:www\.)?bilibili\.com/blackboard/era/[A-Za-z0-9_-]+(?:\.html)?(?:\?[^\s\"'<]*)?"
)
DATE_PATTERN = re.compile(r"(20\d{2})(?:-|/|\u5e74)(\d{1,2})(?:-|/|\u6708)(\d{1,2})")
ACTIVITY_ID_PATTERN = re.compile(
    r"(?:activity[_-]?id|act[_-]?id)[=:\uFF1A\s\"']*([A-Za-z0-9_-]+)",
    re.IGNORECASE,
)
LOTTERY_ID_PATTERN = re.compile(
    r"(?:lottery[_-]?id|sid)[=:\uFF1A\s\"']*([A-Za-z0-9_-]+)",
    re.IGNORECASE,
)


def parse_activity_lottery(payload: dict[str, object]) -> dict[str, object]:
    record = build_activity_lottery_record()
    record.update(payload)
    record["update_time"] = _resolve_update_time(payload)
    return record


def parse_activity_article(article: SpaceArticle) -> list[dict[str, object]]:
    text = article.content or article.summary
    activity_urls = _extract_activity_urls(text)
    if not activity_urls:
        return []

    records: list[dict[str, object]] = []
    for activity_url in activity_urls:
        record = build_activity_lottery_record()
        record.update(
            {
                "title": article.source_title,
                "url": activity_url,
                "page_id": _extract_page_id(activity_url),
                "activity_id": _extract_activity_id(text, activity_url),
                "lottery_id": _extract_lottery_id(text, activity_url),
                "start_time": int(article.publish_time.timestamp()),
                "end_time": _extract_end_time(article.source_title, text),
                "source_cv_id": article.cv_id,
                "source_url": article.source_url,
                "publish_time": article.publish_time.strftime("%Y-%m-%d %H:%M:%S"),
                "update_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            }
        )
        records.append(record)

    return records


def _resolve_update_time(payload: dict[str, object]) -> str:
    update_time = str(payload.get("update_time", "")).strip()
    if update_time != "":
        return update_time

    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def _extract_activity_urls(text: str) -> list[str]:
    normalized_urls: list[str] = []
    for raw_url in extract_urls(ACTIVITY_URL_PATTERN, text):
        normalized = _normalize_activity_url(raw_url)
        if normalized:
            normalized_urls.append(normalized)

    return normalized_urls


def _normalize_activity_url(url: str) -> str:
    parsed = urlparse(url.strip())
    if parsed.scheme not in {"http", "https"}:
        return ""
    if parsed.netloc not in {"www.bilibili.com", "bilibili.com"}:
        return ""
    if "/blackboard/era/" not in parsed.path:
        return ""

    clean_query = parse_qs(parsed.query, keep_blank_values=False)
    whitelisted_query = {
        key: values
        for key, values in clean_query.items()
        if key in {"activity_id", "act_id", "lottery_id", "sid"}
    }
    query = "&".join(
        f"{key}={value}"
        for key, values in whitelisted_query.items()
        for value in values
    )

    normalized = parsed._replace(
        scheme="https",
        netloc="www.bilibili.com",
        fragment="",
        query=query,
    )
    return urlunparse(normalized)


def _extract_page_id(url: str) -> str:
    path = urlparse(url).path.rstrip("/")
    if not path:
        return ""

    page = path.split("/")[-1]
    if page.endswith(".html"):
        page = page[:-5]

    return page.strip()


def _extract_activity_id(text: str, url: str) -> str:
    query = parse_qs(urlparse(url).query, keep_blank_values=False)
    for key in ("activity_id", "act_id"):
        values = query.get(key)
        if values:
            return values[0].strip()

    match = ACTIVITY_ID_PATTERN.search(text)
    return match.group(1).strip() if match else ""


def _extract_lottery_id(text: str, url: str) -> str:
    query = parse_qs(urlparse(url).query, keep_blank_values=False)
    for key in ("lottery_id", "sid"):
        values = query.get(key)
        if values:
            return values[0].strip()

    match = LOTTERY_ID_PATTERN.search(text)
    return match.group(1).strip() if match else ""


def _extract_end_time(title: str, text: str) -> int:
    source = f"{title}\n{text}"
    match = DATE_PATTERN.search(source)
    if match is None:
        return 0

    year, month, day = (int(match.group(index)) for index in range(1, 4))
    return int(datetime(year, month, day, 23, 59, 59).timestamp())
