#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
VAYTOVEN_PHP="${VAYTOVEN_PHP:-/opt/homebrew/opt/php@8.4/bin/php}"
VAYTOVEN_MYSQL="${VAYTOVEN_MYSQL:-/opt/homebrew/opt/mysql@8.4/bin}"
VAYTOVEN_REDIS="${VAYTOVEN_REDIS:-/opt/homebrew/opt/redis/bin}"
[[ -f .env && -f vendor/autoload.php && -d .local/mysql/mysql ]] || { echo 'Complete the setup in docs/LOCAL-AND-MOBILE.md first.'; exit 1; }
mkdir -p .local/redis
if ! "$VAYTOVEN_MYSQL/mysqladmin" --no-defaults --socket="$PWD/.local/mysql.sock" -u root ping >/dev/null 2>&1; then
  nohup "$VAYTOVEN_MYSQL/mysqld" --no-defaults --datadir="$PWD/.local/mysql" --bind-address=127.0.0.1 --port=3307 --socket="$PWD/.local/mysql.sock" --mysqlx=0 --log-bin-trust-function-creators=1 --pid-file="$PWD/.local/mysql.pid" --log-error="$PWD/.local/mysql.log" >.local/mysql-stdout.log 2>&1 &
fi
if ! "$VAYTOVEN_REDIS/redis-cli" -h 127.0.0.1 -p 6380 ping >/dev/null 2>&1; then
  nohup "$VAYTOVEN_REDIS/redis-server" --bind 127.0.0.1 --port 6380 --dir "$PWD/.local/redis" --appendonly yes >.local/redis.log 2>&1 &
  echo $! >.local/redis.pid
fi
for i in {1..30}; do
  if "$VAYTOVEN_MYSQL/mysqladmin" --no-defaults --socket="$PWD/.local/mysql.sock" -u root ping >/dev/null 2>&1 && "$VAYTOVEN_REDIS/redis-cli" -h 127.0.0.1 -p 6380 ping >/dev/null 2>&1; then break; fi
  sleep 1
done
"$VAYTOVEN_MYSQL/mysqladmin" --no-defaults --socket="$PWD/.local/mysql.sock" -u root ping >/dev/null
"$VAYTOVEN_REDIS/redis-cli" -h 127.0.0.1 -p 6380 ping >/dev/null
if ! curl --fail --silent http://127.0.0.1:8000/up >/dev/null; then
  nohup "$VAYTOVEN_PHP" -d memory_limit=768M artisan serve --host=127.0.0.1 --port=8000 >.local/laravel-server.log 2>&1 &
  echo $! >.local/laravel.pid
fi
if ! pgrep -f "$VAYTOVEN_PHP artisan queue:work" >/dev/null; then
  nohup "$VAYTOVEN_PHP" artisan queue:work --sleep=3 --tries=1 --timeout=60 >.local/queue.log 2>&1 &
  echo $! >.local/queue.pid
fi
for i in {1..30}; do
  if curl --fail --silent http://127.0.0.1:8000/up >/dev/null; then break; fi
  sleep 1
done
curl --fail --silent http://127.0.0.1:8000/up >/dev/null
echo 'Website: http://127.0.0.1:8000'
echo 'Mobile preview: http://127.0.0.1:8000/app/'
if [[ "${1:-}" == '--foreground' ]]; then
  wait
fi
