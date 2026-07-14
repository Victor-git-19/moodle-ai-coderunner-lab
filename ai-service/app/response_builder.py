"""Нормализация ответа модели и построение статического fallback."""

from typing import Any

from .schemas import AnalyzeResponse


def _text(value: object, fallback: str) -> str:
    """Вернуть непустую строку модели или безопасное значение анализа."""

    if isinstance(value, str) and value.strip():
        return value.strip()
    return fallback


def _string_list(value: object, fallback: list[str]) -> list[str]:
    """Оставить в списке модели только непустые строки."""

    if not isinstance(value, list):
        return fallback
    items = [item.strip() for item in value if isinstance(item, str) and item.strip()]
    return items or fallback


def _issues(value: object, fallback: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Проверить структуру проблем и отбросить повреждённые элементы."""

    if not isinstance(value, list):
        return fallback

    issues = []
    for item in value:
        if not isinstance(item, dict):
            continue
        title = item.get("title")
        explanation = item.get("explanation")
        hint = item.get("hint")
        if not all(isinstance(field, str) and field.strip() for field in (title, explanation, hint)):
            continue

        severity = item.get("severity")
        if severity not in {"error", "warning", "info"}:
            severity = "warning"
        line = item.get("line")
        if isinstance(line, bool) or not isinstance(line, int) or line < 1:
            line = None

        issues.append(
            {
                "severity": severity,
                "title": title.strip(),
                "explanation": explanation.strip(),
                "hint": hint.strip(),
                "line": line,
            }
        )
    return issues or fallback


def _complexity(value: object, fallback: dict[str, str]) -> dict[str, str]:
    """Проверить три поля оценки сложности по отдельности."""

    if not isinstance(value, dict):
        return fallback
    return {
        "time": _text(value.get("time"), fallback["time"]),
        "memory": _text(value.get("memory"), fallback["memory"]),
        "comment": _text(value.get("comment"), fallback["comment"]),
    }


def response_data(
    *,
    verdict: str,
    strengths: list[str],
    next_step: str,
    issues: list[dict[str, Any]] | None = None,
    failed_test_analysis: list[str] | None = None,
    edge_cases: list[str] | None = None,
    complexity: dict[str, str] | None = None,
    style: list[str] | None = None,
    hardcode_warnings: list[str] | None = None,
) -> dict[str, Any]:
    """Собрать единую структуру ответа для анализа и fallback."""

    return {
        "verdict": verdict,
        "strengths": strengths,
        "issues": issues or [],
        "failed_test_analysis": failed_test_analysis or [],
        "edge_cases": edge_cases or [],
        "complexity": complexity or {
            "time": "Не определена",
            "memory": "Не определена",
            "comment": "",
        },
        "style": style or [],
        "hardcode_warnings": hardcode_warnings or [],
        "next_step": next_step,
    }


def merge_model_response(model_data: dict[str, Any], static_data: dict[str, Any]) -> AnalyzeResponse:
    """Дополнить неполный ответ модели надёжными статическими данными."""

    result = {
        "verdict": _text(model_data.get("verdict"), static_data["verdict"]),
        "strengths": _string_list(model_data.get("strengths"), static_data["strengths"]),
        "issues": _issues(model_data.get("issues"), static_data["issues"]),
        "failed_test_analysis": _string_list(
            model_data.get("failed_test_analysis"), static_data["failed_test_analysis"]
        ),
        "edge_cases": _string_list(model_data.get("edge_cases"), static_data["edge_cases"]),
        "complexity": _complexity(model_data.get("complexity"), static_data["complexity"]),
        "style": _string_list(model_data.get("style"), static_data["style"]),
        "hardcode_warnings": _string_list(
            model_data.get("hardcode_warnings"), static_data["hardcode_warnings"]
        ),
        "next_step": _text(model_data.get("next_step"), static_data["next_step"]),
    }
    if len(str(result.get("verdict", "")).strip()) < 12:
        result["verdict"] = static_data["verdict"]
    result["fallback_used"] = False
    return AnalyzeResponse.model_validate(result)


def fallback_response(static_data: dict[str, Any]) -> AnalyzeResponse:
    """Вернуть статический анализ, если Ollama недоступна."""

    return AnalyzeResponse.model_validate({**static_data, "fallback_used": True})
