from typing import Any

from .schemas import AnalyzeResponse


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
    """Build the one response shape used by static analysis and fallback."""
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
    result = dict(model_data)
    for field in (
        "strengths",
        "issues",
        "failed_test_analysis",
        "edge_cases",
        "complexity",
        "style",
        "hardcode_warnings",
        "next_step",
    ):
        if not result.get(field):
            result[field] = static_data[field]
    if len(str(result.get("verdict", "")).strip()) < 12:
        result["verdict"] = static_data["verdict"]
    result["fallback_used"] = False
    return AnalyzeResponse.model_validate(result)


def fallback_response(static_data: dict[str, Any]) -> AnalyzeResponse:
    return AnalyzeResponse.model_validate({**static_data, "fallback_used": True})
