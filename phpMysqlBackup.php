<?php
/*
PHP MYSQL BACKUP 0.5
---------------------------
Tarih: 13 Kasım(11) 2008
Yazan: Mustafa Atik
Detaylı Bilgi: http://cookingthecode.com

Bu uygulama, mysql veritabanı sunucularıyla çalışabilecek bir yedekleme uygulamasıdır.
İstenildiği veya önceden belirlenen zamanlarda; istenilen veritabanlarını, tabloları ve tablolardaki kayıtları tekrar 
oluşturabilecek sql kodu; bir ftp adresine, bir e-posta kutusuna gönderilebilir veya yerel dizinlere kaydedilebilir.

Bu uygulamayı istediğiniz şekilde kullanabilirsiniz. İyi kullanın.
*/
ini_set('mbstring.internal_encoding','UTF-8');
error_reporting(E_ALL);
require_once('dbConnection.php');	// veritabanı nesnesinin yaratıldığı sınıf
require_once('mailman.php');		// dosya iliştirilmiş e-posta mesajlarının gönderilmesine imkan sunan sınıf. Biraz eski sayılabilir.
class phpMysqlBackup
{
	public $db;								// veritabanı işlemlerinin yapılacağı nesne (db_connection.php)
	public $schedules=array();				// zamanlanmış iş listesi
	public $logFile='tmp/phpMysqlBackup.txt';	// zamanlanmış işlerin en son ne zaman çalıştırıldığı bu dosyada okunacak. eğer boş ise, görevler her tetiklemede çalıştırılır.
	private $oldLogs=array();					// kod tarafından kullanılan yerel değişkendir. hata durumunda log dosyasından okunan değerleri değişlik olamdan geri yazılmasını sağlar.  
	public $logs=array();						// $logFile dosyasından okunana çalışma zamanları, bu dizi değişkende tutulur. 
	public $ddlDB='information_schema';		// veritabanını sunucusunda bulunan neslelerle(table, column, index) ilgili bilgilerin saklandığı veritabanıdır. Mysql'de bu bilgileri information_schema veritabanı tutar.
	public $tmpDir='tmp/';					// oluşturulan sql ifadeleri, ftp veya eposta ile gönderilmeden önce geçici olarak bir dosyaya yazılmalıdır. bu değişlen, dosyanın dizinini belirtir.
	
