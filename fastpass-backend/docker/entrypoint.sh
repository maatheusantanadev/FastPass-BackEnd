#!/bin/sh
set -e

cd /var/www

# Instala dependências na primeira execução
if [ ! -f vendor/autoload.php ]; then
    echo ">> Instalando dependências do Composer..."
    composer install --no-interaction --prefer-dist
fi

# Garante a existência do .env
if [ ! -f .env ]; then
    echo ">> Criando .env a partir do .env.example..."
    cp .env.example .env
fi

# Gera a APP_KEY se ainda não existir
if ! grep -q "^APP_KEY=base64" .env; then
    php artisan key:generate --force
fi

# Aplica a config do .env (limpa cache antigo)
php artisan config:clear

# Roda migrations + seed (idempotente). Não derruba o container se falhar,
# para o servidor subir mesmo assim e o erro do banco ficar visível nos logs.
echo ">> Rodando migrations + seed..."
php artisan migrate --seed --force || echo ">> AVISO: migrate/seed falhou — verifique as credenciais do banco (Supabase) no .env."

# A hospedagem (Render/Railway/Fly) injeta a porta em $PORT; local usa 8000.
PORT="${PORT:-8000}"
echo ">> FastPass API na porta ${PORT}"
php artisan serve --host=0.0.0.0 --port="${PORT}"
