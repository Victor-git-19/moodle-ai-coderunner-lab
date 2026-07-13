import ast
import json
import logging
import os
from typing import Any, Literal

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

app = FastAPI(title="Moodle AI Code Helper", version="2.0.0")


class TestResult(BaseModel):
    number: int = Field(ge=1)
    passed: bool
    hidden: bool = False
    error_type: str = Field(default="", max_length=50)
    message: str = Field(default="", max_length=2000)
    visible_output: dict[str, str] = Field(default_factory=dict)


class AnalyzeRequest(BaseModel):
    language: str = Field(default="python", max_length=30)
    task: str = Field(default="", max_length=5000)
    question_name: str = Field(default="", max_length=500)
    code: str
    grade: float | None = None
    max_grade: float | None = None
    attempt_number: int | None = Field(default=None, ge=1)
    status: str = Field(default="", max_length=100)
    passed_tests: int = Field(default=0, ge=0)
    failed_tests: int = Field(default=0, ge=0)
    test_results: list[TestResult] = Field(default_factory=list, max_length=200)
    compiler_message: str = Field(default="", max_length=5000)
    runtime_error: str = Field(default="", max_length=5000)
    stdout: str = Field(default="", max_length=10000)
    stderr: str = Field(default="", max_length=10000)
    timeout: bool = False
    memory_limit: bool = False
    response_mode: Literal["hint", "detailed", "teacher"] = "teacher"
    allow_full_solution: bool = False


class Issue(BaseModel):
    severity: Literal["error", "warning", "info"]
    title: str
    explanation: str
    hint: str
    line: int | None = None


class Complexity(BaseModel):
    time: str
    memory: str
    comment: str


class AnalyzeResponse(BaseModel):
    verdict: str
    strengths: list[str]
    issues: list[Issue]
    failed_test_analysis: list[str]
    edge_cases: list[str]
    complexity: Complexity
    style: list[str]
    hardcode_warnings: list[str]
    next_step: str
    fallback_used: bool


def issue(
    severity: Literal["error", "warning", "info"],
    title: str,
    explanation: str,
    hint: str,
    line: int | None = None,
) -> dict[str, Any]:
    return {
        "severity": severity,
        "title": title,
        "explanation": explanation,
        "hint": hint,
        "line": line,
    }


def hardcode_analysis(tree: ast.AST, code: str, request: AnalyzeRequest) -> list[str]:
    nodes = list(ast.walk(tree))
    calls = [node for node in nodes if isinstance(node, ast.Call)]
    input_calls = [
        node for node in calls
        if isinstance(node.func, ast.Name) and node.func.id == "input"
    ]
    print_calls = [
        node for node in calls
        if isinstance(node.func, ast.Name) and node.func.id == "print"
    ]
    comparisons = [node for node in nodes if isinstance(node, ast.Compare)]
    constants = [
        node.value for node in nodes
        if isinstance(node, ast.Constant) and isinstance(node.value, (int, float, str))
        and node.value not in ("", 0, 1, None)
    ]
    warnings: list[str] = []

    if print_calls and not input_calls and not any(isinstance(node, ast.FunctionDef) for node in nodes):
        if all(all(isinstance(arg, ast.Constant) for arg in call.args) for call in print_calls):
            warnings.append(
                "Есть признаки возможного хардкода: программа выводит фиксированные значения и не читает ввод. "
                "Стоит проверить, будет ли она работать на других входных данных."
            )
    if len(comparisons) >= 3 and len(set(map(str, constants))) >= 4:
        warnings.append(
            "Есть признаки возможного хардкода: много ветвлений связано с конкретными константами. "
            "Стоит проверить общий алгоритм."
        )
    if request.passed_tests > 0 and request.failed_tests > 0 and len(constants) >= 5:
        warnings.append(
            "Решение проходит часть тестов, но содержит много констант. Это может не работать на других "
            "входных данных; стоит проверить, не подогнано ли решение под открытые примеры."
        )
    if any(isinstance(node, (ast.Return, ast.Raise)) for node in nodes):
        for parent in nodes:
            body = getattr(parent, "body", None)
            if not isinstance(body, list):
                continue
            for index, statement in enumerate(body[:-1]):
                if isinstance(statement, (ast.Return, ast.Raise)):
                    warnings.append(
                        "Стоит проверить код после return или raise: часть инструкций может быть недостижима."
                    )
                    return warnings
    return warnings


