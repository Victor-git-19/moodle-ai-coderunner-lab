import ast
import json
import logging
import os
from typing import Any

import httpx
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

logging.basicConfig(
    level=os.getenv("LOG_LEVEL", "INFO"),
    format="%(asctime)s %(levelname)s %(name)s %(message)s",
)
logger = logging.getLogger("ai-service")

OLLAMA_URL = os.getenv("OLLAMA_URL", "http://ollama:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "qwen2.5-coder:1.5b")
OLLAMA_TIMEOUT = float(os.getenv("AI_TIMEOUT", "60"))
MAX_CODE_SIZE = int(os.getenv("AI_MAX_CODE_SIZE", "20000"))

app = FastAPI(title="Moodle AI Code Helper", version="1.0.0")


class AnalyzeRequest(BaseModel):
    language: str = Field(default="python", max_length=30)
    task: str = Field(default="", max_length=5000)
    code: str


class AnalyzeResponse(BaseModel):
    summary: str
    issues: list[str]
    suggestions: list[str]
    complexity: str
    fallback_used: bool


def static_python_analysis(code: str) -> dict[str, Any]:
    """Inspect Python syntax without executing user code."""
    try:
        tree = ast.parse(code)
    except SyntaxError as error:
        line = error.lineno or "?"
        return {
            "summary": "В коде есть синтаксическая ошибка.",
            "issues": [f"Синтаксическая ошибка в строке {line}: {error.msg}."],
            "suggestions": ["Исправьте синтаксис и повторите анализ."],
            "complexity": "Не определена",
        }

    nodes = list(ast.walk(tree))
    loops = [node for node in nodes if isinstance(node, (ast.For, ast.While, ast.comprehension))]
    function_names = [node.name for node in nodes if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef))]
    calls = [node.func.id for node in nodes if isinstance(node, ast.Call) and isinstance(node.func, ast.Name)]

    issues: list[str] = []
    suggestions: list[str] = []
    if any(isinstance(node, ast.ExceptHandler) and node.type is None for node in nodes):
        issues.append("Использован except без указания типа исключения.")
    if any(name in {"eval", "exec"} for name in calls):
        issues.append("Использование eval или exec опасно для данных из внешнего ввода.")
    if len(code.splitlines()) > 40 and not function_names:
        suggestions.append("Большой фрагмент кода можно разделить на функции.")

    if function_names:
        summary = "Код определяет функции: " + ", ".join(function_names[:5]) + "."
    elif "input" in calls and "print" in calls:
        summary = "Код считывает данные, обрабатывает их и выводит результат."
    elif "print" in calls:
        summary = "Код вычисляет и выводит результат."
    else:
        summary = "Python-код успешно прошёл синтаксический разбор."

    return {
        "summary": summary,
        "issues": issues,
        "suggestions": suggestions,
        "complexity": "O(n)" if loops else "O(1)",
    }


def static_analysis(language: str, code: str) -> dict[str, Any]:
    if language.lower() in {"python", "python3"}:
        return static_python_analysis(code)
    return {
        "summary": "Код принят. Статический AST-анализ доступен только для Python.",
        "issues": [],
        "suggestions": [],
        "complexity": "Не определена",
    }


async def query_ollama(request: AnalyzeRequest, static_result: dict[str, Any]) -> dict[str, Any]:
    prompt = (
        "Проанализируй учебный код. Не выполняй его. Ответь только JSON с полями "
        "summary (строка), issues (массив строк), suggestions (массив строк), "
        "complexity (строка). Пиши кратко по-русски.\n"
        f"Язык: {request.language}\n"
        f"Задание: {request.task}\n"
        f"Результат статического анализа: {json.dumps(static_result, ensure_ascii=False)}\n"
        f"Код:\n{request.code}"
    )
    payload = {
        "model": OLLAMA_MODEL,
        "prompt": prompt,
        "format": "json",
        "stream": False,
        "options": {"temperature": 0.1, "num_predict": 400},
    }
    timeout = httpx.Timeout(OLLAMA_TIMEOUT)
    async with httpx.AsyncClient(timeout=timeout) as client:
        response = await client.post(f"{OLLAMA_URL}/api/generate", json=payload)
        response.raise_for_status()
        generated = response.json().get("response", "")
        result = json.loads(generated)

    for key in ("summary", "issues", "suggestions", "complexity"):
        if key not in result:
            raise ValueError(f"Ollama response has no {key}")
    if not isinstance(result["summary"], str) or not isinstance(result["complexity"], str):
        raise ValueError("Ollama response contains invalid text fields")
    if not isinstance(result["issues"], list) or not isinstance(result["suggestions"], list):
        raise ValueError("Ollama response contains invalid lists")
    if not all(isinstance(item, str) for item in result["issues"] + result["suggestions"]):
        raise ValueError("Ollama response lists must contain strings")
    return result


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok", "model": OLLAMA_MODEL}


@app.post("/api/v1/analyze", response_model=AnalyzeResponse)
async def analyze(request: AnalyzeRequest) -> AnalyzeResponse:
    code_size = len(request.code.encode("utf-8"))
    if not request.code.strip():
        raise HTTPException(status_code=400, detail="Code must not be empty")
    if code_size > MAX_CODE_SIZE:
        raise HTTPException(status_code=413, detail=f"Code is larger than {MAX_CODE_SIZE} bytes")

    logger.info("Analyzing language=%s code_size=%d", request.language, code_size)
    static_result = static_analysis(request.language, request.code)

    try:
        model_result = await query_ollama(request, static_result)
        return AnalyzeResponse(**model_result, fallback_used=False)
    except (httpx.HTTPError, json.JSONDecodeError, KeyError, TypeError, ValueError) as error:
        logger.warning("Ollama unavailable or returned invalid data: %s", type(error).__name__)
        return AnalyzeResponse(**static_result, fallback_used=True)
