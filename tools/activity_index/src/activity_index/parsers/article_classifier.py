TITLE_MATCHERS: tuple[tuple[tuple[str, ...], str], ...] = (
    (
        (
            "\u6d3b\u52a8\u62bd\u5956\u5706\u76d8\u62bd\u5956",
            "\u6d3b\u52a8\u62bd\u5956",
            "\u5706\u76d8\u62bd\u5956",
        ),
        "activity_lottery",
    ),
    (("\u4e92\u52a8\u62bd\u5956",), "interactive_lottery"),
    (("\u9884\u7ea6\u62bd\u5956",), "reservation_lottery"),
    (("\u5145\u7535\u62bd\u5956",), "charge_lottery"),
)


def classify_article_title(title: str) -> str | None:
    normalized = title.strip()
    if not normalized:
        return None

    for markers, article_type in TITLE_MATCHERS:
        if any(normalized.startswith(marker) or marker in normalized for marker in markers):
            return article_type

    return None
