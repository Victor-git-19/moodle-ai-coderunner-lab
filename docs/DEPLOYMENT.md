# Перенос на действующий Moodle МИФИ

Инструкция рассчитана на сервер, где уже работает Moodle **строго версии 5.2.1**. Лабораторный Moodle переносить туда не нужно.

Сначала выполните всё на тестовой копии. Для рабочего сервера заранее сделайте резервную копию базы, `moodledata` и кода Moodle и согласуйте время установки с администратором.

## Что переносим

| Часть проекта | Что с ней делать |
| --- | --- |
| `moodle/local_aicodehelper` | скопировать в каталог локальных плагинов Moodle |
| `ai-service` | запустить рядом с Ollama в Docker |
| Ollama | запустить в Docker с моделью `qwen2.5-coder:0.5b` |
| `course` | при необходимости запустить установщик демонстрационного курса |

Не переносим лабораторную MariaDB, `moodledata`, `config.php` и Docker volumes. Не изменяем ядро Moodle, CodeRunner и Jobe.

Если в МИФИ уже работает Jobe на порту `9000`, оставьте его. В этой инструкции контейнер `jobe` из проекта вообще не запускается.

## 1. Уточнить пути и сделать резервную копию

Ниже используется стандартная структура Moodle 5.2.1:

```bash
export MOODLE_ROOT=/var/www/moodle
export MOODLE_PUBLIC=/var/www/moodle/public
export MOODLE_CONFIG=/var/www/moodle/config.php
export MOODLE_CLI=/var/www/moodle/admin/cli
export REPO="$HOME/moodle-ai-coderunner-lab"
```

Выполняйте следующие команды в одной SSH-сессии. Если Moodle установлен иначе, измените переменные. Проверить реальный `$CFG->dirroot` можно так:

```bash
sudo -u www-data php -r \
  'define("CLI_SCRIPT", true); require "/var/www/moodle/config.php"; echo $CFG->dirroot, PHP_EOL;'
```

Перед продолжением администратор должен подтвердить наличие свежей резервной копии:

- базы Moodle;
- каталога `moodledata`;
- каталога Moodle;
- текущих настроек CodeRunner и адреса Jobe.

## 2. Проверить Moodle, CodeRunner и Jobe

Проверьте версию Moodle:

```bash
sudo -u www-data php -r \
  'define("CLI_SCRIPT", true); require "/var/www/moodle/config.php"; echo $CFG->release, PHP_EOL;'
```

Ожидается `5.2.1`. Для этого проекта проверены:

- PHP 8.3;
- CodeRunner 5.9.2;
- `adaptive_adapted_for_coderunner` 1.4.5;
- Jobe 2.2.2;
- Ollama 0.30.11.

Если CodeRunner и Jobe уже работают, не переустанавливайте их. Создайте или откройте вопрос CodeRunner типа `python3` и отправьте:

```python
print("Hello from Jobe")
```

Ожидаемый вывод:

```text
Hello from Jobe
```

Если CodeRunner ещё не установлен, поставьте проверенные версии из официальных репозиториев:

```bash
curl -fL https://github.com/trampgeek/moodle-qtype_coderunner/archive/refs/tags/v5.9.2.tar.gz \
  -o /tmp/coderunner.tar.gz
curl -fL https://github.com/trampgeek/moodle-qbehaviour_adaptive_adapted_for_coderunner/archive/refs/tags/v1.4.5.tar.gz \
  -o /tmp/coderunner-behaviour.tar.gz
sudo install -d "$MOODLE_PUBLIC/question/type/coderunner"
sudo install -d "$MOODLE_PUBLIC/question/behaviour/adaptive_adapted_for_coderunner"
sudo tar -xzf /tmp/coderunner.tar.gz --strip-components=1 \
  -C "$MOODLE_PUBLIC/question/type/coderunner"
sudo tar -xzf /tmp/coderunner-behaviour.tar.gz --strip-components=1 \
  -C "$MOODLE_PUBLIC/question/behaviour/adaptive_adapted_for_coderunner"
sudo chown -R root:www-data "$MOODLE_PUBLIC/question/type/coderunner"
sudo chown -R root:www-data \
  "$MOODLE_PUBLIC/question/behaviour/adaptive_adapted_for_coderunner"
sudo -u www-data php "$MOODLE_CLI/upgrade.php" --non-interactive
```

