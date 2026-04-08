from datetime import datetime, timedelta

from activity_index.models.article import SpaceArticle
from activity_index.parsers.article_classifier import classify_article_title


def filter_recent_classified_articles(
    articles: list[SpaceArticle],
    now: datetime,
    hours: int = 24,
) -> list[SpaceArticle]:
    threshold = now - timedelta(hours=hours)
    filtered: list[SpaceArticle] = []
    for article in articles:
        if article.publish_time < threshold:
            continue
        article.article_type = classify_article_title(article.source_title)
        if article.article_type is None:
            continue
        filtered.append(article)

    return filtered
