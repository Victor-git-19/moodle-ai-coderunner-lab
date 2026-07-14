"""Pydantic-схемы единственного запроса и ответа AI service."""

from typing import Literal

from pydantic import BaseModel, Field


class TestResult(BaseModel):
    """Безопасная часть результата одного теста CodeRunner."""

    number: int = Field(ge=1)
    passed: bool
    hidden: bool = False
    error_type: str = Field(default="", max_length=50)
    message: str = Field(default="", max_length=2000)
    visible_output: dict[str, str] = Field(default_factory=dict)


class AnalyzeRequest(BaseModel):
    """Данные попытки, которые Moodle разрешает отправить на анализ."""

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
    """Одна найденная проблема и подсказка без готового решения."""

    severity: Literal["error", "warning", "info"]
    title: str
    explanation: str
    hint: str
    line: int | None = None


class Complexity(BaseModel):
    """Оценка времени и памяти алгоритма."""

    time: str
    memory: str
    comment: str


class AnalyzeResponse(BaseModel):
    """Структурированный преподавательский ответ для Moodle."""

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