Не выполняйте эти команды поверх другой установленной версии: её обновление сначала проверьте на копии Moodle.

Если CodeRunner ещё не настроен на существующий Jobe, задайте его внутреннее имя и порт без `http://`:

```bash
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=qtype_coderunner --name=jobe_host \
  --set=jobe.internal.example:9000
```

Замените `jobe.internal.example` на настоящий адрес и снова выполните Python через CodeRunner.

## 3. Запустить только AI service и Ollama

Клонируйте проект на сервер, где будут работать AI service и Ollama:

```bash
git clone https://github.com/Victor-git-19/moodle-ai-coderunner-lab.git "$REPO"
cd "$REPO"
./scripts/create-env.sh
```

Сгенерированные в `.env` параметры лабораторного Moodle и MariaDB в этом сценарии не используются. Для сервера с 4 ГБ RAM оставьте:

```dotenv
OLLAMA_MODEL=qwen2.5-coder:0.5b
AI_TIMEOUT=60
```

Если AI service запускается на том же сервере, что и Moodle, создайте файл `compose.institute.yaml`:

```yaml
services:
  ai-service:
    ports:
      - "127.0.0.1:8000:8000"
```

Запустите только три AI-сервиса:

```bash
docker compose -f compose.yaml -f compose.institute.yaml \
  up -d --build ollama ollama-model ai-service
```

Команда не запускает лабораторные `moodle`, `db` и `jobe`. Модель хранится в volume Ollama и не скачивается при каждом старте.

Проверьте API с сервера Moodle:

```bash
curl -fsS http://127.0.0.1:8000/health
```

Ожидается:

```json
{"status":"ok"}
```

Если AI service размещён на отдельном сервере, опубликуйте порт `8000` только на его внутреннем IP, разрешите доступ к нему только от Moodle и используйте адрес вида `http://10.0.0.20:8000`. Ollama наружу не публикуйте.

Если сам Moodle тоже работает в Docker, `127.0.0.1` внутри его контейнера не ведёт на хост. Укажите адрес AI service, доступный именно из контейнера Moodle, и проверьте его командой `curl` внутри этого контейнера.

## 4. Скопировать собственный Moodle-плагин

Копируется весь каталог `moodle/local_aicodehelper`. Отдельные файлы выбирать не нужно.

```bash
cd "$REPO"
sudo install -d -o root -g www-data "$MOODLE_PUBLIC/local/aicodehelper"
sudo rsync -a --delete moodle/local_aicodehelper/ \
  "$MOODLE_PUBLIC/local/aicodehelper/"
sudo chown -R root:www-data "$MOODLE_PUBLIC/local/aicodehelper"
```

Проверьте, что итоговый путь выглядит так:

```text
/var/www/moodle/public/local/aicodehelper/version.php
```

Затем зарегистрируйте плагин в Moodle:

```bash
sudo -u www-data php "$MOODLE_CLI/upgrade.php" --non-interactive
sudo -u www-data php "$MOODLE_CLI/purge_caches.php"
```

К собственному плагину относятся только файлы внутри `moodle/local_aicodehelper`:

- `classes/payload_builder.php` — собирает безопасные данные попытки;
- `classes/service_client.php` — вызывает AI service с сервера Moodle;
- `classes/output_renderer.php` — безопасно выводит результат;
- `ajax.php` — проверяет авторизацию, capability и `sesskey`;
- `integration.js` — добавляет кнопку к результату CodeRunner;
- `settings.php`, `db/`, `lang/` — настройки, права, таблица кэша и переводы.