	public function __construct()
	{
		// örnek görev tanımlamaları için: http://cookingthecode.com/
		$this->schedules=array(
			'2880'=>array
			(
			'db'=>array('host'=>'localhost','user'=>'mysql_kullanici','password'=>'mysql_parola'),
			'dbObjects'=>array('siteDB'=>array('*')),
			'email'=>array('to'=>'mr.atik@gmail.com','title'=>'CookinTC DB Yedeği - ','from'=>'bilgi@cookingthecode.com','fileName'=>'cookingVtYedek','timeSuffix'=>true,'titleTimeSuffix'=>true,'message'=>'Cookingthecode.com Veritabanı Yedeğidir. Lak lak lak...'),
			'local'=>array('fileName'=>'ctcDB','timeSuffix'=>true),
			'ftp'=>array('address'=>'site.com','username'=>'ftp_user','password'=>'ftp_password','remoteDir'=>'/public_html/','fileName'=>'aaYedek','timeSuffix'=>true)
			)
		);
		
		// örnek siliniyor.
		$this->schedules=array();

		/**
		 * schedules dosyası varsa oku
		 * */
		if(isset($_SERVER["argv"][1])){
			echo 'schedules dosyası okunuyor: '.$_SERVER['argv'][1];
			include($_SERVER["argv"][1]);
		}

	}
	function readLogs()	// önceden kaydedilmiþ iþlerin, yapýlýþ zamanlarý dosyadan okunacak
	{
		$this->logs=array();
		if($this->logFile==null) {$this->logs=array(); return true;}
		if(file_exists($this->logFile)) $f=file_get_contents($this->logFile); else $f='';
		if($f=='lock') {$this->error='Şu an başka bir yedekleme işlemi yapılmaka olduğu için, bu yedekleme işlemi iptal edildi.' ;return false;}
		file_put_contents($this->logFile,'lock');	// diğer işlemlerin, şu an yapılan yedeklemenin farkında olmalarını sağlayacak.
		
		if($f=='') return true;
		$f=explode("\n",$f);
		foreach($f as $i)
		{
			if($i=='') continue;
			$i=explode('=',$i);
			$this->logs[$i[0]]=$i[1];
		}
		$this->oldLogs=$this->logs;
		return true;
	}
	function writeLogs()	// $logs dizisindeki veriler dosyaya kaydedilecek
	{
		if($this->logFile==null) $this->logs=array();
		$fContent='';
		foreach($this->logs as $k=>$i)
		{
			$fContent.=$k.'='.$i."\n";
		}
		return file_put_contents($this->logFile,$fContent,LOCK_EX);
	}
	function makeSchedules()	// zamanlanmýþ görev listesindeki her bir iþ parçacýðý iþlenecek
	{
		if(!$this->readLogs()) return false;
		
		foreach($this->schedules as $t=>$s)
		{
			//iş parçacığı daha önceden çalıştırılmış ve belirtilen süre kadar vakit henüz geçmemişse işi yapma
			if(isset($this->logs[$t]) && $t>((time()-$this->logs[$t])/60)) continue;
			if($this->db==null) $this->db=new dbConnection();
			
			// veritabanı sunucususuna bağlanırken kullanılacak bilgileri atanıyor
			$this->db->host=$s['db']['host'];
			$this->db->password=$s['db']['password'];
			$this->db->username=$s['db']['user'];
			
			$sql="/*PhpMysqlBackup\n".date('Y-m-d h:i:s')."*/\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n";
			
			 foreach($s['dbObjects'] as $dbName=>$tbls)
			 {
			 	// belirtilen veritabanı için bağlantı açılıyor.
			 	$this->db->database=$dbName;
			 	if($this->db->connect()===false) {$this->writeLogs();return false;}
			 	$sql.="use ".$dbName.";\n\n";
			 	
			 	// veritabanındaki tüm tablolar belirtilmişse, tablo listesi alınıyor.
			 	$tList=array();
			 	if((is_array($tbls) && $tbls[0]=='*') || $tbls=='*') $tList=$this->fetchTables($dbName); 
			 	
			 	// belirtilen göz ardı tablolar, tablo listesinden çıkartılıyor ve işlem yapılacak tablolar, tablo listesine aktarılıyor.
			 	if(is_array($tbls))
			 	foreach($tbls as $i)
			 	{
				 	if($i=='*') continue; 
				 	elseif(mb_substr($i,0,2)=='--')
				 	{
				 		foreach($tList as $j=>$k) if($k->table_name==mb_substr($i,2)) unset($tList[$j]);
				 	}
				 	else
				 	{
				 		$tObj->table_name=$i;
				 		$tList[]=$tObj;
				 	} 
			 	}
			 	
			 	// yedeklenecek tablolar birer birer işleniyor
			 	foreach($tList as $i)
			 	{
			 		// tabloyu oluşturacak kod çekiliyor.
			 		$crtT=$this->db->fetchListByQuery('show create table '.$i->table_name,'array');
			 		$sql.=$crtT[0]['Create Table'].";\n\n";
			 		
			 		// tablodaki veriler çekiliyor
			 		$records=$this->db->fetchListByQuery('select * from '.$i->table_name,'row');
			 		if(count($records)>0)
			 		{
			 			$sql.='insert into '.$i->table_name." values ";
			 			foreach($records as $r)
			 			{
			 				foreach($r as $rCK=>$rC) $r[$rCK]=addslashes($rC);
			 				$sql.="\n('".implode('\',\'',$r)."'),";
			 			}
			 			$sql=mb_substr($sql,0,-1).";\n";
			 		}
			 		
			 		$sql.="\n";
			 	}
			 }
			 
			 $sql.="\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=1;";
			 $this->logs[$t]=time();
			 
			 if(isset($s['local'])) $this->sendToFile($s,$sql);
			 if(isset($s['ftp'])) $this->sendToFtp($s,$sql);
			 if(isset($s['email'])) $this->sendToEmail($s,$sql);
		}
		
		$this->oldLogs=$this->logs;
		$this->writeLogs();
	}
	private function fetchTables()
	{
		$tList=$this->db->fetchListByQuery('select table_name,table_type,engine,auto_increment,update_time,table_collation,collations.character_set_name,table_comment from '.$this->ddlDB.'.tables ,'.$this->ddlDB.'.collations where tables.table_collation=collations.collation_name and table_schema=\''.$this->db->database.'\'');
		if($tList==false) return false;
		return $tList;
	}
	private function sendToFile($s,$sql)
	{
		$fname=$s['local']['fileName'];
		if(isset($s['local']['timeSuffix']) && $s['local']['timeSuffix']==true) $fname.=date('Y_m_d_H_i');
		$fname.='.sql';
		return file_put_contents($fname,$sql);
	}
	private function sendToEmail($s,$sql)
	{
		$eParams=$s['email'];
		$fname=$eParams['fileName'];
		if(isset($fParams['timeSuffix']) && $fParams['timeSuffix']==true) $fname.=date('Y_m_d_H_i');
		$fname.='.sql';
		file_put_contents($this->tmpDir.$fname,$sql);
		
		$mm=new mailman();
		$mm->from=$eParams['from'];
		$mm->subject=$eParams['title'].(isset($eParams['titleTimeSuffix']) && $eParams['titleTimeSuffix']?date('Y-m-d H:i'):'');
		if(isset($eParams['message'])) $mm->content=$eParams['message'];
		$mm->files=array($this->tmpDir.$fname);
		$mm->to=$eParams['to'];
		$r=$mm->send();
		unlink($this->tmpDir.$fname);
		return $r;
	}
	private function sendToFtp($s,$sql)
	{
		$fParams=$s['ftp'];
		
		if(!($conn=ftp_connect($fParams['address']))) return false;
		if(!ftp_login($conn,$fParams['username'],$fParams['password'])) return false;
		
		$tmpFile=$this->tmpDir.'phpMysqlBackup'.time().'.sql';
		file_put_contents($tmpFile,$sql);
		
		$fname=$fParams['fileName'];
		if(isset($fParams['timeSuffix']) && $fParams['timeSuffix']==true) $fname.=date('Y_m_d_H_i');
		$fname.='.sql';
		
		$u=ftp_put($conn,$fParams['remoteDir'].$fname,$tmpFile,FTP_BINARY);
		unlink($tmpFile);
		return $u;
	}
}



/*  bu belirtilen işleri çalıştırır. istediğin yerden bu iki satırı çağırabilirsin. fakat bu dosyanın içinden çağırmak yerine başka yerden çağır.
örneğin bir kullanıcı giriş sayfan varsa, kullanıcı giriş yaptığı zaman çalıştırabilirsin. mantıklı olur.
*/
$x=new phpMysqlBackup();
$x->makeSchedules();

?>
