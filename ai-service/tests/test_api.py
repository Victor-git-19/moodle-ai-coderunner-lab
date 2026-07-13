from fastapi.testclient import TestClient

from app import main

client = TestClient(main.app)


def fallback(monkeypatch) -> None:
    async def fail(*_args, **_kwargs):
        raise ValueError("model is unavailable")

    monkeypatch.setattr(main, "query_ollama", fail)


def analyze(monkeypatch, **overrides):
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


def test_possible_hardcode_is_not_asserted_as_fact(monkeypatch) -> None:
    body = analyze(monkeypatch, code="print(42)", passed_tests=1, failed_tests=2).json()
    warning = body["hardcode_warnings"][0]
    assert "признаки возможного хардкода" in warning
    assert "доказан" not in warning


def test_valid_model_response(monkeypatch) -> None:
    async def succeed(*_args, **_kwargs):
        return main.AnalyzeResponse(
            verdict="Подход верный.", strengths=["Ввод обработан."], issues=[],
            failed_test_analysis=[], edge_cases=["Ноль"],
            complexity={"time": "O(1)", "memory": "O(1)", "comment": "Без циклов."},
            style=[], hardcode_warnings=[], next_step="Проверьте ноль.", fallback_used=False,
        )

    monkeypatch.setattr(main, "query_ollama", succeed)
    response = client.post("/api/v1/analyze", json={"code": "print(1)"})
    assert response.status_code == 200
    assert response.json()["fallback_used"] is False


def test_model_result_keeps_coderunner_context() -> None:
    static = main.static_analysis(main.AnalyzeRequest(
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
    enriched = main.enrich_model_result(model, static)
    assert "1 тестов" in enriched["verdict"]
    assert enriched["failed_test_analysis"]
    assert enriched["hardcode_warnings"]


def test_model_html_is_data_not_markup(monkeypatch) -> None:
    async def succeed(*_args, **_kwargs):
        return main.AnalyzeResponse(
            verdict="<script>alert(1)</script>", strengths=[], issues=[],
            failed_test_analysis=[], edge_cases=[],
            complexity={"time": "O(1)", "memory": "O(1)", "comment": "<b>x</b>"},
            style=[], hardcode_warnings=[], next_step="<img src=x onerror=alert(1)>", fallback_used=False,
        )

    monkeypatch.setattr(main, "query_ollama", succeed)
    body = client.post("/api/v1/analyze", json={"code": "print(1)"}).json()
    assert body["verdict"].startswith("<script>")
    assert body["next_step"].startswith("<img")
