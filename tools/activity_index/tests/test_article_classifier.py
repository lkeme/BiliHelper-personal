from activity_index.parsers.article_classifier import classify_article_title


def test_classify_known_prefixes():
    assert classify_article_title("活动抽奖圆盘抽奖【2026-04-08】") == "activity_lottery"
    assert classify_article_title("互动抽奖【2026-04-10开奖】") == "interactive_lottery"
    assert classify_article_title("预约抽奖【2026-04-10开奖】") == "reservation_lottery"
    assert classify_article_title("充电抽奖【2026-04-10开奖】") == "charge_lottery"


def test_reject_unknown_prefix():
    assert classify_article_title("普通更新【2026-04-08】") is None
