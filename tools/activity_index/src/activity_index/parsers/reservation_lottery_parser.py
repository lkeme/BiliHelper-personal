from datetime import datetime

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
