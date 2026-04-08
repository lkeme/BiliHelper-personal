from datetime import datetime
import re

from activity_index.models.article import SpaceArticle
from activity_index.models.reservation_lottery import build_reservation_lottery_record


def parse_reservation_lottery(payload: dict[str, object]) -> dict[str, object]:
    record = build_reservation_lottery_record()
    record.update(payload)
    record["update_time"] = _resolve_update_time(payload)
    return record


def _resolve_update_time(payload: dict[str, object]) -> str:
    update_time = str(payload.get("update_time", "")).strip()
    if update_time != "":
        return update_time

    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def parse_reservation_article(article: SpaceArticle) -> list[dict[str, object]]:
    text = article.content or article.summary
    draw_time = _extract_draw_time(article.source_title)
    up_mids = re.findall(r"space\.bilibili\.com/(\d+)", text)
    sids = re.findall(r"business_id=(\d+)", text)
    prizes = [value.strip() for value in re.findall(r"奖品：([^<\n]+)", text)]

    records: list[dict[str, object]] = []
    for index, up_mid in enumerate(up_mids):
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
                "sid": sids[index] if index < len(sids) else "",
                "name": article.source_title,
                "jump_url": f"https://space.bilibili.com/{up_mid}",
                "text": prizes[index] if index < len(prizes) else "",
                "requires_follow": False,
                "requires_reserve": True,
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
