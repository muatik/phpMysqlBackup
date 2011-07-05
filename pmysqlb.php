<?php
/**
 * pmysqlb, php mysqld backup 
 * 
 * Bu kod, php ile üzerinden çalıştırılan linux komutlarıyla
 * mysql veritabanlarını yedekler. 
 * Tek seferde birden fazla veritabanı yedekleyebilir.
 * Gün bazında yedekleme yapabilir.
 * @author Mustafa Atik <muatik@gmail.com>
 * @version 1.0
*/

error_reporting(E_ALL);
ini_set('display_errors','on');
ini_set('mbstring.internal_encoding','UTF-8');
date_default_timezone_set('Europe/Istanbul');

/*
örnek set	

$backup1=array(
	'host'=>'localhost',
	'user'=>'root',
	'password'=>'root',
	'dbs'=>array('dbname1','dbname2'),
	'days'=>array(1,3,7,30), // t1, t3, t7, t30 
	'storePath'=>'/var/backups/dbs/',
	'email'=>'muatik@gmail.com'
);
 
*/

class pmysqlb
{
	/**
	 * linux komudunu çalıştırır
	 * @param string $c
	 * @return str komudun stdout çıktısı 
	 * */
	public function sexec($c){
		echo $c."\n";
		return shell_exec($c);
	}


	/**
	 * eski yedek dosyalarını siler
	 * @param array $s parametre seti
	 * */
	public function run($s){

		// kaydedilecek dosyanın isim bilgileri hazırlanıyor.
		$fileID=implode('_',$s['dbs']);

		// yedek alınacak günler tek tek dolaşışıyor ve eğer günle ilişkili
		// yedek eski ise, yedek silinip yenisi hazırlanıyor.
		foreach($s['days'] as $d){
			
			$fileName=implode('_',$s['dbs']).'t'.$d.date('Ymd');
			
			// $d günden eski $d günlük yedek dosyaları siliniyor.
			$rmCmd='find tmp/ -iregex .*'
				.$fileID.'t'.$d
				.'.*.bz2 -type f -mtime +'.($d-1).' -exec rm {} \;';
			$this->sexec($rmCmd);
			
			// yine de $d günlük yedek dosyası varsa yani dosya 
			// henüz eskimemişse, işlem yapılmayacak.	
			$fPattern=$fileID.'t'.$d.'.*.bz2';
			$files=$this->sexec('ls -l --time-style=\'+%s\' '.
				$s['storePath'].' |grep '.$fPattern);
			
			if(trim($files)==null){
				// mysqldump ifadesi
				$dumpc=sprintf(
					'mysqldump -h%s -u%s -p%s --databases %s',
					$s['host'],$s['user'],$s['password'],
					implode(' ',$s['dbs'])
				);
				
				// dosyanın kaydetme yöntemi
				$toFile='bzip2 > '.$s['storePath'].'/'.$fileName.'.bz2';
				
				$out=$this->sexec($dumpc.' | '.$toFile);
			}

		}

		// kaydedilecek dosyanın adı oluşturulur.
	}
	
	
	/**
	 * Bir hata olduğunda bunu eposta ile bildirir.
	 * @param array $s parametre seti
	 * @param int $d yedeğin kaç günlük olduğu
	 * @param string $out hata mesajı
	 * */
	public function reportError($s,$d,$out){
		mail(
			$s['email'],
			'Yedeklemede hata meydana geldi.',
			implode(', ',$s['dbs']).' veritabanları '.$d.' günlük
			yedek alınırken şu hata meydana geldi: '.$out,
			'content-type:text/plain;charset=utf-8;'
		);
	}
}


if(isset($_SERVER['argv'][1])){
	$arg=$_SERVER['argv'];

	if(count($arg)==2){
		require($arg[1]);
	}
	elseif(count($arg)==8){
		// $ pmysqlb h=localhost u=root p=root dbs=db1name,db2name 
		//	days=1,3,7,30 spath=/var/backups/ email=x@y.com
		for($i=1;$i<count($arg);$i++){
			$a=explode('=',$arg[$i],2);
			
			if($a[0]=='h')
				$s['host']=$a[1];
			elseif($a[0]=='u')
				$s['user']=$a[1];
			elseif($a[0]=='p')
				$s['password']=$a[1];
			elseif($a[0]=='dbs')
				$s['dbs']=explode(',',$a[1]);
			elseif($a[0]=='days')
				$s['days']=explode(',',$a[1]);
			elseif($a[0]=='spath')
				$s['storePath']=$a[1];
			elseif($a[0]=='email')
				$s['email']=$a[1];
		}
	}

}

$x=new pmysqlb();
$x->run($s);

?>
