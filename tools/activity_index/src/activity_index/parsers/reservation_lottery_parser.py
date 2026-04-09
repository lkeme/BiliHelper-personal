from __future__ import annotations

import re
from datetime import datetime

from activity_index.models.article import SpaceArticle
from activity_index.models.reservation_lottery import build_reservation_lottery_record
from activity_index.parsers.common import extract_draw_time, extract_last_prize, normalize_article_text, resolve_update_time

UP_MID_PATTERN = re.compile(r"space\.bilibili\.com/(\d+)")
SID_PATTERN = re.compile(r"business_id=(\d+)")


def parse_reservation_lottery(payload: dict[str, object]) -> dict[str, object]:
    record = build_reservation_lottery_record()
    record.update(payload)
    record["update_time"] = resolve_update_time(payload)
    return record


def parse_reservation_article(article: SpaceArticle) -> list[dict[str, object]]:
    text = normalize_article_text(article.content or article.summary)
    matches = list(UP_MID_PATTERN.finditer(text))
    if not matches:
        return []

    draw_time = extract_draw_time(article.source_title)
    records: list[dict[str, object]] = []

    for index, match in enumerate(matches):
        previous_end = matches[index - 1].end() if index > 0 else 0
        next_start = matches[index + 1].start() if index + 1 < len(matches) else len(text)
        before_segment = text[previous_end:match.start()]
        after_segment = text[match.end():next_start]
        up_mid = match.group(1)
        sid_match = SID_PATTERN.search(after_segment)
        sid = sid_match.group(1) if sid_match else ""
        if sid == "":
            continue

        record = build_reservation_lottery_record()
        record.update(
            {
                "title": article.source_title,
                "source_cv_id": article.cv_id,
                "source_url": article.source_url,
                "publish_time": article.publish_time.strftime("%Y-%m-%d %H:%M:%S"),
                "draw_time": draw_time,
                "up_mid": up_mid,
                "up_name": "",
                "sid": sid,
                "name": article.source_title,
                "jump_url": f"https://space.bilibili.com/{up_mid}",
                "text": extract_last_prize(before_segment),
                "requires_follow": False,
                "requires_reserve": True,
                "update_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            }
        )
        records.append(record)

    return records
