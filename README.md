Multilingual
===========================

A fully featured multilingual management package for Yii2 projects.

> ✅ Dynamically translate database content  
> ✅ Support for multiple languages with individual tables (lang_*)  
> ✅ Form-level multilingual fields  
> ✅ Static translations (i18n) integration  
> ✅ Excel-based bulk import/export of translations
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

Usage
------------

Once the library is installed, add the following to your project settings:

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

The next important processing steps in the project settings.

```php
# for yii2 basic - config/web.php
# for yii2 advanced - config/main.php
[
    #...
    'modules' => [
        'multilingual' => [
            'class' => 'Yunusbek\Multilingual\Module',
        ],
    ]
    #...
]
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
      "personal_phone": "{user}.phone",
      "personal_address": "{user}.address"
    },
    "company_info": {
      "company": "{company}.company_name",
      "company_email": "{company}.email",
      "company_phone": "{company}.phone",
      "company_address": "{company}.address"
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
            "company_id",
            "started_date",
            "status",
            "product_id" => "product.id",
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
    ]
];

$relations = [

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
> ✅   If you are writing raw SQL conditions (e.g., where, select, join, etc.) instead of using Yii2's ORM syntax, you need to include the following code to ensure that the multilingual feature works properly.
>
```sql
SELECT
    -- Replace `your_table_name.attribute_name` with a multilingual fallback expression
    COALESCE(NULLIF(your_real_table_name_lang_en.value->>'attribute_name', ''), your_table_name.attribute_name) AS attribute_name
FROM
    your_real_table_name AS your_table_name

-- Add JOIN with the multilingual table to fetch translations
LEFT JOIN lang_en AS your_real_table_name_lang_en
    ON your_real_table_name_lang_en.table_name = 'your_real_table_name'
    AND your_real_table_name_lang_en.table_iteration = your_table_name.id
    AND your_real_table_name_lang_en.is_static = false

WHERE
    -- Add your filtering conditions here
```
---

### Form fields:

Add MlFields widget to your form — it will auto-generate inputs for newly added languages.

```php
<?php $form = ActiveForm::begin(); ?>
    #...
    <?php echo \Yunusbek\Multilingual\widgets\MlFields::widget([
        'form' => $form,
        'model' => $model,
        'table_name' => 'model_table_name', # set the model table name to output model attributes to the lang_* table.
        'attribute' => 'attribute_name', # or add multiple like ['attribute_name', 'second_attribute_name']
        //'label' => false, # or 'Some label text' or ['text' => 'Some label text', 'options' => []]
        //'type' => 'textInput', # or 'textarea'
        //'options' => ['class' => 'form-control'], # input options
        //'wrapperOptions' => ['class' => 'form-group'], # parent element options
    ]) ?>
    #...
<?php ActiveForm::end(); ?>
```

```php
<?php $form = ActiveForm::begin(); ?>
    
    <?php MlTabs::begin([
        'tab' => 'basic', # or 'vertical'
        // 'contentOptions' => [],
        // 'headerOptions' => [],
    ]); ?>
    #...
    <?php echo \Yunusbek\Multilingual\widgets\MlFields::widget([
        'form' => $form,
        'model' => $model,
        'table_name' => 'model_table_name', # set the model table name to output model attributes to the lang_* table.
        'attribute' => 'attribute_name', # or add multiple like ['attribute_name', 'second_attribute_name']
        'tab' => true,
        //'label' => false, # or 'Some label text' or ['text' => 'Some label text', 'options' => []]
        //'type' => 'textInput', # or 'textarea'
        //'options' => ['class' => 'form-control'], # input options
        //'wrapperOptions' => ['class' => 'form-group'], # parent element options
    ]) ?>
    #...
    <?php MlTabs::end(); ?>

<?php ActiveForm::end(); ?>
```
When filling data through the backend, use the ->setMlAttributes() method for dynamic languages. By appending suffixes like _ru, _en to the base attribute, the system determines which language the value should be stored in.
```php
$model->setAttributes([
        'name' => 'olma'
    ])
    ->setMlAttributes([
        'name_ru' => 'яблоко',
        'name_en' => 'apple',
    ]);
```
All added languages will automatically be displayed on the form page. From here you can type in the translation of all your newly added languages.
- ⭐ Default language;
- and subsequent form inputs are automatically created for newly added languages;
![All added languages will be displayed on the form page.](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/form.jpg)


Run the following commands to extract the attributes of the models and the static information of the project to the ```lang_*``` table.:

### Console commands

The package includes the following console commands:
```sh
php yii ml-extract/i18n
```
- `ml-extract/i18n` – Extracts static messages used in the app to the database.


```sh
php yii ml-extract/attributes
```
- `ml-extract/attributes` - It collects all the tables and columns called in the MlFields widget into a `multilingual.json` file.
````json
{
  "where": {
    "status": 1
  },
  "tables": {
    "manuals_application_type": ["name"],
    "manuals_collateral_type": ["name"],
    "manuals_department_relevant_type": ["name", "short_name"],
    ...
  }
}
````
`where` applies to all tables. You can extend this system to support per-table filters in future releases.

Necessary additions
===========================

>Not only can you translate new languages one by one on the form page, but you can also do it by translating a single Excel file in bulk.

Useful buttons to install
------------
Add the following button to the top of the created CRUD index page which will take you to the general translations page.

````php
echo Html::a(Yii::t('multilingual', 'All columns'), ['/multilingual/language/index', 'is_static' => 0], ['target' => '_blank']);  // it will take you to all dynamic translations
echo Html::a(Yii::t('multilingual', 'All i18n'), ['/multilingual/language/index', 'is_static' => 1], ['target' => '_blank']);     // it will take you to all static translations
````


Instruction manual
------------
### Excel-based Translation Import

You can bulk-import translations for a new language via an Excel file.

Steps:
1. Download an existing translation as Excel.
2. Translate its contents for the new language.
3. Upload the translated Excel file.
4. Set the path of this Excel file to the `import_excel` attribute of the `language_list` table.

✅ The package will automatically parse the file and save the translations to the appropriate `lang_*` table.

> The image below shows the Excel format used for translating dynamic data. The original language values appear in the fields marked with a red border (column header: ```value```). Only these values need to be translated. 
>
> 💡 As a shortcut, you can also translate the file using Google Translate's document feature.
>
>![This is an Excel for translate dynamic data](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/dinamic.jpg)

> The image below shows the Excel format used for translating static interface texts. Fill in the cells highlighted with a red border with the appropriate translations. If a row is empty, the translation of the corresponding ```Keywords``` column value will be used instead.
>
> ⚠️ Note: This file cannot be used with Google Translate's document translation feature — only individual text translation is supported.
>
>![This is an Excel for translate static data](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/static.jpg)

> When adding a new language, you can save the path of the translated Excel file above to the ```import_excel``` attribute in the ```language_list``` table.
> 
> Result: all translations for the newly added language will be saved, automatically saved from the Excel file to the new ```lang_*``` table.
>

Result:
------------
- When the system is set to the default language:
![before](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/result1.jpg)
  
  
- When the system is set to a newly added language:
![after](https://github.com/yunusbek9669/multilingual/blob/main/dist/img/result2.jpg)
