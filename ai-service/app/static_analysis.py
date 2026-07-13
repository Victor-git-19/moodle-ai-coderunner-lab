import ast
from typing import Any, Literal

from .response_builder import response_data
from .schemas import AnalyzeRequest


def make_issue(
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


def attempt_verdict(request: AnalyzeRequest, issues: list[dict[str, Any]]) -> str:
    if request.timeout:
        return "CodeRunner остановил решение по времени; сначала найдите самую дорогую часть алгоритма."
    if request.memory_limit:
        return "CodeRunner сообщил о превышении памяти; проверьте объём создаваемых данных."
    if request.runtime_error:
        return "Решение запускается, но CodeRunner обнаружил ошибку времени выполнения."
    if request.failed_tests:
        return (
            f"Решение прошло {request.passed_tests} тестов и не прошло {request.failed_tests}; "
            "общий подход частично работает."
        )
    if request.passed_tests:
        return f"Решение прошло все доступные результаты тестов ({request.passed_tests})."
    if issues:
        return "Код разобран, но перед следующей проверкой стоит исправить найденные проблемы."
    return "Код синтаксически корректен; сравните его поведение с требованиями задания."


def failed_test_analysis(request: AnalyzeRequest) -> list[str]:
    result: list[str] = []
    if request.compiler_message:
        result.append("CodeRunner сообщил об ошибке компиляции или синтаксиса; проверку нужно начать с неё.")
    if request.runtime_error:
        result.append(
            "Ошибка времени выполнения может быть связана с неподходящим входом, индексом или преобразованием типа."
        )
    if request.timeout:
        result.append("Превышение времени указывает на слишком дорогой алгоритм или незавершающийся цикл.")
    if request.memory_limit:
        result.append("Превышение памяти может быть связано с накоплением лишних коллекций или ростом данных.")
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
        return "Составьте один крайний тест, на котором алгоритм ошибается, и пройдите его вручную."
    return "Повторно проверьте решение в CodeRunner на граничных входных данных."


def hardcode_warnings(nodes: list[ast.AST], request: AnalyzeRequest) -> list[str]:
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
        if isinstance(node, ast.Constant)
        and isinstance(node.value, (int, float, str))
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
    for parent in nodes:
        body = getattr(parent, "body", None)
        if not isinstance(body, list):
            continue
        if any(isinstance(statement, (ast.Return, ast.Raise)) for statement in body[:-1]):
            warnings.append("Стоит проверить код после return или raise: часть инструкций может быть недостижима.")
            break
    return warnings


def analyze_python(request: AnalyzeRequest) -> dict[str, Any]:
    try:
        tree = ast.parse(request.code)
    except SyntaxError as error:
        line = error.lineno
        return response_data(
            verdict="Решение пока нельзя проверить: в коде есть синтаксическая ошибка.",
            strengths=["Код получен и безопасно разобран без запуска."],
            issues=[make_issue(
                "error",
                "Синтаксическая ошибка",
                f"Интерпретатор не может разобрать строку {line or '?'}: {error.msg}.",
                "Проверьте скобки, двоеточия и структуру указанной строки.",
                line,
            )],
            failed_test_analysis=failed_test_analysis(request),
            edge_cases=["Минимальный допустимый ввод", "Пустой ввод, если он разрешён условием"],
            complexity={
                "time": "Не определена",
                "memory": "Не определена",
                "comment": "Сначала исправьте синтаксис.",
            },
            next_step=f"Исправьте синтаксис в строке {line or '?'} и снова запустите проверку CodeRunner.",
        )

    nodes = list(ast.walk(tree))
    loops = [node for node in nodes if isinstance(node, (ast.For, ast.While, ast.comprehension))]
    nested_loop = any(
        isinstance(child, (ast.For, ast.While, ast.comprehension))
        for loop in loops
        for child in ast.walk(loop)
        if child is not loop
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
        issues.append(make_issue(
            "warning",
            "Слишком широкая обработка ошибок",
            "except без типа скрывает разные причины ошибки.",
            "Укажите ожидаемый тип исключения и обработайте только его.",
        ))
    if any(name in {"eval", "exec"} for name in calls):
        issues.append(make_issue(
            "warning",
            "Опасная динамическая операция",
            "eval или exec выполняют строку как код и обычно не нужны в учебной задаче.",
            "Разберите ввод обычными операциями языка.",
        ))
    if len(request.code.splitlines()) > 40 and not functions:
        style.append("Длинный линейный фрагмент стоит разделить на небольшие функции.")

    return response_data(
        verdict=attempt_verdict(request, issues),
        strengths=strengths,
        issues=issues,
        failed_test_analysis=failed_test_analysis(request),
        edge_cases=["Пустой или минимальный ввод", "Отрицательные числа", "Повторяющиеся значения"],
        complexity={
            "time": "O(n²)" if nested_loop else ("O(n)" if loops else "O(1)"),
            "memory": (
                "O(n)"
                if any(isinstance(node, (ast.ListComp, ast.SetComp, ast.DictComp)) for node in nodes)
                else "O(1)"
            ),
            "comment": "Оценка получена статически по структуре циклов и создаваемых коллекций.",
        },
        style=style,
        hardcode_warnings=hardcode_warnings(nodes, request),
        next_step=next_step(request, issues),
    )


def analyze_code(request: AnalyzeRequest) -> dict[str, Any]:
    if request.language.lower() in {"python", "python3"}:
        return analyze_python(request)
    return response_data(
        verdict=attempt_verdict(request, []),
        strengths=["Код и результаты CodeRunner получены для анализа."],
        failed_test_analysis=failed_test_analysis(request),
        edge_cases=["Минимальный ввод", "Пустой ввод", "Граничные значения"],
        complexity={
            "time": "Не определена",
            "memory": "Не определена",
            "comment": "AST-анализ доступен только для Python.",
        },
        next_step=next_step(request, []),
    )
