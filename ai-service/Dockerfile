FROM python:3.12.10-slim-bookworm AS base

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app

COPY ai-service/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

RUN useradd --create-home --uid 10001 appuser

# Отдельный этап тестов не попадает в рабочий образ.
FROM base AS test

COPY ai-service/requirements-test.txt .
RUN pip install --no-cache-dir -r requirements-test.txt
COPY ai-service/app ./app
COPY ai-service/tests ./tests

USER appuser
ENV PYTHONPATH=/app
CMD ["pytest", "-q", "-p", "no:cacheprovider"]

FROM base AS production

# Рабочий образ содержит только приложение и его основные зависимости.
COPY ai-service/app ./app

USER appuser
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
