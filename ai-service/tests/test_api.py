"""Проверки API, fallback и связи анализа с результатами CodeRunner."""

from fastapi.testclient import TestClient

from app import main
from app.response_builder import merge_model_response
from app.schemas import AnalyzeRequest, AnalyzeResponse
from app.static_analysis import analyze_code

client = TestClient(main.app)


def fallback(monkeypatch) -> None:
    """Подменить Ollama ожидаемым сбоем для проверки статического ответа."""

    async def fail(*_args, **_kwargs):
        raise ValueError("model is unavailable")

    monkeypatch.setattr(main, "analyze_with_ollama", fail)


def analyze(monkeypatch, **overrides):
    """Отправить типовой запрос, меняя только нужные тесту поля."""

    fallback(monkeypatch)
    payload = {
        "language": "python",
        "task": "Вывести квадрат числа",
        "code": "n = int(input())\nprint(n * n)",
        **overrides,
    }
    return client.post("/api/v1/analyze", json=payload)


def test_health() -> None:
    assert client.get("/health").json()["status"] == "ok"


def test_empty_and_large_code_are_rejected(monkeypatch) -> None:
    assert client.post("/api/v1/analyze", json={"code": "   "}).status_code == 400
    monkeypatch.setattr(main, "MAX_CODE_SIZE", 3)
    assert client.post("/api/v1/analyze", json={"code": "print(1)"}).status_code == 413


def test_successful_attempt_uses_coderunner_result(monkeypatch) -> None:
    body = analyze(monkeypatch, status="gradedright", passed_tests=3, failed_tests=0).json()
    assert "прошло все" in body["verdict"]
    assert body["fallback_used"] is True
    assert set(body) == {
        "verdict", "strengths", "issues", "failed_test_analysis", "edge_cases",
        "complexity", "style", "hardcode_warnings", "next_step", "fallback_used",
    }


def test_wrong_answer_uses_failed_tests(monkeypatch) -> None:
    body = analyze(monkeypatch, status="gradedwrong", passed_tests=1, failed_tests=2).json()
    assert "1 тестов" in body["verdict"] and "2" in body["verdict"]
    assert body["failed_test_analysis"]


def test_syntax_error(monkeypatch) -> None:
    body = analyze(monkeypatch, code="if True print('x')", compiler_message="SyntaxError").json()
    assert body["issues"][0]["title"] == "Синтаксическая ошибка"
    assert body["issues"][0]["line"] == 1


def test_runtime_error(monkeypatch) -> None:
    body = analyze(monkeypatch, runtime_error="ZeroDivisionError", failed_tests=1).json()
    assert "времени выполнения" in body["verdict"]
    assert "первую строку" in body["next_step"]


def test_timeout(monkeypatch) -> None:
    body = analyze(monkeypatch, timeout=True, failed_tests=1).json()
    assert "времени" in body["verdict"]
    assert any("цикл" in item for item in body["failed_test_analysis"])


def test_memory_limit(monkeypatch) -> None:
    body = analyze(monkeypatch, memory_limit=True, failed_tests=1).json()
    assert "памяти" in body["verdict"]


def test_missing_required_function_is_reported(monkeypatch) -> None:
    body = analyze(
        monkeypatch,
        task="Обязательно объявите функцию с помощью def circle_area(...).",
        code="import math\nprint(math.pi * 4)",
        failed_tests=3,
    ).json()
    assert any(issue["title"] == "Требуется функция" for issue in body["issues"])


def test_required_class_is_checked(monkeypatch) -> None:
    body = analyze(
        monkeypatch,
        task="Обязательно объявите класс Rectangle.",
        code="def area(width, height):\n    return width * height",
    ).json()
    assert any(issue["title"] == "Требуется класс" for issue in body["issues"])


def test_parallel_api_is_checked(monkeypatch) -> None:
    body = analyze(
        monkeypatch,
        task="Объявите def parallel_lengths(...). Используйте ThreadPoolExecutor.",
        code="def parallel_lengths(items):\n    return list(map(len, items))",
    ).json()
    assert any(issue["title"] == "Не использован обязательный API" for issue in body["issues"])


def test_parallel_worker_limit_is_checked(monkeypatch) -> None:
    body = analyze(
        monkeypatch,
        task="Используйте ThreadPoolExecutor с max_workers=2 в def parallel_lengths(...).",
        code=(
            "from concurrent.futures import ThreadPoolExecutor\n"
            "def parallel_lengths(items):\n"
            "    with ThreadPoolExecutor() as executor:\n"
            "        return list(executor.map(len, items))"
        ),
    ).json()
    assert any(issue["title"] == "Не ограничено число работников" for issue in body["issues"])


