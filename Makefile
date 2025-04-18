DOCKER_PHP = docker-compose exec php_core_package

stan:
	$(DOCKER_PHP) vendor/bin/phpstan analyse --configuration=phpstan.neon --ansi --error-format=compact --verbose --memory-limit=1G
fix:
	$(DOCKER_PHP) vendor/bin/ecs check --ansi --fix
test:
	$(DOCKER_PHP) php -d memory_limit=512M vendor/bin/phpunit  --configuration phpunit.xml.dist