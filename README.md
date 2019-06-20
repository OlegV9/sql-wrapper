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