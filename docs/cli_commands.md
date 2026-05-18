# Тест режима REFACTOR
docker-compose run --rm analyzer php app.php docs/UsersController.php REFACTOR

# Тест режима FEATURE
docker-compose run --rm analyzer php app.php docs/UsersController.php FEATURE

# Тест режима DEBUG
docker-compose run --rm analyzer php app.php docs/UsersController.php DEBUG

docker-compose run --rm analyzer php app.php docs/User.php DEBUG

# Sic project
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Controller/SicsController.php REFACTOR
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Model/Sic.php REFACTOR
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Controller/EventualContractReportsController.php DEBUG

## Build(docker): force clean rebuild of the analyzer service
- Added `--no-cache` flag to guarantee pristine environment setup.
- Forced Composer to clean-install all dependencies from scratch.
- Eliminated any potential ghost file artifacts left from prior layers.
docker-compose build --no-cache analyzer


# Прогон кастомной инструкции
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Controller/SicsController.php REFACTOR --instruction="Вынеси хардкод ID компании 111 в глобальный конфиг Configure::read('Company.default_id') и добавь логирование через CakeLog::write"


# Тестируем глубокий анализ моделей
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Model/Sic.php FEATURE
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Model/Sic.php REFACTOR --instruction="Оптимизируй legacy-циклы foreach, перепиши их через Hash::extract или Hash::combine, где это возможно, для ускорения работы на PHP 5.6"