Adaptive API
===========================

Adaptive API package features

> ✅ Dynamically generate custom API structures based on client requirements  
> ✅ Allow clients to fetch data with dynamic filtering options  
> ✅ Retrieve only the required tables and columns from the database  
> ✅ Access all database tables through a single API endpoint (one unified URL).  
> – Clients send the desired table structures in the request body, and the API returns exactly what is requested  
>

Installation
------------

Either run

```sh
composer require yunusbek/adaptive-api
```

or add

```json
"yunusbek/adaptive-api": "^1.0",
```

to the ```require``` section of your composer.json.


Installation
------------

1. Create ```.env``` file in the root of your application:
```dotenv
API_DB_ROLE=api_reader
API_DB_USER=api_user_api
API_DB_PASSWORD=_@p!_reaDer_
#API_DB_HOST=localhost
#API_DB_NAME=your-dbname
#API_DB_PORT=5432
UPLOAD_FOLDER_PATH=/uploads/
```

2. Load ```.env``` inside ```web/index.php``` and root ```yii``` file:
```php
#...
$dotenv = Dotenv\Dotenv::createUnsafeMutable(dirname(__DIR__.'/../.env'));
$dotenv->load();
#...
```

3. Run migration to create the ```api_user``` database user and apply permissions:
```sh
php yii migrate --migrationPath=@vendor/yunusbek/adaptive-api/src/migrations
```


```php
# Add the following code to controllerMap
[
    #...
    'controllerMap' => [
        'api-schema-migration' => 'Yunusbek\AdaptiveApi\commands\Migrations',
    ],
    #...
]
```

The next thing you need to do is updating your database schema by applying the migration of table ```client_api_schema_permissions```:

```sh
php yii api-schema-migration
```

Example to permission schema
# table - client_api_schema_permissions
# column - schema:jsonb

```json
{
  "{user}": {
    "username": "",
    "email": "",
    "phone": "",
    "address": ""
  },
  "{company}": {
    "company_name": "",
    "email": "",
    "phone": "",
    "address": ""
  }
}
```

Usage
---
Example to api request structure
```json
{
  "customer": {
    "personal_info": {
      "person": "{user}.username",
      "email": "{user}.email",
      "gender": "{user}.gender",
      "age": "TO_CHAR(TO_TIMESTAMP({user}.age), 'DD.MM.YYYY')",
      "personal_phone": "{user}.phone",
      "personal_address": "{user}.address"
    },
    "company_info": {
      "company": "{company}.company_name",
      "company_email": "{company}.email",
      "company_phone": "{company}.phone",
      "company_address": "{company}.address"
    },
    "position_info": {
      "user_department": "{position}.department",
      "user_position": "{position}.name",
      "product_info": {
        "product_name": "{product}.name",
        "product_type": "{product}.type",
        "product_cost": "{product}.cost"
      }
    }
  }
}
```
result
```json
{
  "customer": {
    "personal_info": {
      "person": "John Doe",
      "email": "johndoe@example.com",
      "gender": "male",
      "personal_phone": "+9989********",
      "personal_address": "Some address data"
    },
    "company_info": {
      "company": "Example LLC",
      "company_email": "example@example.com",
      "company_phone": "+9989********",
      "company_address": "Some address data"
    },
    "position_info": {
      "user_department": "Sales department",
      "user_position": "seller",
      "product_info": {
        "product_name": "Bicycle",
        "product_type": "Sport",
        "product_cost": "$33.5"
      }
    }
  }
}
```
---

```php
$root = [
    'unique_number' => 'user_id',
    'select' => [
        "user_id",
        "position_id",
        "company_id",
        "product_id" => "product.id",
        "started_date",
        "status",
    ],
    'class' => UserRelCompany::class, // or by table name 'table' => 'user_rel_company'
    'join' => [
        ['JOIN', "products AS product", 'on' => ["user_id" => "user_id"], 'condition' => ['status' => 'ACTIVE']]
    ],
    'where' => [
        'current_company' => true,
        'product.type' => ['building', 'food', 'sport']
    ],
    'filter' => [
        'product_type' => "product.type",
    ]
];

$relations = [
    'user' => [
        'on' => ["id" => "user_id"],
        'class' => Users::class // or users_table_name,
        'select' => [
            "username",
            "email",
            "gender",
            "phone",
            "address",
        ],
        'where' => [
            'status' => 'ACTIVE'
        ]
    ],
    'company' => [
        'on' => ["id" => "company_id"],
        'class' => Companies::class // or companies_table_name,
        'select' => [
            "company_name" => 'name',
            "email",
            "phone",
            "address",
        ],
        'where' => [
            'status' => 'ACTIVE'
        ]
    ],
    'position' => [
        'on' => ["id" => "position_id"],
        'class' => UserStaffPosition::class,
        'select' => [
            "name",
            "department",
        ],
        'where' => [
            'status' => 'ACTIVE'
        ]
    ],
    'product' => [
        'on' => ["id" => "product_id"],
        'class' => Products::class // or products_table_name,
        'select' => [
            "name",
            "type",
            "cost",
        ],
        'where' => [
            'status' => 'ACTIVE'
        ]
    ],
    #... other relations tables
];
$response = CteBuilder::root($root) // root table
    ->relation($relations)          // relation tables
    ->with($with)                   // if you need with cte
    ->queryParams($params)          // GET query string params (client request)
    ->template($sendTemplate)       // body params (client request structure)
//    ->setCallback(function (string $text, string $lang) use ($params) {           // this is optional if you want to add some callback function
//        if ($lang === 'en') { $lang = 'ru'; }
//        return KirillToLatin::widget(['text' => $text, 'lang' => $lang]);
//    }, '/^\{(uz|ru|en)\}(.*)/')
    ->getApi();                     // finish
```
---