<?php
// Course content kept as plain data so it can be reviewed and changed in Git.

return [
    'shortname' => 'PYTHON-CR-START',
    'fullname' => 'Основы Python: практика с CodeRunner',
    'summary' => <<<'HTML'
<p>Практический вводный курс по Python. После короткой теории в каждой теме студент решает задачи в CodeRunner, получает результат автоматических тестов и может запросить безопасную подсказку ИИ.</p>
HTML,
    'intro' => [
        'name' => 'Как проходить курс',
        'content' => <<<'HTML'
<h3>Как устроено обучение</h3>
<p>В курсе пять тем. Сначала прочитайте страницу с теорией, затем откройте практику и решите три задачи. Решение должно читать данные из стандартного ввода и печатать только требуемый результат.</p>
<ol>
  <li>Разберите примеры и ограничения задачи.</li>
  <li>Напишите программу в поле ответа.</li>
  <li>Нажмите «Проверить» и изучите доступные результаты тестов.</li>
  <li>Если решение не прошло проверку, нажмите «Проанализировать решение с помощью ИИ».</li>
  <li>Исправьте именно причину ошибки и отправьте новую версию.</li>
</ol>
<p>ИИ даёт направление, но окончательный ответ подтверждает только CodeRunner. Часть тестов скрыта: это проверяет, что алгоритм работает не только на примерах.</p>
<h3>Правила ответа</h3>
<ul>
  <li>Не добавляйте приглашения вроде <code>Введите число:</code>.</li>
  <li>Соблюдайте регистр и формат результата.</li>
  <li>Проверяйте крайние случаи до отправки.</li>
  <li>Не подбирайте ответы под примеры — вычисляйте результат для любых допустимых данных.</li>
</ul>
HTML,
    ],
    'sections' => [
        [
            'name' => '1. Ввод, переменные и арифметика',
            'summary' => 'Чтение данных, типы int и float, арифметические операции и формат вывода.',
            'theory' => [
                'name' => 'Теория: данные и вычисления',
                'content' => <<<'HTML'
<h3>Первая программа</h3>
<p>Python выполняет команды сверху вниз. Значение можно сохранить в переменной, а затем использовать в выражении:</p>
<pre><code>side = int(input())
area = side * side
print(area)</code></pre>
<p><code>input()</code> всегда возвращает строку. Для целого числа используйте <code>int(...)</code>, для дробного — <code>float(...)</code>. Несколько целых из одной строки удобно читать так:</p>
<pre><code>a, b = map(int, input().split())</code></pre>
<h3>Операции</h3>
<ul>
  <li><code>+</code>, <code>-</code>, <code>*</code> — сложение, вычитание и умножение;</li>
  <li><code>/</code> — обычное деление;</li>
  <li><code>//</code> и <code>%</code> — целая часть и остаток от деления;</li>
  <li><code>**</code> — возведение в степень.</li>
</ul>
<p>До отправки мысленно пройдите программу на маленьком примере и проверьте, что она печатает только ответ.</p>
HTML,
            ],
            'quiz' => [
                'name' => 'Практика 1: арифметика',
                'intro' => 'Три небольшие программы на чтение данных и вычисления. Все задачи оцениваются автоматически.',
            ],
            'tasks' => [
                [
                    'name' => '01. Квадрат числа',
                    'description' => 'Дано целое число. Выведите его квадрат.',
                    'input' => 'Одно целое число n, −10 000 ≤ n ≤ 10 000.',
                    'output' => 'Одно целое число n².',
                    'answer' => "n = int(input())\nprint(n * n)",
                    'tests' => [
                        ['stdin' => "5\n", 'expected' => "25\n", 'visible' => true],
                        ['stdin' => "-7\n", 'expected' => "49\n", 'visible' => true],
                        ['stdin' => "0\n", 'expected' => "0\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '02. Прямоугольник',
                    'description' => 'Даны длина и ширина прямоугольника. Выведите его площадь и периметр через пробел.',
                    'input' => 'Два положительных целых числа a и b в одной строке, каждое не больше 10 000.',
                    'output' => 'Площадь и периметр прямоугольника через пробел.',
                    'answer' => "a, b = map(int, input().split())\nprint(a * b, 2 * (a + b))",
                    'tests' => [
                        ['stdin' => "3 5\n", 'expected' => "15 16\n", 'visible' => true],
                        ['stdin' => "1 1\n", 'expected' => "1 4\n", 'visible' => true],
                        ['stdin' => "12 7\n", 'expected' => "84 38\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '03. Минуты в часы',
                    'description' => 'Переведите заданное количество минут в полные часы и оставшиеся минуты.',
                    'input' => 'Одно неотрицательное целое число m, m ≤ 1 000 000.',
                    'output' => 'Количество полных часов и оставшихся минут через пробел.',
                    'answer' => "minutes = int(input())\nprint(minutes // 60, minutes % 60)",
                    'tests' => [
                        ['stdin' => "135\n", 'expected' => "2 15\n", 'visible' => true],
                        ['stdin' => "59\n", 'expected' => "0 59\n", 'visible' => true],
                        ['stdin' => "1440\n", 'expected' => "24 0\n", 'visible' => false],
                    ],
                ],
            ],
        ],
        [
            'name' => '2. Условия',
            'summary' => 'Сравнения, логические выражения и выбор одной из нескольких ветвей.',
            'theory' => [
                'name' => 'Теория: условия и границы',
                'content' => <<<'HTML'
<h3>Выбор действия</h3>
<p>Условие позволяет программе выбрать ветвь по данным:</p>
<pre><code>temperature = int(input())
if temperature &lt; 0:
    print("FROST")
elif temperature == 0:
    print("ZERO")
else:
    print("WARM")</code></pre>
<p>Отступы являются частью синтаксиса Python. В одной цепочке <code>if / elif / else</code> выполняется только первая подходящая ветвь.</p>
<h3>Логические выражения</h3>
<p>Сравнения можно объединять словами <code>and</code>, <code>or</code>, <code>not</code>. Например, <code>1 &lt;= month &lt;= 12</code> проверяет диапазон. Границы особенно важны: ноль, равные значения и переход между соседними диапазонами часто обнаруживают ошибку.</p>
<p>Перед отправкой составьте по одному тесту для каждой ветви и отдельно проверьте значения на границах.</p>
HTML,
            ],
            'quiz' => [
                'name' => 'Практика 2: условия',
                'intro' => 'Используйте условия и внимательно обработайте равные и граничные значения.',
            ],
            'tasks' => [
                [
                    'name' => '04. Знак числа',
                    'description' => 'Определите знак целого числа. Выведите POSITIVE, NEGATIVE или ZERO.',
                    'input' => 'Одно целое число.',
                    'output' => 'Одно из слов POSITIVE, NEGATIVE или ZERO.',
                    'answer' => <<<'PY'
n = int(input())
if n > 0:
    print("POSITIVE")
elif n < 0:
    print("NEGATIVE")
else:
    print("ZERO")
PY,
                    'tests' => [
                        ['stdin' => "12\n", 'expected' => "POSITIVE\n", 'visible' => true],
                        ['stdin' => "-3\n", 'expected' => "NEGATIVE\n", 'visible' => true],
                        ['stdin' => "0\n", 'expected' => "ZERO\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '05. Максимум из трёх',
                    'description' => 'Найдите наибольшее из трёх целых чисел. Встроенную функцию max использовать можно.',
                    'input' => 'Три целых числа в одной строке.',
                    'output' => 'Наибольшее число.',
                    'answer' => "a, b, c = map(int, input().split())\nprint(max(a, b, c))",
                    'tests' => [
                        ['stdin' => "4 9 2\n", 'expected' => "9\n", 'visible' => true],
                        ['stdin' => "-5 -2 -11\n", 'expected' => "-2\n", 'visible' => true],
                        ['stdin' => "7 7 3\n", 'expected' => "7\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '06. Високосный год',
                    'description' => 'Год високосный, если делится на 400 или делится на 4, но не на 100. Выведите YES или NO.',
                    'input' => 'Одно положительное целое число — год.',
                    'output' => 'YES для високосного года, иначе NO.',
                    'answer' => <<<'PY'
year = int(input())
if year % 400 == 0 or (year % 4 == 0 and year % 100 != 0):
    print("YES")
else:
    print("NO")
PY,
                    'tests' => [
                        ['stdin' => "2024\n", 'expected' => "YES\n", 'visible' => true],
                        ['stdin' => "1900\n", 'expected' => "NO\n", 'visible' => true],
                        ['stdin' => "2000\n", 'expected' => "YES\n", 'visible' => false],
                    ],
                ],
            ],
        ],
        [
            'name' => '3. Циклы',
            'summary' => 'Повторение действий, накопление результата и оценка числа операций.',
            'theory' => [
                'name' => 'Теория: циклы и накопители',
                'content' => <<<'HTML'
<h3>Цикл <code>for</code></h3>
<p><code>range(start, stop, step)</code> создаёт последовательность, не включая <code>stop</code>:</p>
<pre><code>total = 0
for number in range(1, 6):
    total += number
print(total)  # 15</code></pre>
<p>Переменная <code>total</code> — накопитель. Для подсчёта объектов обычно начинают с нуля и увеличивают счётчик только при выполнении условия.</p>
<h3>Цикл <code>while</code></h3>
<p><code>while</code> подходит, когда заранее неизвестно количество повторений. Внутри обязательно должно изменяться состояние, от которого зависит условие, иначе цикл станет бесконечным.</p>
<p>Один проход по <code>n</code> значениям обычно имеет сложность <code>O(n)</code>. Вложенный полный цикл часто даёт <code>O(n²)</code>. Для начала важнее получить правильный и понятный алгоритм, но число операций нужно уметь оценить.</p>
HTML,
            ],
            'quiz' => [
                'name' => 'Практика 3: циклы',
                'intro' => 'Организуйте повторение и аккуратно выберите начальное значение накопителя.',
            ],
            'tasks' => [
                [
                    'name' => '07. Сумма чётных',
                    'description' => 'Найдите сумму всех чётных чисел от 1 до n включительно.',
                    'input' => 'Одно положительное целое число n, n ≤ 1 000 000.',
                    'output' => 'Сумма чётных чисел от 1 до n.',
                    'answer' => <<<'PY'
n = int(input())
total = 0
for number in range(2, n + 1, 2):
    total += number
print(total)
PY,
                    'tests' => [
                        ['stdin' => "7\n", 'expected' => "12\n", 'visible' => true],
                        ['stdin' => "2\n", 'expected' => "2\n", 'visible' => true],
                        ['stdin' => "100\n", 'expected' => "2550\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '08. Количество цифр',
                    'description' => 'Определите количество цифр в записи целого числа. Знак минус цифрой не считается.',
                    'input' => 'Одно целое число n, |n| ≤ 10¹⁸.',
                    'output' => 'Количество цифр. У числа 0 одна цифра.',
                    'answer' => <<<'PY'
n = abs(int(input()))
count = 1
while n >= 10:
    n //= 10
    count += 1
print(count)
PY,
                    'tests' => [
                        ['stdin' => "58321\n", 'expected' => "5\n", 'visible' => true],
                        ['stdin' => "-90\n", 'expected' => "2\n", 'visible' => true],
                        ['stdin' => "0\n", 'expected' => "1\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '09. Таблица умножения',
                    'description' => 'Для числа n выведите десять строк: произведения n на числа от 1 до 10.',
                    'input' => 'Одно целое число n.',
                    'output' => 'Десять произведений, каждое в новой строке.',
                    'answer' => <<<'PY'
n = int(input())
for multiplier in range(1, 11):
    print(n * multiplier)
PY,
                    'tests' => [
                        ['stdin' => "2\n", 'expected' => "2\n4\n6\n8\n10\n12\n14\n16\n18\n20\n", 'visible' => true],
                        ['stdin' => "0\n", 'expected' => "0\n0\n0\n0\n0\n0\n0\n0\n0\n0\n", 'visible' => true],
                        ['stdin' => "-3\n", 'expected' => "-3\n-6\n-9\n-12\n-15\n-18\n-21\n-24\n-27\n-30\n", 'visible' => false],
                    ],
                ],
            ],
        ],
        [
            'name' => '4. Списки и строки',
            'summary' => 'Обработка последовательностей, уникальные значения, поиск и нормализация текста.',
            'theory' => [
                'name' => 'Теория: коллекции и текст',
                'content' => <<<'HTML'
<h3>Списки</h3>
<p>Список хранит упорядоченную последовательность. Индексация начинается с нуля, отрицательные индексы считают элементы с конца:</p>
<pre><code>numbers = list(map(int, input().split()))
print(numbers[0], numbers[-1])</code></pre>
<p><code>len</code> возвращает длину, <code>sum</code> — сумму, <code>sorted</code> создаёт отсортированный список. Множество <code>set</code> хранит только уникальные значения, но не должно использоваться, если важен исходный порядок.</p>
<h3>Строки</h3>
<p>Строка тоже является последовательностью. Методы <code>lower()</code>, <code>strip()</code>, <code>split()</code> помогают нормализовать ввод. Строки неизменяемы: метод возвращает новое значение.</p>
<p>Всегда спрашивайте себя: возможны ли пустая последовательность, повторения, отрицательные числа, пробелы или разный регистр? Это типичные крайние случаи.</p>
HTML,
            ],
            'quiz' => [
                'name' => 'Практика 4: списки и строки',
                'intro' => 'Обрабатывайте последовательность целиком и отдельно подумайте о повторениях и пустых результатах.',
            ],
            'tasks' => [
                [
                    'name' => '10. Второе уникальное число',
                    'description' => 'Выведите второе по величине уникальное число. Если различных чисел меньше двух, выведите NO.',
                    'input' => 'В одной строке от 1 до 10 000 целых чисел.',
                    'output' => 'Второе по величине уникальное число или NO.',
                    'answer' => <<<'PY'
values = list(map(int, input().split()))
unique = sorted(set(values))
print(unique[-2] if len(unique) >= 2 else "NO")
PY,
                    'tests' => [
                        ['stdin' => "5 1 5 3 4\n", 'expected' => "4\n", 'visible' => true],
                        ['stdin' => "7 7 7\n", 'expected' => "NO\n", 'visible' => true],
                        ['stdin' => "-5 -2 -9 -2\n", 'expected' => "-5\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '11. Палиндром',
                    'description' => 'Проверьте, одинаково ли строка читается слева направо и справа налево, если не учитывать пробелы и регистр.',
                    'input' => 'Одна непустая строка из латинских букв и пробелов.',
                    'output' => 'YES, если нормализованная строка — палиндром, иначе NO.',
                    'answer' => <<<'PY'
text = input().replace(" ", "").lower()
print("YES" if text == text[::-1] else "NO")
PY,
                    'tests' => [
                        ['stdin' => "Never odd or even\n", 'expected' => "YES\n", 'visible' => true],
                        ['stdin' => "Python\n", 'expected' => "NO\n", 'visible' => true],
                        ['stdin' => "A b C c b a\n", 'expected' => "YES\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '12. Положительные и отрицательные',
                    'description' => 'Посчитайте положительные элементы и найдите сумму отрицательных. Нули не относятся ни к одной группе.',
                    'input' => 'В одной строке от 1 до 10 000 целых чисел.',
                    'output' => 'Количество положительных и сумму отрицательных через пробел.',
                    'answer' => <<<'PY'
numbers = list(map(int, input().split()))
positive_count = sum(number > 0 for number in numbers)
negative_sum = sum(number for number in numbers if number < 0)
print(positive_count, negative_sum)
PY,
                    'tests' => [
                        ['stdin' => "3 -2 0 7 -5\n", 'expected' => "2 -7\n", 'visible' => true],
                        ['stdin' => "0 0 4\n", 'expected' => "1 0\n", 'visible' => true],
                        ['stdin' => "-1 -2 -3\n", 'expected' => "0 -6\n", 'visible' => false],
                    ],
                ],
            ],
        ],
        [
            'name' => '5. Простые алгоритмы',
            'summary' => 'Разбиение решения на шаги, функции, делители и словари частот.',
            'theory' => [
                'name' => 'Теория: алгоритм и функция',
                'content' => <<<'HTML'
<h3>Сначала алгоритм</h3>
<p>До кода сформулируйте последовательность действий и определите, какие данные нужно хранить. Хороший алгоритм обрабатывает весь допустимый диапазон, а не только примеры.</p>
<h3>Функции</h3>
<p>Функция именует отдельную операцию и возвращает результат:</p>
<pre><code>def is_even(number):
    return number % 2 == 0

value = int(input())
print(is_even(value))</code></pre>
<p>Понятные имена и небольшие функции упрощают проверку. Для подсчёта частот удобно использовать словарь: ключом будет значение, а значением — число его появлений.</p>
<h3>Проверка решения</h3>
<ul>
  <li>пройдите алгоритм вручную на обычном примере;</li>
  <li>проверьте минимальное и максимальное значение;</li>
  <li>проверьте повторения и равенство кандидатов;</li>
  <li>оцените время и память;</li>
  <li>убедитесь, что в коде нет ветвей под конкретные примеры.</li>
</ul>
HTML,
            ],
            'quiz' => [
                'name' => 'Практика 5: итоговые алгоритмы',
                'intro' => 'Итоговая практика: примените циклы, условия, функции и коллекции в одном решении.',
            ],
            'tasks' => [
                [
                    'name' => '13. Наибольший общий делитель',
                    'description' => 'Найдите наибольший общий делитель двух положительных целых чисел алгоритмом Евклида.',
                    'input' => 'Два положительных целых числа a и b, каждое не больше 10⁹.',
                    'output' => 'Наибольший общий делитель a и b.',
                    'answer' => <<<'PY'
a, b = map(int, input().split())
while b != 0:
    a, b = b, a % b
print(a)
PY,
                    'tests' => [
                        ['stdin' => "48 18\n", 'expected' => "6\n", 'visible' => true],
                        ['stdin' => "17 13\n", 'expected' => "1\n", 'visible' => true],
                        ['stdin' => "270 192\n", 'expected' => "6\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '14. Простое число',
                    'description' => 'Определите, является ли число простым. Простое число больше 1 и имеет ровно два положительных делителя.',
                    'input' => 'Одно целое число n, 0 ≤ n ≤ 10⁹.',
                    'output' => 'YES, если n простое, иначе NO.',
                    'answer' => <<<'PY'
n = int(input())
is_prime = n >= 2
divisor = 2
while divisor * divisor <= n and is_prime:
    if n % divisor == 0:
        is_prime = False
    divisor += 1
print("YES" if is_prime else "NO")
PY,
                    'tests' => [
                        ['stdin' => "29\n", 'expected' => "YES\n", 'visible' => true],
                        ['stdin' => "1\n", 'expected' => "NO\n", 'visible' => true],
                        ['stdin' => "99991\n", 'expected' => "YES\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '15. Самое частое слово',
                    'description' => 'Найдите самое частое слово без учёта регистра. При равной частоте выведите лексикографически меньшее слово.',
                    'input' => 'Одна строка из латинских слов, разделённых пробелами. Строка может быть пустой.',
                    'output' => 'Искомое слово в нижнем регистре. Для пустой строки выведите EMPTY.',
                    'answer' => <<<'PY'
import sys

words = sys.stdin.read().lower().split()
if not words:
    print("EMPTY")
else:
    counts = {}
    for word in words:
        counts[word] = counts.get(word, 0) + 1
    best = min(counts, key=lambda word: (-counts[word], word))
    print(best)
PY,
                    'tests' => [
                        ['stdin' => "Cat dog cat bird\n", 'expected' => "cat\n", 'visible' => true],
                        ['stdin' => "pear Apple pear apple\n", 'expected' => "apple\n", 'visible' => true],
                        ['stdin' => "\n", 'expected' => "EMPTY\n", 'visible' => false],
                    ],
                ],
            ],
        ],
    ],
];
