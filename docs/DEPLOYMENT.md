# Интеграция в действующий Moodle МИФИ

Эта инструкция нужна для сервера, где уже работает Moodle **строго версии 5.2.1**. Второй Moodle, лабораторную MariaDB и контейнер Jobe разворачивать не нужно.

Сначала повторите установку на тестовой копии Moodle. На рабочем сервере начинайте только после резервного копирования и согласования с администратором.

## Что переносится

| Источник в репозитории | Куда и зачем |
| --- | --- |
| `moodle/local_aicodehelper/` | целиком в `$CFG->dirroot/local/aicodehelper/`; это собственный Moodle-плагин |
| `ai-service/` и Docker-файлы проекта | остаются в клонированном репозитории и запускаются через Compose |
| Ollama | запускается рядом с AI service; модель хранится в Docker volume |
| `course/` | необязательно: установщик создаёт отдельный курс с 24 задачами CodeRunner |

Не переносите из лаборатории:

- базу MariaDB;
- `moodledata`;
- `config.php`;
- Docker volumes;
- контейнер Moodle;
- контейнер Jobe, если в МИФИ уже есть рабочий Jobe.

Ядро Moodle, исходники CodeRunner и Jobe изменять не требуется.

## 1. Проверить сервер и определить пути

Нужны Moodle 5.2.1, PHP 8.3, Git, Docker Engine, Docker Compose Plugin, `curl`, `rsync` и доступ `sudo`. В примерах системный пользователь веб-сервера — `www-data`.

Укажите настоящий путь к `config.php`:

```bash
export MOODLE_CONFIG=/var/www/moodle/config.php
export REPO=/opt/moodle-ai-coderunner-lab
```

Moodle 5.2.1 хранит публичные плагины и CLI-скрипты в разных каталогах. Получите пути из самого Moodle:

```bash
export MOODLE_PUBLIC="$(sudo -u www-data env MOODLE_CONFIG="$MOODLE_CONFIG" php -r '
define("CLI_SCRIPT", true);
require getenv("MOODLE_CONFIG");
echo $CFG->dirroot;
')"
export MOODLE_ROOT="$(dirname "$MOODLE_PUBLIC")"
export MOODLE_CLI="$MOODLE_ROOT/admin/cli"

printf 'Public plugins: %s\nCLI scripts: %s\n' "$MOODLE_PUBLIC" "$MOODLE_CLI"
test -f "$MOODLE_PUBLIC/version.php"
test -f "$MOODLE_CLI/upgrade.php"
```

Для стандартной установки результат будет похож на:

```text
Public plugins: /var/www/moodle/public
CLI scripts: /var/www/moodle/admin/cli
```

Проверьте версию:

```bash
sudo -u www-data env MOODLE_CONFIG="$MOODLE_CONFIG" php -r '
define("CLI_SCRIPT", true);
require getenv("MOODLE_CONFIG");
echo $CFG->release, PHP_EOL;
'
```

Продолжайте только при результате `5.2.1`.

Перед установкой сохраните:

- базу Moodle;
- каталог `moodledata`;
- каталог с кодом Moodle;
- текущие настройки CodeRunner и адрес Jobe;
- существующий `local/aicodehelper`, если он уже установлен.

## 2. Проверить CodeRunner и существующий Jobe

В лаборатории проверена комбинация:

- CodeRunner 5.9.2;
- `adaptive_adapted_for_coderunner` 1.4.5;
- Jobe 2.2.2.

Если CodeRunner уже используется в МИФИ, не заменяйте его автоматически. Сначала создайте вопрос типа `CodeRunner`, выберите `python3` и выполните:

```python
print("Hello from Jobe")
```

Ожидаемый вывод:

```text
Hello from Jobe
```

Если Jobe работает на другом сервере и порту `9000`, задайте его адрес без `http://`:

```bash
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=qtype_coderunner --name=jobe_host \
  --set=jobe.internal.example:9000
```

Замените `jobe.internal.example` настоящим внутренним именем. После изменения ещё раз выполните Python через CodeRunner.

### Если CodeRunner отсутствует

Установите оба проверенных плагина из официальных репозиториев:

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

Не распаковывайте архив поверх другой версии CodeRunner. Обновление существующего плагина сначала проверяйте на копии Moodle.

## 3. Запустить AI service и Ollama

Подготовьте каталог и клонируйте проект от обычного серверного пользователя:

```bash
sudo install -d -o "$USER" -g www-data "$REPO"
git clone https://github.com/Victor-git-19/moodle-ai-coderunner-lab.git "$REPO"
cd "$REPO"
./scripts/create-env.sh
sudo chmod 755 "$REPO"
```

`create-env.sh` создаёт полный `.env`, потому что Compose читает весь основной файл. Логин, пароли Moodle и MariaDB из этого `.env` здесь не используются. Сам `.env` сохраняет режим `600` и доступен только владельцу.

Для сервера с 4 ГБ RAM оставьте:

```dotenv
OLLAMA_MODEL=qwen2.5-coder:0.5b
AI_TIMEOUT=60
```

Создайте рядом с `compose.yaml` файл `compose.institute.yaml`:

```yaml
services:
  ai-service:
    ports:
      - "127.0.0.1:8000:8000"
```

Запустите только AI-часть:

```bash
docker compose -f compose.yaml -f compose.institute.yaml \
  up -d --build ollama ollama-model ai-service
```

Эта команда не запускает лабораторные `moodle`, `db` и `jobe`. Модель скачивается только при отсутствии в volume.

Проверьте сервис:

```bash
curl -fsS http://127.0.0.1:8000/health
docker compose -f compose.yaml -f compose.institute.yaml \
  exec ollama ollama list
```

