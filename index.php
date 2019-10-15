<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>

<title>xray</title>

<meta content="text/html; charset=utf-8" name="Content">
<meta http-equiv="Content-Type"
	content="text/html; charset=utf-8">
<meta name="keywords"
	content="web, database, gui">
<meta name="description"
	content="web database gui">

</head>
<body>

<?php

$debug=0;
/* display ALL errors */
error_reporting(E_ALL);

/* Include configuration */
include("config.php");

if (isset($_REQUEST['phpinfo']))
{
	phpinfo();
	die( "exit!" );
}
if (isset($_REQUEST['debug']))
{
	$debug=1;
}

if($database_type=="sqlsrv")
	$dsn = "$database_type:server=$database_hostname;database=$database_default";
else 	
	$dsn = "$database_type:host=$database_hostname;dbname=$database_default;charset=$database_charset";


$opt = array(
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
);

try {
	$conn = new PDO($dsn, $database_username, $database_password, $opt);
}
catch(PDOException $e) {
	die($e->getMessage());

}

//-------------------
if(isset($debug))
	if($debug==1) display_search_form();
//-------------------

$fields_array=array(
		'SNIMOK'=>array(
				"name"=>''
				,"visible"=>0),
		'PATIENT'=>array(
				"name"=>''
				,"visible"=>0),
		'NAME1'=>array(
				"name"=>'Фамилия'
				,"type"=>"text"
				,"visible"=>1),
		'NAME2'=>array(
				"name"=>'Имя'
				,"type"=>"text"
				,"visible"=>1),
		'NAME3'=>array(
				"name"=>'Отчество'
				,"type"=>"text"
				,"visible"=>1),
		'BIRTH'=>array(
				"name"=>'Дата рождения'
				,"type"=>"datetime"
				,"visible"=>1),
		'POL'=>array(
				"name"=>'Пол'
				,"visible"=>1),
		'IMAGEDATE'=>array(
				"name"=>'Дата Исследования'
				,"type"=>"datetime"
				,"visible"=>1),
		'APICRIZ'=>array(
				"name"=>'Результат'
				,"type"=>"text"				
				,"visible"=>1)				
);

$fields_search_array=array(
array("sql"=>"Name1","post"=>"fam","name"=>"Фамилия","value"=>"","html"=>"<br>","type"=>"text"),
array("sql"=>"Name2","post"=>"name","name"=>"Имя","value"=>"","type"=>"text"),
array("sql"=>"Name3","post"=>"ot","name"=>"Отчество","value"=>"","type"=>"text"),
array("sql"=>"birthday","post"=>"birthday","name"=>"Дата или год рождения","value"=>"","type"=>"datetime"),
array("sql"=>"[Date file]","post"=>"imagedate","name"=>"Дата Исследования","value"=>"","html"=>"<br>","type"=>"datetime")
);

echo '<form method=post>';

//вывод полей для поиска с заполнением значений
for($i=0;$i<count($fields_search_array);$i++)
{
	$field=$fields_search_array[$i];
	$field['value']=get_request_var_post($field['post']);
	$field['value']=sanitize_search_string($field['value']);
	$field['value']=trim($field['value']);
	if($field['type']=="datetime")
		$field['value']=str_replace(array('\'', '-', '.', ',', ' ', '/'), '-', $field['value']);
	if(isset($field['html']))
		echo $field['html'];		
	echo $field['name'].': <input type=text name="'.$field['post'].'" value="'.$field['value'].'">';
	$fields_search_array[$i]=$field;
}

echo '		<input type=submit name=send value=search>
	</form>';


$sql_where="";

