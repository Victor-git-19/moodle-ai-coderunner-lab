# Развёртывание

Ниже два разных сценария. Для нового учебного сервера подходит весь Compose-проект. Для действующего Moodle СФТИ полный контейнер Moodle переносить не нужно.

## Сценарий A: чистый Ubuntu-сервер

Требования:

- Ubuntu и пользователь `code`;
- Git;
- Docker Engine;
- Docker Compose Plugin;
- SSH-доступ;
- не менее 4 ГБ RAM, лучше 6 ГБ;
- не менее 20 ГБ свободного места.

Проверьте инструменты:

```bash
git --version
docker version
docker compose version
```

Клонируйте проект и создайте настройки:

```bash
git clone https://github.com/Victor-git-19/moodle-ai-coderunner-lab.git
cd moodle-ai-coderunner-lab
make setup
nano .env
```

Укажите IP или DNS-имя сервера:

```dotenv
MOODLE_URL=http://192.168.1.50:8080
```

При необходимости измените `MOODLE_PORT`, название сайта и отдельную учётную запись администратора:

```dotenv
MOODLE_ADMIN_USER=labadmin
MOODLE_ADMIN_PASSWORD=generated_password
MOODLE_ADMIN_EMAIL=labadmin@example.local
```

Модель выбирается переменной `OLLAMA_MODEL`. Для стенда по умолчанию используется `qwen2.5-coder:1.5b`.

Соберите и запустите проект:

```bash
make build
make up
docker compose logs -f ollama-model
```

После сообщения о готовности модели остановите просмотр логов клавишами `Ctrl+C` и выполните:

```bash
make check
make smoke
```

Откройте `MOODLE_URL` и войдите значениями `MOODLE_ADMIN_USER` и `MOODLE_ADMIN_PASSWORD` из `.env`.

### Проверка учебного сценария

1. Создайте quiz и вопрос CodeRunner типа `python3`.
2. Отправьте ошибочное решение и убедитесь, что CodeRunner показал провал.
3. Нажмите кнопку ИИ и проверьте все разделы анализа.
4. Нажмите её повторно: должен вернуться кэшированный результат.
5. Отправьте правильное решение и снова запустите анализ.
6. Создайте скрытый тест с `Display`, отличным от `SHOW`, и проверьте страницу от роли студента.

Проверьте обычный перезапуск:

```bash
make restart
make check
make smoke
```

Курс, вопросы, попытки, AI-кэш и модель должны сохраниться.

### Автоматический скрипт

Вместо ручного запуска можно выполнить:

```bash
./scripts/deploy-server.sh
```

Скрипт только проверяет Docker, Compose, `.env`, порт, память и диск, запускает Compose, ждёт сервисы и выполняет проверки. Он не устанавливает пакеты, не меняет firewall и не удаляет данные.

### Обновление стенда

Перед обновлением сделайте резервную копию базы и `moodledata`, затем:

```bash
git pull
make build
make up
make check
make smoke
```

Entrypoint выполняет Moodle CLI upgrade и не переустанавливает существующую базу.

## Сценарий B: действующий Moodle СФТИ 5.2.1

Не переносите контейнер Moodle, `config.php` или volumes лабораторного стенда в действующую систему. Обычно нужны:

- собственный плагин `local_aicodehelper`;
- AI service и Ollama;
- настройки endpoint, timeout, режима и capability;
- CodeRunner, behaviour и Jobe, только если их ещё нет или версии не подходят.

Работу должен выполнять администратор Moodle. Сначала разверните копию production и проверьте всё на ней.

### 1. Резервная копия и совместимость

До изменений сохраните:

- дамп базы Moodle;
- весь `moodledata`;
- текущий код Moodle и список установленных плагинов;
- настройки CodeRunner и адрес Jobe.

Проверьте:

- Moodle строго 5.2.1;
- PHP 8.3 с расширениями Moodle;
- MariaDB поддерживаемой версии;
- CodeRunner 5.9.2 и behaviour 1.4.5 либо подтверждённую администратором совместимую версию;
- доступ от PHP-сервера к Jobe и будущему AI service.

Версии стенда и официальные источники: [VERSIONS.md](VERSIONS.md).

