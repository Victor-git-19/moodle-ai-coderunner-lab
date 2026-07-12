from fastapi.testclient import TestClient

from app import main

client = TestClient(main.app)


def test_health() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json()["status"] == "ok"


def test_empty_code_is_rejected() -> None:
    response = client.post(
        "/api/v1/analyze",
        json={"language": "python", "task": "Test", "code": "   "},
    )
    assert response.status_code == 400


def test_large_code_is_rejected(monkeypatch) -> None:
    monkeypatch.setattr(main, "MAX_CODE_SIZE", 3)
    response = client.post(
        "/api/v1/analyze",
        json={"language": "python", "task": "Test", "code": "print(1)"},
    )
    assert response.status_code == 413


def test_static_fallback_when_ollama_fails(monkeypatch) -> None:
    async def fail(*_args, **_kwargs):
        raise ValueError("model is unavailable")

    monkeypatch.setattr(main, "query_ollama", fail)
    response = client.post(
        "/api/v1/analyze",
        json={
            "language": "python",
            "task": "Вывести квадрат числа",
            "code": "n = int(input())\nprint(n * n)",
        },
    )
    body = response.json()
    assert response.status_code == 200
    assert body["fallback_used"] is True
    assert body["complexity"] == "O(1)"


def test_syntax_error_is_reported(monkeypatch) -> None:
    async def fail(*_args, **_kwargs):
        raise ValueError("model is unavailable")

    monkeypatch.setattr(main, "query_ollama", fail)
    response = client.post(
        "/api/v1/analyze",
        json={"language": "python", "task": "Test", "code": "if True print('x')"},
    )
    body = response.json()
    assert response.status_code == 200
    assert body["issues"]
    assert "Синтаксическая ошибка" in body["issues"][0]


def test_valid_model_response(monkeypatch) -> None:
    async def succeed(*_args, **_kwargs):
        return {
            "summary": "Код выводит число.",
            "issues": [],
            "suggestions": [],
            "complexity": "O(1)",
        }

    monkeypatch.setattr(main, "query_ollama", succeed)
    response = client.post(
        "/api/v1/analyze",
        json={"language": "python", "task": "Print", "code": "print(1)"},
    )
    assert response.status_code == 200
    assert response.json()["fallback_used"] is False
