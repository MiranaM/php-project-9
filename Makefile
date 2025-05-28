PORT ?= 8000

setup:
	mkdir -p templates/layouts
	cp -n templates/layouts/base.phtml || true
	cp -n templates/index.phtml || true

install:
	composer install
	make setup

start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public

lint:
	composer exec --verbose phpcs public templates --standard=PSR12