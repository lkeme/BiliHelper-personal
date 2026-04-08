from activity_index.main import build_empty_payloads


def test_build_empty_payloads_has_four_files():
    payloads = build_empty_payloads()
    assert sorted(payloads.keys()) == [
        "activity_lottery_infos.json",
        "charge_lottery_infos.json",
        "interactive_lottery_infos.json",
        "reservation_lottery_infos.json",
    ]
