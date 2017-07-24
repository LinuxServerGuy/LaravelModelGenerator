<?php namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class GenerateRequestFromMySQL extends Command
{

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'generate:request';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate request from MySQL database.';

	/**
	 * Create a new command instance.
	 *
	 * @return \App\Console\Commands\GeneraterequestFromMySQL
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
		preg_match('/((.+)\.)?(.+)/', $this->argument('database_table'), $matches);
		if(empty($matches[2]))
			$matches[2] = env('DB_DATABASE') ;	//No longer require the database name to be provided
		if (empty($matches[2]) || empty($matches[3]))
		{
			$this->error('Please enter a valid Database/Table.');
			exit();
		}
		$database_name = $matches[2];
		$table_name    = $matches[3];

		//Match the tables
		$tables = $this->getMatchingTables($database_name, $table_name);

		if(count($tables) == 0)
		{
			$this->error('Error: No tables found that match your argument: ' . $table_name);
			exit();
		}

		$files_exist = false;
		foreach ($tables AS $table)
		{
			$files_exist = true;
			if (file_exists('app/' . $this->camelCase1($table->name) . '.php'))
				$this->error("Table: {$database_name}.{$table->name}");
			else
				$this->info("Table: {$database_name}.{$table->name}");
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

			$fields          = $this->getTableFields($database_name, $table->name);

			$template = preg_replace('/#CLASS_NAME#/', $this->camelCase1($table->name), $template);
			$template = preg_replace('/#REQUEST_FIELDS#/', $this->generateRequestFields($fields), $template);

			if ($table->name == 'user'){
				$template_user= $this->getUserTable($database_name, $table);
				$template= (!$template_user)? $template : $template_user;
			}
			file_put_contents('app/Http/Requests/' . $this->camelCase1($table->name) . 'Request.php', $template);

		}

		$this->info("\n** The models have been created **\n");

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

	protected function getUserTable($database_name, $table)
	{
		if (!file_exists('app/User.php')) return false;

		$template = $this->template() ;

		$fields = $this->getTableFields($database_name, $table->name);

		$template = preg_replace('/#REQUEST_FIELDS#/', $this->generateRequestFields($fields), $template);

		return $template;
	}


	private function getMatchingTables($database, $table)
	{
		$string = preg_replace('/\*/', '%', $table);

		return DB::select("SELECT TABLE_NAME AS name FROM information_schema.tables
							WHERE TABLE_SCHEMA='{$database}' AND TABLE_NAME LIKE '{$string}'");

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

	private function stripFkId($string)
	{
		$string = preg_replace('/^fk_/', '', $string) ;
		$string = preg_replace('/_id$/', '', $string) ;

		return $string ;
	}

	private function getTableFields($database, $table)
	{
		return DB::select("SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH,
							COLUMN_TYPE, COLUMN_KEY, EXTRA, COLUMN_COMMENT FROM information_schema.columns
							WHERE TABLE_SCHEMA='{$database}' AND TABLE_NAME='{$table}'");
	}


	private function rules()
	{
		$rules = "\n==== Please ensure you follow the rules to avoid any problems ====\n";
		$rules .= "1) Table names should be singular.  If you think differently, you are wrong.\n";
		$rules .= "2) Tables have an auto-increment field named 'id'.\n";
		$rules .= "3) The models in RED will be overwritten! (as app/<model>.php)\n";

		return $rules;
	}

	private function generateRequestFields($table_fields)
	{
		$fields = '' ;

		//Field comments, if available
		foreach ($table_fields AS $field)
		{//			'name' => 'required|max:128',
			if($field->COLUMN_NAME == 'id')
				continue ;

			$fields .= "'{$field->COLUMN_NAME}' => '" ;
			if($field->IS_NULLABLE != 'YES')
				$fields .= "required|" ;
			if(strtolower($field->COLUMN_NAME) == 'email')
				$fields .= 'email|' ;
			if($field->CHARACTER_MAXIMUM_LENGTH != null)
				$fields .= "max:{$field->CHARACTER_MAXIMUM_LENGTH}|" ;
			if(preg_match('/^fk_.{1,}_id$/', $field->COLUMN_NAME))
			{
				$fields .= 'exists:TABLE_NAME|' ;
			}

			$fields = preg_replace("/=> ''/", "=> '',\n", $fields);
			$fields = preg_replace('/\|$/', "',\n", $fields) ;	//Replace final pipe
			$fields = preg_replace('/^[ ]{1,}/', "\t\t\t\t\t", $fields) ;	//Add tabs to the start of each line

		}

		return $fields;
	}

	private function str_replace_first($from, $to, $subject)
	{
		$from = '/'.preg_quote($from, '/').'/';

		return preg_replace($from, $to, $subject, 1);
	}

	private function template()
	{
		return '<?php

	namespace App\Http\Requests;
	
	use Illuminate\Foundation\Http\FormRequest;
	
	class #CLASS_NAME#Request extends FormRequest
	{
		/**
		 * Determine if the user is authorized to make this request.
		 *
		 * @return bool
		 */
		public function authorize()
		{
			return true;
		}
	
		/**
		 * Get the validation rules that apply to the request.
		 *
		 * @return array
		 */
		public function rules()
		{
			return [
				#REQUEST_FIELDS#
			];
		}
	}' ;

	}

}
