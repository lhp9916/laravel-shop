@servers(['web' => ['root@144.202.89.181']])

@task('deploy', ['on' => 'web'])
    cd /var/www/laravel-shop
    git pull
    composer install
    yarn
    npm run dev
@endtask