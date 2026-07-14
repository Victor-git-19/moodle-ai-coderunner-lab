# Интеграция с действующим Moodle 5.2.1

Эта инструкция нужна для Moodle института, который уже установлен и используется. Полный лабораторный Moodle, его базу, `config.php` и Docker volumes переносить не нужно.

Работы сначала выполняются на тестовой копии Moodle. Для production нужны резервная копия и согласованное окно обслуживания.

## Что переносится

Из репозитория нужны три части:

- `moodle/local_aicodehelper` — собственный Moodle-плагин;
- `ai-service` — серверный сервис анализа;
- Ollama и модель из `compose.yaml`.

Плагин CodeRunner остаётся источником результата проверки. Jobe выполняет код. AI service код не запускает, а только анализирует его и разрешённые студенту результаты тестов.

Не нужно изменять ядро Moodle, CodeRunner или Jobe.

## Что проверить до установки

- Moodle имеет версию строго 5.2.1;
- используется PHP 8.3;
- CodeRunner и `adaptive_adapted_for_coderunner` уже работают либо будут установлены администратором;
- Python реально выполняется через Jobe;
- PHP-сервер Moodle может обратиться к будущему AI service;
- сохранены база Moodle, `moodledata`, код Moodle и текущие настройки CodeRunner.

Версии, проверенные в лабораторном стенде:

- CodeRunner 5.9.2;
- `adaptive_adapted_for_coderunner` 1.4.5;
- Jobe 2.2.2;
- Ollama 0.30.11;
- `qwen2.5-coder:0.5b` для сервера с 4 ГБ RAM.

Если в институте уже установлены совместимые CodeRunner и Jobe, переустанавливать их не нужно.

## Настройка существующего Jobe

Сначала создайте обычный вопрос CodeRunner типа `python3` и выполните:

```python
print("Hello from Jobe")
```

Ожидаемый вывод:

```text
Hello from Jobe
```

Если CodeRunner ещё не настроен на существующий Jobe с портом `9000`, задайте его адрес без `http://`:

```bash
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=qtype_coderunner --name=jobe_host \
  --set=jobe.internal.example:9000
```

Замените путь Moodle и имя сервера на реальные. После изменения снова проверьте настоящее выполнение Python.

## Запуск AI service и Ollama

На Docker-сервере склонируйте репозиторий и создайте `.env`:

```bash
git clone https://github.com/Victor-git-19/moodle-ai-coderunner-lab.git
cd moodle-ai-coderunner-lab
./scripts/create-env.sh
```

Для слабого сервера оставьте минимальную модель:

```dotenv
OLLAMA_MODEL=qwen2.5-coder:0.5b
```

Если Moodle установлен прямо на том же сервере, создайте рядом с `compose.yaml` файл `compose.institute.yaml`:

```yaml
services:
  ai-service:
    ports:
      - "127.0.0.1:8000:8000"
```

Запустите только AI-часть. Контейнеры лабораторного Moodle, базы и Jobe при этом не запускаются:

```bash
docker compose -f compose.yaml -f compose.institute.yaml \
  up -d --build ollama ollama-model ai-service
```

Проверьте сервис с Moodle-сервера:

```bash
curl -fsS http://127.0.0.1:8000/health
```

Ожидаемый ответ:

```json
{"status":"ok"}
```

Если AI service находится на другом сервере, публикуйте порт только на его внутреннем IP и разрешите доступ только от Moodle-сервера. Ollama наружу публиковать не нужно.

## Копирование Moodle-плагина

Все собственные файлы находятся в одном каталоге:

```text
moodle/local_aicodehelper
```

Скопируйте каталог в `$CFG->dirroot/local/aicodehelper`. Для стандартного Moodle 5.2.1 с каталогом `public` команды выглядят так:

```bash
sudo mkdir -p /var/www/moodle/public/local/aicodehelper
sudo rsync -a --delete moodle/local_aicodehelper/ \
  /var/www/moodle/public/local/aicodehelper/
sudo chown -R root:www-data /var/www/moodle/public/local/aicodehelper
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

Если `$CFG->dirroot` отличается, замените путь во всех командах. Не копируйте плагин в ядро Moodle или каталог CodeRunner.

Основные файлы плагина:

- `classes/payload_builder.php` — собирает попытку и скрывает закрытые тесты;
- `ajax.php` — проверяет пользователя, capability и `sesskey`;
- `classes/service_client.php` — вызывает AI service с сервера Moodle;
- `classes/output_renderer.php` — экранирует ответ;
- `integration.js` — добавляет кнопку на страницу попытки;
- `settings.php` — настройки администратора;
- `db/` — hook, capability, таблица кэша и обновления;
- `lang/` — русские и английские строки.

## Настройка local_aicodehelper

Откройте:

```text
Администрирование сайта → Плагины → Локальные плагины → ИИ-помощник по коду
```

Рекомендуемые настройки:

- интеграция с CodeRunner включена;
- кнопка показывается после проверки;
- режим ответа — преподавательский анализ;
- полный готовый код запрещён;
- лимит — 3 анализа на один шаг попытки;
- timeout — 60 секунд;
- endpoint — `http://127.0.0.1:8000/api/v1/analyze` для установки на одном сервере.

Endpoint можно задать через CLI:

```bash
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=local_aicodehelper --name=endpoint \
  --set=http://127.0.0.1:8000/api/v1/analyze
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=local_aicodehelper --name=timeout --set=60
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=local_aicodehelper --name=allowfullsolution --set=0
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

Capability `local/aicodehelper:analyzeattempt` по умолчанию разрешена студенту, преподавателю и менеджеру. Проверьте её в ролях Moodle и при необходимости ограничьте на уровне курса или теста.

## Проверка интеграции

Проверяйте с ролью студента, а не только администратора:

1. Отправьте правильное решение CodeRunner и проверьте оценку.
2. Отправьте `SyntaxError` или неправильный ответ.
3. Нажмите «Проанализировать решение с помощью ИИ».
4. Убедитесь, что результат появился под обратной связью CodeRunner.
5. Проверьте скрытый тест: студент не должен видеть его вход, ожидаемый ответ и test code.
6. Временно остановите Ollama и убедитесь, что отображается статический fallback.
7. Снова запустите Ollama и повторите анализ.

Полезные команды:

```bash
docker compose -f compose.yaml -f compose.institute.yaml ps
docker compose -f compose.yaml -f compose.institute.yaml logs -f ai-service ollama
curl -fsS http://127.0.0.1:8000/health
```

## Повторная установка или обновление

На другом Moodle 5.2.1 повторно скопируйте весь каталог `moodle/local_aicodehelper`, затем выполните:

```bash
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

После обновления ещё раз проверьте endpoint, capability, скрытые тесты и ошибочную попытку CodeRunner.

Если появилась проблема, сначала отключите интеграцию в настройках `local_aicodehelper`. Не удаляйте базу, `moodledata` или Docker volumes без резервной копии и отдельного согласования.
