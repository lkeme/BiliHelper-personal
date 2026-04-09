from pathlib import Path

from activity_index.core import PayloadGenerationResult, ResourceStore, get_logger
from activity_index.collectors.space_dynamic import SpaceArticleCollector
from activity_index.parsers.activity_lottery_parser import parse_activity_article
from activity_index.parsers.charge_lottery_parser import parse_charge_article
from activity_index.parsers.interactive_lottery_parser import parse_interactive_article
from activity_index.parsers.reservation_lottery_parser import parse_reservation_article
from activity_index.writers.json_writer import wrap_payload

TARGET_HOST_MID = "3461568884902878"
OUTPUT_FILENAMES = (
    "activity_lottery_infos.json",
    "interactive_lottery_infos.json",
    "reservation_lottery_infos.json",
    "charge_lottery_infos.json",
)


def build_payload_results(logger=None) -> list[PayloadGenerationResult]:
    logger = logger or get_logger()
    collector = SpaceArticleCollector(logger=logger)
    activity_records: list[dict[str, object]] = []
    interactive_records: list[dict[str, object]] = []
    reservation_records: list[dict[str, object]] = []
    charge_records: list[dict[str, object]] = []
    failed_types: set[str] = set()

    try:
        logger.info("collecting recent articles", host_mid=TARGET_HOST_MID)
        articles = collector.collect_recent_articles(
            TARGET_HOST_MID,
            hours=24,
            limit=20,
            fetch_detail_types={
                "activity_lottery",
                "interactive_lottery",
                "reservation_lottery",
                "charge_lottery",
            },
        )
    except Exception as exc:
        logger.error("collecting recent articles failed", error=type(exc).__name__, reason=str(exc))
        return [
            PayloadGenerationResult(filename=name, success=False, reason=f"article collection failed: {exc}")
            for name in OUTPUT_FILENAMES
        ]

    logger.info("recent articles collected", articles=len(articles))

    for article in articles:
        logger.debug(
            "article classified",
            cv_id=article.cv_id,
            article_type=article.article_type or "unknown",
            title=article.source_title,
        )

        try:
            if article.article_type == "activity_lottery":
                activity_records.extend(parse_activity_article(article))
            elif article.article_type == "interactive_lottery":
                interactive_records.extend(parse_interactive_article(article))
            elif article.article_type == "reservation_lottery":
                reservation_records.extend(parse_reservation_article(article))
            elif article.article_type == "charge_lottery":
                charge_records.extend(parse_charge_article(article))
        except Exception as exc:
            if article.article_type is not None:
                failed_types.add(article.article_type)
            logger.warning(
                "article parsing failed",
                cv_id=article.cv_id,
                article_type=article.article_type or "unknown",
                error=type(exc).__name__,
                reason=str(exc),
            )

    results = [
        _build_result("activity_lottery_infos.json", "activity_lottery", activity_records, failed_types),
        _build_result("interactive_lottery_infos.json", "interactive_lottery", interactive_records, failed_types),
        _build_result("reservation_lottery_infos.json", "reservation_lottery", reservation_records, failed_types),
        _build_result("charge_lottery_infos.json", "charge_lottery", charge_records, failed_types),
    ]

    for result in results:
        logger.info(
            "payload result prepared",
            filename=result.filename,
            success=result.success,
            records=result.record_count,
            reason=result.reason,
        )

    return results


def _build_result(
    filename: str,
    article_type: str,
    records: list[dict[str, object]],
    failed_types: set[str],
) -> PayloadGenerationResult:
    if article_type in failed_types:
        return PayloadGenerationResult(
            filename=filename,
            success=False,
            reason=f"{article_type} parsing failed",
        )

    return PayloadGenerationResult(
        filename=filename,
        success=True,
        payload=wrap_payload(records),
    )


def main() -> None:
    resources_root = Path(__file__).resolve().parents[4] / "resources"
    logger = get_logger()
    store = ResourceStore(resources_root, logger)

    results = build_payload_results(logger)
    for result in results:
        store.write(result)

    logger.info(
        "activity index generation finished",
        files=len(results),
        succeeded=sum(1 for result in results if result.success),
        failed=sum(1 for result in results if not result.success),
    )


if __name__ == "__main__":
    main()
