# Правила работы с репозиторием

- Не изменяйте ядро Moodle, CodeRunner и минифицированные JavaScript-файлы.
- Изменения окружения описывайте в `compose.yaml`, Dockerfile или скриптах.
- Не добавляйте `.env`, дампы базы и данные Docker volumes в Git.
- Перед коммитом запускайте `make test`, `make check` и `make smoke`, если Docker доступен.
- Не удаляйте volumes без явного подтверждения `CONFIRM=yes`.
- Не выполняйте `git push` без разрешения владельца репозитория.