foreach ($fields_search_array as $field)
{
	if(strlen($field['value'])>0)
	{			
		if($field['type']=="datetime")
		{
			//datetime query
			$day = "";
			$month = "";
			$year = "";
			$sqldate="";
			$sqldate_query="";
			
			$a_date = explode('-', $field['value']); 
			if(count($a_date)==1)
			{
				//only year
				$year=$a_date[0];
				$month = 1;
				$day = 1;
				if(checkdate($month, $day, $year))
				{
					$sqldate=date("Ymd",mktime(0,0,0,$month,$day,$year));
					$sqldate_query=	"(".$field['sql'].">='".$sqldate." 00:00:00.000'";
					
					$sqldate=date("Ymd",mktime(0,0,0,$month,$day,$year+1));
					$sqldate_query.=" and ".$field['sql']."<'".$sqldate." 00:00:00.000')";
				}					
			}
			else if(count($a_date)==2)
			{
				//year and month
				$year = $a_date[0];
				$month = $a_date[1];
				$day = 1;
				if(checkdate($month, $day, $year))
				{
					$sqldate=date("Ymd",mktime(0,0,0,$month,$day,$year));
					$sqldate_query=	"(".$field['sql'].">='".$sqldate." 00:00:00.000'";
					
					$sqldate=date("Ymd",mktime(0,0,0,$month+1,$day,$year));
					$sqldate_query.=" and ".$field['sql']."<'".$sqldate." 00:00:00.000')";
				}
			}				
			else if(count($a_date)==3)
			{
				//full date with year, month, day
				$year = $a_date[0];
				$month = $a_date[1];
				$day = $a_date[2];
				if(checkdate($month, $day, $year))
				{
					$sqldate=date("Ymd",mktime(0,0,0,$month,$day,$year));
					$sqldate_query=$field['sql']."='".$sqldate." 00:00:00.000'";
				}	
			}

			if(strlen($sqldate_query)>0)
			{
				if(strlen($sql_where)>0)
					$sql_where.=" and ";
				$sql_where.=$sqldate_query;
			}
		}
		else 
		{
			if(strlen($sql_where)>0)
				$sql_where.=" and ";
			$sql_where.="[".$field['sql']."] like '".$field['value']."%'";
		}	
	}
}

if(isset($debug))
	if($debug==1) echo $sql_where;

$top_count=60;


/* Set up and execute the query. */
$tsql = "SELECT TOP 50
	[Snimok]
      ,p.[Patient]
	  ,p.Name1
	  ,p.Name2
	  ,p.Name3
	  ,cast(p.Birthday as date) as birth
	  ,(case p.pol 
			when 0 then 'Ж'
			when 1 then 'М'
			else '-' end) as pol
      ,[Date file] as imagedate
      ,[Apicriz]
  FROM [xray2007].[dbo].[Snimok] as s
  join Patient as p on p.Patient=S.Patient
";

if(strlen($sql_where)>0)
	$tsql.="\n where ".$sql_where;

$tsql.="\n order by name1";


$stmt = $conn->query($tsql);
$rows = $stmt->fetchAll();

$numRows = count($rows);
//echo "<p>$numRows Row" . ($numRows == 1 ? "" : "s") . " Returned </p>";

