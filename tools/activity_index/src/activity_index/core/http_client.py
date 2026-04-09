from __future__ import annotations

import json
import os
import time
import urllib.error
import urllib.request
from dataclasses import dataclass
from typing import Any

from activity_index.core.logger import ActivityIndexLogger

DEFAULT_HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36"
    ),
}


@dataclass(slots=True)
class HttpClientConfig:
    timeout_seconds: float = 15.0
    min_interval_seconds: float = 2.5
    max_retries: int = 5


class HttpClient:
    def __init__(
        self,
        logger: ActivityIndexLogger,
        config: HttpClientConfig | None = None,
    ) -> None:
        self.logger = logger
        self.config = config or HttpClientConfig()
        self.optional_cookie = os.getenv("BILI_COOKIE", "").strip()
        self._last_request_started_at = 0.0
        self.total_requests = 0
        self.failed_requests = 0
        self.rate_limited_requests = 0
        self.successful_requests = 0

    def get_json(self, url: str, *, headers: dict[str, str] | None = None, context: str = "") -> dict[str, Any]:
        last_error: Exception | None = None

        for attempt in range(1, self.config.max_retries + 1):
            self.total_requests += 1
            self._throttle()
            request = urllib.request.Request(url, headers=self._build_headers(headers or {}))

            try:
                with urllib.request.urlopen(request, timeout=self.config.timeout_seconds) as response:
                    payload = json.load(response)
            except (urllib.error.URLError, TimeoutError, json.JSONDecodeError, ValueError) as exc:
                last_error = exc
                self.failed_requests += 1
                self.logger.warning(
                    "HTTP request failed",
                    context=context,
                    attempt=attempt,
                    url=url,
                    error=type(exc).__name__,
                )
                if attempt >= self.config.max_retries:
                    break
                self._sleep_with_backoff(attempt)
                continue

            if isinstance(payload, dict) and payload.get("code") == -509 and attempt < self.config.max_retries:
                self.rate_limited_requests += 1
                self.logger.warning(
                    "API rate limit detected, backing off",
                    context=context,
                    attempt=attempt,
                    url=url,
                )
                self._sleep_with_backoff(attempt, minimum_seconds=4.0)
                continue

            if not isinstance(payload, dict):
                raise ValueError(f"{context or 'request'} returned non-object JSON")

            self.successful_requests += 1
            self.logger.debug("HTTP request succeeded", context=context, attempt=attempt, url=url)
            return payload

        raise RuntimeError(f"{context or 'request'} failed after retries: {url}") from last_error

    def get_text(self, url: str, *, headers: dict[str, str] | None = None, context: str = "") -> str:
        last_error: Exception | None = None

        for attempt in range(1, self.config.max_retries + 1):
            self.total_requests += 1
            self._throttle()
            request = urllib.request.Request(url, headers=self._build_headers(headers or {}))

            try:
                with urllib.request.urlopen(request, timeout=self.config.timeout_seconds) as response:
                    body = response.read().decode("utf-8", errors="ignore")
            except (urllib.error.URLError, TimeoutError, ValueError) as exc:
                last_error = exc
                self.failed_requests += 1
                self.logger.warning(
                    "HTTP text request failed",
                    context=context,
                    attempt=attempt,
                    url=url,
                    error=type(exc).__name__,
                )
                if attempt >= self.config.max_retries:
                    break
                self._sleep_with_backoff(attempt)
                continue

            self.successful_requests += 1
            self.logger.debug("HTTP text request succeeded", context=context, attempt=attempt, url=url)
            return body

        raise RuntimeError(f"{context or 'request'} failed after retries: {url}") from last_error

    def diagnostics(self) -> dict[str, int]:
        return {
            "total_requests": self.total_requests,
            "successful_requests": self.successful_requests,
            "failed_requests": self.failed_requests,
            "rate_limited_requests": self.rate_limited_requests,
        }

    def _build_headers(self, extra: dict[str, str]) -> dict[str, str]:
        headers = {**DEFAULT_HEADERS, **extra}
        if self.optional_cookie:
            headers["Cookie"] = self.optional_cookie

        return headers

    def _throttle(self) -> None:
        now = time.monotonic()
        wait_seconds = self.config.min_interval_seconds - (now - self._last_request_started_at)
        if wait_seconds > 0:
            time.sleep(wait_seconds)

        self._last_request_started_at = time.monotonic()

    def _sleep_with_backoff(self, attempt: int, minimum_seconds: float = 0.0) -> None:
        delay = max(self.config.min_interval_seconds * attempt, minimum_seconds)
        time.sleep(delay)
