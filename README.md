## Установка

Чтобы подключить либу в свой проект, нужно прописать в composer.json

```json
{
    "repositories": [
        {
            "type": "git",
            "url": "git@git.semalt.com:libs/dbwrapper.git"
        }
    ],
    "require": {
        "semalt/db-wrapper": "dev-master"
    }
}

```

и потом сделать `composer update`

## Использование

```php
use DBWrapper\Connection;

$db = new Connection('host', 'user', 'pass', 'optional_database');
$db->query('SELECT ...')->fetchAll();
```