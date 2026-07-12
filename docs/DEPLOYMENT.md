# Развёртывание на Ubuntu

Инструкция рассчитана на сервер в локальной сети, пользователя `code`, установленный Git, Docker Engine и Docker Compose Plugin. Проект не меняет настройки Ubuntu, firewall или Docker.

## Первый запуск

Подключитесь по SSH и выполните:

```bash
git clone https://github.com/Victor-git-19/moodle-ai-coderunner-lab.git
cd moodle-ai-coderunner-lab
./scripts/create-env.sh
nano .env
```

В `.env` замените `MOODLE_URL` на адрес сервера, например:

```dotenv
MOODLE_URL=http://192.168.1.50:8080
```

При необходимости измените `MOODLE_PORT`, имя сайта, логин и email администратора. Учётная запись создаётся из `MOODLE_ADMIN_USER` и `MOODLE_ADMIN_PASSWORD`; это отдельный администратор стенда.

Запустите автоматическую проверку и развёртывание:

```bash
./scripts/deploy-server.sh
```

Скрипт проверяет Docker, Compose, `.env`, порт, RAM и место на диске. Затем он собирает проект, ждёт сервисы, запускает `check.sh` и реальный smoke-тест. Системные пакеты и настройки он не меняет.

То же самое вручную:

```bash
docker compose up -d --build
./scripts/check.sh
./scripts/smoke-test.sh
```

Вход: откройте значение `MOODLE_URL` и используйте `MOODLE_ADMIN_USER`/`MOODLE_ADMIN_PASSWORD` из `.env`.

## Обновление и повторный запуск

```bash
git pull
docker compose up -d --build
./scripts/check.sh
./scripts/smoke-test.sh
```

Entrypoint видит существующую базу, выполняет только upgrade плагинов и не переустанавливает Moodle. Volumes сохраняются. Перед обновлением учебного сервера сделайте резервную копию базы и `moodledata` средствами администратора сервера.

## Перенос на вузовский Moodle 5.2.1

Сначала согласуйте изменения с администратором вуза и сделайте резервную копию. Не копируйте Docker volumes и `config.php` лабораторного стенда.

Нужно перенести следующие настройки:

- CodeRunner 5.9.2 и `adaptive_adapted_for_coderunner` 1.4.5;
- адрес отдельного Jobe Server в настройке `qtype_coderunner/jobe_host` без `http://`;
- порт Jobe в «Безопасность → Безопасность HTTP», если используется не 80 или 443;
- сетевой доступ от PHP-сервера Moodle к Jobe и AI service;
- полный URL `/api/v1/analyze` и таймаут в настройках `local_aicodehelper`.

В лабораторном Compose Jobe называется `jobe`, а AI service — `ai-service`. На вузовском сервере укажите реальные DNS-имена, доступные именно с сервера Moodle. Не открывайте Jobe и Ollama в интернет.

Если CodeRunner ещё не установлен, распакуйте официальные теги в каталоги Moodle 5.2 и выполните upgrade:

```bash
curl -fsSL https://github.com/trampgeek/moodle-qtype_coderunner/archive/refs/tags/v5.9.2.tar.gz -o /tmp/coderunner.tar.gz
curl -fsSL https://github.com/trampgeek/moodle-qbehaviour_adaptive_adapted_for_coderunner/archive/refs/tags/v1.4.5.tar.gz -o /tmp/coderunner-behaviour.tar.gz
sudo mkdir -p /var/www/moodle/public/question/type/coderunner
sudo mkdir -p /var/www/moodle/public/question/behaviour/adaptive_adapted_for_coderunner
sudo tar -xzf /tmp/coderunner.tar.gz --strip-components=1 -C /var/www/moodle/public/question/type/coderunner
sudo tar -xzf /tmp/coderunner-behaviour.tar.gz --strip-components=1 -C /var/www/moodle/public/question/behaviour/adaptive_adapted_for_coderunner
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=qtype_coderunner --name=jobe_host --set=jobe.example.local:80
```

Замените пути и адрес Jobe на вузовские. Behaviour нужно распаковать до запуска upgrade, потому что это обязательная зависимость CodeRunner.

### Файлы собственного плагина

Весь разработанный Moodle-плагин находится только в `moodle/local_aicodehelper`:

- `version.php` — версия и требование Moodle 5.2.1;
- `index.php` — форма, серверный запрос и безопасный вывод;
- `settings.php` — адрес AI service и таймаут;
- `lib.php` — ссылка в навигации;
- `lang/en/local_aicodehelper.php` и `lang/ru/local_aicodehelper.php` — строки интерфейса.

### Повторная установка плагина

В Moodle 5.2 публичные плагины лежат внутри каталога `public`. Если корень Moodle — `/var/www/moodle`, выполните:

```bash
sudo mkdir -p /var/www/moodle/public/local/aicodehelper
sudo rsync -a --delete moodle/local_aicodehelper/ /var/www/moodle/public/local/aicodehelper/
sudo chown -R root:www-data /var/www/moodle/public/local/aicodehelper
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

Затем откройте «Администрирование сайта → Плагины → Локальные плагины → ИИ-помощник по коду» и задайте адрес AI service. То же через CLI:

```bash
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=local_aicodehelper --name=endpoint \
  --set=http://ai-service.example.local:8000/api/v1/analyze
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php \
  --component=local_aicodehelper --name=timeout --set=60
```

Проверьте страницу `/local/aicodehelper/index.php` под обычной авторизованной учётной записью.
