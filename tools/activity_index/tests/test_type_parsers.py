from activity_index.parsers.activity_lottery_parser import parse_activity_lottery
from activity_index.parsers.charge_lottery_parser import parse_charge_lottery
from activity_index.parsers.interactive_lottery_parser import parse_interactive_lottery
from activity_index.parsers.reservation_lottery_parser import parse_reservation_lottery


def test_activity_lottery_parser_emits_compatible_fields():
    record = parse_activity_lottery(
        {
            "title": "活动抽奖圆盘抽奖【2026-04-08】",
            "source_cv_id": "47582136",
            "source_url": "https://www.bilibili.com/read/cv47582136/",
            "publish_time": "2026-04-08 00:10:00",
            "url": "https://www.bilibili.com/blackboard/era/example.html",
            "page_id": "3ERAcwxxxx",
            "activity_id": "1ERAxxxx",
            "lottery_id": "4ERAxxxx",
            "start_time": 1775606400,
            "end_time": 1775692800,
        }
    )

    for key in [
        "title",
        "url",
        "page_id",
        "activity_id",
        "lottery_id",
        "start_time",
        "end_time",
        "source_cv_id",
        "source_url",
        "publish_time",
        "update_time",
    ]:
        assert key in record


def test_interactive_lottery_parser_emits_hints_not_final_payload():
    record = parse_interactive_lottery(
        {
            "title": "互动抽奖【2026-04-10开奖】",
            "source_cv_id": "47581954",
            "source_url": "https://www.bilibili.com/read/cv47581954/",
            "publish_time": "2026-04-08 00:05:00",
            "draw_time": "2026-04-10 00:00:00",
            "dynamic_id": "1234567890",
            "dynamic_url": "https://t.bilibili.com/1234567890",
            "reserve_rid": "998877",
            "up_mid": "10001",
            "requires_follow": True,
            "requires_repost": True,
            "requires_comment": False,
            "prize_summary": "测试奖品",
        }
    )

    assert "dynamic_id" in record
    assert "reserve_rid" in record
    assert "csrf" not in record
    assert "id_str" not in record


def test_reservation_lottery_parser_emits_hints_not_final_payload():
    record = parse_reservation_lottery(
        {
            "title": "预约抽奖【2026-04-10开奖】",
            "source_cv_id": "47582005",
            "source_url": "https://www.bilibili.com/read/cv47582005/",
            "publish_time": "2026-04-08 00:06:00",
            "draw_time": "2026-04-10 00:00:00",
            "up_mid": "20002",
            "up_name": "测试UP",
            "sid": "9988",
            "name": "测试预约",
            "jump_url": "https://www.bilibili.com/blackboard/test",
            "text": "预约有奖",
            "requires_follow": True,
            "requires_reserve": True,
        }
    )

    assert "sid" in record
    assert "up_mid" in record
    assert "csrf" not in record
    assert "vmid" not in record


def test_charge_lottery_parser_still_emits_quality_fields():
    record = parse_charge_lottery(
        {
            "title": "充电抽奖【2026-04-10开奖】",
            "source_cv_id": "47581977",
            "source_url": "https://www.bilibili.com/read/cv47581977/",
            "publish_time": "2026-04-08 00:07:00",
            "draw_time": "2026-04-10 00:00:00",
            "prize_summary": "测试奖品",
            "requires_charge": True,
        }
    )

    assert record["requires_charge"] is True
    assert "update_time" in record
