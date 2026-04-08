from __future__ import annotations

import json
import os
import re
import time
import urllib.parse
import urllib.request
from datetime import datetime, timedelta

from activity_index.models.article import SpaceArticle
from activity_index.parsers.article_classifier import classify_article_title

DEFAULT_HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36",
}


class SpaceArticleCollector:
    def __init__(self, request_delay_seconds: float = 3.0, max_retries: int = 5) -> None:
        self.request_delay_seconds = request_delay_seconds
        self.max_retries = max_retries
        self.optional_cookie = os.getenv("BILI_COOKIE", "").strip()

    def collect_recent_articles(
        self,
        host_mid: str,
        hours: int = 24,
        limit: int = 20,
        fetch_detail_types: set[str] | None = None,
    ) -> list[SpaceArticle]:
        raw_articles = self._fetch_article_list(host_mid, page=1, page_size=limit)
        threshold = datetime.now() - timedelta(hours=hours)

        collected: list[SpaceArticle] = []
        for item in raw_articles:
            publish_time = datetime.fromtimestamp(int(item.get("publish_time", 0)))
            if publish_time < threshold:
                continue

            title = str(item.get("title", "")).strip()
            article_type = classify_article_title(title)
            if article_type is None:
                continue

            cv_id = str(item.get("id", "")).strip()
            if cv_id == "":
                continue

            details = {"summary": "", "content": "", "dyn_id_str": ""}
            if fetch_detail_types is None or article_type in fetch_detail_types:
                details = self._fetch_article_details(cv_id)
                time.sleep(self.request_delay_seconds)

            collected.append(
                SpaceArticle(
                    cv_id=cv_id,
                    source_url=f"https://www.bilibili.com/read/cv{cv_id}/",
                    source_title=title,
                    publish_time=publish_time,
                    summary=details.get("summary", ""),
                    content=details.get("content", ""),
                    dyn_id_str=details.get("dyn_id_str", ""),
                    article_type=article_type,
                )
            )

        return collected

    def _fetch_article_list(self, host_mid: str, page: int, page_size: int) -> list[dict[str, object]]:
        query = urllib.parse.urlencode(
            {
                "mid": host_mid,
                "pn": page,
                "ps": page_size,
                "sort": "publish_time",
            }
        )
        req = urllib.request.Request(
            f"https://api.bilibili.com/x/space/article?{query}",
            headers=self._build_headers(
                {
                    "Origin": "https://space.bilibili.com",
                    "Referer": f"https://space.bilibili.com/{host_mid}/",
                }
            ),
        )
        with urllib.request.urlopen(req) as resp:
            data = json.load(resp)

        if data.get("code") != 0:
            return []

        articles = data.get("data", {}).get("articles", [])
        return [item for item in articles if isinstance(item, dict)]

    def _fetch_article_details(self, cv_id: str) -> dict[str, str]:
        for attempt in range(self.max_retries):
            req = urllib.request.Request(
                f"https://api.bilibili.com/x/article/view?id={cv_id}",
                headers=self._build_headers(
                    {
                        "Origin": "https://www.bilibili.com",
                        "Referer": f"https://www.bilibili.com/read/cv{cv_id}/",
                    }
                ),
            )
            with urllib.request.urlopen(req) as resp:
                data = json.load(resp)

            if data.get("code") == 0 and isinstance(data.get("data"), dict):
                article = data["data"]
                return {
                    "summary": str(article.get("summary", "")).strip(),
                    "content": str(article.get("content", "")).strip(),
                    "dyn_id_str": str(article.get("dyn_id_str", "")).strip(),
                }

            if data.get("code") != -509 or attempt >= (self.max_retries - 1):
                break

            time.sleep(max(self.request_delay_seconds * (attempt + 1), 4.0))

        return {"summary": "", "content": "", "dyn_id_str": ""}

    def _build_headers(self, extra: dict[str, str]) -> dict[str, str]:
        headers = {**DEFAULT_HEADERS, **extra}
        if self.optional_cookie != "":
            headers["Cookie"] = self.optional_cookie

        return headers


def extract_urls(pattern: str, text: str) -> list[str]:
    return list(dict.fromkeys(re.findall(pattern, text)))