## 5. Настроить плагин

Для AI service на том же сервере выполните:

```bash
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=endpoint \
  --set=http://127.0.0.1:8000/api/v1/analyze
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=integrationenabled --set=1
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=showaftergrading --set=1
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=onlyfailed --set=0
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=responsemode --set=teacher
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=allowfullsolution --set=0
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=maxanalyses --set=3
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=timeout --set=60
sudo -u www-data php "$MOODLE_CLI/purge_caches.php"
```

Те же параметры доступны в интерфейсе:

```text
Администрирование сайта → Плагины → Локальные плагины → ИИ-помощник по коду
```

Capability `local/aicodehelper:analyzeattempt` по умолчанию разрешена студентам и преподавателям. На рабочем сервере проверьте назначение capability для реальных ролей.

## 6. При необходимости установить курс Python

Курс не нужно копировать внутрь Moodle. Установщик запускается прямо из клонированного репозитория и создаёт отдельный курс `PYTHON-CR-START` с 15 задачами CodeRunner.

```bash
cd "$REPO"
sudo -u www-data env MOODLE_CONFIG="$MOODLE_CONFIG" \
  php course/install.php
```

По умолчанию используется категория Moodle с ID `1`. Для другой категории:

```bash
sudo -u www-data env \
  MOODLE_CONFIG="$MOODLE_CONFIG" \
  PYTHON_COURSE_CATEGORY_ID=3 \
  php course/install.php
```

Повторный запуск не создаёт копию и не перезаписывает существующий курс. После установки запишите преподавателей и студентов обычными средствами Moodle.

Проверить структуру курса и выполнить все 15 эталонных решений через рабочий Jobe:

```bash
sudo -u www-data env MOODLE_CONFIG="$MOODLE_CONFIG" \
  php course/check.php --run-reference
```

## 7. Проверить интеграцию как студент

Проверяйте с тестовой учётной записью студента или через «Переключиться к роли → Студент»:

1. Откройте вопрос CodeRunner и отправьте правильное решение.
2. Отправьте неправильное решение или код с `SyntaxError`.
3. Убедитесь, что после проверки появилась кнопка «Проанализировать решение с помощью ИИ».
4. Нажмите кнопку и дождитесь разделов с оценкой, проблемами и следующим шагом.
5. Убедитесь, что вход и ожидаемый результат скрытого теста не отображаются.
6. Повторите анализ: параллельные запросы не должны создаваться.

Проверьте контейнеры и логи:

```bash
cd "$REPO"
docker compose -f compose.yaml -f compose.institute.yaml ps
docker compose -f compose.yaml -f compose.institute.yaml \
  logs --tail=100 ai-service ollama
```

## Обновление

1. Сделайте резервную копию рабочего сервера.
2. Выполните `git pull` в каталоге репозитория.
3. Повторно синхронизируйте весь `moodle/local_aicodehelper`.
4. Выполните `upgrade.php` и `purge_caches.php`.
5. Перезапустите только AI-часть:

   ```bash
   docker compose -f compose.yaml -f compose.institute.yaml \
     up -d --build ollama ollama-model ai-service
   ```

6. Повторите проверку с ролью студента.

Установщик курса существующий курс не обновляет: изменения учебных материалов переносите через интерфейс Moodle или сначала проверяйте на отдельной копии.

## Быстрое отключение и откат

Если возникла проблема, сначала отключите только интеграцию:

```bash
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=integrationenabled --set=0
sudo -u www-data php "$MOODLE_CLI/purge_caches.php"
```

Затем остановите AI-часть:

```bash
cd "$REPO"
docker compose -f compose.yaml -f compose.institute.yaml \
  stop ai-service ollama
```

CodeRunner и существующий Jobe продолжат работать без ИИ. Не удаляйте базу, `moodledata`, курс или Docker volumes. Для полного удаления плагина используйте штатную страницу удаления Moodle и заранее сделанную резервную копию.
