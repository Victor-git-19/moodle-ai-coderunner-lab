<?php
// Содержимое курса хранится как обычный массив: его легко читать и менять в Git.

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
<p>В курсе восемь тем. Сначала прочитайте страницу с теорией, затем откройте практику и решите три задачи. В первых темах нужно написать программу, а в следующих — объявить указанную функцию или класс.</p>
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
            'summary' => 'Разбиение решения на шаги, делители, оценка сложности и словари частот.',
            'theory' => [
                'name' => 'Теория: простые алгоритмы',
                'content' => <<<'HTML'
<h3>Сначала алгоритм</h3>
<p>До кода сформулируйте последовательность действий и определите, какие данные нужно хранить. Хороший алгоритм обрабатывает весь допустимый диапазон, а не только примеры.</p>
<h3>Выбор структуры данных</h3>
<p>Для подсчёта частот удобно использовать словарь: ключом будет значение, а значением — число его появлений. Для поиска делителей часто достаточно проверять числа только до квадратного корня.</p>
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
                'intro' => 'Итоговая практика: примените циклы, условия и коллекции в одном решении.',
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
        [
            'name' => '6. Функции и вещественные числа',
            'summary' => 'Объявление функций, возврат результата и корректная работа с вещественными числами.',
            'theory' => [
                'name' => 'Теория: функции и точность вычислений',
                'content' => <<<'HTML'
<h3>Функция как часть программы</h3>
<p>Функция получает аргументы, выполняет одну понятную операцию и возвращает результат:</p>
<pre><code>def rectangle_area(width, height):
    return width * height</code></pre>
<p>В заданиях этого раздела CodeRunner сам вызывает функцию. Поэтому не нужно читать <code>input()</code> и печатать ответ. Нужно объявить функцию через <code>def</code> с точным именем из условия и вернуть значение через <code>return</code>.</p>
<h3>Вещественные числа</h3>
<p>Числа типа <code>float</code> хранятся с конечной точностью. Например, результат некоторых вычислений может отличаться от математического значения на очень малую величину. Тесты курса сравнивают такие результаты с допустимой погрешностью.</p>
<p>Проверяйте ноль, отрицательные значения, дробные аргументы и числа разного масштаба. Не округляйте результат без требования условия.</p>
HTML,
            ],
            'quiz' => [
                'name' => 'Практика 6: функции и float',
                'intro' => 'Объявите функции с указанными именами. CodeRunner вызовет их с целыми и вещественными аргументами.',
            ],
            'tasks' => [
                [
                    'name' => '16. Площадь круга',
                    'mode' => 'function',
                    'required_function' => 'circle_area',
                    'description' => 'Объявите функцию circle_area(radius), которая возвращает площадь круга.',
                    'input' => 'Аргумент radius — неотрицательное число.',
                    'output' => 'Вещественное число math.pi * radius ** 2.',
                    'answer' => <<<'PY'
import math

def circle_area(radius):
    return math.pi * radius ** 2
PY,
                    'invalid_answer' => "import math\nprint(math.pi * 4)",
                    'tests' => [
                        [
                            'testcode' => "import math\nprint(math.isclose(circle_area(2.0), 4.0 * math.pi, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nprint(math.isclose(circle_area(0.5), 0.25 * math.pi, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nprint(math.isclose(circle_area(0.0), 0.0, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => false,
                        ],
                    ],
                ],
                [
                    'name' => '17. Среднее значение',
                    'mode' => 'function',
                    'required_function' => 'average',
                    'description' => 'Объявите функцию average(values), которая возвращает среднее арифметическое непустого списка.',
                    'input' => 'Аргумент values — непустой список целых или вещественных чисел.',
                    'output' => 'Среднее арифметическое элементов без лишнего округления.',
                    'answer' => <<<'PY'
def average(values):
    return sum(values) / len(values)
PY,
                    'tests' => [
                        [
                            'testcode' => "import math\nprint(math.isclose(average([2, 4, 9]), 5.0, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nprint(math.isclose(average([-1.5, 0.5, 4.0]), 1.0, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nprint(math.isclose(average([0.1, 0.2]), 0.15, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => false,
                        ],
                    ],
                ],
                [
                    'name' => '18. Линейное уравнение',
                    'mode' => 'function',
                    'required_function' => 'solve_linear',
                    'description' => 'Объявите функцию solve_linear(a, b), которая решает уравнение a * x + b = 0.',
                    'input' => 'Аргументы a и b — целые или вещественные числа, a не равно нулю.',
                    'output' => 'Вещественный корень уравнения.',
                    'answer' => <<<'PY'
def solve_linear(a, b):
    return -b / a
PY,
                    'tests' => [
                        [
                            'testcode' => "import math\nprint(math.isclose(solve_linear(2, -5), 2.5, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nprint(math.isclose(solve_linear(-0.5, 2), 4.0, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nprint(math.isclose(solve_linear(1e-6, 3e-6), -3.0, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => '7. Классы',
            'summary' => 'Состояние объекта, конструктор, методы и совместная работа нескольких операций.',
            'theory' => [
                'name' => 'Теория: классы и объекты',
                'content' => <<<'HTML'
<h3>Описание объекта</h3>
<p>Класс объединяет данные и операции над ними:</p>
<pre><code>class Rectangle:
    def __init__(self, width, height):
        self.width = width
        self.height = height

    def area(self):
        return self.width * self.height</code></pre>
<p><code>__init__</code> задаёт начальное состояние. Первый аргумент метода — <code>self</code>, через него метод обращается к полям конкретного объекта.</p>
<p>В заданиях CodeRunner создаёт объекты и вызывает их методы. Не добавляйте <code>input()</code> и демонстрационный вывод. Объявите только требуемый класс и его методы.</p>
HTML,
            ],
            'quiz' => [
                'name' => 'Практика 7: классы',
                'intro' => 'Реализуйте три небольших класса. Тесты проверяют создание объектов, изменение состояния и вещественные значения.',
            ],
            'tasks' => [
                [
                    'name' => '19. Прямоугольник как объект',
                    'mode' => 'class',
                    'required_class' => 'Rectangle',
                    'description' => 'Объявите класс Rectangle(width, height) с методами area() и perimeter().',
                    'input' => 'Ширина и высота передаются конструктору и могут быть вещественными.',
                    'output' => 'area() возвращает площадь, perimeter() — периметр.',
                    'answer' => <<<'PY'
class Rectangle:
    def __init__(self, width, height):
        self.width = width
        self.height = height

    def area(self):
        return self.width * self.height

    def perimeter(self):
        return 2 * (self.width + self.height)
PY,
                    'tests' => [
                        [
                            'testcode' => "import math\nshape = Rectangle(3, 5)\nprint(math.isclose(shape.area(), 15.0) and math.isclose(shape.perimeter(), 16.0))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nshape = Rectangle(2.5, 0.4)\nprint(math.isclose(shape.area(), 1.0) and math.isclose(shape.perimeter(), 5.8))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nshape = Rectangle(0.0, 7.25)\nprint(math.isclose(shape.area(), 0.0) and math.isclose(shape.perimeter(), 14.5))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => false,
                        ],
                    ],
                ],
                [
                    'name' => '20. Банковский счёт',
                    'mode' => 'class',
                    'required_class' => 'BankAccount',
                    'description' => 'Объявите класс BankAccount(balance=0). Методы deposit(amount) и withdraw(amount) изменяют баланс. withdraw возвращает False и не меняет баланс, если денег недостаточно; иначе возвращает True.',
                    'input' => 'Начальный баланс и суммы операций — неотрицательные числа.',
                    'output' => 'Текущий баланс хранится в поле balance.',
                    'answer' => <<<'PY'
class BankAccount:
    def __init__(self, balance=0):
        self.balance = balance

    def deposit(self, amount):
        self.balance += amount

    def withdraw(self, amount):
        if amount > self.balance:
            return False
        self.balance -= amount
        return True
PY,
                    'tests' => [
                        [
                            'testcode' => "import math\naccount = BankAccount(100.0)\naccount.deposit(25.5)\nresult = account.withdraw(20.25)\nprint(result is True and math.isclose(account.balance, 105.25))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\naccount = BankAccount(10)\nresult = account.withdraw(11)\nprint(result is False and math.isclose(account.balance, 10.0))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\naccount = BankAccount()\naccount.deposit(0.1)\naccount.deposit(0.2)\nprint(math.isclose(account.balance, 0.3, rel_tol=1e-9, abs_tol=1e-9))",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => false,
                        ],
                    ],
                ],
                [
                    'name' => '21. Результаты студента',
                    'mode' => 'class',
                    'required_class' => 'Student',
                    'description' => 'Объявите класс Student(name). Метод add_grade(grade) добавляет оценку, average() возвращает среднее или 0.0 без оценок, is_passed() возвращает True при среднем не ниже 3.0.',
                    'input' => 'Имя передаётся конструктору, оценки добавляются по одной.',
                    'output' => 'Методы возвращают среднее значение и итоговый логический результат.',
                    'answer' => <<<'PY'
class Student:
    def __init__(self, name):
        self.name = name
        self.grades = []

    def add_grade(self, grade):
        self.grades.append(grade)

    def average(self):
        if not self.grades:
            return 0.0
        return sum(self.grades) / len(self.grades)

    def is_passed(self):
        return self.average() >= 3.0
PY,
                    'tests' => [
                        [
                            'testcode' => "import math\nstudent = Student('Анна')\nstudent.add_grade(5)\nstudent.add_grade(4)\nprint(math.isclose(student.average(), 4.5) and student.is_passed() is True)",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nstudent = Student('Иван')\nstudent.add_grade(2.5)\nstudent.add_grade(3.0)\nprint(math.isclose(student.average(), 2.75) and student.is_passed() is False)",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => true,
                        ],
                        [
                            'testcode' => "import math\nstudent = Student('Нет оценок')\nprint(math.isclose(student.average(), 0.0) and student.is_passed() is False)",
                            'stdin' => '',
                            'expected' => "True\n",
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => '8. Параллельное и асинхронное выполнение',
            'summary' => 'Базовое применение asyncio, потоков и процессов на небольших безопасных примерах.',
            'theory' => [
                'name' => 'Теория: asyncio, потоки и процессы',
                'content' => <<<'HTML'
<h3>Три способа организовать работу</h3>
<ul>
  <li><code>asyncio</code> переключает корутины, пока одна из них ожидает ввод-вывод;</li>
  <li><code>ThreadPoolExecutor</code> удобен для нескольких блокирующих операций ввода-вывода;</li>
  <li><code>ProcessPoolExecutor</code> выполняет вычисления в отдельных процессах и подходит для независимых тяжёлых расчётов.</li>
</ul>
<p>Параллельность не всегда ускоряет программу: создание задач и исполнителей тоже требует времени. В учебных заданиях входы специально небольшие, а число рабочих потоков и процессов ограничено двумя.</p>
<h3>Безопасные правила</h3>
<p>Создавайте исполнитель через <code>with</code>, задавайте <code>max_workers=2</code> и возвращайте результаты в исходном порядке. В задании с <code>asyncio</code> объявите корутину через <code>async def</code> и объедините задачи функцией <code>asyncio.gather</code>.</p>
HTML,
            ],
            'quiz' => [
                'name' => 'Практика 8: параллельное выполнение',
                'intro' => 'Небольшие задания знакомят с тремя стандартными API Python. Лимиты подобраны для учебного сервера.',
            ],
            'tasks' => [
                [
                    'name' => '22. Асинхронная сумма квадратов',
                    'mode' => 'function',
                    'required_async_function' => 'async_square_sum',
                    'required_symbols' => ['gather'],
                    'description' => 'Объявите корутину async_square_sum(values). Для каждого числа создайте асинхронное вычисление квадрата, запустите их через asyncio.gather и верните сумму результатов.',
                    'input' => 'Аргумент values — список целых чисел.',
                    'output' => 'Целая сумма квадратов.',
                    'answer' => <<<'PY'
import asyncio

async def square(number):
    await asyncio.sleep(0)
    return number * number

async def async_square_sum(values):
    squares = await asyncio.gather(*(square(number) for number in values))
    return sum(squares)
PY,
                    'tests' => [
                        ['testcode' => "import asyncio\nprint(asyncio.run(async_square_sum([1, 2, 3])))", 'stdin' => '', 'expected' => "14\n", 'visible' => true],
                        ['testcode' => "import asyncio\nprint(asyncio.run(async_square_sum([])))", 'stdin' => '', 'expected' => "0\n", 'visible' => true],
                        ['testcode' => "import asyncio\nprint(asyncio.run(async_square_sum([-4, 5])))", 'stdin' => '', 'expected' => "41\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '23. Длины строк в потоках',
                    'mode' => 'function',
                    'required_function' => 'parallel_lengths',
                    'required_symbols' => ['ThreadPoolExecutor'],
                    'max_workers' => 2,
                    'description' => 'Объявите функцию parallel_lengths(items), которая вычисляет длины строк через ThreadPoolExecutor с max_workers=2 и возвращает список в исходном порядке.',
                    'input' => 'Аргумент items — список строк.',
                    'output' => 'Список длин строк.',
                    'answer' => <<<'PY'
from concurrent.futures import ThreadPoolExecutor

def parallel_lengths(items):
    with ThreadPoolExecutor(max_workers=2) as executor:
        return list(executor.map(len, items))
PY,
                    'tests' => [
                        ['testcode' => "print(parallel_lengths(['cat', '', 'python']))", 'stdin' => '', 'expected' => "[3, 0, 6]\n", 'visible' => true],
                        ['testcode' => "print(parallel_lengths([]))", 'stdin' => '', 'expected' => "[]\n", 'visible' => true],
                        ['testcode' => "print(parallel_lengths(['а', 'курс', 'CodeRunner']))", 'stdin' => '', 'expected' => "[1, 4, 10]\n", 'visible' => false],
                    ],
                ],
                [
                    'name' => '24. Факториалы в процессах',
                    'mode' => 'function',
                    'required_function' => 'parallel_factorials',
                    'required_symbols' => ['ProcessPoolExecutor'],
                    'max_workers' => 2,
                    'description' => 'Объявите функцию parallel_factorials(values), которая вычисляет факториалы через ProcessPoolExecutor с max_workers=2 и возвращает список в исходном порядке.',
                    'input' => 'Аргумент values — список целых чисел от 0 до 20.',
                    'output' => 'Список факториалов.',
                    'cputime' => 5,
                    'memory' => 256,
                    'answer' => <<<'PY'
import math
from concurrent.futures import ProcessPoolExecutor

def parallel_factorials(values):
    with ProcessPoolExecutor(max_workers=2) as executor:
        return list(executor.map(math.factorial, values))
PY,
                    'tests' => [
                        ['testcode' => "print(parallel_factorials([0, 3, 5]))", 'stdin' => '', 'expected' => "[1, 6, 120]\n", 'visible' => true],
                        ['testcode' => "print(parallel_factorials([]))", 'stdin' => '', 'expected' => "[]\n", 'visible' => true],
                        ['testcode' => "print(parallel_factorials([6, 1, 8]))", 'stdin' => '', 'expected' => "[720, 1, 40320]\n", 'visible' => false],
                    ],
                ],
            ],
        ],
    ],
];
