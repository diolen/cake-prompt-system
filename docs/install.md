### Шаг 1: Структура нового проекта

Давайте создадим чистую и понятную структуру папок для нашей системы. Создайте в вашей новой директории следующие папки и файлы:

```text
cake-prompt-system/
├── src/
│   ├── Analyzer/        # Шаг 1: Анализатор кода
│   ├── Generator/       # Шаг 2: Генератор промптов
│   └── Shared/          # Валидаторы, JSON-схемы, общие хелперы
├── app.php              # Главный CLI-файл запуска системы
├── composer.json        # Зависимости (парсер, валидатор JSON)
├── docker-compose.yml   # Окружение
└── Dockerfile

```

---

### Шаг 2: Инициализация окружения (Docker + Composer)

Давайте сразу заложим фундамент, чтобы код работал в изолированном контейнере.

Создайте файл **`composer.json`**:
туда мы добавим `nikic/php-parser` (индустриальный стандарт для анализа PHP-кода, на нем работает PHPStan и Rector) и валидатор JSON-схем.

```json
{
    "name": "custom/cake-prompt-system",
    "description": "LLM Prompt Generator based on CakePHP static analysis",
    "type": "project",
    "require": {
        "php": ">=8.2",
        "nikic/php-parser": "^5.0",
        "justinrainbow/json-schema": "^5.2"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}

```

Создайте **`Dockerfile`**:

```dockerfile
FROM php:8.3-cli-alpine

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

CMD ["php", "app.php"]

```

Создайте **`docker-compose.yml`**:

```yaml
version: '3.8'

services:
  analyzer:
    build: .
    volumes:
      - .:/app
      # Сюда мы позже примонтируем ваш реальный legacy-проект для анализа:
      # - /path/to/your/legacy-project:/app/legacy_project:ro

```

---

### Ваш следующий шаг

1. Создайте эти файлы в новой папке.
2. Запустите сборку и установку зависимостей в терминале:
```bash
docker-compose build
docker-compose run --rm analyzer composer install

```