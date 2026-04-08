from datetime import datetime
import re

from activity_index.collectors.space_dynamic import extract_urls
from activity_index.models.article import SpaceArticle
from activity_index.models.interactive_lottery import build_interactive_lottery_record


def parse_interactive_lottery(payload: dict[str, object]) -> dict[str, object]:
    record = build_interactive_lottery_record()
    record.update(payload)
    record["update_time"] = _resolve_update_time(payload)
    return record


def _resolve_update_time(payload: dict[str, object]) -> str:
    update_time = str(payload.get("update_time", "")).strip()
    if update_time != "":
        return update_time

    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def parse_interactive_article(article: SpaceArticle) -> list[dict[str, object]]:
    dynamic_urls = extract_urls(r"https://t\.bilibili\.com/\d+", article.content or article.summary)
    draw_time = _extract_draw_time(article.source_title)
    prize_summaries = _extract_prize_summaries(article.content or article.summary)

    records: list[dict[str, object]] = []
    for index, dynamic_url in enumerate(dynamic_urls):
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
                "prize_summary": prize_summaries[index] if index < len(prize_summaries) else "",
                "update_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            }
        )
        records.append(record)

    return records


def _extract_draw_time(title: str) -> str:
    match = re.search(r"(\d{4}-\d{2}-\d{2})开奖", title)
    if match is None:
        return ""

    return match.group(1) + " 00:00:00"


def _extract_prize_summaries(text: str) -> list[str]:
    return [value.strip() for value in re.findall(r"奖品：([^<\n]+)", text)]
