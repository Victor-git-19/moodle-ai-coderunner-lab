"""HTTP API: проверка входных данных, статический анализ и обращение к Ollama."""

import json
import logging
import os

import httpx
from fastapi import FastAPI, HTTPException

from .ollama_client import OLLAMA_MODEL, analyze_with_ollama
from .response_builder import fallback_response
from .schemas import AnalyzeRequest, AnalyzeResponse
from .static_analysis import analyze_code

logging.basicConfig(
    level=os.getenv("LOG_LEVEL", "INFO"),
    format="%(asctime)s %(levelname)s %(name)s %(message)s",
)
logger = logging.getLogger("ai-service")

MAX_CODE_SIZE = int(os.getenv("AI_MAX_CODE_SIZE", "20000"))

app = FastAPI(title="Moodle AI Code Helper", version="2.0.0")


@app.get("/health")
async def health() -> dict[str, str]:
    """Вернуть состояние сервиса и имя выбранной модели."""

    return {"status": "ok", "model": OLLAMA_MODEL}


@app.post("/api/v1/analyze", response_model=AnalyzeResponse)
async def analyze(request: AnalyzeRequest) -> AnalyzeResponse:
    """Проанализировать код, не выполняя его внутри AI service."""

    code_size = len(request.code.encode("utf-8"))
    if not request.code.strip():
        raise HTTPException(status_code=400, detail="Code must not be empty")
    if code_size > MAX_CODE_SIZE:
        raise HTTPException(status_code=413, detail=f"Code is larger than {MAX_CODE_SIZE} bytes")

    logger.info(
        "Analyzing language=%s code_size=%d status=%s tests=%d/%d",
        request.language,
        code_size,
        request.status,
        request.passed_tests,
        request.failed_tests,
    )
    static_data = analyze_code(request)
    try:
        return await analyze_with_ollama(request, static_data)
    except (httpx.HTTPError, json.JSONDecodeError, KeyError, TypeError, ValueError) as error:
        # Сбой модели не должен лишать студента безопасного статического разбора.
        logger.warning("Ollama unavailable or returned invalid data: %s", type(error).__name__)
        return fallback_response(static_data)
