from __future__ import annotations

from typing import Any

REQUIRED_FIELDS: dict[str, tuple[str, ...]] = {
    "activity_lottery_infos.json": ("title", "url", "source_cv_id", "source_url", "publish_time", "update_time"),
    "interactive_lottery_infos.json": (
        "title",
        "dynamic_id",
        "dynamic_url",
        "source_cv_id",
        "source_url",
        "publish_time",
        "update_time",
    ),
    "reservation_lottery_infos.json": (
        "title",
        "up_mid",
        "sid",
        "source_cv_id",
        "source_url",
        "publish_time",
        "update_time",
    ),
    "charge_lottery_infos.json": ("title", "source_cv_id", "source_url", "publish_time", "update_time"),
}


def validate_payload(filename: str, payload: dict[str, Any] | None) -> tuple[bool, str]:
    if not isinstance(payload, dict):
        return False, "payload is not an object"

    if not isinstance(payload.get("code"), int):
        return False, "payload.code must be int"

    if not isinstance(payload.get("remarks"), str):
        return False, "payload.remarks must be string"

    data = payload.get("data")
    if not isinstance(data, list):
        return False, "payload.data must be list"

    required_fields = REQUIRED_FIELDS.get(filename, ())
    for index, item in enumerate(data):
        if not isinstance(item, dict):
            return False, f"payload.data[{index}] must be object"

        for field in required_fields:
            value = item.get(field)
            if not isinstance(value, str) or value.strip() == "":
                return False, f"payload.data[{index}].{field} is required"

    return True, ""
