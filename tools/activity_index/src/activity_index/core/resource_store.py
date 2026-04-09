from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from activity_index.core.logger import ActivityIndexLogger
from activity_index.validators import validate_payload
from activity_index.writers.json_writer import empty_payload


@dataclass(slots=True)
class PayloadGenerationResult:
    filename: str
    success: bool
    payload: dict[str, Any] | None = None
    reason: str = ""

    @property
    def record_count(self) -> int:
        if not isinstance(self.payload, dict):
            return 0

        data = self.payload.get("data")
        return len(data) if isinstance(data, list) else 0


@dataclass(slots=True)
class ResourceWriteResult:
    filename: str
    action: str
    records: int
    reason: str = ""


class ResourceStore:
    def __init__(self, base_path: Path, logger: ActivityIndexLogger) -> None:
        self.base_path = base_path
        self.logger = logger

    def write(self, result: PayloadGenerationResult) -> ResourceWriteResult:
        self.base_path.mkdir(parents=True, exist_ok=True)
        target = self.base_path / result.filename
        existing_payload = self._read_existing_payload(target)
        valid_payload, invalid_reason = validate_payload(result.filename, result.payload)

        if result.success and valid_payload:
            assert result.payload is not None
            self._write_payload(target, result.payload)
            self.logger.info(
                "resource file updated",
                filename=result.filename,
                records=result.record_count,
                action="overwrite",
            )
            return ResourceWriteResult(result.filename, "overwrite", result.record_count)

        if existing_payload is not None:
            reason = result.reason or invalid_reason or "invalid payload"
            self.logger.warning(
                "resource generation failed, keeping existing file",
                filename=result.filename,
                reason=reason,
                action="keep_existing",
            )
            records = len(existing_payload.get("data", [])) if isinstance(existing_payload.get("data"), list) else 0
            return ResourceWriteResult(result.filename, "keep_existing", records, reason)

        self._write_payload(target, empty_payload())
        reason = result.reason or invalid_reason or "invalid payload"
        self.logger.warning(
            "resource generation failed, wrote empty payload because no existing file was found",
            filename=result.filename,
            reason=reason,
            action="write_empty",
        )
        return ResourceWriteResult(result.filename, "write_empty", 0, reason)

    def _read_existing_payload(self, path: Path) -> dict[str, Any] | None:
        if not path.is_file():
            return None

        try:
            decoded = json.loads(path.read_text(encoding="utf-8"))
        except Exception:
            return None

        return decoded if isinstance(decoded, dict) else None

    def _write_payload(self, path: Path, payload: dict[str, Any]) -> None:
        path.write_text(
            json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
            encoding="utf-8",
        )
