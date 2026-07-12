# Moodle AI CodeRunner Lab

Учебный стенд на Moodle 5.2.1. В нём есть CodeRunner с Jobe Server и простая страница анализа кода локальной моделью. Пользовательский код выполняется только в Jobe; AI service делает безопасный разбор Python через `ast` и не запускает код.

Сервисы: `moodle`, `db` (MariaDB), `jobe`, `ai-service` (FastAPI), `ollama` и одноразовый загрузчик модели `ollama-model`. Наружу открыт только Moodle.

## Быстрый запуск

Нужны Docker Engine и Docker Compose Plugin. Рекомендуется 6 ГБ RAM и не менее 20 ГБ свободного места: официальные образы Ollama и JobeInABox довольно большие.

```bash
./scripts/create-env.sh
docker compose up -d --build
./scripts/check.sh
./scripts/smoke-test.sh
```

При первом запуске сборка Jobe и загрузка модели примерно на 1 ГБ занимают несколько минут. Повторный запуск модель не скачивает.

Moodle открывается по `MOODLE_URL` из `.env`, по умолчанию — [http://localhost:8080](http://localhost:8080). Логин администратора находится в `MOODLE_ADMIN_USER`, пароль генератор показывает один раз и сохраняет в `.env`.

```bash
grep '^MOODLE_ADMIN_' .env
```

Moodle, CodeRunner, обязательный behaviour-плагин и `local_aicodehelper` устанавливаются автоматически. Jobe записывается в настройку CodeRunner как хост `jobe`.

## Проверка CodeRunner вручную

1. Войдите администратором и создайте курс.
2. Добавьте элемент «Тест», затем вопрос типа `CodeRunner`.
3. Выберите тип вопроса `python3`.
4. В качестве правильного ответа укажите `print("Hello from Jobe")`.
5. Добавьте тест без входных данных с ожидаемым выводом `Hello from Jobe`.
6. Сохраните вопрос, откройте предпросмотр и запустите проверку.

Адрес Jobe можно увидеть в «Администрирование сайта → Плагины → Типы вопросов → CodeRunner». Не добавляйте `http://` к значению `jobe`.

## ИИ-анализ

После входа откройте `/local/aicodehelper/index.php`, например [http://localhost:8080/local/aicodehelper/index.php](http://localhost:8080/local/aicodehelper/index.php). Выберите язык, вставьте условие и код, затем нажмите «Анализировать». Если Ollama временно недоступна, страница покажет результат статического анализа с предупреждением.

Адрес AI service и таймаут меняются в «Администрирование сайта → Плагины → Локальные плагины → ИИ-помощник по коду».

## Обычные команды

```bash
make logs       # все логи
docker compose logs -f moodle
docker compose logs -f ai-service ollama
make test       # pytest для AI service
make check      # состояние сервисов
make smoke      # выполнение Python и запрос анализа
make restart
make down       # остановить, сохранив данные
```

Данные базы, `moodledata` и модель лежат в Docker volumes. Обычные `down`, `up` и `restart` их не удаляют.

Полное удаление данных требует явного подтверждения:

```bash
make reset CONFIRM=yes
```

Эта команда безвозвратно удаляет базу Moodle, загруженные файлы и модель Ollama.

## Ubuntu и вузовский Moodle

Для сервера Ubuntu используйте [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Короткий путь после клонирования:

```bash
./scripts/create-env.sh
# Укажите IP сервера в MOODLE_URL файла .env.
./scripts/deploy-server.sh
```

На существующий вузовский Moodle 5.2.1 нужно переносить только собственный каталог `moodle/local_aicodehelper`. CodeRunner и behaviour устанавливайте из официальных выпусков 5.9.2 и 1.4.5. Подробные команды, настройки Jobe и список файлов собственного плагина приведены в разделе «Перенос на вузовский Moodle» документа [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

Точные версии и причины выбора записаны в [docs/VERSIONS.md](docs/VERSIONS.md).
