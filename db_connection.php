<?
/*	
	Tarih: 2006
	PHP 4.4.2  sürümünde yazýldý ve denendi. Bu sýnýf mysql veri tabaný sunucusu üzerinde iþlem yapmayý kolaþtýrmak amacýyla yazýldý.
	Gerekli veri tabaný hata ve kontrol kodlarýný her uygulamada tekrardan yazmaktansa bu sýnýfý kullanarak zaman kazanýlabilir.
	Yazdýn diðer sýnýflara, bu sýnýfý kalýtým yoluyla aktarabilirsiniz.
	
	NOT:PHP5 ve daha üst bir sürüm kullanýyorsanýz bu sýnýf çalýþmayabilir.  Çünki php5 sürümünde sýnýflar konusunda köklü deðiþiklikler yapýldý.
	YAZAN: Mustafa ATÝX
	E-Posta: mr.mustafaatik@gmail.com
*/

class dbConnection
{
	
	var $host='localhost';
	var $username='root';
	var $password='root';
	var $database='DB';

	var $connection;
	var $reader;
	var $affectedRows;
	var $numRows;
	var $error;
	var $charSet='utf8';
	var $collate='utf8_turkish_ci';
	function connect()
	{
		if($this->connection=new mysqli($this->host,$this->username,$this->password))
		{
			$this->query('set names "'.$this->charSet.'" collate "'.$this->collate.'"');
			if(@$this->connection->select_db($this->database))
			{
				$this->query('set names "'.$this->charSet.'" collate "'.$this->collate.'"');
				return true;
			}
			$this->error='Veri tabaný seçilemedi.';
			return false;
		}
		$this->error='Veri tabaný sunucusuna baðlanýlamadý.';return false;
	}
	
	function query($sql,$buffered=true)
	{
		$this->affectedRows=0;
		$this->numRows=0;
		if(!$this->connection && !$this->connect())	return false;
		if(($buffered && $this->reader=$this->connection->query($sql)) || (!$buffered && $this->reader=$this->connection->query($sql)))
		{
			if(gettype($this->reader)=='object') $this->numRows=$this->reader->num_rows; 
			else $this->affectedRows=$this->connection->affected_rows;
			return true;
		}
		$this->error='Sorgu çalýþtýrýlamadý.';return false;
	}
	
	function unbufferedQuery($sql)
	{
		return $this->query($sql,false);
	}
	
	function fetchObject()
	{
		return $this->reader->fetch_object();
	}
	function fetchArray()
	{
		return $this->reader->fetch_array();
	}
	function fetchRow()
	{
		return $this->reader->fetch_row();
	}
	function nextIncrement($t) 
	{
		$this->query('show table status where name=\''.$t.'\'');
		$nau=$this->fetchObject(); // next auto_increment
		return $nau->Auto_increment;
	}
	function getError()
	{
		return $this->connection->error;
	}
	function fetchListByQuery($sql,$fetchType='object')
	{
		if($this->query($sql) )
		{
			$arr=array();
			if($fetchType=='object') while($r=$this->fetchObject()) $arr[]=$r;
			elseif($fetchType=='array') while($r=$this->fetchArray()) $arr[]=$r;
			elseif($fetchType=='row') while($r=$this->fetchRow()) $arr[]=$r;
			return $arr;
		}
		return false;
	}
}
?>