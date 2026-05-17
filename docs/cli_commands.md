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

## Build(docker): force clean rebuild of the analyzer service
- Added `--no-cache` flag to guarantee pristine environment setup.
- Forced Composer to clean-install all dependencies from scratch.
- Eliminated any potential ghost file artifacts left from prior layers.
docker-compose build --no-cache analyzer