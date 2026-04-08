from dataclasses import dataclass
from datetime import datetime


@dataclass(slots=True)
class SpaceArticle:
    cv_id: str
    source_url: str
    source_title: str
    publish_time: datetime
    summary: str = ""
    content: str = ""
    dyn_id_str: str = ""
    article_type: str | None = None
