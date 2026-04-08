import json
from pathlib import Path

from activity_index.collectors.space_dynamic import SpaceArticleCollector
from activity_index.parsers.charge_lottery_parser import parse_charge_article
from activity_index.parsers.interactive_lottery_parser import parse_interactive_article
from activity_index.parsers.reservation_lottery_parser import parse_reservation_article
from activity_index.writers.json_writer import wrap_payload

TARGET_HOST_MID = "3461568884902878"


def build_empty_payloads() -> dict[str, dict[str, object]]:
    names = [
        "activity_lottery_infos.json",
        "interactive_lottery_infos.json",
        "reservation_lottery_infos.json",
        "charge_lottery_infos.json",
    ]

    return {
        name: wrap_payload([])
        for name in names
    }


def build_payloads() -> dict[str, dict[str, object]]:
    payloads = build_empty_payloads()
    collector = SpaceArticleCollector()

    try:
        articles = collector.collect_recent_articles(
            TARGET_HOST_MID,
            hours=24,
            limit=20,
            fetch_detail_types={"interactive_lottery", "reservation_lottery", "charge_lottery"},
        )
    except Exception:
        return preserve_existing_activity_lottery(payloads, Path(__file__).resolve().parents[4] / "resources")

    interactive_records: list[dict[str, object]] = []
    reservation_records: list[dict[str, object]] = []
    charge_records: list[dict[str, object]] = []

    for article in articles:
        if article.article_type == "interactive_lottery":
            interactive_records.extend(parse_interactive_article(article))
        elif article.article_type == "reservation_lottery":
            reservation_records.extend(parse_reservation_article(article))
        elif article.article_type == "charge_lottery":
            charge_records.extend(parse_charge_article(article))

    resources_root = Path(__file__).resolve().parents[4] / "resources"
    payloads["interactive_lottery_infos.json"] = preserve_existing_payload_if_empty(
        resources_root,
        "interactive_lottery_infos.json",
        wrap_payload(interactive_records),
    )
    payloads["reservation_lottery_infos.json"] = preserve_existing_payload_if_empty(
        resources_root,
        "reservation_lottery_infos.json",
        wrap_payload(reservation_records),
    )
    payloads["charge_lottery_infos.json"] = preserve_existing_payload_if_empty(
        resources_root,
        "charge_lottery_infos.json",
        wrap_payload(charge_records),
    )

    return preserve_existing_payload_if_empty(
        resources_root,
        "activity_lottery_infos.json",
        payloads["activity_lottery_infos.json"],
    )


def preserve_existing_payload_if_empty(
    resources_root: Path,
    filename: str,
    payload: dict[str, object],
) -> dict[str, object]:
    if payload.get("data"):
        return payload

    existing = resources_root / filename
    if existing.is_file():
        try:
            return json.loads(existing.read_text(encoding="utf-8"))
        except Exception:
            return payload

    return payload


def write_payloads(base_path: Path) -> None:
    base_path.mkdir(parents=True, exist_ok=True)
    for name, payload in build_payloads().items():
        target = base_path / name
        target.write_text(
            json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
            encoding="utf-8",
        )


def main() -> None:
    resources_root = Path(__file__).resolve().parents[4] / "resources"
    write_payloads(resources_root)


if __name__ == "__main__":
    main()
