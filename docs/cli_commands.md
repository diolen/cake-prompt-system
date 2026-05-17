# Тест режима REFACTOR
docker-compose run --rm analyzer php app.php docs/UsersController.php REFACTOR

# Тест режима FEATURE
docker-compose run --rm analyzer php app.php docs/UsersController.php FEATURE

# Тест режима DEBUG
docker-compose run --rm analyzer php app.php docs/UsersController.php DEBUG

docker-compose run --rm analyzer php app.php docs/User.php DEBUG