def static_python_analysis(request: AnalyzeRequest) -> dict[str, Any]:
    code = request.code
    try:
        tree = ast.parse(code)
    except SyntaxError as error:
        line = error.lineno
        return {
            "verdict": "Решение пока нельзя проверить: в коде есть синтаксическая ошибка.",
            "strengths": ["Код получен и безопасно разобран без запуска."],
            "issues": [issue(
                "error",
                "Синтаксическая ошибка",
                f"Интерпретатор не может разобрать строку {line or '?'}: {error.msg}.",
                "Проверьте скобки, двоеточия и структуру указанной строки.",
                line,
            )],
            "failed_test_analysis": failure_analysis(request),
            "edge_cases": ["Минимальный допустимый ввод", "Пустой ввод, если он разрешён условием"],
            "complexity": {"time": "Не определена", "memory": "Не определена", "comment": "Сначала исправьте синтаксис."},
            "style": [],
            "hardcode_warnings": [],
            "next_step": f"Исправьте синтаксис в строке {line or '?'} и снова запустите проверку CodeRunner.",
        }

    nodes = list(ast.walk(tree))
    loops = [node for node in nodes if isinstance(node, (ast.For, ast.While, ast.comprehension))]
    nested_loop = any(
        isinstance(child, (ast.For, ast.While, ast.comprehension))
        for loop in loops for child in ast.walk(loop) if child is not loop
    )
    functions = [node.name for node in nodes if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef))]
    calls = [node.func.id for node in nodes if isinstance(node, ast.Call) and isinstance(node.func, ast.Name)]

    strengths: list[str] = []
    issues: list[dict[str, Any]] = []
    style: list[str] = []
    if "input" in calls:
        strengths.append("Решение читает пользовательский ввод, а не использует только фиксированные данные.")
    if "print" in calls:
        strengths.append("Решение формирует вывод программы.")
    if functions:
        strengths.append("Логика разделена на функции: " + ", ".join(functions[:4]) + ".")
    if not strengths:
        strengths.append("Код успешно прошёл синтаксический разбор.")
    if any(isinstance(node, ast.ExceptHandler) and node.type is None for node in nodes):
        issues.append(issue(
            "warning", "Слишком широкая обработка ошибок",
            "except без типа скрывает разные причины ошибки.",
            "Укажите ожидаемый тип исключения и обработайте только его.",
        ))
    if any(name in {"eval", "exec"} for name in calls):
        issues.append(issue(
            "warning", "Опасная динамическая операция",
            "eval или exec выполняют строку как код и обычно не нужны в учебной задаче.",
            "Разберите ввод обычными операциями языка.",
        ))
    if len(code.splitlines()) > 40 and not functions:
        style.append("Длинный линейный фрагмент стоит разделить на небольшие функции.")

    time_complexity = "O(n²)" if nested_loop else ("O(n)" if loops else "O(1)")
    memory_complexity = "O(n)" if any(isinstance(node, (ast.ListComp, ast.SetComp, ast.DictComp)) for node in nodes) else "O(1)"
    verdict = attempt_verdict(request, issues)
    return {
        "verdict": verdict,
        "strengths": strengths,
        "issues": issues,
        "failed_test_analysis": failure_analysis(request),
        "edge_cases": ["Пустой или минимальный ввод", "Отрицательные числа", "Повторяющиеся значения"],
        "complexity": {
            "time": time_complexity,
            "memory": memory_complexity,
            "comment": "Оценка получена статически по структуре циклов и создаваемых коллекций.",
        },
        "style": style,
        "hardcode_warnings": hardcode_analysis(tree, code, request),
        "next_step": next_step(request, issues),
    }


def attempt_verdict(request: AnalyzeRequest, issues: list[dict[str, Any]]) -> str:
    if request.timeout:
        return "CodeRunner остановил решение по времени; сначала найдите самую дорогую часть алгоритма."
    if request.memory_limit:
        return "CodeRunner сообщил о превышении памяти; проверьте объём создаваемых данных."
    if request.runtime_error:
        return "Решение запускается, но CodeRunner обнаружил ошибку времени выполнения."
    if request.failed_tests:
        return f"Решение прошло {request.passed_tests} тестов и не прошло {request.failed_tests}; общий подход частично работает."
    if request.passed_tests and not request.failed_tests:
        return f"Решение прошло все доступные результаты тестов ({request.passed_tests})."
    if issues:
        return "Код разобран, но перед следующей проверкой стоит исправить найденные проблемы."
    return "Код синтаксически корректен; сравните его поведение с требованиями задания."


def failure_analysis(request: AnalyzeRequest) -> list[str]:
    result: list[str] = []
    if request.compiler_message:
        result.append("CodeRunner сообщил об ошибке компиляции или синтаксиса; проверку нужно начать с неё.")
    if request.runtime_error:
        result.append("Ошибка времени выполнения может быть связана с неподходящим входом, индексом или преобразованием типа.")
    if request.timeout:
        result.append("Превышение времени указывает на слишком дорогой алгоритм или незавершающийся цикл.")
    if request.memory_limit:
        result.append("Превышение памяти может быть связано с накоплением лишних коллекций или бесконечным ростом данных.")
    if request.failed_tests and not result:
        result.append("Проверьте границы диапазонов, формат вывода и случаи, отличающиеся от открытых примеров.")
    return result


