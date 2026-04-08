from dataclasses import dataclass
from datetime import datetime


@dataclass(slots=True)
class SpaceArticle:
    cv_id: str
    source_url: str
    source_title: str
    publish_time: datetime
    article_type: str | None = None
