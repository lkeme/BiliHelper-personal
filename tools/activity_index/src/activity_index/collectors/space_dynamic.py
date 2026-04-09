from __future__ import annotations

import re
import urllib.parse
from datetime import datetime, timedelta

from activity_index.core import ActivityIndexLogger, HttpClient, get_logger
from activity_index.models.article import SpaceArticle
from activity_index.parsers.article_classifier import classify_article_title


class SpaceArticleCollector:
    def __init__(self, logger: ActivityIndexLogger | None = None, client: HttpClient | None = None) -> None:
        self.logger = logger or get_logger()
        self.client = client or HttpClient(self.logger)
        self.raw_article_count = 0
        self.skipped_older_than_threshold = 0
        self.skipped_unsupported_type = 0
        self.skipped_missing_cv_id = 0
        self.detail_fetch_failures = 0
        self.detail_fetch_successes = 0

    def collect_recent_articles(
        self,
        host_mid: str,
        hours: int = 24,
        limit: int = 20,
        fetch_detail_types: set[str] | None = None,
    ) -> list[SpaceArticle]:
        raw_articles = self._fetch_article_list(host_mid, page=1, page_size=limit)
        self.raw_article_count = len(raw_articles)
        threshold = datetime.now() - timedelta(hours=hours)

        collected: list[SpaceArticle] = []
        for item in raw_articles:
            publish_time = datetime.fromtimestamp(int(item.get("publish_time", 0)))
            if publish_time < threshold:
                self.skipped_older_than_threshold += 1
                self.logger.debug("article skipped because it is older than threshold", publish_time=publish_time.isoformat())
                continue

            title = str(item.get("title", "")).strip()
            article_type = classify_article_title(title)
            if article_type is None:
                self.skipped_unsupported_type += 1
                self.logger.debug("article skipped because no supported type matched", title=title)
                continue

            cv_id = str(item.get("id", "")).strip()
            if cv_id == "":
                self.skipped_missing_cv_id += 1
                self.logger.debug("article skipped because cv_id is empty", title=title)
                continue

            details = {
                "summary": str(item.get("summary", "")).strip(),
                "content": "",
                "dyn_id_str": "",
            }
            if fetch_detail_types is None or article_type in fetch_detail_types:
                fetched = self._fetch_article_details(cv_id)
                if fetched.get("summary"):
                    details["summary"] = fetched["summary"]
                if fetched.get("content"):
                    details["content"] = fetched["content"]
                elif article_type in {"activity_lottery", "interactive_lottery", "reservation_lottery", "charge_lottery"}:
                    fallback_content = self._fetch_article_page_html(cv_id)
                    if fallback_content != "":
                        details["content"] = fallback_content
                if fetched.get("dyn_id_str"):
                    details["dyn_id_str"] = fetched["dyn_id_str"]

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

        self.logger.info("article collection finished", collected=len(collected))
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
        payload = self.client.get_json(
            f"https://api.bilibili.com/x/space/article?{query}",
            headers={
                "Origin": "https://space.bilibili.com",
                "Referer": f"https://space.bilibili.com/{host_mid}/",
            },
            context="space.article_list",
        )
        if payload.get("code") != 0:
            self.logger.warning("article list API returned non-zero code", code=payload.get("code"))
            return []

        articles = payload.get("data", {}).get("articles", [])
        return [item for item in articles if isinstance(item, dict)]

    def _fetch_article_details(self, cv_id: str) -> dict[str, str]:
        try:
            payload = self.client.get_json(
                f"https://api.bilibili.com/x/article/view?id={cv_id}",
                headers={
                    "Origin": "https://www.bilibili.com",
                    "Referer": f"https://www.bilibili.com/read/cv{cv_id}/",
                },
                context="space.article_detail",
            )
        except Exception as exc:
            self.logger.warning(
                "article detail request failed, falling back to summary only",
                cv_id=cv_id,
                error=type(exc).__name__,
            )
            return {"summary": "", "content": "", "dyn_id_str": ""}

        if payload.get("code") == 0 and isinstance(payload.get("data"), dict):
            self.detail_fetch_successes += 1
            article = payload["data"]
            return {
                "summary": str(article.get("summary", "")).strip(),
                "content": str(article.get("content", "")).strip(),
                "dyn_id_str": str(article.get("dyn_id_str", "")).strip(),
            }

        self.detail_fetch_failures += 1
        self.logger.warning(
            "article detail API returned non-zero code",
            cv_id=cv_id,
            code=payload.get("code"),
        )
        return {"summary": "", "content": "", "dyn_id_str": ""}

    def _fetch_article_page_html(self, cv_id: str) -> str:
        try:
            return self.client.get_text(
                f"https://www.bilibili.com/read/cv{cv_id}/",
                headers={
                    "Origin": "https://www.bilibili.com",
                    "Referer": f"https://www.bilibili.com/read/cv{cv_id}/",
                },
                context="space.article_page",
            )
        except Exception as exc:
            self.logger.warning(
                "article page fallback request failed",
                cv_id=cv_id,
                error=type(exc).__name__,
            )
            return ""

    def diagnostics(self, collected_count: int) -> dict[str, int]:
        return {
            "raw_article_count": self.raw_article_count,
            "collected_count": collected_count,
            "skipped_older_than_threshold": self.skipped_older_than_threshold,
            "skipped_unsupported_type": self.skipped_unsupported_type,
            "skipped_missing_cv_id": self.skipped_missing_cv_id,
            "detail_fetch_successes": self.detail_fetch_successes,
            "detail_fetch_failures": self.detail_fetch_failures,
        }


def extract_urls(pattern: str, text: str) -> list[str]:
    return list(dict.fromkeys(re.findall(pattern, text)))
