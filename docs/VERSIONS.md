# Версии компонентов

Версии проверены 14 июля 2026 года. По обязательному требованию учебного задания используется строго Moodle 5.2.1. В `public/admin/environment.xml` этого выпуска указаны минимум PHP 8.3 и MariaDB 10.11.

| Компонент | Версия | Почему |
| --- | --- | --- |
| Moodle | 5.2.1 | Точная версия из задания, официальный тег `v5.2.1` |
| PHP | 8.3.28, Apache, Debian Bookworm | Moodle 5.2 требует PHP 8.3 или новее; образ доступен для amd64/arm64 |
| CodeRunner | 5.9.2 | Официальный стабильный тег требует Moodle 4.3+ и PHP 8.1+, поэтому совместим с 5.2.1 |
| adaptive_adapted_for_coderunner | 1.4.5 | Обязательная зависимость CodeRunner |
| Jobe | 2.2.2 | Закреплённый выпуск официального Jobe Server |
| MariaDB | 11.4.12 LTS | Долгосрочно поддерживаемая ветка, официальный Docker-образ |
| Ollama | 0.30.11 | Стабильный образ для amd64 и arm64 |
| Модель | qwen2.5-coder:0.5b | Минимальная официальная code-модель, около 398 МБ; подходит для сервера с 4 ГБ RAM |
| Python | 3.12 | Стабильная версия из Debian Bookworm-образа |

Официальные источники:

- [официальный тег Moodle 5.2.1](https://github.com/moodle/moodle/releases/tag/v5.2.1);
- [требования Moodle 5.2](https://github.com/moodle/moodle/blob/v5.2.1/public/admin/environment.xml);
- [репозиторий CodeRunner](https://github.com/trampgeek/moodle-qtype_coderunner);
- [репозиторий Jobe](https://github.com/trampgeek/jobe);
- [официальный контейнер JobeInABox](https://github.com/trampgeek/jobeinabox);
- [официальный образ MariaDB](https://hub.docker.com/_/mariadb);
- [выпуски Ollama](https://github.com/ollama/ollama/releases);
- [карточка qwen2.5-coder](https://ollama.com/library/qwen2.5-coder).
