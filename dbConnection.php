<?php
/*	
	PHP 4.4.2  sürümünde yazýldý ve denendi. Bu sýnýf mysql veri tabaný sunucusu üzerinde iþlem yapmayý kolaþtýrmak amacýyla yazýldý.
	Gerekli veri tabaný hata ve kontrol kodlarýný her uygulamada tekrardan yazmaktansa bu sýnýfý kullanarak zaman kazanýlabilir.
	Yazdýn diðer sýnýflara, bu sýnýfý kalýtým yoluyla aktarabilirsiniz.
	
	NOT:PHP5 ve daha üst bir sürüm kullanýyorsanýz bu sýnýf çalýþmayabilir.  Çünki php5 sürümünde sýnýflar konusunda köklü deðiþiklikler yapýldý.
	YAZAN: Mustafa ATÝX
	E-Posta: mr.mustafaatik@gmail.com
*/

class dbConnection{
	
	var $host='localhost';
	var $username='root';
	var $password='root';
	var $database='lcdocSearch2';
	
	var $connection;
	var $reader;
	var $affectedRows;
	var $numRows;
	var $error;
	var $charSet='utf8';
	var $collate='utf8_turkish_ci';
	
	var $configPath;
	
	/**
	 * konfigürasyon dosyasından okuma yapılıp yapılmayacağını belirtir.
	 * */
	var $readConfig=true;
	
	function readConfigs(){
		if(!isset($_dbHost)){
			if($this->configPath==null){
				$this->configPath=$_SERVER['DOCUMENT_ROOT'].'/';
			}
			
			$cFile=$this->configPath.'_config.php';
			if(file_exists($cFile))
				require($cFile);
		}
		
		if(isset($_dbHost)){
			$this->host=$_dbHost;
			$this->username=$_dbUser;
			$this->password=$_dbPassword;
			$this->database=$_dbName;
		}
	}
	
	function connect(){
		if($this->readConfig)
			$this->readConfigs();
		
		if($this->connection=new mysqli($this->host,$this->username,$this->password)){
			$this->query('set names "'.$this->charSet.'" collate "'.$this->collate.'"');
			if(@$this->connection->select_db($this->database)){
				$this->query('set names "'.$this->charSet.'" collate "'.$this->collate.'"');
				return true;
			}
			$this->error='Veri tabaný seçilemedi.';
			return false;
		}
		$this->error='Veri tabaný sunucusuna baðlanýlamadý.';return false;
	}
	
	function query($sql,$buffered=true){
		$this->affectedRows=0;
		$this->numRows=0;
		if(!$this->connection && !$this->connect())	return false;
		if(($buffered && $this->reader=$this->connection->query($sql)) ||
			(!$buffered && $this->reader=$this->connection->query($sql))){
				
				if(gettype($this->reader)=='object') 
					$this->numRows=$this->reader->num_rows; 
				else
					$this->affectedRows=$this->connection->affected_rows;
				
			return true;
		}
		$this->error='Sorgu çalýþtýrýlamadý.';return false;
	}
	
	function unbufferedQuery($sql){
		return $this->query($sql,false);
	}
	
	function fetchObject(){
		return $this->reader->fetch_object();
	}
	function fetchArray(){
		return $this->reader->fetch_array();
	}
	function fetchRow(){
		return $this->reader->fetch_row();
	}
	function nextIncrement($t){
		$this->query('show table status like \''.$t.'\'');
		$nau=$this->fetchObject(); // next auto_increment
		return $nau->Auto_increment;
	}
	function lastIncrement($t){
		return $this->nextIncrement($t)-1;
	}
	function getInsertId(){
		return $this->connection->insert_id;
	}
	function getError(){
		return $this->connection->error;
	}
	
	function fetchListByQuery($sql,$style='object'){
		return $this->fetch($sql,$style);
	}
	function fetch($sql,$style='object'){
		if($this->query($sql) ){
			$arr=array();
			if($style=='object')
				while($r=$this->fetchObject()) $arr[]=$r;
			elseif($style=='array')
				while($r=$this->fetchArray()) $arr[]=$r;
			elseif($style=='row')
				while($r=$this->fetchRow()) $arr[]=$r;
			return $arr;
		}
		return false;
	}
	
	function fetchFirstRecord($q){
		return $this->fetchFirst($q);
	}
	function fetchFirst($q){
		if($this->query($q) )
		{
			if($this->numRows>0)
			return $this->fetchObject();
		}
		return false;
	} 
	
	public function escape($s,$strip=true){
		if(!$this->connection && !$this->connect())	return false;
		if($strip){
			if(strpos($s,'\\\'')!==false || strpos($s,'\\"')!==false)
				$s=stripslashes($s);
		}
		return $this->connection->real_escape_string($s);
	}
}
?>
