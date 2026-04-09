from __future__ import annotations

import re
from datetime import datetime

from activity_index.models.article import SpaceArticle
from activity_index.models.interactive_lottery import build_interactive_lottery_record
from activity_index.parsers.common import extract_draw_time, extract_last_prize, normalize_article_text, resolve_update_time

DYNAMIC_URL_PATTERN = re.compile(r"https://t\.bilibili\.com/\d+")


def parse_interactive_lottery(payload: dict[str, object]) -> dict[str, object]:
    record = build_interactive_lottery_record()
    record.update(payload)
    record["update_time"] = resolve_update_time(payload)
    return record


def parse_interactive_article(article: SpaceArticle) -> list[dict[str, object]]:
    text = normalize_article_text(article.content or article.summary)
    matches = list(DYNAMIC_URL_PATTERN.finditer(text))
    if not matches:
        return []

    draw_time = extract_draw_time(article.source_title)
    records: list[dict[str, object]] = []

    for index, match in enumerate(matches):
        previous_end = matches[index - 1].end() if index > 0 else 0
        segment = text[previous_end:match.start()]
        dynamic_url = match.group(0)
        dynamic_id = dynamic_url.rstrip("/").split("/")[-1]

        record = build_interactive_lottery_record()
        record.update(
            {
                "title": article.source_title,
                "source_cv_id": article.cv_id,
                "source_url": article.source_url,
                "publish_time": article.publish_time.strftime("%Y-%m-%d %H:%M:%S"),
                "draw_time": draw_time,
                "dynamic_id": dynamic_id,
                "dynamic_url": dynamic_url,
                "reserve_rid": "",
                "up_mid": "",
                "requires_follow": False,
                "requires_repost": False,
                "requires_comment": False,
                "prize_summary": extract_last_prize(segment),
                "update_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            }
        )
        records.append(record)

    return records