### 2. CodeRunner, behaviour и Jobe

Если CodeRunner уже работает, не переустанавливайте его без причины. Создайте тестовый вопрос `python3` и подтвердите реальное выполнение через Jobe.

Если плагинов нет, положите официальные выпуски до Moodle CLI upgrade:

```text
public/question/type/coderunner
public/question/behaviour/adaptive_adapted_for_coderunner
```

Behaviour — обязательная зависимость. Адрес Jobe задаётся без `http://`:

```bash
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=qtype_coderunner --name=jobe_host --set=jobe.example.local:80
```

Замените путь и DNS-имя на реальные. Не открывайте Jobe в интернет. Учтите настройки Moodle «Безопасность HTTP»: Moodle-сервер должен иметь разрешённый путь к Jobe.

### 3. AI service и Ollama

Их можно запустить отдельным Compose-проектом на внутреннем сервере. Скопируйте каталоги `ai-service`, настройки Ollama из `compose.yaml` и закреплённые переменные. Опубликуйте AI service только в доверенной серверной сети, Ollama наружу не открывайте.

С Moodle должен быть доступен полный серверный URL:

```text
http://ai-service.example.local:8000/api/v1/analyze
```

Проверьте `/health` с самого PHP-сервера. Адрес AI service никогда не должен передаваться браузеру студента.

### 4. Установка собственного плагина

Все файлы собственного Moodle-плагина находятся в каталоге:

```text
moodle/local_aicodehelper
```

Скопируйте его на копию Moodle 5.2.1:

```bash
sudo mkdir -p /var/www/moodle/public/local/aicodehelper
sudo rsync -a --delete moodle/local_aicodehelper/ /var/www/moodle/public/local/aicodehelper/
sudo chown -R root:www-data /var/www/moodle/public/local/aicodehelper
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

Если в вашей установке нет каталога `public`, используйте фактический `$CFG->dirroot/local/aicodehelper`.

Главные файлы плагина:

- `ajax.php` — защищённый запрос попытки;
- `integration.js` — кнопка и отображение ответа;
- `classes/payload_builder.php` — безопасный payload;
- `classes/service_client.php` — серверный вызов AI;
- `classes/output_renderer.php` — экранирование результата;
- `settings.php` — настройки администратора;
- `db/` — capability, hook, кэш и upgrade;
- `lang/` — русские и английские строки.

### 5. Настройки и capability

Откройте «Администрирование сайта → Плагины → Локальные плагины → ИИ-помощник по коду» и задайте:

- endpoint AI service;
- timeout;
- режим ответа;
- лимит анализов;
- запрет полного исправленного кода, если методика не требует иного.

То же через CLI:

```bash
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=local_aicodehelper --name=endpoint \
  --set=http://ai-service.example.local:8000/api/v1/analyze
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=local_aicodehelper --name=timeout --set=60
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

Выдайте роли студента capability `local/aicodehelper:analyzeattempt` только в нужном контексте.

### 6. Проверка перед production

На копии Moodle:

1. Откройте `/local/aicodehelper/index.php` и проверьте диагностику.
2. Создайте отдельный quiz CodeRunner.
3. Проверьте ошибочную и правильную попытки.
4. Проверьте повторный запрос из кэша.
5. Проверьте SyntaxError, RuntimeError и timeout.
6. В скрытом тесте выключите `Use as example`, установите `Display` не `SHOW` и войдите как студент.
7. Убедитесь, что вход, ожидаемый ответ, test code и эталонное решение не видны и не попадают в AI payload или логи.
8. Временно остановите Ollama и проверьте fallback.

Только после этого повторите установку на production в согласованное окно.

### 7. Откат

Если проверка не прошла:

1. отключите интеграцию `local_aicodehelper` в настройках;
2. верните предыдущий код плагина или удалите новый каталог по процедуре администратора;
3. выполните CLI upgrade и purge caches;
4. восстановите базу и `moodledata` из согласованной резервной копии, если миграция уже затронула production;
5. верните старые настройки CodeRunner/Jobe;
6. изучите логи Moodle, PHP, AI service и Ollama.

Перенос на живой Moodle не является операцией одной команды: пути, сеть, роли и политика скрытых тестов различаются между установками.
