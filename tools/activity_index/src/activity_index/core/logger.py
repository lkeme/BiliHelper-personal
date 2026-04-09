from __future__ import annotations

import logging
import os
import sys
from typing import Any


class ActivityIndexLogger:
    def __init__(self, debug_enabled: bool = False) -> None:
        self.debug_enabled = debug_enabled
        self._loguru_logger = self._build_loguru_logger(debug_enabled)
        self._logging_logger = self._build_logging_logger(debug_enabled)

    def debug(self, message: str, **context: Any) -> None:
        self._log("debug", message, context)

    def info(self, message: str, **context: Any) -> None:
        self._log("info", message, context)

    def warning(self, message: str, **context: Any) -> None:
        self._log("warning", message, context)

    def error(self, message: str, **context: Any) -> None:
        self._log("error", message, context)

    def exception(self, message: str, **context: Any) -> None:
        self._log("exception", message, context)

    def _log(self, level: str, message: str, context: dict[str, Any]) -> None:
        rendered = self._render_message(message, context)

        if self._loguru_logger is not None:
            getattr(self._loguru_logger, level)(rendered)
            return

        if level == "exception":
            self._logging_logger.exception(rendered)
            return

        getattr(self._logging_logger, level)(rendered)

    def _render_message(self, message: str, context: dict[str, Any]) -> str:
        if not context:
            return message

        suffix = " ".join(
            f"{key}={value}"
            for key, value in context.items()
            if value is not None and value != ""
        )
        return f"{message} [{suffix}]" if suffix else message

    def _build_loguru_logger(self, debug_enabled: bool):  # type: ignore[no-untyped-def]
        try:
            from loguru import logger as loguru_logger
        except Exception:
            return None

        loguru_logger.remove()
        loguru_logger.add(
            sys.stderr,
            level="DEBUG" if debug_enabled else "INFO",
            format="{time:YYYY-MM-DD HH:mm:ss} | {level:<8} | {message}",
        )
        return loguru_logger

    def _build_logging_logger(self, debug_enabled: bool) -> logging.Logger:
        logger = logging.getLogger("activity_index")
        logger.setLevel(logging.DEBUG if debug_enabled else logging.INFO)
        logger.handlers.clear()

        handler = logging.StreamHandler(sys.stderr)
        handler.setLevel(logging.DEBUG if debug_enabled else logging.INFO)
        handler.setFormatter(logging.Formatter("%(asctime)s | %(levelname)-8s | %(message)s"))
        logger.addHandler(handler)
        logger.propagate = False

        return logger


def get_logger() -> ActivityIndexLogger:
    debug_enabled = os.getenv("ACTIVITY_INDEX_DEBUG", "").strip().lower() in {"1", "true", "yes", "on"}
    return ActivityIndexLogger(debug_enabled=debug_enabled)
