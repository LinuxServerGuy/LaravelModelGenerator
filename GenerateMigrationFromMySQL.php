<?php namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class GenerateMigrationFromMySQL extends Command
{

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'generate:migration';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate migration from MySQL database.';

	/**
	 * Create a new command instance.
	 *
	 * @return \App\Console\Commands\GenerateModelFromMySQL
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		preg_match('/(.+)\.(.+)/', $this->argument('database_table'), $matches);
		if (empty($matches[1]) || empty($matches[2]))
		{
			$this->error('Please enter a valid Database/Table.');
			exit();
		}
		$database_name = $matches[1];
		$table_name    = $matches[2];

		//Match the tables
		$tables = $this->getMatchingTables($database_name, $table_name);

		if (count($tables) == 0)
		{
			$this->error('Error: No tables found that match your argument: ' . $table_name);
			exit();
		}

		foreach ($tables AS $table)
		{
			$this->info('Migration: database/migrations/<date>_create_' . $this->camelCase1($table->name) . '_table.php');
		}

		$this->comment($this->rules());

		if (!$this->confirm('Are you happy to proceed? [yes|no]'))
		{
			$this->error('Error: User is a chicken.');
			exit();
		}

		foreach ($tables AS $table)
		{
			$template = $this->template();

			$template = preg_replace('/#CLASS_NAME#/', $this->camelCase1($table->name), $template);
			$template = preg_replace('/#TABLE_NAME#/', $table->name, $template);
			$template = preg_replace('/#FIELD_DESCRIPTORS#/', $this->generateFieldDescriptors($database_name, $table_name), $template) ;
			$template = preg_replace('/#FOREIGN_KEYS#/', $this->generateForeignKeys($database_name, $table_name), $template);

			file_put_contents('database/migrations/' . date('Y_m_d_His_') . 'create_' . $this->camelCase1($table->name) . '_table.php', $template);

		}

		$this->info("\n** The migrations have been created **\n");

	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return [
			['database_table', InputArgument::REQUIRED, 'Qualified table name (database.table)'],
		];
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
			//['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
		];
	}

	private function getMatchingTables($database, $table)
	{
		$string = preg_replace('/\*/', '%', $table);

		return DB::select("SELECT TABLE_NAME AS name FROM information_schema.tables
							WHERE TABLE_SCHEMA='{$database}' AND TABLE_NAME LIKE '{$string}'");

	}

	private function generateFieldDescriptors($database_name, $table_name)
	{
		$fields = DB::select("SELECT columns.COLUMN_NAME, columns.COLUMN_DEFAULT, columns.IS_NULLABLE, columns.DATA_TYPE,
							columns.CHARACTER_MAXIMUM_LENGTH, columns.NUMERIC_PRECISION, columns.NUMERIC_SCALE, columns.DATETIME_PRECISION,
							columns.COLUMN_TYPE, columns.COLUMN_KEY, columns.EXTRA, columns.COLUMN_COMMENT
							FROM information_schema.columns
							WHERE columns.TABLE_SCHEMA='{$database_name}' AND columns.TABLE_NAME='{$table_name}' AND columns.COLUMN_NAME != 'id'
							ORDER BY columns.ORDINAL_POSITION");

		$descriptors = '' ;
		$mysql_hacks = '' ;

		foreach($fields AS $field)
		{
			$descriptor = "\t\t\$table->" ;
			$hack = false ;

			switch(strtoupper($field->DATA_TYPE))
			{
				case 'BIGINT' :
					$descriptor .= "bigInteger('{$field->COLUMN_NAME}')" ;
					break ;
				case 'BLOB' :
					$descriptor .= "binary('{$field->COLUMN_NAME}')" ;
					break ;
				case 'LONGBLOB' :
					$descriptor = '' ;
					$hack = true ;
					$mysql_hacks .= "\tDB::statement('ALTER TABLE {$database_name}.{$table_name} ADD {$field->COLUMN_NAME} LONGBLOB') ;" ;
					break ;
				case 'MEDIUMBLOB' :
					$descriptor = '' ;
					$hack = true ;
					$mysql_hacks .= "\tDB::statement('ALTER TABLE {$database_name}.{$table_name} ADD {$field->COLUMN_NAME} MEDIUMBLOB') ;" ;
					break ;
				case 'BOOLEAN' :
					$descriptor .= "boolean('{$field->COLUMN_NAME}')" ;
					break ;
				case 'CHAR' :
					$descriptor .= "char('{$field->COLUMN_NAME}', {$field->CHARACTER_MAXIMUM_LENGTH})"  ;
					break ;
				case 'DATE' :
					$descriptor .= "date('{$field->COLUMN_NAME}')" ;
					break ;
				case 'DATETIME' :
					$descriptor .= "dateTime('{$field->COLUMN_NAME}')" ;
					break ;
				case 'DECIMAL' :
					$descriptor .= "decimal('{$field->COLUMN_NAME}', PRECISION, SCALE)" ;
					break ;
				case 'DOUBLE' :
					$descriptor .= "double('{$field->COLUMN_NAME}', {$field->NUMERIC_PRECISION}, {$field->NUMERIC_SCALE})" ;
					break ;
				case 'ENUM' :
					$descriptor .= "enum('{$field->COLUMN_NAME}', [" . preg_replace('/\)$/', '', preg_replace('/enum\(/', '', $field->COLUMN_TYPE)) . "])" ;
					break ;
				case 'FLOAT' :
					$descriptor .= "float('{$field->COLUMN_NAME}')" ;
					break ;
				case 'INT' :
					$descriptor .= "integer('{$field->COLUMN_NAME}')" ;
					break ;
				case 'INTEGER' :
					$descriptor .= "integer('{$field->COLUMN_NAME}')" ;
					break ;
				case 'JSON' :
					$descriptor .= "json('{$field->COLUMN_NAME}')" ;
					break ;
				case 'JSONB' :
					$descriptor .= "jsonb('{$field->COLUMN_NAME}')" ;
					break ;
				case 'LONGTEXT' :
					$descriptor .= "longText('{$field->COLUMN_NAME}')" ;
					break ;
				case 'MEDIUMINT' :
					$descriptor .= "mediumInteger('{$field->COLUMN_NAME}')" ;
					break ;
				case 'MEDIUMTEXT' :
					$descriptor .= "mediumText('{$field->COLUMN_NAME}')" ;
					break ;
				case 'SMALLINT' :
					$descriptor .= "smallInteger('{$field->COLUMN_NAME}')" ;
					break ;
				case 'VARCHAR' :
					$descriptor .= "string('{$field->COLUMN_NAME}', {$field->CHARACTER_MAXIMUM_LENGTH})" ;
					break ;
				case 'TEXT' :
					$descriptor .= "text('{$field->COLUMN_NAME}')" ;
					break ;
				case 'TIME' :
					$descriptor .= "time('{$field->COLUMN_NAME}')" ;
					break ;
				case 'TINYINT' :
					$descriptor .= "tinyInteger('{$field->COLUMN_NAME}')" ;
					break ;
				case 'TIMESTAMP' :
					$descriptor .= "timestamp('{$field->COLUMN_NAME}')" ;
					break ;
				default:
					break ;
			}

			if(!$hack)
			{
				$descriptor .= $this->generateNullable($field);
				$descriptor .= $this->generateFieldUniqueness($field);
				$descriptor .= $this->generateDefault($field);
			}
			$descriptors .= $descriptor . " ;\n" ;
		}

		$descriptors .= "\n" ;

		if(!empty($mysql_hacks))
			$descriptors .= "\n{$mysql_hacks}\n" ;

		return $descriptors ;

	}

	private function generateForeignKeys($database_name, $table_name)
	{
		$fields = DB::select("SELECT key_column_usage.TABLE_SCHEMA, key_column_usage.TABLE_NAME, key_column_usage.COLUMN_NAME,
									key_column_usage.REFERENCED_TABLE_SCHEMA, key_column_usage.REFERENCED_TABLE_NAME, key_column_usage.REFERENCED_COLUMN_NAME
								  FROM information_schema.key_column_usage
								  WHERE TABLE_SCHEMA='{$database_name}' AND TABLE_NAME='{$table_name}'
								AND REFERENCED_COLUMN_NAME IS NOT NULL");

		if(empty($fields))
			return '' ;

		$foreign_keys = "\t/**  Foreign Key Relations  **/\n";

		foreach($fields AS $field)
		{
			$foreign_keys .= "\n\t\t\$table->foreign('{$field->COLUMN_NAME}')->references('{$field->REFERENCED_TABLE_NAME}')->on('{$field->REFERENCED_COLUMN_NAME}') ;"; //->onDelete('cascade')" ;
		}

		return $foreign_keys ;
	}

	private function generateFieldUniqueness($field)
	{
		if($field->COLUMN_KEY == 'UNI')
			return '->unique()' ;

		return '' ;
	}

	private function generateFieldLength($length)
	{
		if(empty($length))
			return '' ;

		return ", {$length}" ;
	}

	private function generateNullable($field)
	{
		if($field->IS_NULLABLE == 'NO')
			return '' ;

		return '->nullable()' ;
	}

	private function generateDefault($field)
	{
		if(!empty($field->COLUMN_DEFAULT) && (!empty(stringValue($field->COLUMN_DEFAULT))))
			return "->default('{$field->COLUMN_DEFAULT}')" ;

		return '' ;
	}

	//Camel case with init cap
	private function camelCase1($string, array $noStrip = [])
	{
		// non-alpha and non-numeric characters become spaces
		$string = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $string);
		$string = trim($string);
		// uppercase the first character of each word
		$string = ucwords($string);
		$string = str_replace(" ", "", $string);

		return $string;
	}

	//Camel case with no init cap
	private function camelCase2($string, array $noStrip = [])
	{
		// non-alpha and non-numeric characters become spaces
		$string = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $string);
		$string = trim($string);
		// uppercase the first character of each word
		$string = ucwords($string);
		$string = str_replace(" ", "", $string);
		$string = lcfirst($string);

		return $string;
	}

	private function rules()
	{
		$rules = "\n==== Please ensure you follow the rules to avoid any problems ====\n";
		$rules .= "1) Table names should be singular.  If you think differently, you are wrong.\n";
		$rules .= "2) Tables have an auto-increment field named 'id'.\n";
		$rules .= "3  Foreign key relations might fail depending on the order you generate the migrations. (you wont realise until you deploy)\n";
		$rules .= "4) The models in RED will be overwritten! (as database/migrations/<date>_create_<migration>_table.php)\n";

		return $rules;
	}


	private function template()
	{
		return '<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Create#CLASS_NAME#Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(\'#TABLE_NAME#\', function (Blueprint $table) {
            $table->increments(\'id\');
#FIELD_DESCRIPTORS#

#FOREIGN_KEYS#
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop(\'#TABLE_NAME#\');
    }
}';

	}
}