# Laravel Model Generator (For L5)
###Generates Laravel 5 models from existing MySQL database schema.

Copy GenerateModelFromMYSQL.php to app/Console/Commands/

Edit app/Console/Kernel.php, add the following line to the $commands[] array:
```
'App\Console\Commands\GenerateModelFromMySQL',
```

Set up your MySQL connection within Laravel, then use the generator as follows:
```
php artisan generate:model <database>.<table>
```

Table param accepts the * wildcard.
