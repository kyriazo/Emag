COMPOSER_MEMORY_LIMIT=-1 composer create-project drupal-composer/drupal-project:8.x-dev web --stability dev --no-interaction --no-install &&
  cd web &&
  cp -r . ../ &&
  cd .. &&
  rm -rf web &&
  perl -i -pe's/web\//public_html\//g' composer.json &&
  composer install