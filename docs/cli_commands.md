# Sic project - Controllers
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Controller/SicsController.php REFACTOR
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Controller/EventualContractReportsController.php DEBUG

# Sic project - Models
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Model/Sic.php REFACTOR
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Model/Sic.php FEATURE --instruction="Test instruction parameter TEST"

# Sic project - View (новая функциональность)
docker-compose run --rm analyzer php app.php /sic/app/View/Centers/admin_index.ctp FEATURE
docker-compose run --rm analyzer php app.php /sic/app/View/Reports/admin_reports_costs.ctp FEATURE --instruction="Добавить фильтр по дате"

## Build(docker): force clean rebuild of the analyzer service
- Added `--no-cache` flag to guarantee pristine environment setup.
- Forced Composer to clean-install all dependencies from scratch.
- Eliminated any potential ghost file artifacts left from prior layers.
docker-compose build --no-cache analyzer

# Прогон кастомной инструкции
docker-compose run --rm analyzer php -d memory_limit=-1 app.php /sic/app/Controller/SicsController.php REFACTOR --instruction="Test instruction parameter TEST"