# The storage tests run when these are set; make databases-up starts the databases they point at.
export PAYOUT_PG_DSN ?= pgsql:host=127.0.0.1;port=55434;dbname=payout;user=payout;password=payout
# Percona Server is MySQL 8.4 that starts reliably on arm64 Docker; CI tests the official
# mysql:8.4 and mysql:9 images on amd64.
MYSQL_IMAGE ?= percona/percona-server:8.4
export PAYOUT_MYSQL_DSN ?= mysql:host=127.0.0.1;port=33307;dbname=payout;user=root;password=root

.PHONY: test stan cs cs-fix check databases-up databases-down

test:
	vendor/bin/phpunit

stan:
	vendor/bin/phpstan analyse --no-progress

cs:
	vendor/bin/php-cs-fixer check --diff

cs-fix:
	vendor/bin/php-cs-fixer fix

check: cs stan test

databases-up:
	docker run -d --rm --name payout-pg -e POSTGRES_USER=payout -e POSTGRES_PASSWORD=payout -e POSTGRES_DB=payout -p 55434:5432 postgres:17-alpine
	docker run -d --rm --name payout-mysql -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=payout -p 33307:3306 $(MYSQL_IMAGE)
	until docker exec payout-pg pg_isready -U payout >/dev/null 2>&1; do sleep 1; done
	for i in $$(seq 1 120); do docker exec payout-mysql mysql -h127.0.0.1 -uroot -proot -e 'select 1' payout >/dev/null 2>&1 && exit 0; sleep 1; done; docker logs payout-mysql; exit 1

databases-down:
	docker rm -f payout-pg payout-mysql