def test_float_task_gets_precision_edge_cases(monkeypatch) -> None:
    body = analyze(
        monkeypatch,
        task="Верните вещественный результат через def average(...).",
        code="def average(values):\n    return sum(values) / len(values)",
    ).json()
    assert any("погреш" in item.lower() for item in body["edge_cases"])


def test_possible_hardcode_is_not_asserted_as_fact(monkeypatch) -> None:
    body = analyze(monkeypatch, code="print(42)", passed_tests=1, failed_tests=2).json()
    warning = body["hardcode_warnings"][0]
    assert "признаки возможного хардкода" in warning
    assert "доказан" not in warning


def test_valid_model_response(monkeypatch) -> None:
    async def succeed(*_args, **_kwargs):
        return AnalyzeResponse(
            verdict="Подход верный.", strengths=["Ввод обработан."], issues=[],
            failed_test_analysis=[], edge_cases=["Ноль"],
            complexity={"time": "O(1)", "memory": "O(1)", "comment": "Без циклов."},
            style=[], hardcode_warnings=[], next_step="Проверьте ноль.", fallback_used=False,
        )

    monkeypatch.setattr(main, "analyze_with_ollama", succeed)
    response = client.post("/api/v1/analyze", json={"code": "print(1)"})
    assert response.status_code == 200
    assert response.json()["fallback_used"] is False


def test_model_result_keeps_coderunner_context() -> None:
    static = analyze_code(AnalyzeRequest(
        code="print(4)", status="incorrect", passed_tests=1, failed_tests=2,
    ))
    model = {
        "verdict": "incorrect",
        "strengths": [],
        "failed_test_analysis": [],
        "edge_cases": [],
        "style": [],
        "hardcode_warnings": [],
    }
    enriched = merge_model_response(model, static).model_dump()
    assert "1 тестов" in enriched["verdict"]
    assert enriched["failed_test_analysis"]
    assert enriched["hardcode_warnings"]


def test_model_cannot_remove_static_structure_issue() -> None:
    static = analyze_code(AnalyzeRequest(
        task="Обязательно объявите функцию с помощью def circle_area(...).",
        code="print(12.56)",
        failed_tests=3,
    ))
    model = {
        "verdict": "Вычисление почти готово, осталось проверить формат результата.",
        "strengths": ["Есть числовой результат."],
        "issues": [],
        "next_step": "Проверьте формат.",
    }
    enriched = merge_model_response(model, static).model_dump()
    assert any(issue["title"] == "Требуется функция" for issue in enriched["issues"])


def test_malformed_model_fields_are_safely_normalized() -> None:
    static = analyze_code(AnalyzeRequest(
        code="print(4)", status="incorrect", passed_tests=1, failed_tests=2,
    ))
    model = {
        "verdict": "В решении есть полезная идея, но тесты пройдены не все.",
        "strengths": "not a list",
        "issues": [
            {
                "severity": "critical",
                "title": "Проверьте условие",
                "explanation": "Одна из веток может обрабатывать вход неверно.",
                "hint": "Разберите вручную непройденный случай.",
                "line": "4",
            },
            {"severity": "error"},
        ],
        "failed_test_analysis": [1, "Стоит сопоставить ветвления с результатами тестов."],
        "edge_cases": None,
        "complexity": {"time": ["bad"], "memory": "O(1)"},
        "style": [{"text": "bad"}],
        "hardcode_warnings": [],
        "next_step": {"text": "bad"},
    }

    result = merge_model_response(model, static).model_dump()

    assert result["fallback_used"] is False
    assert result["strengths"] == static["strengths"]
    assert result["issues"][0]["severity"] == "warning"
    assert result["issues"][0]["line"] is None
    assert result["failed_test_analysis"] == ["Стоит сопоставить ветвления с результатами тестов."]
    assert result["complexity"]["time"] == static["complexity"]["time"]
    assert result["complexity"]["memory"] == "O(1)"
    assert result["next_step"] == static["next_step"]


def test_model_html_is_data_not_markup(monkeypatch) -> None:
    async def succeed(*_args, **_kwargs):
        return AnalyzeResponse(
            verdict="<script>alert(1)</script>", strengths=[], issues=[],
            failed_test_analysis=[], edge_cases=[],
            complexity={"time": "O(1)", "memory": "O(1)", "comment": "<b>x</b>"},
            style=[], hardcode_warnings=[], next_step="<img src=x onerror=alert(1)>", fallback_used=False,
        )

    monkeypatch.setattr(main, "analyze_with_ollama", succeed)
    body = client.post("/api/v1/analyze", json={"code": "print(1)"}).json()
    assert body["verdict"].startswith("<script>")
    assert body["next_step"].startswith("<img")
