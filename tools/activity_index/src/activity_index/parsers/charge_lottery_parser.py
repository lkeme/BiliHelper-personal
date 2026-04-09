from __future__ import annotations

from datetime import datetime

from activity_index.models.article import SpaceArticle
from activity_index.models.charge_lottery import build_charge_lottery_record
from activity_index.parsers.common import extract_all_prizes, extract_draw_time, normalize_article_text, resolve_update_time


def parse_charge_lottery(payload: dict[str, object]) -> dict[str, object]:
    record = build_charge_lottery_record()
    record.update(payload)
    record["update_time"] = resolve_update_time(payload)
    return record


def parse_charge_article(article: SpaceArticle) -> list[dict[str, object]]:
    text = normalize_article_text(article.content or article.summary)
    prizes = extract_all_prizes(text)
    prize_summary = " | ".join(prizes[:3])

    record = build_charge_lottery_record()
    record.update(
        {
            "title": article.source_title,
            "source_cv_id": article.cv_id,
            "source_url": article.source_url,
            "publish_time": article.publish_time.strftime("%Y-%m-%d %H:%M:%S"),
            "draw_time": extract_draw_time(article.source_title),
            "prize_summary": prize_summary,
            "requires_charge": True,
            "update_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        }
    )
    return [record]
