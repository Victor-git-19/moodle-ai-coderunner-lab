"""Безопасный статический анализ Python через стандартный модуль ast."""

import ast
import re
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
    """Собрать одну проблему в формате ответа API."""

    return {
        "severity": severity,
        "title": title,
        "explanation": explanation,
        "hint": hint,
        "line": line,
    }


def attempt_verdict(request: AnalyzeRequest, issues: list[dict[str, Any]]) -> str:
    """Сформулировать общий вывод прежде всего по результатам CodeRunner."""

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
    """Объяснить возможные причины неудачи без раскрытия тестов."""

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
    """Выбрать одно конкретное следующее действие для студента."""

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
    """Найти только признаки возможного хардкода, не объявляя его доказанным."""

    # ast.walk читает структуру программы, но никогда не запускает код студента.
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


def syntax_error_response(request: AnalyzeRequest, error: SyntaxError) -> dict[str, Any]:
    """Сформировать понятный ответ для кода, который не удалось разобрать."""

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


def estimate_complexity(nodes: list[ast.AST]) -> dict[str, str]:
    """Грубо оценить сложность по вложенным циклам и создаваемым коллекциям."""

    loops = [node for node in nodes if isinstance(node, (ast.For, ast.While, ast.comprehension))]
    nested_loop = any(
        isinstance(child, (ast.For, ast.While, ast.comprehension))
        for loop in loops
        for child in ast.walk(loop)
        if child is not loop
    )
    creates_collection = any(
        isinstance(node, (ast.ListComp, ast.SetComp, ast.DictComp)) for node in nodes
    )
    return {
        "time": "O(n²)" if nested_loop else ("O(n)" if loops else "O(1)"),
        "memory": "O(n)" if creates_collection else "O(1)",
        "comment": "Оценка получена статически по структуре циклов и создаваемых коллекций.",
    }


def structural_requirements(task: str) -> dict[str, Any]:
    """Извлечь из условия явно указанную функцию, корутину, класс и API."""

    async_match = re.search(r"\basync\s+def\s+([A-Za-z_]\w*)", task)
    function_match = re.search(r"(?<!async\s)\bdef\s+([A-Za-z_]\w*)", task)
    class_match = re.search(r"(?:класс|class)\s+([A-Za-z_]\w*)", task, re.IGNORECASE)
    workers_match = re.search(r"max_workers\s*=\s*(\d+)", task)
    known_apis = ("asyncio.gather", "ThreadPoolExecutor", "ProcessPoolExecutor")
    return {
        "async_function": async_match.group(1) if async_match else None,
        "function": function_match.group(1) if function_match else None,
        "class": class_match.group(1) if class_match else None,
        "apis": [name for name in known_apis if name.lower() in task.lower()],
        "max_workers": int(workers_match.group(1)) if workers_match else None,
        "float": bool(re.search(r"веществен|\bfloat\b|погрешност", task, re.IGNORECASE)),
    }


def structural_issues(
    nodes: list[ast.AST],
    requirements: dict[str, Any],
) -> list[dict[str, Any]]:
    """Проверить обязательную структуру задания, не запуская код студента."""

    issues: list[dict[str, Any]] = []
    functions = {
        node.name: node
        for node in nodes
        if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef))
    }
    classes = {node.name: node for node in nodes if isinstance(node, ast.ClassDef)}
    names = {
        node.id for node in nodes if isinstance(node, ast.Name)
    } | {
        node.attr for node in nodes if isinstance(node, ast.Attribute)
    }

    function_name = requirements["function"]
    if function_name and function_name not in functions:
        issues.append(make_issue(
            "error",
            "Требуется функция",
            f"В условии требуется функция {function_name}, объявленная через def.",
            f"Объявите def {function_name}(...) и верните результат через return.",
        ))

    async_name = requirements["async_function"]
    async_node = functions.get(async_name) if async_name else None
    if async_name and not isinstance(async_node, ast.AsyncFunctionDef):
        issues.append(make_issue(
            "error",
            "Требуется корутина",
            f"В условии требуется корутина {async_name}, объявленная через async def.",
            f"Объявите async def {async_name}(...) и верните результат.",
        ))

    class_name = requirements["class"]
    if class_name and class_name not in classes:
        issues.append(make_issue(
            "error",
            "Требуется класс",
            f"В условии требуется класс {class_name}, но его объявление не найдено.",
            f"Объявите class {class_name} и добавьте методы из условия.",
        ))

    for api_name in requirements["apis"]:
        short_name = api_name.rsplit(".", 1)[-1]
        if short_name not in names:
            issues.append(make_issue(
                "error",
                "Не использован обязательный API",
                f"Условие требует использовать {api_name}, но такого обращения в коде не найдено.",
                f"Организуйте вычисления через {api_name}, как указано в условии.",
            ))
    required_workers = requirements["max_workers"]
    has_worker_limit = any(
        isinstance(node, ast.Call) and any(
            keyword.arg == "max_workers"
            and isinstance(keyword.value, ast.Constant)
            and keyword.value.value == required_workers
            for keyword in node.keywords
        )
        for node in nodes
    )
    if required_workers is not None and not has_worker_limit:
        issues.append(make_issue(
            "error",
            "Не ограничено число работников",
            f"Условие требует max_workers={required_workers}, чтобы не перегружать учебный сервер.",
            f"Передайте max_workers={required_workers} при создании исполнителя.",
        ))
    return issues


def analyze_python(request: AnalyzeRequest) -> dict[str, Any]:
    """Разобрать Python-код и оценить его структуру без выполнения."""

    try:
        tree = ast.parse(request.code)
    except SyntaxError as error:
        return syntax_error_response(request, error)

    # Один плоский список узлов упрощает последующие небольшие проверки.
    nodes = list(ast.walk(tree))
    functions = [node.name for node in nodes if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef))]
    classes = [node.name for node in nodes if isinstance(node, ast.ClassDef)]
    calls = [node.func.id for node in nodes if isinstance(node, ast.Call) and isinstance(node.func, ast.Name)]
    requirements = structural_requirements(request.task)

    strengths: list[str] = []
    issues = structural_issues(nodes, requirements)
    style: list[str] = []
    if "input" in calls:
        strengths.append("Решение читает пользовательский ввод, а не использует только фиксированные данные.")
    if "print" in calls:
        strengths.append("Решение формирует вывод программы.")
    if functions:
        strengths.append("Логика разделена на функции: " + ", ".join(functions[:4]) + ".")
    if classes:
        strengths.append("Объявлены классы: " + ", ".join(classes[:3]) + ".")
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

    edge_cases = ["Пустой или минимальный ввод", "Отрицательные числа", "Повторяющиеся значения"]
    if requirements["float"]:
        edge_cases = [
            "Нулевые и отрицательные вещественные значения, если они разрешены",
            "Очень маленькие и большие значения",
            "Погрешность вычислений с float без лишнего округления",
        ]

    return response_data(
        verdict=attempt_verdict(request, issues),
        strengths=strengths,
        issues=issues,
        failed_test_analysis=failed_test_analysis(request),
        edge_cases=edge_cases,
        complexity=estimate_complexity(nodes),
        style=style,
        hardcode_warnings=hardcode_warnings(nodes, request),
        next_step=next_step(request, issues),
    )


def analyze_code(request: AnalyzeRequest) -> dict[str, Any]:
    """Выбрать AST-анализ Python или общий разбор результатов CodeRunner."""

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
