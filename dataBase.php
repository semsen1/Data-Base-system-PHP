<?php
//db class
class db{
		private $db;

	    public function __construct($name,$host,$login,$password){
			if(!empty($name) && !empty($host) && !empty($login)){
				//if exists
				try{$this->db = new PDO("mysql:host={$host};dbname={$name}",$login,$password);}
				catch(PDOexception $e){
					//if not, create
					$db = new PDO("mysql:host={$host}",$login,$password);
					$db->exec("CREATE DATABASE {$name}");
					$this->db = new PDO("mysql:host={$host};dbname={$name}",$login,$password);
				}
			}else{$error[] = "ошибка подключения к Базе";}
			$this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		}
		// get db from outside;
		public function getDb(){
			return $this->db;
		}

	}
	//table class
	class table{
		private $db,$name,$columns,$key;
		//here you need an instance of the db class and the name of the table 
		public function __construct($db,$name){
			$this->db = $db;
			$this->name = $name;
		}
		//save the new column of the table
		public function columns($column){
			$this->columns .= $column.",";
		}
		//new index for column
		public function keys($key){
			$this->key .= "KEY({$key})".",";
		}
		//create new table 
		public function create(){
			$query = $this->columns.$this->key;
			$query = preg_replace("/,$/",'',$query);
			$this->db->exec("CREATE TABLE IF NOT EXISTS {$this->name}({$query})");
		}
		//select from table
		public function select($columns,$fetch = 0,$query= false,$having=false){
			//if isset query
			if(!empty($query) && $query != false){
				$terms = "WHERE $query";
				if(!empty($having) && $having != false){
					$terms .= "HAVING $having";
				}
			}
			//if fetch == 1 fetch assoc
			if($fetch != 0){
				$data = $this->db->query("SELECT {$columns} FROM {$this->name} {$terms}",PDO::FETCH_ASSOC);
				$data = $data->fetchall();
			//if fetch == 0 fetch all
			}elseif($fetch == 0){
				$data = $this->db->query("SELECT {$columns} FROM {$this->name} {$terms}");
				$data = $data->fetchall();
			}
			//if fetch is double-digit fetch row = the second digit of the number
			if($fetch > 1){
				$pos = substr($fetch,1);
				$data = $data[$pos];
			}
			return $data;
		}
		//delete from table
		public function delete($where){
			if(isset($where) && !empty($where)){
				$this->db->exec("DELETE FROM {$this->name} WHERE {$where}");	
			}
			
		}
		//delete from table columns as string values as array
		public function insert(string $columns,...$values){
			$error = 0;
			if(!empty($columns)){
				$columns = "($columns)";
				//ifcolumns length != values length error
				if(count(explode(',',$columns)) != count($values)){
					$error = $error +1;
				}
			}
			//for prepare bind
			for($i = 0;$i < count($values);$i++){
				$val .= ":v".$i.","; 
				
			}

 			if($error == 0){
 				//drop last comma
 				$val = preg_replace("/,$/","",$val);

 				$userAdd = $this->db->prepare("INSERT INTO {$this->name}$columns VALUES({$val})");
 				//bind vN with valueN
				for($i2 = 0;$i2 < count($values);$i2++){
					$userAdd->bindValue(":v".$i2,$values[$i2]);
				}	
				$userAdd->execute();
 			}

		}
	}
	//special class for the user table
	class user extends table{
		public function __construct($db,$name){
			parent::__construct($db,$name);
		}
		//add user,need array POST and FILES
		public function addUser($data){
			if(isset($data['login']) && isset($data['bdate']) && isset($data['email']) && isset($data['password'])
			   && isset($data['ConfPassword']) && isset($data['subReg'])){
				$pass = password_hash($data['password'],PASSWORD_DEFAULT);
				if(isset($data['userA']['tmp_name']) && ($data['userA']['type'] == "image/jpeg"
				    || $data['userA']['type'] == "image/gif" 
					|| $data['userA']['type'] == "image/png"))
				{
					$timeInd =time().rand(0,10).".webp";
					$name = $_SERVER['DOCUMENT_ROOT']."/view/userFiles/".$timeInd;
				}else{
					$timeInd = "user.webp";
				}
				if($this->select("COUNT(id) as count",10,"login = '{$data['login']}'")['count'] == 0 && $data['password'] == $data['ConfPassword']){

					move_uploaded_file($data['userA']['tmp_name'],$name);
					$this->insert("login,password,email,birth_date,image",$data['login'],$pass,$data['email'],$data['bdate'],$timeInd);
					$userid=$this->select("id",10,"login = '{$data['login']}'")['id'];
					setcookie("user",$userid,time()+60*60*24);
					$_COOKIE['user'] = $userid;
					header("Location: " . $_SERVER['REQUEST_URI']);
				}
			}
			
	   }
	   //login user 
	   public function login($data){
	   
	   		if($this->select("COUNT(id) as count",10,"login = '{$data['login']}'")['count'] > 0){
	   			if(password_verify($data['userPassword'],$this->select("password",10,"login = '{$data['login']}'")['password']) === true){
					$userid=$this->select("id",10,"login = '{$data['login']}'")['id'];
					setcookie("user",$userid,time()+60*60*24);
					$_COOKIE['user'] = $userid;
					header("Location: " . $_SERVER['REQUEST_URI']);
	   			}
	   		}
	   }
	}
	//get userInf
	class userInf{
		private $user,$id,$login,$email,$bdate,$image;

		public function __construct($id,$table){
			$this->user = $table->select("id,login,email,birth_date,image",10,"id={$id}");
			
		}
		public function getId(){
			return $this->user['id'];
		}

		public function getLogin(){
			return $this->user['login'];
		}

		public function getEmail(){
			return $this->user['email'];
		}

		public function getBdate(){
			return $this->user['birth_date'];
		}

		public function getImage(){
			return $this->user['image'];
		}
	}
//just example
 $db = new db("newsPlat","127.0.0.1","root","");

 $users = new user($db->getDb(),"users");
 $categories = new user($db->getDb(),"categories");
 $articles = new user($db->getDb(),"articles");
 $Text = new user($db->getDb(),"Text");

 	$users->columns("id int(10) auto_increment PRIMARY KEY");
 	$users->columns("login varchar(255) not null");
 	$users->columns("password varchar(70) not null");
 	$users->columns("email varchar(255) not null");
 	$users->columns("birth_date date not null");
 	$users->columns("image text");
 	$users->keys("id");
 	$users->keys("login");
 	$users->create();

 	$categories->columns("id_c int(10) auto_increment PRIMARY KEY");
 	$categories->columns("name varchar(255) not null");
 	$categories->columns("author int(10)");
 	$categories->keys("id_c");
 	$categories->keys("name");
 	$categories->create();

 	$articles->columns("id_a int(10) auto_increment PRIMARY KEY");
 	$articles->columns("title varchar(255) not null");
 	$articles->columns("cover varchar(255) not null");
 	$articles->columns("alias varchar(255) not null");
 	$articles->columns("author int(1) not null");
 	$articles->columns("add_date date not null DEFAULT NOW()");
 	$articles->columns("categories varchar(255) not null");
 	$articles->columns("views varchar(255) not null DEFAULT 0");
 	$articles->columns("valid int(1) DEFAULT 0 ");
 	$articles->keys("id_a");
 	$articles->keys("title");
 	$articles->keys("alias");
 	$articles->keys("add_date");
 	$articles->create();

 	$Text->columns("alias varchar(255) not null");
 	$Text->columns("text text not null");
 	$Text->keys("alias");
 	$Text->create();
?>