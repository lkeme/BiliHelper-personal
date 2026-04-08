from pathlib import Path


def test_activity_index_workflow_contract():
    workflow = Path(__file__).resolve().parents[3] / ".github" / "workflows" / "activity-index-refresh.yml"
    text = workflow.read_text(encoding="utf-8")

    assert "workflow_dispatch:" in text
    assert "40 16 * * *" in text
    assert "40 4 * * *" in text
    assert "python -m activity_index.main" in text
    assert "chore: refresh remote activity indexes" in text
    assert "git diff --quiet" in text or "git diff --cached --quiet" in text
