from datetime import datetime
import re

from activity_index.models.article import SpaceArticle
from activity_index.models.charge_lottery import build_charge_lottery_record


def parse_charge_lottery(payload: dict[str, object]) -> dict[str, object]:
    record = build_charge_lottery_record()
    record.update(payload)
    record["update_time"] = _resolve_update_time(payload)
    return record


def _resolve_update_time(payload: dict[str, object]) -> str:
    update_time = str(payload.get("update_time", "")).strip()
    if update_time != "":
        return update_time

    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def parse_charge_article(article: SpaceArticle) -> list[dict[str, object]]:
    record = build_charge_lottery_record()
    record.update(
        {
            "title": article.source_title,
            "source_cv_id": article.cv_id,
            "source_url": article.source_url,
            "publish_time": article.publish_time.strftime("%Y-%m-%d %H:%M:%S"),
            "draw_time": _extract_draw_time(article.source_title),
            "prize_summary": _extract_first_prize(article.content or article.summary),
            "requires_charge": True,
            "update_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        }
    )
    return [record]


def _extract_draw_time(title: str) -> str:
    match = re.search(r"(\d{4}-\d{2}-\d{2})开奖", title)
    if match is None:
        return ""

    return match.group(1) + " 00:00:00"


def _extract_first_prize(text: str) -> str:
    match = re.search(r"奖品：([^<\n]+)", text)
    if match is None:
        return ""

    return match.group(1).strip()