def next_step(request: AnalyzeRequest, issues: list[dict[str, Any]]) -> str:
    if request.compiler_message:
        return "Исправьте первую ошибку компилятора и повторите проверку CodeRunner."
    if request.runtime_error:
        return "Найдите первую строку из сообщения об ошибке и проверьте значения переменных перед ней."
    if request.timeout:
        return "Оцените число повторений самого вложенного цикла и уменьшите его."
    if request.memory_limit:
        return "Проверьте, какие коллекции растут вместе с входом, и уберите лишнее хранение."
    if issues:
        return "Исправьте первую отмеченную проблему и повторно отправьте решение в CodeRunner."
    if request.failed_tests:
        return "Составьте один крайний тест, на котором текущий алгоритм даёт неверный ответ, и пройдите его вручную."
    return "Повторно проверьте решение в CodeRunner на граничных входных данных."


def static_analysis(request: AnalyzeRequest) -> dict[str, Any]:
    if request.language.lower() in {"python", "python3"}:
        return static_python_analysis(request)
    return {
        "verdict": attempt_verdict(request, []),
        "strengths": ["Код и результаты CodeRunner получены для анализа."],
        "issues": [],
        "failed_test_analysis": failure_analysis(request),
        "edge_cases": ["Минимальный ввод", "Пустой ввод", "Граничные значения"],
        "complexity": {"time": "Не определена", "memory": "Не определена", "comment": "AST-анализ доступен только для Python."},
        "style": [],
        "hardcode_warnings": [],
        "next_step": next_step(request, []),
    }


def enrich_model_result(parsed: dict[str, Any], static_result: dict[str, Any]) -> dict[str, Any]:
    for field in ("strengths", "failed_test_analysis", "edge_cases", "style", "hardcode_warnings"):
        if not parsed.get(field):
            parsed[field] = static_result[field]
    if len(str(parsed.get("verdict", "")).strip()) < 12:
        parsed["verdict"] = static_result["verdict"]
    return parsed


async def query_ollama(request: AnalyzeRequest, static_result: dict[str, Any]) -> AnalyzeResponse:
    permission = (
        "Можно показать небольшой исправленный фрагмент, но не переписывай всё решение."
        if request.allow_full_solution else
        "Полностью готовое решение и переписанный целиком код запрещены."
    )
    prompt = (
        "Ты преподаватель программирования. Анализируй решение вместе с фактическими результатами CodeRunner, "
        "не выполняй код и не раскрывай скрытые тесты. Сначала объясни проблему, затем дай направляющую подсказку. "
        "Хвали только конкретные правильные части. Предположение о хардкоде не называй доказанным. "
        f"{permission} Режим ответа: {request.response_mode}. Ответь коротким JSON без Markdown. "
        "Используй точно такую структуру: "
        "{\"verdict\":\"\",\"strengths\":[],\"issues\":[{\"severity\":\"error|warning|info\","
        "\"title\":\"\",\"explanation\":\"\",\"hint\":\"\",\"line\":null}],"
        "\"failed_test_analysis\":[],\"edge_cases\":[],"
        "\"complexity\":{\"time\":\"\",\"memory\":\"\",\"comment\":\"\"},"
        "\"style\":[],\"hardcode_warnings\":[],\"next_step\":\"\",\"fallback_used\":false}. "
        "В каждом массиве не больше трёх коротких пунктов.\n"
        f"Контекст попытки: {request.model_dump_json(exclude={'code'})}\n"
        f"Статический анализ: {json.dumps(static_result, ensure_ascii=False)}\n"
        f"Код студента:\n{request.code}"
    )
    payload = {
        "model": OLLAMA_MODEL,
        "prompt": prompt,
        "format": "json",
        "stream": False,
        "options": {"temperature": 0.1, "num_predict": 700},
    }
    async with httpx.AsyncClient(timeout=httpx.Timeout(OLLAMA_TIMEOUT)) as client:
        response = await client.post(f"{OLLAMA_URL}/api/generate", json=payload)
        response.raise_for_status()
        generated = response.json().get("response", "")
        parsed = json.loads(generated)
    parsed = enrich_model_result(parsed, static_result)
    parsed["fallback_used"] = False
    return AnalyzeResponse.model_validate(parsed)


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

    logger.info(
        "Analyzing language=%s code_size=%d status=%s tests=%d/%d",
        request.language, code_size, request.status, request.passed_tests, request.failed_tests,
    )
    static_result = static_analysis(request)
    try:
        return await query_ollama(request, static_result)
    except (httpx.HTTPError, json.JSONDecodeError, KeyError, TypeError, ValueError) as error:
        logger.warning("Ollama unavailable or returned invalid data: %s", type(error).__name__)
        return AnalyzeResponse(**static_result, fallback_used=True)
