# Laravel Model & Migration Generator
###Generates Laravel 5 models and migrations from existing MySQL database schema.

Use composer to create the stub with
```
php artisan make:command GenerateModelFromMySQL
php artisan make:command GenerateMigrationFromMySQL
```

Copy GenerateModelFromMySQL.php and GenerateMigrationFromMySQL.php to app/Console/Commands/

Update app/Console/Kernel.php and add to the $commands[] array:
```
\App\Console\Commands\GenerateModelFromMySQL::class,
\App\Console\Commands\GenerateMigrationFromMySQL::class,
```

Set up your MySQL connection within Laravel, then use the generator as follows:
```
php artisan generate:model <database>.<table>
```

Table param accepts the * wildcard.

After the models are generated, uncomment the required $fillable fields.
Timestamps field is included, but probably not required when the model is generated in the MySQL->Laravel direction.
