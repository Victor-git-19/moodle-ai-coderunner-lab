"""Формирование учебного запроса к локальной модели Ollama."""

import json
import os
from typing import Any

import httpx

from .response_builder import merge_model_response
from .schemas import AnalyzeRequest, AnalyzeResponse

OLLAMA_URL = os.getenv("OLLAMA_URL", "http://ollama:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "qwen2.5-coder:0.5b")
OLLAMA_TIMEOUT = float(os.getenv("AI_TIMEOUT", "60"))


def build_prompt(request: AnalyzeRequest, static_data: dict[str, Any]) -> str:
    """Собрать короткий промпт с кодом и уже известными результатами CodeRunner."""

    permission = (
        "Можно показать небольшой исправленный фрагмент, но не переписывай всё решение."
        if request.allow_full_solution
        else "Полностью готовое решение и переписанный целиком код запрещены."
    )
    return (
        "Ты преподаватель программирования. Анализируй решение вместе с фактическими результатами CodeRunner, "
        "не выполняй код и не раскрывай скрытые тесты. Сначала объясни проблему, затем дай направляющую подсказку. "
        "Ошибки обязательной структуры из статического анализа нельзя удалять или объявлять неважными. "
        "Хвали только конкретные правильные части. Предположение о хардкоде не называй доказанным. "
        f"{permission} Режим ответа: {request.response_mode}. Ответь коротким JSON без Markdown. "
        "Используй точно такую структуру: "
        '{"verdict":"","strengths":[],"issues":[{"severity":"error|warning|info",'
        '"title":"","explanation":"","hint":"","line":null}],'
        '"failed_test_analysis":[],"edge_cases":[],'
        '"complexity":{"time":"","memory":"","comment":""},'
        '"style":[],"hardcode_warnings":[],"next_step":"","fallback_used":false}. '
        "В каждом массиве не больше трёх коротких пунктов.\n"
        f"Контекст попытки: {request.model_dump_json(exclude={'code'})}\n"
        f"Статический анализ: {json.dumps(static_data, ensure_ascii=False)}\n"
        f"Код студента:\n{request.code}"
    )


async def analyze_with_ollama(request: AnalyzeRequest, static_data: dict[str, Any]) -> AnalyzeResponse:
    """Получить JSON от Ollama и привести его к строгой схеме ответа."""

    payload = {
        "model": OLLAMA_MODEL,
        "prompt": build_prompt(request, static_data),
        "format": "json",
        "stream": False,
        "options": {"temperature": 0.1, "num_predict": 1000},
    }
    async with httpx.AsyncClient(timeout=httpx.Timeout(OLLAMA_TIMEOUT)) as client:
        response = await client.post(f"{OLLAMA_URL}/api/generate", json=payload)
        response.raise_for_status()
        model_data = json.loads(response.json().get("response", ""))
    if not isinstance(model_data, dict):
        raise ValueError("Ollama response must be a JSON object")
    return merge_model_response(model_data, static_data)
