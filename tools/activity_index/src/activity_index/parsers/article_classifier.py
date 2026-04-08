TITLE_PREFIX_TO_TYPE: dict[str, str] = {
    "活动抽奖圆盘抽奖": "activity_lottery",
    "互动抽奖": "interactive_lottery",
    "预约抽奖": "reservation_lottery",
    "充电抽奖": "charge_lottery",
}


def classify_article_title(title: str) -> str | None:
    normalized = title.strip()
    if not normalized:
        return None

    for prefix, article_type in TITLE_PREFIX_TO_TYPE.items():
        if normalized.startswith(prefix):
            return article_type

    return None