if($numRows>0)
{	
	print '<table cellspacing="0" cellpadding="1" border="1" align="center"
	width="100%" >
	<tbody>';
		
	$metadata=array();
	$i=0;
	// add the table headers
	foreach ($rows[0] as $key => $useless){
		//print "<th>$key</th>";
		$metadata[$i]['Name']=$key;
		$i++;
	}
	
	//print_r($metadata);
	$column_name="";
/*
	//internal column names
	echo '<tr>';
	for ($i=0;$i < count($metadata);$i++)
	{
		$meta = $metadata[$i];
		//print_r($meta);
		$column_name=strtoupper($meta['Name']);
		
		if(get_column_visibility($column_name)==1)
			echo '<td>' . $meta['Name'] . '</td>';
	}
	echo '</tr>';
*/

	//human readable column names
	echo '<tr>';
	for ($i=0;$i < count($metadata);$i++)
	{
		$meta = $metadata[$i];
		$column_name=strtoupper($meta['Name']);
		//print_r($meta);
		$header=get_column_username($column_name,"&nbsp");
		
		if(get_column_visibility($column_name)==1)
			echo '<td'.get_column_style($column_name).'><h1>' . $header . '</h1></td>';
	}
	echo '</tr>';


	/* Retrieve each row as an associative array and display the results.*/
	foreach ($rows as $row)
	{
		$rowColor='White';
		echo '<tr>';
		//echo '<tr>';
		
		//print_r($row);
		
		for ($i=0;$i < count($row);$i++)
		{
			$column_name=$metadata[$i]['Name'];
					
			if(get_column_visibility($column_name)==1)
			{					
				$field=$row[$column_name];
				$text='';
					
				if (gettype($field)=="object" && (get_class($field)=="DateTime"))
				{
					$text = $field->format('Y-m-d');
					if($text=='1899-12-30')
						$text="&nbsp";
				}
				else
					$text = trim($field);

				if($text=='')
					$text ='&nbsp';
				
				echo '<td'.get_column_style($column_name).'>' . $text . '</td>';
			}
		}
		print "</a></tr> \n";
	}
	print '	</tbody>
	</table>';
}
else 
{
	echo "No rows returned.";
}


function get_column_visibility($name, $default = 1)
{
	global $fields_array;
	
	$name=strtoupper($name);

	if (isset($fields_array[$name]['visible']))
		return $visible_flag=$fields_array[$name]['visible'];

	else
		return $default;
}
function get_column_username($name, $default = '')
{
	global $fields_array;

	if (isset($fields_array[$name]['name']))
		return $visible_flag=$fields_array[$name]['name'];

	else
		return $default;
}

function get_column_style($name, $default = '')
{
	global $fields_array;
	
	$name=strtoupper($name);

	if (isset($fields_array[$name]['html']))
		return $visible_flag=$fields_array[$name]['html'];

	else
		return $default;
}

/* sanitize_search_string - cleans up a search string submitted by the user to be passed
     to the database. NOTE: some of the code for this function came from the phpBB project.
   @arg $string - the original raw search string
   @returns - the sanitized search string */
function sanitize_search_string($string) {
	static $drop_char_match =   array('^', '$', '<', '>', '`', '\'', '"', '|', ',', '?', '~', '+', '[', ']', '{', '}', '#', ';', '!', '=');
	static $drop_char_replace = array(' ', ' ', ' ', ' ',  '',   '', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ');

	/* Replace line endings by a space */
	$string = preg_replace('/[\n\r]/is', ' ', $string);
	/* HTML entities like &nbsp; */
	$string = preg_replace('/\b&[a-z]+;\b/', ' ', $string);
	/* Remove URL's */
	$string = preg_replace('/\b[a-z0-9]+:\/\/[a-z0-9\.\-]+(\/[a-z0-9\?\.%_\-\+=&\/]+)?/', ' ', $string);

	/* Filter out strange characters like ^, $, &, change "it's" to "its" */
	for($i = 0; $i < count($drop_char_match); $i++) {
		$string =  str_replace($drop_char_match[$i], $drop_char_replace[$i], $string);
	}

	$string = str_replace('*', ' ', $string);

	return $string;
}

/* get_request_var_post - returns the current value of a PHP $_POST variable, optionally
     returning a default value if the request variable does not exist
   @arg $name - the name of the request variable. this should be a valid key in the
     $_POST array
   @arg $default - the value to return if the specified name does not exist in the
     $_POST array
   @returns - the value of the request variable */
function get_request_var_post($name, $default = "") {
	if (isset($_POST[$name])) {
		if (isset($_GET[$name])) {
			unset($_GET[$name]);
			$_REQUEST[$name] = $_POST[$name];
		}

		return $_POST[$name];
	}else{
		return $default;
	}
}

function display_search_form()
{
	print '<p>';
	print 'POST:';
	print_r($_POST);
	print '<br>GET:';
	print_r($_GET);
	print '<br>REQUEST:';
	print_r($_REQUEST);
	print '</p>';
	
}

?>
</body>
</html>