Ответ `/health` должен содержать `"status":"ok"` и имя модели.

Если AI service находится на отдельном сервере, публикуйте порт `8000` только на его внутреннем IP и разрешите доступ только от Moodle-сервера.

Если Moodle работает в Docker, `127.0.0.1` внутри его контейнера указывает на сам контейнер Moodle. В таком случае подключите Moodle к сети AI service либо используйте внутренний IP хоста. Обязательно выполните `curl` к `/health` именно из контейнера Moodle.

## 4. Установить собственный плагин

Копируйте весь каталог, а не отдельные PHP- или JavaScript-файлы:

```bash
cd "$REPO"
sudo install -d -o root -g www-data "$MOODLE_PUBLIC/local/aicodehelper"
sudo rsync -a --delete moodle/local_aicodehelper/ \
  "$MOODLE_PUBLIC/local/aicodehelper/"
sudo chown -R root:www-data "$MOODLE_PUBLIC/local/aicodehelper"

test -f "$MOODLE_PUBLIC/local/aicodehelper/version.php"
sudo -u www-data php "$MOODLE_CLI/upgrade.php" --non-interactive
sudo -u www-data php "$MOODLE_CLI/purge_caches.php"
```

Если на сервере используются другие владелец и группа файлов Moodle, замените `root:www-data` на принятые значения.

Главные файлы собственного плагина:

- `classes/hook_callbacks.php` и `integration.js` — добавляют кнопку к CodeRunner;
- `classes/payload_builder.php` — собирает попытку и удаляет закрытые данные;
- `ajax.php` — проверяет авторизацию, capability и `sesskey`;
- `classes/service_client.php` — обращается к AI service с сервера Moodle;
- `classes/output_renderer.php` — экранирует и показывает ответ;
- `settings.php`, `db/`, `lang/` — настройки, права, кэш и переводы.

## 5. Настроить плагин

Если AI service работает на том же обычном сервере, выполните:

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

Те же параметры находятся в:

```text
Администрирование сайта → Плагины → Локальные плагины → ИИ-помощник по коду
```

Полный готовый код оставьте запрещённым. Capability `local/aicodehelper:analyzeattempt` должна быть разрешена только тем ролям, которым нужен анализ.

## 6. Необязательно: установить курс Python

Установщик создаёт отдельный курс `PYTHON-CR-START`. Другие курсы он не меняет. Если этот курс уже создан проектом, установщик дополнит его недостающими разделами и вопросами без удаления пользователей и попыток.

```bash
cd "$REPO"
sudo -u www-data env MOODLE_CONFIG="$MOODLE_CONFIG" \
  php course/install.php
```

Для категории Moodle с другим ID:

```bash
sudo -u www-data env \
  MOODLE_CONFIG="$MOODLE_CONFIG" \
  PYTHON_COURSE_CATEGORY_ID=3 \
  php course/install.php
```

Проверьте все 24 эталонных решения через существующий Jobe:

```bash
sudo -u www-data env MOODLE_CONFIG="$MOODLE_CONFIG" \
  php course/check.php --run-reference
```

Повторный запуск не создаёт копию курса. Он синхронизирует страницы проекта и добавляет отсутствующие разделы, тесты и вопросы. Существующие вопросы и попытки не удаляются. Если страницы курса меняли вручную, сначала проверьте обновление на копии Moodle.

## 7. Итоговая проверка

Проверяйте с тестовой учётной записью студента:

1. Отправьте правильное решение CodeRunner.
2. Отправьте неправильное решение или `SyntaxError`.
3. Убедитесь, что после проверки появилась кнопка анализа.
4. Нажмите кнопку и дождитесь структурированного ответа.
5. Проверьте, что скрытый вход, ожидаемый ответ и test code не показаны.
6. Нажмите повторно и убедитесь, что не создаются параллельные запросы.

Проверьте контейнеры и логи:

```bash
cd "$REPO"
docker compose -f compose.yaml -f compose.institute.yaml ps
docker compose -f compose.yaml -f compose.institute.yaml \
  logs --tail=100 ai-service ollama ollama-model
```

После проверки назначьте реальные роли и откройте плагин только нужным курсам или пользователям.

## Обновление и откат

Для обновления:

1. Сделайте резервную копию.
2. Выполните `git pull` в `$REPO`.
3. Повторите `rsync` каталога `moodle/local_aicodehelper/`.
4. Запустите `upgrade.php` и `purge_caches.php`.
5. Пересоберите только `ollama`, `ollama-model` и `ai-service`.
6. Если используется учебный курс, снова запустите `course/install.php`, затем `course/check.php --run-reference`.
7. Повторите проверку от роли студента.

Команды для пункта 5:

```bash
cd "$REPO"
docker compose -f compose.yaml -f compose.institute.yaml \
  up -d --build ollama ollama-model ai-service
```

Команды для пункта 6:

```bash
sudo -u www-data env MOODLE_CONFIG="$MOODLE_CONFIG" php course/install.php
sudo -u www-data env MOODLE_CONFIG="$MOODLE_CONFIG" \
  php course/check.php --run-reference
```

Для быстрого отключения интеграции:

```bash
sudo -u www-data php "$MOODLE_CLI/cfg.php" \
  --component=local_aicodehelper --name=integrationenabled --set=0
sudo -u www-data php "$MOODLE_CLI/purge_caches.php"

cd "$REPO"
docker compose -f compose.yaml -f compose.institute.yaml \
  stop ai-service ollama
```

CodeRunner и существующий Jobe продолжат работать без ИИ. Не удаляйте базу, `moodledata`, курс или Docker volumes во время отката.
